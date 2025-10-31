<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['auth'] ?? null;
if (!$user) {
    // Fallback via cookie assinado
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
if (!$user) { http_response_code(401); echo json_encode(['error'=>'not_authenticated']); exit; }

try {
    $pdo = conectarBanco();
    if (!$pdo) { throw new Exception('db'); }
    $userId = (int)$user['user_id'];

    // Totais
    $totalAbastec = (int)$pdo->query("SELECT COALESCE(COUNT(*),0) FROM abastecimentos WHERE participante_id = $userId")->fetchColumn();
    $totalCodigos = (int)$pdo->query("SELECT COALESCE(COUNT(*),0) FROM codigos WHERE participante_id = $userId")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(COUNT(*),0) FROM codigos WHERE participante_id = ? AND MONTH(data_criacao) = MONTH(CURRENT_DATE()) AND YEAR(data_criacao) = YEAR(CURRENT_DATE())");
    $stmt->execute([$userId]);
    $codMes = (int)$stmt->fetchColumn();

    echo json_encode([
        'total_codigos' => $totalCodigos,
        'total_abastecimentos' => $totalAbastec,
        'codigos_mes' => $codMes,
        'atualizado_em' => date('d/m/Y H:i')
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'internal']);
}
