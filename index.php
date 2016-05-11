<?php

// as a composer component
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../../../');

// inside /public/api
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../../');

// inside /public
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../');

// inside ./
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

// as a composer component
set_include_path(
    get_include_path() . PATH_SEPARATOR . __DIR__ .
    '/../../../application/configs/'
);

include_once 'vendor/autoload.php';


use DMelo\YouBetter\YouBetter;

ignore_user_abort(true);

$youBetter = new YouBetter();
$youBetter->processRequest();
