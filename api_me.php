<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['auth'] ?? null;
// Fallback via cookie assinado se a sessão estiver vazia
if (!$user) {
    $token = $_COOKIE['user_token'] ?? '';
    if ($token) {
        $pos = strrpos($token, '.');
        if ($pos !== false) {
            $payloadB64 = substr($token, 0, $pos);
            $sig = substr($token, $pos + 1);
            $payload = base64_decode($payloadB64, true);
            if ($payload !== false) {
                $arr = json_decode($payload, true);
                if (is_array($arr) && isset($arr['id'])) {
                    $secret = hash('sha256', DB_PASS . '|user_secret');
                    $expected = hash_hmac('sha256', $payload, $secret);
                    if (hash_equals($expected, $sig)) {
                        $_SESSION['auth'] = [ 'user_id' => (int)$arr['id'], 'name' => 'Participante', 'login_at' => time() ];
                        $user = $_SESSION['auth'];
                    }
                }
            }
        }
    }
}
if (!$user) {
    echo json_encode(['auth' => false]);
    exit;
}

// Buscar dados atualizados do banco de dados
$userId = (int)$user['user_id'];
$nome = $user['name'] ?? 'Participante';
$email = $user['email'] ?? null;

try {
    $pdo = conectarBanco();
    if ($pdo) {
        $stmt = $pdo->prepare('SELECT nome, email FROM participantes WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $participante = $stmt->fetch();
        if ($participante) {
            $nome = $participante['nome'];
            $email = $participante['email'];
            // Atualizar sessão com dados do banco
            $_SESSION['auth']['name'] = $nome;
            $_SESSION['auth']['email'] = $email;
        }
    }
} catch (Exception $e) {
    // Se falhar, usa os dados da sessão
}

echo json_encode([
    'auth' => true,
    'id' => $userId,
    'nome' => $nome,
    'email' => $email
]);
