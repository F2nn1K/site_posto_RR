// Google Apps Script para receber currículos
// Auto Posto Estrela D'Alva - Boa Vista/RR

// Função obrigatória para Web App - responde a requisições GET
function doGet(e) {
  return ContentService.createTextOutput(JSON.stringify({
    status: 'success',
    message: 'Google Apps Script está funcionando!',
    timestamp: new Date().toISOString()
  })).setMimeType(ContentService.MimeType.JSON);
}

// Função principal para receber dados do formulário
function doPost(e) {
  try {
    console.log('📝 Recebendo dados do formulário...');
    
    // Obter dados do formulário
    const formData = e.parameter;
    const fileBlob = e.parameter.curriculo;
    
    console.log('📋 Dados recebidos:', {
      nome: formData.nome,
      email: formData.email,
      telefone: formData.telefone,
      cargo: formData.cargo,
      temArquivo: !!fileBlob
    });
    
    // Validar dados obrigatórios
    if (!formData.nome || !formData.email || !formData.telefone || !formData.cargo) {
      console.log('❌ Erro: Campos obrigatórios faltando');
      return ContentService.createTextOutput(JSON.stringify({
        success: false,
        message: 'Todos os campos são obrigatórios'
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    // Validar email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(formData.email)) {
      console.log('❌ Erro: Email inválido');
      return ContentService.createTextOutput(JSON.stringify({
        success: false,
        message: 'E-mail inválido'
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    // Validar telefone (formato brasileiro)
    const phoneRegex = /^\(?[1-9]{2}\)?[0-9]{4,5}-?[0-9]{4}$/;
    if (!phoneRegex.test(formData.telefone.replace(/\s/g, ''))) {
      console.log('❌ Erro: Telefone inválido');
      return ContentService.createTextOutput(JSON.stringify({
        success: false,
        message: 'Telefone inválido'
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    // Sanitizar dados
    const nome = sanitizeInput(formData.nome);
    const email = sanitizeInput(formData.email);
    const telefone = sanitizeInput(formData.telefone);
    const cargo = sanitizeInput(formData.cargo);
    
    console.log('✅ Dados sanitizados:', { nome, email, telefone, cargo });
    
    // Construir assunto do email
    const assunto = `Candidatura - ${cargo} - ${nome}`;
    
    // Construir corpo do email
    const corpoEmail = `
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
    .header { background: #dc2626; color: white; padding: 20px; text-align: center; }
    .content { padding: 20px; }
    .field { margin-bottom: 15px; }
    .label { font-weight: bold; color: #dc2626; }
    .value { margin-left: 10px; }
    .footer { background: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #666; }
  </style>
</head>
<body>
  <div class="header">
    <h2>🆕 Nova Candidatura Recebida</h2>
    <p>Auto Posto Estrela D'Alva - Boa Vista/RR</p>
  </div>
  
  <div class="content">
    <div class="field">
      <span class="label">Nome:</span>
      <span class="value">${nome}</span>
    </div>
    
    <div class="field">
      <span class="label">E-mail:</span>
      <span class="value">${email}</span>
    </div>
    
    <div class="field">
      <span class="label">Telefone:</span>
      <span class="value">${telefone}</span>
    </div>
    
    <div class="field">
      <span class="label">Cargo de Interesse:</span>
      <span class="value">${cargo}</span>
    </div>
    
    <div class="field">
      <span class="label">Data e Hora:</span>
      <span class="value">${new Date().toLocaleString('pt-BR')}</span>
    </div>
    
    <div class="field">
      <span class="label">IP do Candidato:</span>
      <span class="value">${formData.ip || 'Não disponível'}</span>
    </div>
  </div>
  
  <div class="footer">
    <p>📧 Este currículo foi enviado através do site do Auto Posto Estrela D'Alva</p>
    <p>📍 R. Estrela D'álva, 1794 - Boa Vista/RR | 📞 (95) 99174-0090</p>
  </div>
</body>
</html>
    `;
    
    // Configurar opções do email
    const mailOptions = {
      to: 'leonardobrsvicente@gmail.com', // Email de destino
      subject: assunto,
      htmlBody: corpoEmail,
      noReply: true
    };
    
    // Adicionar anexo se existir
    if (fileBlob && fileBlob.getBytes && fileBlob.getBytes().length > 0) {
      console.log('📎 Adicionando anexo ao email...');
      mailOptions.attachments = [{
        fileName: `Curriculo_${nome.replace(/\s+/g, '_')}.pdf`,
        content: fileBlob.getBytes(),
        mimeType: 'application/pdf'
      }];
    }
    
    console.log('📧 Enviando email...');
    
    // Enviar email
    GmailApp.sendEmail(
      mailOptions.to,
      mailOptions.subject,
      mailOptions.htmlBody.replace(/<[^>]*>/g, ''), // Versão texto simples
      mailOptions
    );
    
    // Log do envio
    console.log(`✅ Currículo enviado com sucesso: ${nome} - ${cargo} - ${new Date()}`);
    
    // Retornar sucesso
    return ContentService.createTextOutput(JSON.stringify({
      success: true,
      message: 'Currículo enviado com sucesso!'
    })).setMimeType(ContentService.MimeType.JSON);
    
  } catch (error) {
    // Log do erro
    console.error('❌ Erro ao processar currículo:', error);
    
    // Retornar erro
    return ContentService.createTextOutput(JSON.stringify({
      success: false,
      message: 'Erro interno do servidor. Tente novamente.'
    })).setMimeType(ContentService.MimeType.JSON);
  }
}

// Função para sanitizar entrada
function sanitizeInput(input) {
  if (!input) return '';
  
  return input
    .toString()
    .replace(/[<>]/g, '') // Remove < e >
    .replace(/javascript:/gi, '') // Remove javascript:
    .replace(/on\w+=/gi, '') // Remove event handlers
    .replace(/data:/gi, '') // Remove data URLs
    .trim()
    .substring(0, 1000); // Limita a 1000 caracteres
}

// Função para testar o envio de email (execute esta no editor)
function testarEnvioEmail() {
  try {
    console.log('🧪 Iniciando teste de envio de email...');
    
    // Dados de teste
    const dadosTeste = {
      nome: 'João Silva Teste',
      email: 'joao.teste@gmail.com',
      telefone: '(95) 99999-9999',
      cargo: 'Frentista',
      ip: '192.168.1.1'
    };
    
    // Sanitizar dados
    const nome = sanitizeInput(dadosTeste.nome);
    const email = sanitizeInput(dadosTeste.email);
    const telefone = sanitizeInput(dadosTeste.telefone);
    const cargo = sanitizeInput(dadosTeste.cargo);
    
    console.log('📝 Dados de teste:', dadosTeste);
    
    // Construir assunto do email
    const assunto = `TESTE - Candidatura - ${cargo} - ${nome}`;
    
    // Construir corpo do email
    const corpoEmail = `
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
    .header { background: #dc2626; color: white; padding: 20px; text-align: center; }
    .content { padding: 20px; }
    .field { margin-bottom: 15px; }
    .label { font-weight: bold; color: #dc2626; }
    .value { margin-left: 10px; }
    .footer { background: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #666; }
  </style>
</head>
<body>
  <div class="header">
    <h2>🧪 TESTE - Nova Candidatura Recebida</h2>
    <p>Auto Posto Estrela D'Alva - Boa Vista/RR</p>
  </div>
  
  <div class="content">
    <div class="field">
      <span class="label">Nome:</span>
      <span class="value">${nome}</span>
    </div>
    
    <div class="field">
      <span class="label">E-mail:</span>
      <span class="value">${email}</span>
    </div>
    
    <div class="field">
      <span class="label">Telefone:</span>
      <span class="value">${telefone}</span>
    </div>
    
    <div class="field">
      <span class="label">Cargo de Interesse:</span>
      <span class="value">${cargo}</span>
    </div>
    
    <div class="field">
      <span class="label">Data e Hora:</span>
      <span class="value">${new Date().toLocaleString('pt-BR')}</span>
    </div>
    
    <div class="field">
      <span class="label">IP do Candidato:</span>
      <span class="value">${dadosTeste.ip}</span>
    </div>
    
    <div class="field">
      <span class="label">Status:</span>
      <span class="value">🧪 EMAIL DE TESTE - IGNORAR</span>
    </div>
  </div>
  
  <div class="footer">
    <p>📧 Este é um email de teste do sistema de currículos</p>
    <p>📍 R. Estrela D'álva, 1794 - Boa Vista/RR | 📞 (95) 99174-0090</p>
  </div>
</body>
</html>
    `;
    
    console.log('📧 Enviando email de teste...');
    
    // Enviar email
    GmailApp.sendEmail(
      'leonardobrsvicente@gmail.com',
      assunto,
      corpoEmail.replace(/<[^>]*>/g, ''), // Versão texto simples
      {
        htmlBody: corpoEmail,
        noReply: true
      }
    );
    
    console.log('✅ Email de teste enviado com sucesso!');
    console.log('📬 Verifique a caixa de entrada: leonardobrsvicente@gmail.com');
    
  } catch (error) {
    console.error('❌ Erro no teste:', error);
  }
} 