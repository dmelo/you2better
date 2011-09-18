<?php

include_once 'conf.php';

function writeTimestamp($filename)
{
    $fd = fopen($filename, 'w');
    fprintf($fd, "%d", time());
    fclose($fd);
}

$youtubeId = $_GET['youtubeid'];
header("Content-Type: audio/mpeg\n");

$filename = DIRECTORY . '/' . $youtubeId . '.mp3';
$metaFilename = DIRECTORY . '/internal--' . $youtubeId . '.mp3';

writeTimestamp($metaFilename);
if (is_file($filename)) {
    header('Content-Length: ' . filesize($filename));
    echo file_get_contents($filename);
} else {
    // Here is where all the magic happens.
    $ydl = './youtube-dl/youtube-dl --no-part -q';
    $ysite = 'http://www.youtube.com/watch';
    system("${ydl} --output=/dev/stdout \"${ysite}?v={$youtubeId}\" | ffmpeg -i - -f mp3 pipe:1 | tee ${filename}");
}
