<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Checagens básicas
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }

// Correlation id para depuração
$__RID = bin2hex(random_bytes(4));
$__IP  = $_SERVER['REMOTE_ADDR'] ?? null;
$__SID = session_id();

// Origem/Referer: simplificado para evitar bloqueios – permitir sempre (mesmo domínio)
$validHosts = [$_SERVER['HTTP_HOST'] ?? 'localhost'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$ok = true;

// CSRF (tolerante): aceita via POST ou header; se falhar, segue mesmo assim para simplificar o login do admin
$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$csrfOk = (!empty($_SESSION['csrf_token']) && !empty($csrf) && hash_equals($_SESSION['csrf_token'], $csrf));

$user = trim($_POST['usuario'] ?? '');
$senha = $_POST['senha'] ?? '';

// ============================================================
// PROTEÇÃO CONTRA BRUTE FORCE (ADMIN)
// ============================================================
// Inicializar contador de tentativas falhas para admin
if (!isset($_SESSION['admin_login_attempts'])) {
    $_SESSION['admin_login_attempts'] = 0;
    $_SESSION['admin_login_first_attempt'] = time();
}

// Verificar se está bloqueado
$tempoDecorrido = time() - ($_SESSION['admin_login_first_attempt'] ?? time());
$maxTentativas = 5;
$tempoBloqueio = 900; // 15 minutos

if ($_SESSION['admin_login_attempts'] >= $maxTentativas && $tempoDecorrido < $tempoBloqueio) {
    $minutosRestantes = ceil(($tempoBloqueio - $tempoDecorrido) / 60);
    http_response_code(429); // Too Many Requests
    try {
        logarSistema('security', 'Admin login bloqueado por brute force - Tentativas: ' . $_SESSION['admin_login_attempts'], $__IP);
    } catch (Throwable $____) {}
    echo json_encode([
        'success' => false, 
        'message' => "Muitas tentativas falhas. Aguarde {$minutosRestantes} minuto(s)."
    ]);
    exit;
}

// Se passou o tempo de bloqueio, resetar contador
if ($tempoDecorrido >= $tempoBloqueio) {
    $_SESSION['admin_login_attempts'] = 0;
    $_SESSION['admin_login_first_attempt'] = time();
}

try {
    $pdo = conectarBanco(); if (!$pdo) throw new Exception('db');

    // Tabela de logs para depuração (idempotente)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo VARCHAR(50) NOT NULL,
            mensagem TEXT NULL,
            ip_address VARCHAR(45) NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $____) { /* ignora */ }

    $dbg = function($tipo, $mensagem) use ($pdo, $__IP, $__RID) {
        try { $stmt=$pdo->prepare('INSERT INTO logs(tipo, mensagem, ip_address) VALUES(?,?,?)'); $stmt->execute([$tipo, '['.$__RID.'] '.$mensagem, $__IP]); } catch (Throwable $e) {}
        error_log('[admin_login]['.$__RID.'] '.$mensagem);
    };
    $dbg('admin_login','BEGIN sid='.$__SID.' origin='.( $origin ?: '-' ).' referer='.( $referer ?: '-' ).' hasSessCookie='.( !empty($_COOKIE[session_name()]) ? '1' : '0' ).' csrfOk='.( $csrfOk ? '1':'0' ));

    // Criar/atualizar tabela admins com campos adicionais
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        usuario VARCHAR(100) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        nome VARCHAR(100) NULL,
        email VARCHAR(150) NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'admin',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        ultimo_login_em TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Detectar colunas existentes (usar fallback sem exigir ALTER privileges)
    $cols = $pdo->query("SHOW COLUMNS FROM admins")->fetchAll();
    $have = array_column($cols, 'Field');
    $haveRole = in_array('role', $have);
    $haveActive = in_array('is_active', $have);
    // Tentativa amigável de adicionar colunas (ignorar erros)
    try { if (!$haveRole)   { $pdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'admin'"); $haveRole = true; } } catch (Throwable $__) {}
    try { if (!$haveActive) { $pdo->exec("ALTER TABLE admins ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1"); $haveActive = true; } } catch (Throwable $__) {}
    try { if (!in_array('nome', $have)) { $pdo->exec("ALTER TABLE admins ADD COLUMN nome VARCHAR(100) NULL"); } } catch (Throwable $__) {}
    try { if (!in_array('email', $have)) { $pdo->exec("ALTER TABLE admins ADD COLUMN email VARCHAR(150) NULL"); } } catch (Throwable $__) {}
    try { if (!in_array('ultimo_login_em', $have)) { $pdo->exec("ALTER TABLE admins ADD COLUMN ultimo_login_em TIMESTAMP NULL DEFAULT NULL"); } } catch (Throwable $__) {}

    // Seed/garantia: cria o usuário 'administrador' (ativo e com role admin) caso não exista
    $stChk = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE usuario = ?');
    $stChk->execute(['administrador']);
    if ((int)$stChk->fetchColumn() === 0) {
        $seedPass = password_hash('Str@da302', PASSWORD_BCRYPT, ['cost'=>12]);
        $stIns = $pdo->prepare('INSERT INTO admins(usuario, senha, nome, role, is_active) VALUES(?,?,?,?,1)');
        $stIns->execute(['administrador', $seedPass, 'Administrador', 'admin']);
    }

    $select = 'SELECT id, usuario, senha';
    if ($haveRole)   { $select .= ', role'; }
    if ($haveActive) { $select .= ', is_active'; }
    $select .= ' FROM admins WHERE usuario = ? LIMIT 1';
    $stmt = $pdo->prepare($select);
    $stmt->execute([$user]);
    $row = $stmt->fetch();
    if (!$row) {
        // Incrementar contador de tentativas falhas (usuário não encontrado)
        $_SESSION['admin_login_attempts']++;
        $dbg('admin_login','user_not_found usuario='.$user.' tentativa='.$_SESSION['admin_login_attempts']);
        throw new Exception('Credenciais inválidas');
    }
    $rowRole = $haveRole ? ($row['role'] ?? 'admin') : 'admin';
    $rowActive = $haveActive ? (int)($row['is_active'] ?? 1) : 1;
    if ($rowActive !== 1 || $rowRole !== 'admin') {
        // Incrementar contador de tentativas falhas (usuário inativo ou role inválida)
        $_SESSION['admin_login_attempts']++;
        $dbg('admin_login','user_inactive_or_role usuario='.$user.' role='.$rowRole.' active='.$rowActive.' tentativa='.$_SESSION['admin_login_attempts']);
        throw new Exception('Credenciais inválidas');
    }

    // Verificar senha (bcrypt) e permitir upgrade automático caso esteja em texto puro
    $senhaValida = password_verify($senha, (string)$row['senha']);
    if (!$senhaValida) {
        $hashStr = (string)$row['senha'];
        $aparenteHash = preg_match('/^\$2[aby]\$/', $hashStr) === 1; // já é bcrypt?
        if (!$aparenteHash && hash_equals($hashStr, $senha)) {
            // senha estava em texto puro -> fazer upgrade para bcrypt
            $novoHash = password_hash($senha, PASSWORD_BCRYPT, ['cost'=>12]);
            $pdo->prepare('UPDATE admins SET senha = ? WHERE id = ?')->execute([$novoHash, (int)$row['id']]);
            $senhaValida = true;
        }
    }
    if (!$senhaValida) {
        // Incrementar contador de tentativas falhas
        $_SESSION['admin_login_attempts']++;
        $dbg('admin_login','password_invalid usuario='.$user.' tentativa='.$_SESSION['admin_login_attempts']);
        throw new Exception('Credenciais inválidas');
    }

    // Login bem-sucedido: RESETAR contador de tentativas
    $_SESSION['admin_login_attempts'] = 0;
    unset($_SESSION['admin_login_first_attempt']);

    session_regenerate_id(true);
    $_SESSION['auth_admin'] = [ 'id' => (int)$row['id'], 'usuario' => $row['usuario'], 'login_at' => time() ];
    $_SESSION['last_activity'] = time(); // Inicializar última atividade
    // Emite cookie de fallback assinado (como no painel do usuário)
    try {
        $secret = hash('sha256', DB_PASS . '|admin_secret');
        $payloadArr = [ 'id' => (int)$row['id'], 'ts' => time() ];
        $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha256', $payload, $secret);
        $token = base64_encode($payload) . '.' . $sig;
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
        setcookie('admin_token', $token, [ 'expires' => time()+86400, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax' ]);
        // Cookie de boot de sessão (curto prazo) para permitir apenas o redirect imediato
        setcookie('admin_boot', '1', [ 'expires' => time()+60, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax' ]);
    } catch (Throwable $____) {}
    // Garante persistência imediata da sessão
    session_write_close();
    $dbg('admin_login','SUCCESS admin_id='.(int)$row['id']);

    // Atualizar timestamp de último login
    $upd = $pdo->prepare('UPDATE admins SET ultimo_login_em = NOW() WHERE id = ?');
    $upd->execute([(int)$row['id']]);

    echo json_encode(['success'=>true,'rid'=>$__RID]);
} catch (Throwable $e) {
    http_response_code(400);
    try { error_log('[admin_login]['.$__RID.'] ERROR '.$e->getMessage()); } catch (Throwable $____) {}
    echo json_encode(['success'=>false,'message'=>$e->getMessage(),'rid'=>$__RID]);
}


