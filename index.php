<?php

$conf = include('conf.php');

$youtubeId = $_GET['youtubeid'];
$ext = $_GET['ext'];
if ('mp3' === $ext) {
    $contentType = "application/mpeg";
} elseif ('mp4' === $ext) {
    $contentType = 'video/mp4';
} elseif ('flv' === $ext) {
    $contentType = "video/x-flv";
} elseif ('3gp' === $ext) {
    $contentType = "video/3gpp";
}

$ysite = 'http://www.youtube.com/watch';
$ydl = $conf['ydl'];
$cacheFilename = realpath(__DIR__ . '/cache/' . $youtubeId);
$cacheFilenameHeader = "$cacheFilename.$ext.header";
$cacheFilenameContent = "$cacheFilename.$ext.content";

$isHeader = true;
if (file_exists($cacheFilenameHeader) && file_exists($cacheFilenameContent)) {
    $fd = fopen($cacheFilenameHeader, 'r');
    while (!feof($fd)) {
        header(fgets($fd, 4096));
    }

    fclose($fd);
    $fd = fopen($cacheFilenameContent, 'r');
    while (!feof($fd)) {
        echo fgets($fd, 4096);
    }
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
            fwrite($fp, "GET $uri HTTP/1.1" . PHP_EOL . PHP_EOL);
            
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
