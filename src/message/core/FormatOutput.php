<?php


namespace uukule\message\core;


class FormatOutput
{
    private $log = '';
    private $start = '';
    private $end = "\033[0m";
    private $cli = true;
    private $color = '';
    private $background = false;

    public function __construct()
    {
        if (false === strpos(php_sapi_name(), 'cli')) {
            $this->cli = false;
        }
    }

    public function __destruct()
    {
        $this->output();
    }

    public function output($val = null)
    {
        if (empty($val)) {
            $val = $this->log;
        }
        if ($this->cli) {
            $val = $this->start.$val.$this->end;
        }

        echo $val;
    }

    public static function __callStatic($name, $arguments)
    {
        $color = 31;
        switch ($name) {
            case 'red': $color = 31; break;
            case 'yellow': $color = 33; break;
            case 'green': $color = 32; break;
            case 'blue': $color = 34; break;
            default: $color = 31;
        }

        $obj = new self();

        $constant = '';
        if (count($arguments) > 1) {
            if ('b' == strtolower($arguments[1]) || 'background' == strtolower($arguments[1])) {
                $color = $color + 10;
                // $this->background = true;
                $constant = ';37';
            }
            if (isset($arguments[2]) && 'log' == $arguments[2]) {
                $obj->cli = true;
                $obj->log = $arguments[0];

                return false;
            }
        }

        $obj->log = "\033[".$color.$constant.'m'.$arguments[0] .$obj->end;
    }
}