<?php
require_once __DIR__ . '/config.php';

// Controlador de rotas protegidas (sessão ativa; com fallback por token após login)
// Uso: /route.php?route=dashboard ou /route.php?route=admin (mapeado pelo .htaccess)

$route = isset($_GET['route']) ? trim($_GET['route']) : '';

function redirectToLogin(string $target) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Location: ' . ($target === 'admin' ? '/login?admin=1' : '/login'), true, 302);
    exit;
}

function ensureUserAuth(): void {
    // Verifica se tem sessão ativa
    if (!empty($_SESSION['auth']['user_id'])) {
        // Verifica timeout de inatividade (já controlado em config.php)
        return;
    }
    
    // Fallback APENAS com cookie de boot (logo após login)
    $hasBoot = !empty($_COOKIE['user_boot']);
    $token = $_COOKIE['user_token'] ?? '';
    
    if ($hasBoot && $token) {
        $pos = strrpos($token, '.');
        if ($pos !== false) {
            $payloadB64 = substr($token, 0, $pos);
            $sig = substr($token, $pos + 1);
            $payload = base64_decode($payloadB64, true);
            $arr = json_decode($payload, true);
            if (is_array($arr) && isset($arr['id'])) {
                $secret = hash('sha256', DB_PASS . '|user_secret');
                $expected = hash_hmac('sha256', $payload, $secret);
                if (hash_equals($expected, $sig)) {
                    $_SESSION['auth'] = [ 'user_id' => (int)$arr['id'], 'name' => 'Participante', 'login_at' => time() ];
                    // Consumir o boot imediatamente
                    setcookie('user_boot', '', time()-3600, '/', $_SERVER['HTTP_HOST'] ?? '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
                    return;
                }
            }
        }
    }
    
    // Sem sessão e sem boot válido: redireciona para login
    redirectToLogin('dashboard');
}

function ensureAdminAuth(): void {
    // Verifica se tem sessão ativa de admin
    if (!empty($_SESSION['auth_admin']['id'])) {
        return;
    }
    
    // Fallback APENAS com cookie de boot (logo após login)
    $hasBoot = !empty($_COOKIE['admin_boot']);
    $token = $_COOKIE['admin_token'] ?? '';
    
    if ($hasBoot && $token) {
        $pos = strrpos($token, '.');
        if ($pos !== false) {
            $payloadB64 = substr($token, 0, $pos);
            $sig = substr($token, $pos + 1);
            $payload = base64_decode($payloadB64, true);
            $arr = json_decode($payload, true);
            if (is_array($arr) && isset($arr['id'])) {
                $secret = hash('sha256', DB_PASS . '|admin_secret');
                $expected = hash_hmac('sha256', $payload, $secret);
                if (hash_equals($expected, $sig)) {
                    $_SESSION['auth_admin'] = [ 'id' => (int)$arr['id'], 'usuario' => 'administrador', 'login_at' => time() ];
                    // Consumir o boot imediatamente
                    setcookie('admin_boot', '', time()-3600, '/', $_SERVER['HTTP_HOST'] ?? '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
                    return;
                }
            }
        }
    }
    
    // Sem sessão e sem boot válido: redireciona para login
    redirectToLogin('admin');
}

switch ($route) {
    case 'dashboard':
        ensureUserAuth();
        require __DIR__ . '/dashboard.php';
        break;
    case 'admin':
        ensureAdminAuth();
        require __DIR__ . '/admin.php';
        break;
    default:
        redirectToLogin('dashboard');
}
?>


