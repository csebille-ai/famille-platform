<?php

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$publicPath = __DIR__.'/public';
$filePath = $publicPath.$uri;

// Only serve existing *files* as static assets.
// Directories like /images should still be handled by Laravel routes.
if ($uri !== '/' && is_file($filePath)) {
    return false;
}

require_once $publicPath.'/index.php';
