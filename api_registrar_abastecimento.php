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
if (!$user) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Não autenticado']); exit; }

// Validações de chave NFC-e (44 dígitos + DV)
function validarChaveNFeLocal(string $chave): bool {
    return preg_match('/^\d{44}$/', $chave) === 1;
}
function verificarDVChaveNFeLocal(string $chave): ?bool {
    if (!validarChaveNFeLocal($chave)) return null;
    $dvInformado = (int)substr($chave, -1);
    $numeros = substr($chave, 0, 43);
    $peso = 2; $soma = 0;
    for ($i = strlen($numeros) - 1; $i >= 0; $i--) {
        $soma += ((int)$numeros[$i]) * $peso;
        $peso = ($peso == 9) ? 2 : $peso + 1;
    }
    $dv = 11 - ($soma % 11);
    if ($dv >= 10) $dv = 0;
    return $dv === $dvInformado;
}

try {
    $valor = isset($_POST['valor']) ? (int)$_POST['valor'] : 0;
    $chave = isset($_POST['chave']) ? preg_replace('/\D+/', '', (string)$_POST['chave']) : '';

    if ($valor <= 0) { throw new Exception('Informe um valor válido.'); }
    if (!validarChaveNFeLocal($chave)) { throw new Exception('Chave de acesso inválida. Informe os 44 dígitos. Você digitou ' . strlen($chave) . ' dígitos.'); }
    
    // Validação do DV (permite notas em contingência)
    $dvOk = verificarDVChaveNFeLocal($chave);
    // Aceita mesmo com DV inválido (para notas em contingência)
    // if ($dvOk !== true) { throw new Exception('Chave de acesso inválida. Verifique se digitou corretamente os 44 números que aparecem no cupom fiscal.'); }

    // Regra: 1 código a cada R$20
    $qtd = (int) floor($valor / 20);
    if ($qtd <= 0) { throw new Exception('Valor insuficiente para gerar códigos'); }

    // Foto OBRIGATÓRIA (até 50MB, JPG/PNG/WEBP)
    $fotoConteudo = null; $fotoMime = null; $fotoNome = null;
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Anexe a foto do cupom (JPG, PNG ou WEBP, até 50MB).');
    }
    if ($_FILES['foto']['size'] > 50 * 1024 * 1024) {
        throw new Exception('Foto muito grande (máx 50MB)');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['foto']['tmp_name']);
    finfo_close($finfo);
    $permitidos = ['image/jpeg','image/png','image/webp'];
    if (!in_array($mime, $permitidos)) { throw new Exception('Tipo de imagem inválido'); }
    $fotoConteudo = file_get_contents($_FILES['foto']['tmp_name']);
    $fotoMime = $mime;
    $fotoNome = basename($_FILES['foto']['name']);

    $pdo = conectarBanco();
    if (!$pdo) { throw new Exception('Erro de conexão'); }
    $pdo->beginTransaction();

    // Impedir reuso da mesma chave de acesso (usa coluna cupom para armazenar a chave)
    $dup = $pdo->prepare('SELECT 1 FROM abastecimentos WHERE cupom = ? LIMIT 1');
    $dup->execute([$chave]);
    if ($dup->fetchColumn()) {
        throw new Exception('Esta chave de acesso já foi registrada.');
    }

    // Registrar abastecimento (com foto opcional salva no banco)
    $stmt = $pdo->prepare('INSERT INTO abastecimentos (participante_id, valor, cupom, foto_nome, foto_mime, foto_conteudo, data_criacao) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([(int)$_SESSION['auth']['user_id'], $valor, $chave, $fotoNome, $fotoMime, $fotoConteudo]);
    $abastecimentoId = (int)$pdo->lastInsertId();

    // Gerar códigos
    $stmtCodigo = $pdo->prepare('INSERT INTO codigos (participante_id, abastecimento_id, codigo, data_criacao) VALUES (?, ?, ?, NOW())');
    for ($i = 0; $i < $qtd; $i++) {
        $codigo = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
        $stmtCodigo->execute([(int)$_SESSION['auth']['user_id'], $abastecimentoId, $codigo]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'qtd_codigos' => $qtd]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
