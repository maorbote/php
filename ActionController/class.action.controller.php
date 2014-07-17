<?php
/**
 * 簡易動作控制器
 * 
 * 集成自動路由解析、函式自動調用
 * 功能包含自動發送HTTP標頭、收集運行期輸出、前/後置動作調用
 * 
 * 
 * 路由解析：
 * 利用PATH_INFO，自動解析呼叫的動作和參數
 * 
 * 函式自動調用：
 * 3個動作呼叫接口
 *   1.execute:呼叫由路由解析得到的動作
 *   2.call:呼叫指定動作並輸出
 *   3.fetch:取得指定動作的內容
 * 
 * 前/後置動作：
 * 可設置before()/after()進行調用前/後動作，例如驗證、開啟/關閉連線等
 * 
 */


class Action_Controller {
    /**
     * @var int  HTTP 狀態碼
     */
    protected $code = 200;

    /**
     * @var array  HTTP 標頭
     */
    protected $headers = array('Content-Type' => 'text/html; charset=utf-8');

    /**
     * @var staring  回應的內容
     */
    protected $response;

    /**
     * @var staring  運行期的輸出
     */
    protected $runtime_output;

    /**
     * @var boolean  回傳運行期輸出  
     */
    protected $show_error = false;
    
    /**
     * @var staring  請求的路徑
     */
    protected $path = '';

    /**
     * @var array  請求路徑分段
     */
    protected $segment = array();

    /**
     * @var string  請求的動作
     */
    protected $action = 'index';

    /**
     * @var array  請求的參數
     */
    protected $params = array();

    /**
     * @var string  HTTP請求方法(GET|POST)
     */
    protected $method = 'get';

    /**
     * @var array  HTTP 狀態
     */
    public static $statuses = array(
        200 => 'OK',

        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',

        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout'
    );
    
    /**
     * @var array  單例容器
     */
    private static $instances = array();
    
    /**
     * 單例
     *
     * @return  $instances  單例實體
     */
    private static function ins() {
        $class = self::get_called_class();
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }
    
    /**
     * 呼叫預設動作並輸出
     */
    final public static function execute() {
        $ins = self::ins();
        echo $ins->invoke($ins->action, $ins->params, $ins->method)->send_headers()->render();
    }
    
    /**
     * 呼叫動作並輸出回應
     *
     * @param   string  $action  呼叫的動作
     * @param   array   $params  參數
     * @param   string  $method  HTTP請求方法 (GET|POST)
     */
    final public static function call($action='', array $params=array(), $method='get') {
        echo self::ins()->invoke($action, $params, $method)->send_headers()->render();
    }

    /**
     * 呼叫動作並回傳內容
     *
     * @param   string  $action  呼叫的動作
     * @param   array   $params  參數
     * @param   string  $method  HTTP請求方法 (GET|POST)
     * @return  $response  回應內容
     */
    final public static function fetch($action='', array $params=array(), $method='get') {
        return self::ins()->invoke($action, $params, $method)->render();
    }
    
    /**
     * 建構式:禁止重載
     * 從PATH_INFO取得請求路徑
     */    
    final private function __construct() {        
        $this->path = 
            (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] :
            (isset($_SERVER['ORIG_PATH_INFO']) ?
                # 修正微軟IIS ORIG_PATH_INFO包含腳本名稱
                str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['ORIG_PATH_INFO']) :
            (strpos($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']) === 0 ? 
                str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['REQUEST_URI']) :
                $_SERVER['REQUEST_URI']
            )));
        
        $this->segment = explode('/', trim($this->path, '/'));
        $this->method = strtolower($_SERVER['REQUEST_METHOD']);
        $this->init();
        $this->router();
    }
    
    /**
     * 初始化
     * 由於建構式禁止重載，可重載此函式，建構式會呼叫此函式進行初始化
     */
    protected function init() {}

    /**
     * 路由
     * 建構式會呼叫此函式進行路由解析，預設取第一段路徑為動作，餘為參數
     * 範例：
     * example.com/interface.php/act/arg1/arg2
     * action = act
     * params = [arg1,arg2]
     * 
     * 可重載此函式重新定義路由解析
     */
    protected function router() {
        if(count($this->segment)) {
            $this->params = $this->segment;
            $this->action = array_shift($this->params);
        }
    }
    
    /**
     * 調用動作函式
     *
     * @param   string  $action  呼叫的動作
     * @param   array   $params  參數
     * @param   string  $method  HTTP請求方法 (GET|POST)
     */
    protected function invoke($action='', array $params=array(), $method='get') {
        if(empty($action)) $action = $this->action ? $this->action : 'index' ;
        
        $callback = 
            (method_exists($this, $method.'_'.$action) ? $method.'_'.$action :
            (method_exists($this, 'action_'.$action) ? 'action_'.$action :
            'not_implemented'));
        
        $this->before();
        $this->response = call_user_func_array(array($this, $callback), $params);
        $this->after();
        return $this;
    }

    /**
     * 動作前置函式，重載時需補上parent::before()呼叫此函式
     */
    protected function before() {
        ob_start();
    }
    
    /**
     * 動作後置函式，重載時需補上parent::after()呼叫此函式
     */
    protected function after() {
        $this->runtime_output = ob_get_clean();
    }

    /**
     * 發送HTTP標頭
     */
    protected function send_headers() {
        if ( ! headers_sent()) {            
            
            if(isset(self::$statuses[$this->code])) {
                header($_SERVER['SERVER_PROTOCOL'] . ' ' . $this->code.' '.self::$statuses[$this->code]);
            } else {
                header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
            }
            
            foreach ($this->headers as $name => $value) {
                is_string($name) and strlen($name) and header("$name: $value");
            }

        }
        return $this;
    }
    
    /**
     * 渲染輸出內容
     * 
     * @return  $response  回應內容
     */
    protected function render() {
        return $this->response;
    }
    
    protected function not_implemented() {
        $this->code = 501;
        return "501 Method(".$this->action.") Not Implemented!";
    }
    
    protected function not_found($msg='404 Not Found!') {
        $this->code = 404;
        return $msg;
    }
    
    /**
     * get_called_class 5.2相容
     * 
     * @return  $className  類名稱
     */
    private static function get_called_class() {
        if (function_exists('get_called_class')) {
            return get_called_class();
        } else {
            $bt = debug_backtrace();
            $bt = $bt[2];
            $lines = file($bt['file']);
            preg_match(
                '/(\w+)::'.$bt['function'].'/',
                $lines[$bt['line']-1],
                $matches
            );
            return $matches[1];
        }
    }
    
}

