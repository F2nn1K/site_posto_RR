<?php
// Endpoint simples para emitir/renovar token CSRF atrelado à sessão
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Sempre gera/renova um token forte e o armazena na sessão
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['form_issued_at'] = time();

// Guardar em variáveis antes de fechar a sessão
$token = $_SESSION['csrf_token'];
$issued = $_SESSION['form_issued_at'];

// Garantir que a sessão seja salva imediatamente
session_write_close();

echo json_encode([
    'token' => $token,
    'issued_at' => $issued
]);
?>


