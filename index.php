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
exec("$ydl -g \"{$ysite}?v={$youtubeId}\"", $output, $ret);

if (0 != $ret) {
    header("HTTP/1.0 404 Not Found");
} else {
    header('Location: ' . $output[0]);
}
