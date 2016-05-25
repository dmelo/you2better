<?php

namespace DMelo\YouBetter;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\ProcessIdProcessor;

class YouBetter
{
    /**
     * Logger.
     */
    private $logger;

    /**
     * Path of the file storing the HTTP header of the content.
     */
    private $cacheFilenameHeader;

    /**
     * Path of the file storing the requested content.
     */
    private $cacheFilenameContent;

    /**
     * Check if size informed on header matches the cached file size.
     *
     * @param $cacheFilenameHeader file name of the header file.
     * @param $cacheFilenameContent file name of the content file.
     */
    private function checkFileSize($cacheFilenameHeader, $cacheFilenameContent)
    {
        $ret = false;
        if (($fd = fopen($cacheFilenameHeader, 'r')) !== false) {
            while (!feof($fd)) {
                $str = fgets($fd, 4096);
                if (preg_match('/Content-Length/i', $str)) {
                    if (($str = preg_replace('/.* *:/', '', $str)) !== null) {
                        $size = (int) $str;
                        if ($size === filesize($cacheFilenameContent)) {
                            $ret = true;
                        }
                        $this->logger->addWarning(
                            "file: $cacheFilenameContent. header size: $size." .
                            " actual size: " . filesize($cacheFilenameContent)
                        );
                    } else {
                        $this->logger->addError(
                            "file: $cacheFilenameContent. Couldn't isolate " .
                            "file size on header file"
                        );
                    }
                    break;
                }
            }

            fclose($fd);
        } else {
            $this->logger->addError(
                "could not open header file $cacheFilenameHeader."
            );
        }

        return $ret;
    }

    /**
     * Write HTTP headers.
     *
     * @param boolean $full Indicate if it is a full or range request.
     * @param array $range If it is a range request, will contain the boundaries.
     * @param int $length Length of the content.
     * @return void
     *
     */
    private function processHeader($full, $range, $length)
    {
        header(
            $full ?
            'HTTP/1.1 200 OK' :
            'HTTP/1.1 206 Partial Content'
        );
        $lastModified = date(
            'r',
            file_exists($this->cacheFilenameContent) ?
                stat($this->cacheFilenameContent)['mtime'] : time()
        );
        header('Last-Modified: ' . $lastModified);
        header('Accept-Ranges: bytes');
        header(
            'Content-Length: ' . ($full ?
            $length : $range['Content-Length'])
        );
        !$full && header(
            'Content-Range:bytes ' . $range[0] . '-' .
            $range[1] . '/' . $length
        );

        header('Connection: keep-alive');
        header('Content-Type: audio:mp4');
    }

    /**
     * Download content from given URL, serves the content and store on cache
     * files.
     *
     * @param $url URL of the content
     */
    private function saveUrl($url)
    {
        $this->logger->info("saving url: $url");
        $url = parse_url($url);
        $host = 'ssl://' . $url['host'];
        $uri = $url['path'] . '?' . $url['query'];
        $this->logger->info("Open connection with $host on port 443");

        $fp = fsockopen($host, 443, $errno, $errstr);
        $this->logger->info("fsockopen. host: $host. errno: $errno. errstr: $errstr");
        // If error while opening socket, exit.
        if (false === $fp) {
            $this->logger->err("error opening socket");
            return 1;
        }

        $out = "GET $uri HTTP/1.1\r\n";
        $out .= "Host: " . $url['host'] . "\r\n";
        $out .= "Connection: keep-alive\r\n\r\n";
        $this->logger->info("send header: $out");
        fwrite($fp, $out);
        $length = 0;

        $rand = rand();
        $tmpCacheFilenameHeader = $this->cacheFilenameHeader . '.' . $rand;
        $tmpCacheFilenameContent = $this->cacheFilenameContent . '.' . $rand;
        
        $this->logger->info(
            "inflate {$tmpCacheFilenameHeader} and " .
            "{$tmpCacheFilenameContent} AND respond request"
        );
        $isHeader = true;
        $fd = fopen($tmpCacheFilenameHeader, 'w');
        if (false === $fd) {
            $this->logger->err(
                "error opening file {$tmpCacheFilenameHeader} for writing"
            );
            return 2;
        }

        while (!feof($fp)) {
            $str = fgets($fp, 4096);
            if (false === $str) {
                $this->logger->addError("fgets returned false");
                break;
            }

            if ($isHeader && "\r\n" == $str) {
                $isHeader = false;
                fclose($fd);
                $full = HttpRange::isFullRequest($length);
                $range = HttpRange::getRange($length);
                $this->processHeader($full, $range, $length);
                $fd = fopen($tmpCacheFilenameContent, 'w');
            } else {
                if ($isHeader) {
                    if (stripos($str, 'Location: ') !== false) {
                        $url = preg_replace('/location: /i', '', $str);
                        $url = preg_replace(
                            array("/\n/", "/\r/"),
                            array('', ''),
                            $url
                        );
                        fclose($fd);
                        fclose($fp);
                        return $this->saveUrl($url);
                    } elseif (stripos($str, 'Content-Length') !== false) {
                        $length = (int) preg_replace(
                            '/Content-Length: */i',
                            '',
                            $str
                        );
                    }
                } else {
                    // TODO: fix second parameter.
                    HttpRange::echoData($str, $length, $this->logger);
                }
                fwrite($fd, $str);
            }
        }
        fclose($fd);
        fclose($fp);

        // If this->cacheFilenameContent is not complete then replace it
        $files = "$tmpCacheFilenameHeader and $tmpCacheFilenameContent";
        if (file_exists($this->cacheFilenameHeader) &&
            file_exists($this->cacheFilenameContent) &&
            $this->checkFileSize($this->cacheFilenameHeader, $this->cacheFilenameContent)) {
            $this->logger->debug(
                "Whoops. It looks like someone have created the cache files" .
                " before this thread. Removing tmp files $files"
                
            );
            unlink($tmpCacheFilenameHeader);
            unlink($tmpCacheFilenameContent);
        } else {
            $this->logger->debug("Renaming tmp cache files $files");
            rename($tmpCacheFilenameHeader, $this->cacheFilenameHeader);
            rename($tmpCacheFilenameContent, $this->cacheFilenameContent);
        }
    }

    /**
     * Output HTTP header for 404 - Page not found error.
     *
     * @return void.
     */
    private function pageNotFound()
    {
        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    }

    /**
     * Process the request and outputs the requested media.
     *
     * @return void.
     */
    public function processRequest()
    {
        // config
        $conf = include('you2better-conf.php');

        // datetime
        isset($conf['timezone']) && date_default_timezone_set($conf['timezone']);

        // logger
        $this->logger = new Logger('default');
        $this->logger->pushHandler(
            new RotatingFileHandler(
                $conf['logpath'] . '/you2better.log',
                0,
                Logger::INFO
            )
        );
        $this->logger->pushProcessor(new ProcessIdProcessor);

        $this->logger->info("Start");
        $this->logger->info("Request headers: " . print_r(getallheaders(), true));

        // Decide content-type
        $youtubeId = $_GET['youtubeid'];
        $ext = isset($_GET['ext']) ? $_GET['ext'] : 'm4a';
        if ('mp4' === $ext || 'm4v' === $ext) {
            $contentType = 'video/mp4';
        } elseif ('m4a' === $ext) {
            $contentType = 'audio/mp4';
        }

        $this->logger->info('contentType: ' . $contentType);

        $ysite = 'http://www.youtube.com/watch';
        $ydl = $conf['ydl'];

        // Calculate cacheFilename[Header|Content]
        $cacheFilename = $conf['cachepath'];
        $filenameBase = "$cacheFilename/$youtubeId.$ext";
        $this->cacheFilenameHeader = "$filenameBase.header";
        $this->cacheFilenameContent = "$filenameBase.content";

        // Use cached file
        if (file_exists($this->cacheFilenameHeader) &&
            file_exists($this->cacheFilenameContent) &&
            $this->checkFileSize($this->cacheFilenameHeader, $this->cacheFilenameContent)) {
            $this->logger->info("request for $youtubeId is cached. just output cached file");
            $etag = md5($this->cacheFilenameContent);
            $range = HttpRange::getRange(filesize($this->cacheFilenameContent));

            $full = HttpRange::isFullRequest(filesize($this->cacheFilenameContent), $etag);
            header(
                $full ?
                'HTTP/1.1 200 OK' :
                'HTTP/1.1 206 Partial Content'
            );

            header('Last-Modified: ' . date('r', stat($this->cacheFilenameContent)['mtime']));
            header('ETag: ' . md5($this->cacheFilenameContent));
            header('Accept-Ranges: bytes');
            header('Content-Length: ' . ($full ? filesize($this->cacheFilenameContent) : $range['Content-Length']));

            $this->logger->err('contentFile: ' . $this->cacheFilenameContent);
            $this->logger->err('getRange ' . print_r($range, true));
            $this->logger->err('_SERVER: ' . print_r($_SERVER, true));
            header('Connection: keep-alive');
            header('Content-Type: audio/mp4');

            if (!$full) {
                header('Content-Range:bytes ' . $range[0] . '-' . $range[1] . '/' . filesize($this->cacheFilenameContent));
            }
            $this->logger->debug('contentFile: ' . $this->cacheFilenameContent);
            $this->logger->debug('getRange ' . print_r($range, true));
            $this->logger->debug('_SERVER: ' . print_r($_SERVER, true));
            header('Connection: keep-alive');
            header('Content-Type: audio/mp4');

            // write content.
            if (false !== ($fd = fopen($this->cacheFilenameContent, 'r'))) {
                $totalLength = filesize($this->cacheFilenameContent);
                while (!feof($fd)) {
                    $str = fread($fd, 1024 * 1024);
                    HttpRange::echoData($str, $totalLength, $this->logger);
                }
            } else {
                $this->logger->err(
                    "Could not open file " . $this->cacheFilenameContent .
                    " for reading"
                );
            }

        } else { // Or get from Youtube and cache it
            $this->logger->info("there is no cache for $youtubeId and no process handling it already");


            $ydlFile = "/tmp/{$youtubeId}";
            if (file_exists($ydlFile)) {
                $this->saveUrl(file_get_contents($ydlFile));
            } else {
                $tmpYdlFile = $ydlFile . "." . rand();

                $cmd = "$ydl -g \"{$ysite}?v={$youtubeId}\" > {$tmpYdlFile} ; " .
                    "cat {$tmpYdlFile} | grep \"mime=audio\" || cat {$tmpYdlFile}";
                $this->logger->info("run command: $cmd");
                exec("$cmd", $output, $ret);
                unlink($tmpYdlFile);
                $this->logger->info("command: $cmd. ret: $ret. output: " . print_r($output, true));
                if (0 === $ret && isset($output[0])) {
                    $this->saveUrl($output[0]);
                    file_put_contents($ydlFile, $output[0], LOCK_EX);
                    file_exists($tmpYdlFile) && unlink($tmpYdlFile);
                } else {
                    $this->logger->err("something wrong with cmd. return 404");
                    $this->pageNotFound();
                }
            }
        }
    }
}
