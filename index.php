<?php

$conf = include('conf.php');

function checkFileSize($cacheFilenameHeader, $cacheFilenameContent)
{
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
                    error_log("file: $cacheFilenameContent. header size: $size. actual size: " . filesize($cacheFilenameContent));
                } else {
                    error_log("file: $cacheFilenameContent. Couldn't isolate file size on header file");
                }
                break;
            }
        }

        fclose($fd);
    } else {
        error_log("could not open header file $cacheFilenameHeader.");
    }

    return $ret;
}

$youtubeId = $_GET['youtubeid'];
$ext = $_GET['ext'];
if ('mp3' === $ext) {
    $contentType = "application/mpeg";
} elseif ('mp4' === $ext || 'm4v' === $ext) {
    $contentType = 'video/mp4';
} elseif ('flv' === $ext) {
    $contentType = "video/x-flv";
} elseif ('3gp' === $ext) {
    $contentType = "video/3gpp";
}

$ysite = 'http://www.youtube.com/watch';
$ydl = $conf['ydl'];

$cacheFilename = realpath(__DIR__ . '/cache/');
$cacheFilenameHeader = "$cacheFilename/$youtubeId.$ext.header";
$cacheFilenameContent = "$cacheFilename/$youtubeId.$ext.content";

if (file_exists($cacheFilenameHeader) && file_exists($cacheFilenameContent) && checkFileSize($cacheFilenameHeader, $cacheFilenameContent)) {
    $fd = fopen($cacheFilenameHeader, 'r');
    while (!feof($fd)) {
        header(fgets($fd, 4096));
    }

    fclose($fd);
    echo file_get_contents($cacheFilenameContent);
} else {
    exec("$ydl -g \"{$ysite}?v={$youtubeId}\"", $output, $ret);
    if (0 != $ret) {
        header("HTTP/1.0 404 Not Found");
    } else {
        $url = parse_url($output[0]);
        $host = 'ssl://' . $url['host'];
        $uri = $url['path'] . '?' . $url['query'];

        $fp = fsockopen($host, 443);
        if ($fp) {
            stream_set_timeout($fp, 10);
            $out = "GET $uri HTTP/1.1\r\n";
            $out .= "Host: " . $url['host'] . "\r\n";
            $out .= "Connection: Close\r\n\r\n";
            fwrite($fp, $out);
            
            $isHeader = true;
            $fd = fopen($cacheFilenameHeader, 'w');
            while(!feof($fp)) {
                $str = fgets($fp, 4096);
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
            fclose($fd);
            fclose($fp);
        }
    }
}
