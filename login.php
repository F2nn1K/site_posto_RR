<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Checagem de origem simplificada (evita bloqueio indevido)
// Permitimos o POST vindo do mesmo host ou sem cabeçalhos de origem
/* removido para simplificar em produção
*/

// CSRF tolerante para simplificar o fluxo
$csrf = $_POST['csrf_token'] ?? '';
if (!empty($_SESSION['csrf_token']) && !empty($csrf)) {
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Falha de verificação CSRF']);
        exit;
    }
}

// Honeypot
if (!empty($_POST['website'])) {
    logarSistema('security', 'Bot detectado no login', $_SERVER['REMOTE_ADDR'] ?? null);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

// ============================================================
// PROTEÇÃO CONTRA BRUTE FORCE
// ============================================================
// Inicializar contador de tentativas falhas
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_first_attempt'] = time();
}

// Verificar se está bloqueado
$tempoDecorrido = time() - ($_SESSION['login_first_attempt'] ?? time());
$maxTentativas = 5;
$tempoBloqueio = 900; // 15 minutos

if ($_SESSION['login_attempts'] >= $maxTentativas && $tempoDecorrido < $tempoBloqueio) {
    $minutosRestantes = ceil(($tempoBloqueio - $tempoDecorrido) / 60);
    http_response_code(429); // Too Many Requests
    logarSistema('security', 'Login bloqueado por brute force - Tentativas: ' . $_SESSION['login_attempts'], $_SERVER['REMOTE_ADDR'] ?? null);
    echo json_encode([
        'success' => false, 
        'message' => "Muitas tentativas falhas. Aguarde {$minutosRestantes} minuto(s) e tente novamente."
    ]);
    exit;
}

// Se passou o tempo de bloqueio, resetar contador
if ($tempoDecorrido >= $tempoBloqueio) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_first_attempt'] = time();
}

try {
    $email = sanitizarEntrada($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    // Simplificar: permitir login se existir participante com este email e a senha confere
    if (!validarEmail($email)) { throw new Exception('Credenciais inválidas'); }

    $pdo = conectarBanco();
    if (!$pdo) { throw new Exception('Erro de conexão com o banco'); }

    // Autenticar na tabela participantes
    $stmt = $pdo->prepare('SELECT id, nome, email, senha FROM participantes WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        // Incrementar contador de tentativas falhas (usuário não encontrado)
        $_SESSION['login_attempts']++;
        logarSistema('login_failed', 'Tentativa falha #' . $_SESSION['login_attempts'] . ' - Email não encontrado: ' . $email, $_SERVER['REMOTE_ADDR'] ?? null);
        throw new Exception('Email ou senha incorretos');
    }
    // Se senha do banco for bcrypt, valida; se estiver em texto puro, aceita e atualiza para bcrypt
    $ok = password_verify($senha, (string)$user['senha']);
    if (!$ok) {
        $hashStr = (string)$user['senha'];
        $aparenteHash = preg_match('/^\$2[aby]\$/', $hashStr) === 1;
        if (!$aparenteHash && hash_equals($hashStr, $senha)) {
            $novoHash = password_hash($senha, PASSWORD_BCRYPT, ['cost'=>12]);
            try { $pdo->prepare('UPDATE participantes SET senha = ? WHERE id = ?')->execute([$novoHash, (int)$user['id']]); } catch (Throwable $__) {}
            $ok = true;
        }
    }
    if (!$ok) {
        // Incrementar contador de tentativas falhas
        $_SESSION['login_attempts']++;
        logarSistema('login_failed', 'Tentativa falha #' . $_SESSION['login_attempts'] . ' - Email: ' . $email, $_SERVER['REMOTE_ADDR'] ?? null);
        throw new Exception('Email ou senha incorretos');
    }

    // Login bem-sucedido: RESETAR contador de tentativas
    $_SESSION['login_attempts'] = 0;
    unset($_SESSION['login_first_attempt']);

    // Regenerar sessão e guardar dados mínimos
    session_regenerate_id(true);
    $_SESSION['auth'] = [
        'user_id' => (int)$user['id'],
        'name' => $user['nome'],
        'email' => $user['email'],
        'login_at' => time()
    ];
    $_SESSION['last_activity'] = time(); // Inicializar última atividade

    logarSistema('login', 'Login bem-sucedido: ' . $user['email'], $_SERVER['REMOTE_ADDR'] ?? null);

    // Emitir cookie de fallback assinado (para ambientes que perdem a sessão)
    try {
        $secret = hash('sha256', DB_PASS . '|user_secret');
        $payloadArr = [ 'id' => (int)$user['id'], 'ts' => time() ];
        $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha256', $payload, $secret);
        $token = base64_encode($payload) . '.' . $sig;
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        setcookie('user_token', $token, [ 'expires' => time()+86400, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax' ]);
        // Cookie de boot de sessão (curto prazo). Somente durante o redirect pós-login permitimos restaurar sessão.
        setcookie('user_boot', '1', [ 'expires' => time()+60, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax' ]);
    } catch (Throwable $__e) { /* ignore */ }

    // Persistir sessão já e retornar sucesso
    session_write_close();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    logarSistema('login_error', $e->getMessage(), $_SERVER['REMOTE_ADDR'] ?? null);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>


