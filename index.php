<?php

include_once 'conf.php';

$youtubeId = $_GET['youtubeid'];
$ext = $_GET['ext'];
if ('mp3' === $ext) {
    $contentType = "application/mpeg";
} elseif ('flv' === $ext) {
    $contentType = "video/x-flv";
} elseif ('3gp' === $ext) {
    $contentType = "video/3gpp";
}


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
    fwrite($fd, date('Y-m-d H:i:s', time()) . ' -- ' . $str. PHP_EOL);
    fclose($fd);
}

function echoFile($filename)
{
    $fd = fopen($filename, "r");
    while (!feof($fd)) {
	echo fread($fd, 1024 * 1024);
    }

    fclose($fd);
}

logFile('start');
header("Accept-Ranges: bytes\n");
header("Content-Type: " . $contentType . "\n");
header("Keep-Alive: timeout=15, max=100\n");
header("Connection: Keep-Alive\n");
header("Content-Transfer-Encoding: binary\n");
header_remove("Transfer-Encoding");


$filename = DIRECTORY . '/' . $youtubeId . '.' . $ext;
$filelock = $filename . '-lock';
$metaFilename = DIRECTORY . '/internal--' . $youtubeId . '.' . $ext;
$fileAccess = "${filename}.access";


writeTimestamp($metaFilename);
logFile("Beginning with file $filename");
if (file_exists($filename)) {
    logFile("File $filename exists");
    if (!file_exists($filelock)) {
        logFile("lock file $filelock doesn't exists");
        header('Content-Length: ' . filesize($filename));
        logFile("file header Content-Length set to " . filesize($filename));
        echoFile($filename);
    } else {
        logFile("Lock file $filelock exists");
        if(array_key_exists('duration', $_GET)) {
            $estimatedLength = 7900 * $_GET['duration'];
                header('Content-Length: ' . $estimatedLength );
            logFile("Estimating Content-Length to $estimatedLength");
        } else  {
            logFile("Content-Length was not set");
        }

        $offset = 0;
        $last = 0;
        while(0 === $last) {
            clearstatcache();
            if (!file_exists($filelock))
                $last = 1;
            $fd = fopen($filename, 'rb');
            $size = filesize($filename);
            fseek($fd, $offset, SEEK_SET);
            logFile("filename: ${filename}. last: ${last}. offset: ${offset}. size: ${size}");
            echo fread($fd, $size - $offset);
            $offset = $size;
            fclose($fd);
            sleep(1);
        }
    }
} else {
    logFile("File $filename doesn't exists");
    // Here is where all the magic happens.
    if(array_key_exists('duration', $_GET)) {
	$estimatedLength = 7900 * $_GET['duration'];
        header('Content-Length: ' . $estimatedLength );
	logFile("Estimating header Content-Length to $estimatedLength");
    } else {
	logFile("Content-Length was not set");
    }

    $ydl = './youtube-dl/youtube-dl --no-part -q';
    $ysite = 'http://www.youtube.com/watch';
    $cmd = "nice touch -- \"${filelock}\"; ";
    if ($ext === 'mp3') {
        $cmd .= "nice ${ydl} --output=/dev/stdout \"${ysite}?v={$youtubeId}\" | nice -n 18 ffmpeg -i - -f mp3 pipe:1 | nice tee ${filename}; nice rm ${filelock}";
    } elseif ($ext === 'flv') {
        $cmd .= "nice ${ydl} --output=/dev/stdout \"${ysite}?v={$youtubeId}\" | nice tee ${filename}; nice rm ${filelock}";
    } elseif ($ext === '3gp') {
        $cmd .= "cvlc  --sout file/3gp:- \"`wget -O - 'https://gdata.youtube.com/feeds/api/videos/{$youtubeId}' | grep 3gp | sed 's/.*rtsp/rtsp/g' | sed 's/\.3gp.*$/.3gp/g'`\" | nice tee ${filename}; nice rm ${filelock}";
    }
    logFile('cmd: ' . $cmd);
    system($cmd);
}

logFile("touch -- \"$fileAccess\"");
system("touch ${fileAccess}");

logFile("End of process on file $filename" . PHP_EOL . PHP_EOL);
