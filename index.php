<?php

$youtubeId = $_GET['youtubeid'];
header("Content-Type: audio/mpeg\n");
system("./api/youtube-dl/youtube-dl --no-part -q --output=/dev/stdout http://www.youtube.com/watch?v={$youtubeId} | ffmpeg -i - -f mp3 pipe:1 | cat");
//var_dump($_GET);
