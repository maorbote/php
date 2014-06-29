<?php

class JSON_API extends Action_Controller {
    
    protected $show_error = true;
    
    protected function send_headers() {
        if ( ! headers_sent()) {            
            
            if(isset(self::$statuses[$this->code])) {
                header($_SERVER['SERVER_PROTOCOL'] . ' ' . $this->code.' '.self::$statuses[$this->code]);
            } else {
                header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
            }
            
            header('Content-Type: application/json');
        }
        return $this;
    }
    
    protected function rander() {
        $output = array( 'code' => $this->code );
        $output['status'] = isset(self::$statuses[$this->code]) ? self::$statuses[$this->code] : 'Undefined';
        $this->response and $output['response'] = $this->response;
        $this->show_error and $this->runtime_output and $output['runtime_output'] = $this->runtime_output;
        
        return json_encode($output);
    }
    
}

