<?php

define('INF_MAX', 1024 * 1024 * 1024 * 1024);

class HttpRange {
    static private $_currentByte = 0;
    static private $_range = null;

    static public function echoData($data)
    {
        global $_SERVER;
        $range = null;
        if (isset($_SERVER['HTTP_RANGE'])) {
            if (null === self::$_range) {
                if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
                    self::$_range = [$matches[1], $matches[2]];
                    if ('' === $matches[2]) {
                        self::$_range[1] = INF_MAX;
                    }
                }
            }
            
            $range = self::$_range;
        }

        // var_dump($range);
        if (null !== $range) {
            $start = $range[0] < self::$_currentByte ? 0 : $range[0] - self::$_currentByte;
            $length = $range[1] - $start + 1;
            $length = $start + $length > strlen($data) ? strlen($data) - $start : $length;
            $length = $length < 0 ? 0 : $length;
            echo substr($data, $start, $length);
            // echo "$start $length" . PHP_EOL;
            self::$_currentByte += $length;
        } else {
            // echo "bad" . PHP_EOL;
            echo $data;
        }
    }
}
