<?php

abstract class Singleton {

    private static $instances = array();

    final public static function obj() {
        return self::instance();
    }

    final protected static function instance() {
        $class = self::get_called_class();
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    final private function __construct() {
        $this->init();
    }

    protected function init() {}

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
