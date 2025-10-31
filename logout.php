<?php
require_once __DIR__ . '/config.php';

// Encerrar sessão com segurança
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Limpar também cookies de fallback e boot
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$host = $_SERVER['HTTP_HOST'] ?? '';
$expireTime = time() - 3600;

setcookie('user_token', '', $expireTime, '/', $host, $isHttps, true);
setcookie('admin_token', '', $expireTime, '/', $host, $isHttps, true);
setcookie('user_boot', '', $expireTime, '/', $host, $isHttps, true);
setcookie('admin_boot', '', $expireTime, '/', $host, $isHttps, true);

// Decidir resposta: se o cliente espera JSON (fetch/AJAX), retorna JSON; senão, redireciona
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantsJson = (stripos($accept, 'application/json') !== false) || (isset($_GET['json']) && $_GET['json'] == '1');

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true]);
} else {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Location: /login', true, 302);
}
