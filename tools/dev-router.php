<?php
// Local test server router: blocks the same folders the .htaccess files block on the real hosting.
// Usage: php -S 127.0.0.1:8083 -t <copy-of-website-files> tools/dev-router.php
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('~^/(clients|album-core)(/|$)~i', $path)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden';
    return true;
}
return false;
