<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\Response;

// Session ayarları
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_lifetime', '86400'); // 24 saat
ini_set('session.gc_maxlifetime', '86400'); // 24 saat
session_start();

// Session cookie ayarlarını güncelle
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), session_id(), [
        'expires' => time() + 86400, // 24 saat
        'path' => '/',
        'domain' => '',
        'secure' => false, // HTTPS yoksa false
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// CORS - credentials için origin belirtilmeli
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();

// Login sayfası
$router->get('/login', function () {
    $html = file_get_contents(__DIR__ . '/login.html');
    return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
});

// App SPA - Auth kontrolü ile
$spaHandler = function () {
    // Eğer login değilse login sayfasına yönlendir
    if (empty($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
    $html = file_get_contents(__DIR__ . '/app.html');
    return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
};

// Dashboard route'ları
$router->get('/', $spaHandler);
$router->get('/dashboard', $spaHandler);
$router->get('/dashboard/siteler', $spaHandler);
$router->get('/dashboard/sayfalar', $spaHandler);
$router->get('/dashboard/domains', $spaHandler);
$router->get('/dashboard/editor', $spaHandler);
$router->get('/admin', $spaHandler); // Admin paneli için aynı SPA

// Auth
$router->get('/api/auth/check', [App\Controllers\AuthController::class, 'check']);
$router->post('/api/auth/register', [App\Controllers\AuthController::class, 'register']);
$router->post('/api/auth/login', [App\Controllers\AuthController::class, 'login']);
$router->post('/api/auth/logout', [App\Controllers\AuthController::class, 'logout']);

// Sites & Pages
$router->get('/api/sites', [App\Controllers\SiteController::class, 'index']);
$router->post('/api/sites', [App\Controllers\SiteController::class, 'store']);
$router->put('/api/sites/{siteId}', [App\Controllers\SiteController::class, 'update']);
$router->get('/api/sites/{siteId}/pages', [App\Controllers\PageController::class, 'index']);
$router->post('/api/sites/{siteId}/pages', [App\Controllers\PageController::class, 'store']);
$router->get('/api/sites/{siteId}/pages/{pageId}', [App\Controllers\PageController::class, 'show']);
$router->put('/api/sites/{siteId}/pages/{pageId}', [App\Controllers\PageController::class, 'update']);
$router->post('/api/sites/{siteId}/pages/{pageId}/publish', [App\Controllers\PageController::class, 'publish']);
$router->post('/api/sites/{siteId}/pages/{pageId}/preview-token', [App\Controllers\PageController::class, 'createPreviewToken']);
$router->post('/api/sites/{siteId}/pages/{pageId}/social-settings', [App\Controllers\PageController::class, 'saveSocialSettings']);
$router->get('/api/sites/{siteId}/pages/{pageId}/social-settings', [App\Controllers\PageController::class, 'loadSocialSettings']);

// Media
$router->post('/api/media/upload', [App\Controllers\MediaController::class, 'upload']);
$router->get('/api/media', [App\Controllers\MediaController::class, 'index']);
$router->delete('/api/media/{mediaId}', [App\Controllers\MediaController::class, 'destroy']);

// Public page render
$router->get('/s/{siteSlug}/{pageSlug}', [App\Controllers\RenderController::class, 'show']);
$router->get('/preview/{token}', [App\Controllers\RenderController::class, 'preview']);

// Admin routes
$router->get('/api/admin/users', [App\Controllers\AdminController::class, 'users']);
$router->get('/api/admin/sites', [App\Controllers\AdminController::class, 'sites']);
$router->get('/api/admin/pages', [App\Controllers\AdminController::class, 'pages']);
$router->get('/api/admin/audits', [App\Controllers\AdminController::class, 'audits']);
$router->get('/api/admin/status', [App\Controllers\AdminController::class, 'status']);
$router->post('/api/admin/impersonate/{userId}', [App\Controllers\AdminController::class, 'impersonate']);
$router->post('/api/admin/stop-impersonate', [App\Controllers\AdminController::class, 'stopImpersonate']);

// Basit health check
$router->get('/health', function () {
    return Response::json(['status' => 'ok']);
});

// Media files
$router->get('/media/{filename}', function ($filename) {
    $filePath = __DIR__ . '/../storage/media/' . basename($filename);
    if (file_exists($filePath)) {
        $mime = mime_content_type($filePath);
        header('Content-Type: ' . $mime);
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    exit;
});

$router->dispatch($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'));


