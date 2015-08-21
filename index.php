<?php

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../../../'); // as a composer component
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../../'); // inside /public/api
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../'); // inside /public
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__); // inside ./
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../../../application/configs/'); // as a composer component

include_once 'vendor/autoload.php';
include_once 'HttpRange.php';

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\ProcessIdProcessor;

ignore_user_abort(true);

// logger
$conf = include('you2better-conf.php');
$logger = new Logger('default');
$logger->pushHandler(new RotatingFileHandler($conf['logpath'] . '/you2better.log', 0, Logger::INFO));
$logger->pushProcessor(new ProcessIdProcessor);

$logger->addInfo("Start");
$logger->addInfo("Request headers: " . print_r(getallheaders(), true));

/**
 * Check if size informed on header matches the cached file size.
 *
 * @param $cacheFilenameHeader file name of the header file.
 * @param $cacheFilenameContent file name of the content file.
 */
function checkFileSize($cacheFilenameHeader, $cacheFilenameContent)
{
    global $logger;
    $ret = false;
    if (($fd = fopen($cacheFilenameHeader, 'r')) !== false) {
        while(!feof($fd)) {
            $str = fgets($fd, 4096);
            if (preg_match('/Content-Length/i', $str)) {
                if (($str = preg_replace('/.* *:/', '',$str)) !== null) {
                    $size = (int) $str;
                    if ($size === filesize($cacheFilenameContent)) {
                        $ret = true;
                    }
                    $logger->addWarning("file: $cacheFilenameContent. header size: $size. actual size: " . filesize($cacheFilenameContent));
                } else {
                    $logger->addError("file: $cacheFilenameContent. Couldn't isolate file size on header file");
                }
                break;
            }
        }

        fclose($fd);
    } else {
        $logger->addError("could not open header file $cacheFilenameHeader.");
    }

    return $ret;
}

// Decide content-type
$youtubeId = $_GET['youtubeid'];
$ext = $_GET['ext'];
if ('mp4' === $ext || 'm4v' === $ext) {
    $contentType = 'video/mp4';
} elseif ('m4a' === $ext) {
    $contentType = 'audio/mp4';
}

$logger->addInfo('contentType: ' . $contentType);

$ysite = 'http://www.youtube.com/watch';
$ydl = $conf['ydl'];

// Calculate cacheFilename[Header|Content|PID]
$cacheFilename = realpath(__DIR__ . '/cache/');
$filenameBase = "$cacheFilename/$youtubeId.$ext";
$cacheFilenameHeader = "$filenameBase.header";
$cacheFilenameContent = "$filenameBase.content";
$cacheFilenamePID = "$filenameBase.pid";

/**
 * Download content from given URL, serves the content and store on cache files.
 *
 * @param $url URL of the content
 */
function saveUrl($url)
{
    global $logger, $youtubeId, $cacheFilenameHeader, $cacheFilenameContent;
    $url = parse_url($url);
    $host = 'ssl://' . $url['host'];
    $uri = $url['path'] . '?' . $url['query'];
    $logger->addInfo("Open connection with $host on port 443");

    $fp = fsockopen($host, 443, $errno, $errstr);

    $logger->addInfo("fsockopen. host: $host. errno: $errno. errstr: $errstr");
    if (false !== $fp) {
        $out = "GET $uri HTTP/1.1\r\n";
        $out .= "Host: " . $url['host'] . "\r\n";
        $out .= "Connection: keep-alive\r\n\r\n";
        $logger->addInfo("send header: $out");
        fwrite($fp, $out);
        $length = 0;
        
        $logger->addInfo("inflate $cacheFilenameHeader and $cacheFilenameContent AND respond request");
        $isHeader = true;
        $fd = fopen($cacheFilenameHeader, 'w');
        while(!feof($fp)) {
            $str = fgets($fp, 4096);
            if (false === $str) {
                $logger->addError("fgets returned false");
                break;
            } else {
                if ($isHeader && "\r\n" == $str) {
                    $isHeader = false;
                    fclose($fd);
                    $full = HttpRange::isFullRequest($length);
                    $range = HttpRange::getRange($length);
                    header(
                         $full ?
                        'HTTP/1.1 200 OK' :
                        'HTTP/1.1 206 Partial Content'
                    );
                    $lastModified = date('r', file_exists($cacheFilenameContent) ? stat($cacheFilenameContent)['mtime'] : time());
                    header('Last-Modified: ' . $lastModified);
                    header('Accept-Ranges: bytes');
                    header('Content-Length: ' . ($full ? $length : $range['Content-Length']));
                    if (!$full) {
                        header('Content-Range:bytes ' . $range[0] . '-' . $range[1] . '/' . $length);
                    }
                    header('Connection: keep-alive');
                    header('Content-Type: audio:mp4');

                    $fd = fopen($cacheFilenameContent, 'w');
                } else {
                    if ($isHeader) {
                        if (stripos($str, 'Location: ') !== false) {
                            $url = preg_replace('/location: /i', '', $str);
                            $url = preg_replace(
                                array("/\n/", "/\r/"), 
                                array('', ''),
                                $url
                            );
                            fclose($fd);
                            fclose($fp);
                            return saveUrl($url, $youtubeId);
                        } elseif (stripos($str, 'Content-Length') !== false) {
                            $length = (int) preg_replace(
                                '/Content-Length: */i', '', $str
                            );
                        }
                    } else {
                        // TODO: fix second parameter.
                        HttpRange::echoData($str, $length, $logger);
                    }
                    fwrite($fd, $str);
                }
            }
        }
        fclose($fd);
        fclose($fp);
    }
}

// Wait while another process handle this request.
while (file_exists($cacheFilenamePID)) {
    $pid = file_get_contents($cacheFilenamePID);
    $logger->addInfo("another process ($pid) is locking this request");

    // If pid doesn't exists, remove the file and continue.
    if (!file_exists("/proc/$pid")) {
        $logger->addInfo("process $pid is not running, removing lock file");
        unlink($cacheFilenamePID);
    } else {
        $logger->addInfo("wait another second for $pid");
        sleep(1);
    }
}

if (file_exists($cacheFilenameHeader) && file_exists($cacheFilenameContent) && checkFileSize($cacheFilenameHeader, $cacheFilenameContent)) {
    $logger->addInfo("request for $youtubeId is cached. just output cached file");
    $etag = md5($cacheFilenameContent);
    $range = HttpRange::getRange(filesize($cacheFilenameContent));

    $full = HttpRange::isFullRequest(filesize($cacheFilenameContent), $etag);
    header(
         $full ?
        'HTTP/1.1 200 OK' :
        'HTTP/1.1 206 Partial Content'
    );

    header('Last-Modified: ' . date('r', stat($cacheFilenameContent)['mtime']));
    header('ETag: ' . md5($cacheFilenameContent));
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($full ? filesize($cacheFilenameContent) : $range['Content-Length']));
    if (!$full) {
        header('Content-Range:bytes ' . $range[0] . '-' . $range[1] . '/' . filesize($cacheFilenameContent));
    }
    $logger->err('getRange ' . print_r($range, true));
    $logger->err('_SERVER: ' . print_r($_SERVER, true));
    header('Connection: keep-alive');
    header('Content-Type: audio/mp4');

    // write content.
    HttpRange::echoData(file_get_contents($cacheFilenameContent), filesize($cacheFilenameContent), $logger);
} else {
    $logger->addInfo("there is no cache for $youtubeId and no process handling it already");

    // create PID file.
    $logger->addInfo("create PID file $cacheFilenamePID");
    file_put_contents($cacheFilenamePID, getmypid());

    $tmpFile = "/tmp/{$youtubeId}";
    $cmd = "$ydl -g \"{$ysite}?v={$youtubeId}\" > {$tmpFile} ; cat {$tmpFile} | grep \"audio/mp4\" || cat {$tmpFile}"; 
    $logger->addInfo("run command: $cmd");
    exec("$cmd", $output, $ret);
    $logger->addInfo("command output: $cmd. ret: $ret");
    if (0 != $ret) {
        $logger->addInfo("command returned error. respond user request with 404");
        header("HTTP/1.0 404 Not Found");
    } else {
        saveUrl($output[0]);
    }

    // delete PID file.
    $logger->addInfo("delete PID file $cacheFilenamePID");
    unlink($cacheFilenamePID);
}
