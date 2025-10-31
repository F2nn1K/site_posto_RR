<?php
// ================================================================
// CONFIGURAÇÃO DO BANCO DE DADOS - HOSTINGER
// Auto Posto Estrela D'Alva
// ================================================================

// Configurações do banco MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'u995570504_posto');
define('DB_USER', 'u995570504_root');
define('DB_PASS', 'Str@da302');
define('DB_CHARSET', 'utf8mb4');

// Configurações de segurança
define('MAX_FILE_SIZE', 1048576); // 1MB
define('ALLOWED_FILE_TYPES', ['application/pdf']);

// Criar conexão com o banco
function conectarBanco() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Alinhar fuso horário da sessão MySQL ao fuso do PHP (Boa Vista)
        try {
            $tz = new DateTimeZone('America/Boa_Vista');
            $offset = (new DateTime('now', $tz))->format('P'); // ex: -04:00
            $pdo->exec("SET time_zone = '" . $offset . "'");
        } catch (Exception $e) {
            // Se falhar, segue sem alterar
        }
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erro de conexão: " . $e->getMessage());
        return false;
    }
}

// Função para log
function logarSistema($tipo, $mensagem, $ip = null) {
    try {
        $pdo = conectarBanco();
        if ($pdo) {
            $ip = $ip ?: $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO logs (tipo, mensagem, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$tipo, $mensagem, $ip]);
        }
    } catch (Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

// Função para sanitizar entrada
function sanitizarEntrada($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Função para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para validar telefone brasileiro
function validarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    return preg_match('/^[1-9]{2}[0-9]{8,9}$/', $telefone);
}

// Configurações de erro
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// Configurações de sessão (cookies endurecidos)
// Aplicar antes de iniciar a sessão
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    // PHP >= 7.3: usar array de parâmetros
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => $isHttps,
        'httponly' => true,
        // Lax evita bloqueios em navegações pós-login mantendo boa segurança
        'samesite' => 'Lax'
    ];
    // Definir parâmetros de cookie da sessão
    if (function_exists('session_set_cookie_params')) {
        session_set_cookie_params($cookieParams);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
    // Prevenir fixação de sessão em primeiro acesso
    if (!isset($_SESSION['__init'])) {
        session_regenerate_id(true);
        $_SESSION['__init'] = true;
    }
}

// Fuso horário padrão (Boa Vista - RR)
date_default_timezone_set('America/Boa_Vista');

// ============================================================
// CONTROLE DE SESSÃO E TIMEOUT
// ============================================================
// Máximo de inatividade permitido (segundos)
$__MAX_IDLE_SECONDS_USER = 1800;  // 30 minutos para participantes
$__MAX_IDLE_SECONDS_ADMIN = 600;  // 10 minutos para admin

// IMPORTANTE: Removida restauração automática de sessão via cookie
// Sessões DEVEM ser criadas APENAS no login (login.php ou admin_login.php)
// Cookies servem APENAS para fallback imediato após login (com cookie boot)

// Enforça timeout por inatividade para PARTICIPANTES (30 minutos)
if (!empty($_SESSION['auth']) && !empty($_SESSION['auth']['user_id'])) {
    $now = time();
    $last = $_SESSION['last_activity'] ?? $now;
    if (($now - (int)$last) > $__MAX_IDLE_SECONDS_USER) {
        // Timeout: limpa sessão e cookies
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        setcookie('user_token', '', time() - 3600, '/', $host, $isHttps, true);
        setcookie('user_boot', '', time() - 3600, '/', $host, $isHttps, true);
        // Não faz redirect aqui; cada endpoint/página pode decidir o fluxo
    } else {
        $_SESSION['last_activity'] = $now;
    }
}

// Mesmo controle para ADMIN (10 minutos - mais rigoroso)
if (!empty($_SESSION['auth_admin']) && !empty($_SESSION['auth_admin']['id'])) {
    $now = time();
    $last = $_SESSION['last_activity'] ?? $now;
    if (($now - (int)$last) > $__MAX_IDLE_SECONDS_ADMIN) {
        // Timeout admin: limpa sessão e cookies
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        setcookie('admin_token', '', time() - 3600, '/', $host, $isHttps, true);
        setcookie('admin_boot', '', time() - 3600, '/', $host, $isHttps, true);
    } else {
        $_SESSION['last_activity'] = $now;
    }
}

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

// Content Security Policy (adequada ao uso atual com CDNs e estilos inline mínimos)
// Ajuste os domínios conforme novos assets externos forem adicionados
$csp = "default-src 'self'; "
    . "script-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net 'unsafe-inline'; "
    . "style-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net 'unsafe-inline'; "
    . "img-src 'self' data: blob:; "
    . "font-src 'self' https://cdnjs.cloudflare.com data:; "
    . "connect-src 'self'; "
    . "media-src 'self'; "
    . "frame-ancestors 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'";
header('Content-Security-Policy: ' . $csp);

// HSTS somente em HTTPS
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
?>
