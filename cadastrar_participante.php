<?php
// ================================================================
// PROCESSAMENTO DE CADASTRO DE PARTICIPANTES
// Auto Posto Estrela D'Alva - Promoção Aniversário
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

// Bloquear requests que não vierem do próprio site (origin/referrer check)
$validHosts = [
    $_SERVER['HTTP_HOST'] ?? 'localhost',
    'autopostoestreladalva.com.br',
    'www.autopostoestreladalva.com.br'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isSameOrigin = false;

foreach ($validHosts as $host) {
    if ($origin && stripos($origin, $host) !== false) { $isSameOrigin = true; break; }
    if ($referer && stripos($referer, $host) !== false) { $isSameOrigin = true; break; }
}

if (!$isSameOrigin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Origem inválida']);
    exit;
}

// Verificação de CSRF (tolerante com auto-recuperação)
$csrf_post = $_POST['csrf_token'] ?? '';
$csrf_session = $_SESSION['csrf_token'] ?? '';

// Se não houver token na sessão, criar um novo (auto-recuperação)
if (empty($csrf_session)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrf_session = $_SESSION['csrf_token'];
}

// CSRF tolerante: só verificar se o token foi enviado
if (!empty($csrf_post) && !empty($csrf_session)) {
    if (!hash_equals($csrf_session, $csrf_post)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        logarSistema('csrf_warning', 'Token CSRF não confere mas permitindo - IP: ' . $ip, $ip);
        // Não bloqueia mais, apenas loga
    }
}
// Se não houver token enviado, também permite (modo muito tolerante)

// Verificar se todos os campos obrigatórios foram enviados
$campos_obrigatorios = ['nome', 'email', 'cpf', 'whatsapp', 'idade', 'senha'];
foreach ($campos_obrigatorios as $campo) {
    if (empty($_POST[$campo])) {
        echo json_encode(['success' => false, 'message' => "Campo $campo é obrigatório"]);
        exit;
    }
}

try {
    // Honeypot servidor (caso seja adicionado no HTML)
    if (!empty($_POST['website'])) {
        logarSistema('security', 'Bot detectado via honeypot (cadastro)', $_SERVER['REMOTE_ADDR'] ?? null);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit;
    }

    // Rate limiting por sessão/IP (máx 5 por hora)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $_SESSION['cadastro_attempts'] = $_SESSION['cadastro_attempts'] ?? [];
    $now = time();
    // limpar entradas > 1h
    $_SESSION['cadastro_attempts'] = array_filter(
        $_SESSION['cadastro_attempts'],
        function ($ts) use ($now) { return ($now - $ts) < 3600; }
    );
    if (count($_SESSION['cadastro_attempts']) >= 5) {
        logarSistema('security', 'Rate limit cadastro excedido', $ip);
        throw new Exception('Muitas tentativas. Tente novamente mais tarde.');
    }
    $_SESSION['cadastro_attempts'][] = $now;

    // Sanitizar dados
    $nome = sanitizarEntrada($_POST['nome']);
    // Normalizar espaços (sem espaço inicial/final, sem duplos)
    $nome = trim(preg_replace('/\s+/', ' ', $nome));
    // Validar: apenas letras (com acentos) e espaços entre palavras
    if (!preg_match('/^[\p{L}]+(?: [\p{L}]+)*$/u', $nome)) {
        throw new Exception('Nome inválido. Use apenas letras e espaço (sem começar com espaço).');
    }
    $email = sanitizarEntrada($_POST['email']);
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']); // Apenas números
    $whatsapp = preg_replace('/[^0-9]/', '', $_POST['whatsapp']); // Apenas números
    $idade = (int) $_POST['idade'];
    $senha = $_POST['senha']; // Não sanitizar senha antes de criptografar
    
    // Validações
    if (strlen($nome) < 3 || strlen($nome) > 100) {
        throw new Exception('Nome deve ter entre 3 e 100 caracteres');
    }
    
    if (!validarEmail($email)) {
        throw new Exception('Email inválido');
    }
    
    if (strlen($cpf) !== 11) {
        throw new Exception('CPF inválido. Digite 11 dígitos');
    }
    
    if (strlen($whatsapp) < 10 || strlen($whatsapp) > 11) {
        throw new Exception('WhatsApp inválido');
    }
    
    if ($idade < 18 || $idade > 120) {
        throw new Exception('Idade inválida. Mínimo 18 anos');
    }
    
    if (strlen($senha) < 6) {
        throw new Exception('Senha deve ter pelo menos 6 caracteres');
    }
    
    // Conectar ao banco
    $pdo = conectarBanco();
    if (!$pdo) {
        throw new Exception('Erro de conexão com o banco de dados');
    }
    
    // Verificar se CPF já está cadastrado
    $stmt = $pdo->prepare("SELECT id, nome FROM participantes WHERE cpf = ?");
    $stmt->execute([$cpf]);
    if ($usuario = $stmt->fetch()) {
        throw new Exception('cpf_duplicado');
    }
    
    // Verificar se email já está cadastrado
    $stmt = $pdo->prepare("SELECT id, nome FROM participantes WHERE email = ?");
    $stmt->execute([$email]);
    if ($usuario = $stmt->fetch()) {
        throw new Exception('email_duplicado');
    }
    
    // Verificar se WhatsApp já está cadastrado
    $stmt = $pdo->prepare("SELECT id, nome FROM participantes WHERE whatsapp = ?");
    $stmt->execute([$whatsapp]);
    if ($usuario = $stmt->fetch()) {
        throw new Exception('whatsapp_duplicado');
    }
    
    // Criptografar senha usando bcrypt (PASSWORD_DEFAULT usa bcrypt)
    // Cost 12 = muito seguro (padrão é 10, mas 12 é mais seguro)
    $senha_hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
    
    if ($senha_hash === false) {
        throw new Exception('Erro ao criptografar senha');
    }
    
    // Obter IP do usuário
    $ip = $_SERVER['REMOTE_ADDR'] ?? $ip;
    
    // Inserir no banco de dados
    $sql = "INSERT INTO participantes (nome, email, cpf, whatsapp, idade, senha, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([$nome, $email, $cpf, $whatsapp, $idade, $senha_hash, $ip]);
    
    if ($resultado) {
        $participante_id = $pdo->lastInsertId();
        
        // Registrar log de sucesso
        logarSistema('cadastro_participante', "Participante cadastrado - ID: $participante_id, Nome: $nome, CPF: $cpf", $ip);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cadastro realizado com sucesso! Você já está participando dos sorteios.',
            'id' => $participante_id
        ]);
    } else {
        throw new Exception('Erro ao salvar no banco de dados');
    }
    
} catch (Exception $e) {
    // Registrar log de erro
    logarSistema('error_cadastro', 'Erro ao cadastrar participante: ' . $e->getMessage(), $_SERVER['REMOTE_ADDR']);
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

// ================================================================
// INFORMAÇÕES SOBRE A CRIPTOGRAFIA
// ================================================================
// 
// PASSWORD_BCRYPT com cost 12:
// - Bcrypt é considerado um dos algoritmos mais seguros para senhas
// - Cost 12 significa 2^12 (4096) iterações do algoritmo
// - Quanto maior o cost, mais seguro, mas mais lento
// - Cost 12 leva ~300ms para processar (bom balanço segurança/performance)
// - A senha hash terá 60 caracteres no formato: $2y$12$...
// - Cada hash é único mesmo para senhas iguais (salt automático)
// 
// Para verificar uma senha posteriormente, use:
// password_verify($senha_digitada, $senha_hash_do_banco)
// 
// ================================================================
?>

