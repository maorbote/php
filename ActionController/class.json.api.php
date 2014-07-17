<?php

class JSON_API extends Action_Controller {
    
    protected $show_error = true;

    protected $headers = array('Content-Type' => 'application/json; charset=utf-8');
    
    protected function render() {
        $output = array( 'code' => $this->code );
        $output['status'] = isset(self::$statuses[$this->code]) ? self::$statuses[$this->code] : 'Undefined';
        $this->response and $output['response'] = $this->response;
        $this->show_error and $this->runtime_output and $output['runtime_output'] = $this->runtime_output;
        
        return json_encode($output);
    }
    
}

