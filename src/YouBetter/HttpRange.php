<?php

namespace DMelo\YouBetter;

define('INF_MAX', 1024 * 1024 * 1024 * 1024);

class HttpRange {
    static private $_currentByte = 0;
    static private $_range = null;

    static public function echoData($data, $totalLength, $logger)
    {
        // var_dump($range);
        $start = 0;
        $length = 0;
        $range = self::getRange($totalLength);

        if (null !== $range) {
            $start = $range[0] < self::$_currentByte ? 0 : $range[0] - self::$_currentByte;
            $length = $range[1] - $start + 1;
            $length = $start + $length > strlen($data) ? strlen($data) - $start : $length;
            $length = $length < 0 ? 0 : $length;
            echo substr($data, $start, $length);
            self::$_currentByte += $length;
        } else {
            echo $data;
        }

        /*
        $logger->err(
            'datasize: ' . sprintf("%5d", strlen($data)) . '. range: ' .
            print_r($range, true) . '. start: ' . sprintf("%7d", $start) .
            '. length: ' . sprintf("%5d", $length) . '. HTTP_RANGE: ' .
            (isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : 'HTTP_RANGE NOT SET')
        );
        */
        flush();
    }

    static public function getRange($totalLength)
    {
        global $_SERVER;
        if (isset($_SERVER['HTTP_RANGE'])) {
            if (null === self::$_range) {
                if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
                    self::$_range = [$matches[1], $matches[2]];
                    if ('' === $matches[2]) {
                        self::$_range[1] = $totalLength - 1;
                        self::$_range['Content-Length'] = $totalLength - self::$_range[0];
                    } else {
                        self::$_range['Content-Length'] = self::$_range[1] - self::$_range[0] + 1;
                    }
                }
            }
        }

        return self::$_range;
    }

    static public function isFullRequest($length, $etag = null)
    {
        $status = '';
        $range = HttpRange::getRange($length);
        if (null === $range || (isset($_SERVER['If-Range']) && $etag != $_SERVER['If-Range'])) {
            $isFull = true;
        } else {
            $isFull = false;
        }

        return $isFull;
    }
}
