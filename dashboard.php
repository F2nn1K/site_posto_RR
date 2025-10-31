<?php
require_once __DIR__ . '/config.php';

// Gate de autenticação FORTE - sem renderizar nada antes do redirect
// APENAS sessão ativa é permitida (sem fallback de cookie)
if (empty($_SESSION['auth']) || empty($_SESSION['auth']['user_id'])) {
    // Redireciona imediatamente para login
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Location: /login', true, 302);
    exit;
}

// Cache control para área autenticada
header('Cache-Control: no-store, private');
header('Pragma: no-cache');

// Servir o dashboard HTML (conteúdo do dashboard.html)
readfile(__DIR__ . '/dashboard.html');
?>


