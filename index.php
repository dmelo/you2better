<?php

include_once 'conf.php';

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
$ydl = '../../rg3/youtube-dl/youtube-dl';
exec("$ydl -g \"{$ysite}?v={$youtubeId}\"", $output, $ret);

header('Location: ' . $output[0]);
