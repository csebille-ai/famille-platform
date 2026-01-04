<?php

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$publicPath = __DIR__.'/public';
$filePath = $publicPath.$uri;

if ($uri !== '/' && file_exists($filePath)) {
    return false;
}

require_once $publicPath.'/index.php';
