<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$user = $_SESSION['auth'] ?? null;
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

try {
    $pdo = conectarBanco();
    if (!$pdo) { throw new Exception('db'); }

    $userId = (int)$user['user_id'];

    // Totais simples (ajuste conforme schema real)
    $total = (int)$pdo->query("SELECT COALESCE(SUM(pontos),0) FROM pontos WHERE participante_id = $userId")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(pontos),0) FROM pontos WHERE participante_id = ? AND MONTH(data_criacao) = MONTH(CURRENT_DATE()) AND YEAR(data_criacao) = YEAR(CURRENT_DATE())");
    $stmt->execute([$userId]);
    $mes = (int)$stmt->fetchColumn();

    $resgates = (int)$pdo->query("SELECT COALESCE(COUNT(*),0) FROM resgates WHERE participante_id = $userId")->fetchColumn();

    echo json_encode([
        'total' => $total,
        'mes' => $mes,
        'resgates' => $resgates,
        'atualizado_em' => date('d/m/Y H:i')
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'internal']);
}
