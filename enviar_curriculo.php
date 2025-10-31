<?php
// ================================================================
// PROCESSAMENTO DE ENVIO DE CURRÍCULOS
// Auto Posto Estrela D'Alva
// ================================================================

require_once 'config.php';

// Definir que vai retornar JSON
header('Content-Type: application/json; charset=utf-8');

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se todos os campos obrigatórios foram enviados
$campos_obrigatorios = ['nome', 'email', 'telefone', 'cargo'];
foreach ($campos_obrigatorios as $campo) {
    if (empty($_POST[$campo])) {
        echo json_encode(['success' => false, 'message' => "Campo $campo é obrigatório"]);
        exit;
    }
}

// Verificar honeypot (campo oculto para detectar bots)
if (!empty($_POST['website'])) {
    logarSistema('security', 'Bot detectado via honeypot', $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

try {
    // Sanitizar dados
    $nome = sanitizarEntrada($_POST['nome']);
    $email = sanitizarEntrada($_POST['email']);
    $telefone = sanitizarEntrada($_POST['telefone']);
    $cargo = sanitizarEntrada($_POST['cargo']);
    
    // Validações
    if (strlen($nome) < 3 || strlen($nome) > 100) {
        throw new Exception('Nome deve ter entre 3 e 100 caracteres');
    }
    
    if (!validarEmail($email)) {
        throw new Exception('Email inválido');
    }
    
    if (!validarTelefone($telefone)) {
        throw new Exception('Telefone inválido. Use o formato: (95) 99999-9999');
    }
    
    $cargos_validos = ['Frentista', 'Auxiliar de Limpeza', 'Auxiliar Administrativo'];
    if (!in_array($cargo, $cargos_validos)) {
        throw new Exception('Cargo inválido');
    }
    
    // Verificar rate limiting (máximo 3 envios por IP por hora)
    $pdo = conectarBanco();
    if (!$pdo) {
        throw new Exception('Erro de conexão com o banco de dados');
    }
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM curriculos WHERE ip_address = ? AND data_criacao > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$ip]);
    $count = $stmt->fetchColumn();
    
    if ($count >= 3) {
        logarSistema('security', 'Rate limit excedido', $ip);
        throw new Exception('Muitas tentativas. Tente novamente em uma hora.');
    }
    
    // Processar upload do arquivo se enviado
    $arquivo_conteudo = null;
    $arquivo_nome = null;
    $arquivo_mime_type = null;
    
    if (isset($_FILES['curriculo']) && $_FILES['curriculo']['error'] === UPLOAD_ERR_OK) {
        $arquivo = $_FILES['curriculo'];
        
        // Validar arquivo
        if ($arquivo['size'] > MAX_FILE_SIZE) {
            throw new Exception('Arquivo muito grande. Máximo 1MB');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $arquivo['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, ALLOWED_FILE_TYPES)) {
            throw new Exception('Apenas arquivos PDF são permitidos');
        }
        
        // Ler o conteúdo do arquivo para salvar no banco
        $arquivo_conteudo = file_get_contents($arquivo['tmp_name']);
        $arquivo_nome = $arquivo['name'];
        $arquivo_mime_type = $mime_type;
        
        if ($arquivo_conteudo === false) {
            throw new Exception('Erro ao ler o arquivo');
        }
    }
    
    // Inserir no banco de dados
    $sql = "INSERT INTO curriculos (nome, email, telefone, cargo, arquivo_nome, arquivo_conteudo, arquivo_mime_type, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([$nome, $email, $telefone, $cargo, $arquivo_nome, $arquivo_conteudo, $arquivo_mime_type, $ip]);
    
    if ($resultado) {
        $curriculo_id = $pdo->lastInsertId();
        
        // Registrar log de sucesso
        logarSistema('form_submit', "Currículo enviado com sucesso - ID: $curriculo_id, Nome: $nome, Cargo: $cargo", $ip);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Currículo enviado com sucesso! Entraremos em contato em breve.',
            'id' => $curriculo_id
        ]);
    } else {
        throw new Exception('Erro ao salvar no banco de dados');
    }
    
} catch (Exception $e) {
    // Registrar log de erro
    logarSistema('error', 'Erro ao enviar currículo: ' . $e->getMessage(), $_SERVER['REMOTE_ADDR']);
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
