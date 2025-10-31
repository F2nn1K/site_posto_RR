<?php
require_once __DIR__ . '/config.php';

// Apenas admin OU participante autenticado pode ver sua própria imagem
// Se a sessão não estiver presente, tentamos restaurar a partir de tokens válidos
$isAdmin = !empty($_SESSION['auth_admin']['id']);
$isUser = !empty($_SESSION['auth']['user_id']);
if (!$isAdmin && !$isUser) {
    // Tentar admin_token
    $t = $_COOKIE['admin_token'] ?? '';
    if ($t) {
        $pos = strrpos($t, '.');
        if ($pos !== false) {
            $payload = base64_decode(substr($t, 0, $pos), true);
            $sig = substr($t, $pos + 1);
            $arr = json_decode($payload, true);
            $secret = hash('sha256', DB_PASS . '|admin_secret');
            if (is_array($arr) && isset($arr['id']) && hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
                $_SESSION['auth_admin'] = [ 'id' => (int)$arr['id'], 'usuario' => 'administrador', 'login_at' => time() ];
                $isAdmin = true;
            }
        }
    }
    // Tentar user_token (somente para o próprio usuário)
    if (!$isAdmin && !$isUser) {
        $t = $_COOKIE['user_token'] ?? '';
        if ($t) {
            $pos = strrpos($t, '.');
            if ($pos !== false) {
                $payload = base64_decode(substr($t, 0, $pos), true);
                $sig = substr($t, $pos + 1);
                $arr = json_decode($payload, true);
                $secret = hash('sha256', DB_PASS . '|user_secret');
                if (is_array($arr) && isset($arr['id']) && hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
                    $_SESSION['auth'] = [ 'user_id' => (int)$arr['id'], 'name' => 'Participante', 'login_at' => time() ];
                    $isUser = true;
                }
            }
        }
    }
    if (!$isAdmin && !$isUser) {
        http_response_code(403);
        exit('Acesso negado');
    }
}

$abastecimentoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($abastecimentoId <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

try {
    $pdo = conectarBanco();
    if (!$pdo) { throw new Exception('db'); }

    if ($isAdmin) {
        $stmt = $pdo->prepare('SELECT foto_conteudo, foto_mime, foto_nome FROM abastecimentos WHERE id = ?');
        $stmt->execute([$abastecimentoId]);
    } else {
        // Participante só pode ver a própria imagem
        $stmt = $pdo->prepare('SELECT foto_conteudo, foto_mime, foto_nome FROM abastecimentos WHERE id = ? AND participante_id = ?');
        $stmt->execute([$abastecimentoId, (int)$_SESSION['auth']['user_id']]);
    }

    $row = $stmt->fetch();
    if (!$row || empty($row['foto_conteudo'])) {
        http_response_code(404);
        exit('Imagem não encontrada');
    }

    $mime = $row['foto_mime'] ?: 'image/jpeg';
    $name = $row['foto_nome'] ?: 'cupom.jpg';
    $content = $row['foto_conteudo'];

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: private, max-age=604800'); // 7 dias
    header('Content-Disposition: inline; filename="' . $name . '"');
    echo $content;
} catch (Throwable $e) {
    http_response_code(500);
    exit('Erro ao carregar imagem');
}


