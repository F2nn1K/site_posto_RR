<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Apenas admin (com fallback por cookie assinado)
if (empty($_SESSION['auth_admin']) || empty($_SESSION['auth_admin']['id'])) {
    $token = $_COOKIE['admin_token'] ?? '';
    if ($token) {
        $pos = strrpos($token, '.');
        if ($pos !== false) {
            $payloadB64 = substr($token, 0, $pos);
            $sig = substr($token, $pos + 1);
            $payload = base64_decode($payloadB64, true);
            if ($payload !== false) {
                $arr = json_decode($payload, true);
                if (is_array($arr) && isset($arr['id'])) {
                    $secret = hash('sha256', DB_PASS . '|admin_secret');
                    $expected = hash_hmac('sha256', $payload, $secret);
                    if (hash_equals($expected, $sig)) {
                        $_SESSION['auth_admin'] = [ 'id' => (int)$arr['id'], 'usuario' => 'administrador', 'login_at' => time() ];
                    }
                }
            }
        }
    }
    if (empty($_SESSION['auth_admin']) || empty($_SESSION['auth_admin']['id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = conectarBanco();
    if (!$pdo) { throw new Exception('Erro de conexão'); }
    $pdo->beginTransaction();

    // Buscar um código aleatório ainda não usado
    $sql = "SELECT c.id AS codigo_id, c.codigo, a.id AS abastecimento_id, a.cupom, a.valor, a.data_criacao,
                   p.nome, p.email, p.whatsapp,
                   CASE WHEN a.foto_conteudo IS NULL THEN 0 ELSE 1 END AS tem_foto
            FROM codigos c
            JOIN abastecimentos a ON a.id = c.abastecimento_id
            JOIN participantes p ON p.id = c.participante_id
            WHERE c.usado = 0
            ORDER BY RAND()
            LIMIT 1 FOR UPDATE";
    $row = $pdo->query($sql)->fetch();

    if (!$row) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Nenhum código disponível para sorteio']);
        exit;
    }

    // Marcar o código como usado imediatamente para não ser sorteado novamente
    $upd = $pdo->prepare('UPDATE codigos SET usado = 1, data_uso = NOW() WHERE id = ? AND usado = 0');
    $upd->execute([(int)$row['codigo_id']]);
    // Mesmo que já tenha sido marcado por outra transação, seguimos com o resultado atual
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'codigo_id' => (int)$row['codigo_id'],
            'codigo' => $row['codigo'],
            'participante' => $row['nome'],
            'cupom' => $row['cupom'],
            'valor' => (float)$row['valor'],
            'data' => date('d/m/Y H:i', strtotime($row['data_criacao'])),
            'abastecimento_id' => (int)$row['abastecimento_id'],
            'email' => $row['email'],
            'whatsapp' => $row['whatsapp'],
            'tem_foto' => (int)($row['tem_foto'] ?? 0) === 1
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no sorteio']);
}


