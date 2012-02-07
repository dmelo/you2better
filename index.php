<?php

include_once 'conf.php';

$youtubeId = $_GET['youtubeid'];


/**
 * writeTimestamp Write the timestamp on the meta file.
 *
 * @param mixed $filename
 * @access public
 * @return void
 */
function writeTimestamp($filename)
{
    $fd = fopen($filename, 'w');
    fprintf($fd, "%d", time());
    fclose($fd);
}

/**
 * logFile Log debug information.
 *
 * @param mixed $str
 * @access public
 * @return void
 */
function logFile($str)
{
    global $youtubeId;
    $fd = fopen(DIRECTORY . '/log-' . $youtubeId, 'a');
    fwrite($fd, $str. PHP_EOL);
    fclose($fd);
}

logFile('start');
header("Accept-Ranges: bytes\n");
header("Content-Type: application/mpeg\n");
header("Keep-Alive: timeout=15, max=100\n");
header("Connection: Keep-Alive\n");
header("Content-Transfer-Encoding: binary\n");
header_remove("Transfer-Encoding");


$filename = DIRECTORY . '/' . $youtubeId . '.mp3';
$filelock = $filename . '-lock';
$metaFilename = DIRECTORY . '/internal--' . $youtubeId . '.mp3';


writeTimestamp($metaFilename);
if (file_exists($filename)) {
    if (!file_exists($filelock)) {
        logFile('here 3');
        header('Content-Length: ' . filesize($filename));
        echo file_get_contents($filename);
    } else {
        logFile('here 2');
        if(array_key_exists('duration', $_GET))
            header('Content-Length: ' . ( 7900 * $_GET['duration'] ) );
        $offset = 0;
        $last = 0;
        while(0 === $last) {
            clearstatcache();
            if (!file_exists($filelock))
                $last = 1;
            $fd = fopen($filename, 'rb');
            $size = filesize($filename);
            fseek($fd, $offset, SEEK_SET);
            logFile("filename: ${filename}. last: ${last}. offset: ${offset}. size: ${size}" );
            echo fread($fd, $size - $offset);
            $offset = $size;
            fclose($fd);
            sleep(1);
        }
    }
} else {
    logFile('here 1');
    // Here is where all the magic happens.
    if(array_key_exists('duration', $_GET))
        header('Content-Length: ' . ( 7900 * $_GET['duration'] ) );
    $ydl = './youtube-dl/youtube-dl --no-part -q';
    $ysite = 'http://www.youtube.com/watch';
    system("touch ${filelock}; ${ydl} --output=/dev/stdout \"${ysite}?v={$youtubeId}\" | nice -n 18 ffmpeg -i - -f mp3 pipe:1 | tee ${filename}; rm ${filelock}");
}
