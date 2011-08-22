<?php

$youtubeId = $_GET['youtubeid'];
header("Content-Type: audio/mpeg\n");

// Here is where all the magic happens.
system("./youtube-dl/youtube-dl --no-part -q --output=/dev/stdout http://www.youtube.com/watch?v={$youtubeId} | ffmpeg -i - -f mp3 pipe:1 | cat");
