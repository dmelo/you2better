<?php

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../../../'); // as a composer component
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../../'); // inside /public/api
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../'); // inside /public

include_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\ProcessIdProcessor;


ignore_user_abort(true);
$conf = include('conf.php');
$logger = new Logger('default');
$logger->pushHandler(new RotatingFileHandler($conf['logpath'] . '/you2better.log', 0, Logger::INFO));
$logger->pushProcessor(new ProcessIdProcessor);

$logger->addInfo("Start");
$logger->addInfo("Request headers: " . print_r(getallheaders(), true));

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

$cacheFilename = realpath(__DIR__ . '/cache/');
$filenameBase = "$cacheFilename/$youtubeId.$ext";
$cacheFilenameHeader = "$filenameBase.header";
$cacheFilenameContent = "$filenameBase.content";
$cacheFilenamePID = "$filenameBase.pid";

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
    
    // write header.
    $fd = fopen($cacheFilenameHeader, 'r');
    while (!feof($fd)) {
        header(fgets($fd, 4096));
    }
    fclose($fd);

    // write content.
    echo file_get_contents($cacheFilenameContent);
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
        $url = parse_url($output[0]);
        $host = 'ssl://' . $url['host'];
        $uri = $url['path'] . '?' . $url['query'];
        $logger->addInfo("Open connection with $host on port 443");

        $fp = fsockopen($host, 443);
        if ($fp) {
            socket_set_option($fp, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 10, 'usec' => 0));
            socket_set_option($fp, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 10, 'usec' => 0));
            $out = "GET $uri HTTP/1.1\r\n";
            $out .= "Host: " . $url['host'] . "\r\n";
            $out .= "Connection: Close\r\n\r\n";
            $logger->addInfo("send header: $out");
            fwrite($fp, $out);
            
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
                        $fd = fopen($cacheFilenameContent, 'w');
                    } else {
                        if ($isHeader) {
                            header($str);
                        } else {
                            echo $str;
                        }
                        fwrite($fd, $str);
                    }
                }
            }
            fclose($fd);
            fclose($fp);
        }
    }

    // delete PID file.
    $logger->addInfo("delete PID file $cacheFilenamePID");
    unlink($cacheFilenamePID);
}
