<?php

header('Content-Type: audio/mpeg');
system("./youtube-dl --no-part -q --output=/dev/stdout http://www.youtube.com/watch?v={$youtubeId} | ffmpeg -i - -f mp3 pipe:1 | cat");
