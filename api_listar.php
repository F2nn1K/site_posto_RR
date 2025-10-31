<?php
// API para listar currículos em JSON (compatível com o viewer)
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = conectarBanco();
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'db_connect']);
        exit;
    }

    $sql = "SELECT id, nome, email, telefone, cargo, arquivo_nome, arquivo_conteudo, arquivo_mime_type, data_criacao, status FROM curriculos ORDER BY data_criacao DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $r) {
        $arquivoUrl = null;
        if (!empty($r['arquivo_conteudo'])) {
            $mime = $r['arquivo_mime_type'] ?: 'application/pdf';
            $arquivoUrl = 'data:' . $mime . ';base64,' . base64_encode($r['arquivo_conteudo']);
        }

        $data[] = [
            'id' => (int)$r['id'],
            'nome' => $r['nome'],
            'email' => $r['email'],
            'telefone' => $r['telefone'],
            'cargo' => $r['cargo'],
            'arquivo_url' => $arquivoUrl,
            'data_envio' => $r['data_criacao'],
            'data_visualizacao' => null
        ];
    }

    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'internal', 'message' => $e->getMessage()]);
}
?>


