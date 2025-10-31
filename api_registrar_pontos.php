<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$user = $_SESSION['auth'] ?? null;
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    $valor = isset($_POST['valor']) ? (int)$_POST['valor'] : 0;
    if ($valor <= 0) { throw new Exception('Valor inválido'); }

    $pontos = (int) floor($valor / 10); // Regra: 1 ponto por R$10
    if ($pontos <= 0) { throw new Exception('Valor muito baixo para gerar pontos'); }

    $pdo = conectarBanco();
    if (!$pdo) { throw new Exception('Erro de conexão'); }

    $stmt = $pdo->prepare('INSERT INTO pontos (participante_id, pontos, valor_compra, data_criacao) VALUES (?, ?, ?, NOW())');
    $stmt->execute([(int)$_SESSION['auth']['user_id'], $pontos, $valor]);

    logarSistema('pontos', 'Pontos registrados: '.$pontos.' (R$'.$valor.')', $_SERVER['REMOTE_ADDR'] ?? null);

    echo json_encode(['success' => true, 'pontos' => $pontos]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
