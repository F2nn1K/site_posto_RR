// Google Apps Script para receber currículos
// Deploy como Web App para receber dados do formulário

function doPost(e) {
  try {
    // Obter dados do formulário
    const formData = e.parameter;
    const fileBlob = e.parameter.curriculo;
    
    // Validar dados obrigatórios
    if (!formData.nome || !formData.email || !formData.telefone || !formData.cargo) {
      return ContentService.createTextOutput(JSON.stringify({
        success: false,
        message: 'Todos os campos são obrigatórios'
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    // Validar email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(formData.email)) {
      return ContentService.createTextOutput(JSON.stringify({
        success: false,
        message: 'E-mail inválido'
      })).setMimeType(ContentService.MimeType.JSON);
    }
    
    // Validar telefone (formato brasileiro)
    const phoneRegex = /^\(?[1-9]{2}\)?[0-9]{4,5}-?[0-9]{4}$/;
    if (!phoneRegex.test(formData.telefone.replace(/\s/g, ''))) {
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
      <span class="value">${e.parameter.ip || 'Não disponível'}</span>
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
    if (fileBlob && fileBlob.getBytes().length > 0) {
      mailOptions.attachments = [{
        fileName: `Curriculo_${nome.replace(/\s+/g, '_')}.pdf`,
        content: fileBlob.getBytes(),
        mimeType: 'application/pdf'
      }];
    }
    
    // Enviar email
    GmailApp.sendEmail(
      mailOptions.to,
      mailOptions.subject,
      mailOptions.htmlBody.replace(/<[^>]*>/g, ''), // Versão texto simples
      mailOptions
    );
    
    // Log do envio
    console.log(`Currículo enviado: ${nome} - ${cargo} - ${new Date()}`);
    
    // Retornar sucesso
    return ContentService.createTextOutput(JSON.stringify({
      success: true,
      message: 'Currículo enviado com sucesso!'
    })).setMimeType(ContentService.MimeType.JSON);
    
  } catch (error) {
    // Log do erro
    console.error('Erro ao processar currículo:', error);
    
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

// Função para testar o script
function testScript() {
  const testData = {
    nome: 'João Silva',
    email: 'joao@teste.com',
    telefone: '(95) 99999-9999',
    cargo: 'Frentista'
  };
  
  console.log('Teste de sanitização:', sanitizeInput(testData.nome));
  console.log('Teste de validação de email:', /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(testData.email));
  console.log('Teste de validação de telefone:', /^\(?[1-9]{2}\)?[0-9]{4,5}-?[0-9]{4}$/.test(testData.telefone.replace(/\s/g, '')));
}

// Função para configurar webhook (opcional)
function setupWebhook() {
  const webAppUrl = ScriptApp.getService().getUrl();
  console.log('URL da Web App:', webAppUrl);
  console.log('Use esta URL no seu formulário HTML');
} 