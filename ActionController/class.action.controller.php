<?php
/**
 * 簡易動作控制器
 *
 * 集成以下功能:
 *   1. 路由(Router)
 *   2. 控制器(Controller)
 *   3. 響應(Response)
 *
 *
 * 3個動作呼叫接口:
 *   void execute()
 *   自動路由解析並輸出
 *
 *   void call(string $action [, array $params [, string $method]])
 *   輸出指定動作的回應
 *
 *   mixed fetch(string $action [, array $params [, string $method [, boolean $render]]])
 *   取得指定動作的內容
 */


class Action_Controller {
    /**
     * HTTP 狀態碼
     * @var int
     */
    protected $code = 200;

    /**
     * HTTP 標頭
     * @var array
     */
    protected $headers = array('Content-Type' => 'text/html; charset=utf-8');

    /**
     * 回應的內容
     * @var string
     */
    protected $response;

    /**
     * 運行期的輸出
     * @var string
     */
    protected $runtime_output;

    /**
     * 回傳運行期輸出
     * @var boolean
     */
    protected $show_error = false;

    /**
     * 請求的路徑
     * @var string
     */
    protected $path = '';

    /**
     * 請求路徑分段
     * @var array
     */
    protected $segment = array();

    /**
     * 請求的動作
     * @var string
     */
    protected $action = 'index';

    /**
     * 請求的參數
     * @var array
     */
    protected $params = array();

    /**
     * HTTP請求方法(GET|POST)
     * @var string
     */
    protected $method = 'get';

    /**
     * HTTP 狀態
     * @var array
     */
    public static $statuses = array(
        200 => 'OK',

        302 => 'Found',

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
     * 單例容器
     * @var array
     */
    private static $instances = array();

    /**
     * 單例
     *
     * @return  object  $instances  單例實體
     */
    private static function ins() {
        $class = self::get_called_class();
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class;
        }
        return self::$instances[$class];
    }

    /**
     * 呼叫預設動作並輸出
     */
    final public static function execute() {
        $ins = self::ins();
        $ins->router();
        echo $ins->invoke($ins->action, $ins->params, $ins->method)->send_headers()->render();
    }

    /**
     * 呼叫動作並輸出回應
     *
     * @param   string  $action     呼叫的動作
     * @param   array   $params     參數
     * @param   string  $method     HTTP請求方法 (GET|POST)
     */
    final public static function call($action='', array $params=array(), $method='get') {
        echo self::ins()->invoke($action, $params, $method)->send_headers()->render();
    }

    /**
     * 呼叫動作並回傳內容
     *
     * @param   string  $action     呼叫的動作
     * @param   array   $params     參數
     * @param   string  $method     HTTP請求方法 (GET|POST)
     * @param   boolean $render     是否渲染回應內容為字串
     *                              -預設為true，false為路由方法(route_)專用
     * @return  string/object  $response   回應內容
     */
    final public static function fetch($action='', array $params=array(), $method='get', $render=true) {
        $response = self::ins()->invoke($action, $params, $method);
        return $render ? $response->render() : $response;
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
                str_replace($_SERVER['SCRIPT_NAME'], '', strtok($_SERVER['REQUEST_URI'], '?')) :
                strtok($_SERVER['REQUEST_URI'], '?')
            )));

        $this->segment = explode('/', trim($this->path, '/'));
        $this->method = strtolower($_SERVER['REQUEST_METHOD']);
        $this->init();
    }

    /**
     * 初始化
     * 由於建構式禁止重載，可重載此函式，建構式會呼叫此函式進行初始化
     */
    protected function init() {}

    /**
     * 路由
     * execute()會呼叫此函式進行路由解析，預設取第一段路徑為動作，餘為參數
     *
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
     * @param   string  $action     呼叫的動作
     * @param   array   $params     參數
     * @param   string  $method     HTTP請求方法 (GET|POST)
     * @return  object  $this       物件本身
     */
    protected function invoke($action='', array $params=array(), $method='get') {
        if(empty($action)) $action = 'index' ;

        if(method_exists($this, 'route_'.$action)) {
            $callback = 'route_'.$action;
            $action = array_shift($params);
            return call_user_func(array($this, $callback), $action, $params, $method);
        } elseif(method_exists($this, $method.'_'.$action)) {
            $callback = $method.'_'.$action;
        } elseif (method_exists($this, 'action_'.$action)) {
            $callback = 'action_'.$action;
        } else {
            $callback = 'not_implemented';
            array_unshift($params, $action);
        }

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
     * @return  string  $response   回應內容
     */
    protected function render() {
        return (string)$this->response.($this->show_error ? $this->runtime_output : '');
    }

    protected function action_index() {}

    /**
     * 302 重新導向
     *
     * @param   string  $url        網址
     */
    protected function redirect($url='') {
        if(empty($url)) $url = $_SERVER['REQUEST_URI'];

        $this->code = 302;
        $this->headers = array('Location' => $url);
        $this->send_headers();
        exit;
    }

    /**
     * 302 重新導向返回
     */
    protected function redirect_back() {
        $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_SERVER['REQUEST_URI'];
        $this->redirect($url);
    }

    /**
     * 404 找不到
     */
    protected function not_found($msg='404 Not Found!') {
        $this->code = 404;
        return $msg;
    }

    /**
     * 501 方法未實現
     */
    protected function not_implemented($action) {
        $this->code = 501;
        return "501 Method(".$action.") Not Implemented!";
    }

    /**
     * get_called_class 5.2相容
     *
     * @return  string  $className  類名稱
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
