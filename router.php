<?php
// Development server only: php -S 127.0.0.1:8080 router.php
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (preg_match('#^/assets/[a-zA-Z0-9_./-]+\.(css|js|png|jpg|webp|svg)$#',$path) && !str_contains($path,'..') && is_file(__DIR__.$path)) return false;
if ($path === '/') $path='/index.php';
if (!in_array($path,['/index.php','/portal.php','/api.php','/blueprint-image.php','/product-image.php','/report.php'],true)) { http_response_code(404); exit('Not found'); }
require __DIR__.$path;
