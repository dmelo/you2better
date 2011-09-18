<?php

include_once 'conf.php';

/**
 * getDirectorySize Calculate the size of the directory.
 *
 * @param mixed $directory Path to the directory.
 * @access public
 * @return int Return the size of the path, in KB.
 */
function getDirectorySize($directory)
{
    $total = 0;
    if (is_dir($directory)) {
        $handle = opendir($directory);
        while ($file = readdir($handle)) {
            if (strcmp($file, '.') !== 0 && strcmp($file, '..')) {
                $fullName = $directory . '/' . $file;
                if (is_file($fullName))
                    $total += filesize($fullName) / 1024;
                else
                    $total += getDirectorySize($fullName);
            }
        }
    }

    return $total;
}

/**
 * isUnderLimit
 *
 * @access public
 * @return void
 */
function isUnderLimit()
{
    return getDirectorySize(DIRECTORY) < SIZE_LIMIT ? true : false;
}

/**
 * deleteOneFile
 *
 * @access public
 * @return void
 */
function deleteOneFile()
{
    $ret = false;
    $handle = opendir(DIRECTORY);
    $oldest = time();
    $oldestFile = '';
    while ($file = readdir($handle)) {
        if (strpos($file, 'internal') !== false) {
            $date = (int) file_get_contents(DIRECTORY . '/' . $file);
            if ($oldest > $date) {
                $oldest = $date;
                $oldestFile = $file;
            }
        }
    }

    if ('' !== $oldestFile) {
        $file = str_replace('internal--', '', $oldestFile);
        //$ret = unlink($file); // TODO test and uncomment.
        echo 'deleting ' . $oldestFile . ' and ' . $file . PHP_EOL;
        $ret = unlink(DIRECTORY . '/' . $file) & unlink(DIRECTORY . '/' . $oldestFile);
    }

    return $ret;
}

while (!isUnderLimit() && deleteOneFile());
