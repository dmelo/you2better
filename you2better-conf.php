<?php

define('DIRECTORY', 'tmp/cache/');
define('SIZE_LIMIT', 3048); // Cache size limit in KB.

return array(
    'ydl' => '../amuzi/vendor/rg3/youtube-dl/youtube-dl',
    'logpath' => dirname(__FILE__) . '/log/',
);
