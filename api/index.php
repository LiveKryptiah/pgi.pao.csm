<?php
/**
 * Vercel Serverless Front Controller for PHP
 * Handles routing to all application scripts from project root.
 */

$baseDir = dirname(__DIR__);
chdir($baseDir);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
$path = trim($path, '/');

// 1. Root / Index
if ($path === '' || $path === 'index' || $path === 'index.php' || $path === 'api/index.php' || $path === 'api/index' || $path === 'api') {
    require $baseDir . '/index.php';
    exit;
}

// Sanitize path against directory traversal
$cleanPath = ltrim(str_replace(['..', "\0"], '', $path), '/');
$targetFile = $baseDir . '/' . $cleanPath;

// 2. Direct PHP file (e.g. /dashboard.php, /login.php, /submit.php)
if (is_file($targetFile) && pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
    require $targetFile;
    exit;
}

// 3. Clean URL without extension (e.g. /dashboard, /login)
if (is_file($targetFile . '.php')) {
    require $targetFile . '.php';
    exit;
}

// 4. Static asset fallback
if (is_file($targetFile)) {
    $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $mimes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf'
    ];
    if (isset($mimes[$ext])) {
        header("Content-Type: " . $mimes[$ext]);
    }
    readfile($targetFile);
    exit;
}

// 5. 404 Fallback
http_response_code(404);
echo "404 Not Found";
