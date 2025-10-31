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
if (!$user) { echo json_encode(['items' => []]); exit; }

try {
    $pdo = conectarBanco();
    if (!$pdo) { throw new Exception('db'); }
    $userId = (int)$user['user_id'];

$where = 'WHERE c.participante_id = ?';
$params = [$userId];

// Filtro opcional por data (YYYY-MM-DD)
$dia = isset($_GET['dia']) ? $_GET['dia'] : '';
if ($dia && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
    $where .= ' AND DATE(a.data_criacao) = ?';
    $params[] = $dia;
}

$sql = "SELECT c.codigo, c.abastecimento_id, a.cupom, a.valor, 
        DATE_FORMAT(a.data_criacao, '%d/%m/%Y %H:%i') as data,
        CASE WHEN a.foto_conteudo IS NOT NULL THEN 1 ELSE 0 END as tem_foto
        FROM codigos c
        JOIN abastecimentos a ON a.id = c.abastecimento_id
        $where
        ORDER BY a.data_criacao DESC
        LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
    $rows = $stmt->fetchAll();

    $items = array_map(function($r){
        return [
            'codigo' => $r['codigo'],
            'cupom' => $r['cupom'],
            'valor' => (int)$r['valor'],
            'data' => $r['data'],
            'abastecimento_id' => (int)$r['abastecimento_id'],
            'tem_foto' => ($r['tem_foto'] ? true : false)
        ];
    }, $rows);

    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['items' => []]);
}
