# 🚀 Configuração do Google Apps Script para Receber Currículos

## 📋 Pré-requisitos
- Conta Google (Gmail)
- Acesso ao Google Apps Script

## 🔧 Passo a Passo da Configuração

### **Passo 1: Acessar o Google Apps Script**
1. Vá para [script.google.com](https://script.google.com)
2. Faça login com sua conta Google
3. Clique em **"Novo projeto"**

### **Passo 2: Configurar o Script**
1. **Renomeie o projeto** para "Auto Posto - Receber Currículos"
2. **Substitua todo o código** pelo conteúdo do arquivo `google-apps-script.js`
3. **Altere o email de destino** na linha 89:
   ```javascript
   to: 'rh@autopostoestreladalva.com.br', // Mude para o email desejado
   ```

### **Passo 3: Fazer Deploy da Web App**
1. Clique em **"Deploy"** → **"New deployment"**
2. Clique em **"Select type"** → **"Web app"**
3. Configure:
   - **Description**: "Versão 1.0 - Receber Currículos"
   - **Execute as**: "Me" (sua conta)
   - **Who has access**: "Anyone" (para permitir envios do site)
4. Clique em **"Deploy"**
5. **Autorize** o script quando solicitado

### **Passo 4: Obter a URL da Web App**
1. Após o deploy, copie a **URL da Web App**
2. Substitua no arquivo `script.js` na linha:
   ```javascript
   const scriptUrl = 'SUA_URL_DO_GOOGLE_APPS_SCRIPT_AQUI';
   ```

### **Passo 5: Testar o Sistema**
1. Execute a função `testScript()` no Google Apps Script
2. Verifique os logs no console
3. Faça um teste real no site

## 📧 Configuração do Email

### **Email de Destino**
- **Padrão**: `rh@autopostoestreladalva.com.br`
- **Altere** para o email que receberá os currículos

### **Formato do Email Recebido**
- **Assunto**: "Candidatura - [Cargo] - [Nome]"
- **Corpo**: Email HTML formatado com todos os dados
- **Anexo**: PDF do currículo (se enviado)

## 🔒 Segurança Implementada

### **Validações**
- ✅ Dados obrigatórios
- ✅ Formato de email válido
- ✅ Formato de telefone brasileiro
- ✅ Sanitização contra injeção de código
- ✅ Limite de tamanho de arquivo (1MB)

### **Rate Limiting**
- ✅ Máximo 3 tentativas por minuto
- ✅ Detecção de bots
- ✅ Logs de todas as tentativas

## 📊 Monitoramento

### **Logs Disponíveis**
- Acesse **"Executions"** no Google Apps Script
- Veja todas as execuções e erros
- Monitore o volume de currículos recebidos

### **Limites do Google Apps Script**
- ✅ **1.500 emails por dia** (gratuito)
- ✅ **6 horas de execução por dia**
- ✅ **50MB de armazenamento**

## 🛠️ Personalizações

### **Alterar Email de Destino**
```javascript
// Linha 89 do script
to: 'seu-email@dominio.com',
```

### **Alterar Assunto do Email**
```javascript
// Linha 47 do script
const assunto = `Candidatura - ${cargo} - ${nome}`;
```

### **Adicionar Campos Extras**
1. Adicione o campo no HTML
2. Inclua no FormData do JavaScript
3. Processe no Google Apps Script

## 🚨 Solução de Problemas

### **Erro: "Quota Exceeded"**
- Limite diário atingido
- Aguarde até o próximo dia
- Considere upgrade para conta paga

### **Erro: "Authorization Required"**
- Reautorize o script
- Verifique as permissões do Gmail

### **Email não chega**
- Verifique a pasta de spam
- Confirme o email de destino
- Teste com email diferente

### **Arquivo não anexa**
- Verifique se é PDF
- Confirme tamanho < 1MB
- Teste sem anexo primeiro

## 📞 Suporte

### **Logs de Erro**
- Acesse **"Executions"** no Google Apps Script
- Clique na execução com erro
- Veja os detalhes do erro

### **Teste Manual**
```javascript
// Execute no Google Apps Script
function testManual() {
  const testData = {
    nome: 'Teste',
    email: 'teste@teste.com',
    telefone: '(95) 99999-9999',
    cargo: 'Frentista'
  };
  
  // Simular envio
  console.log('Dados de teste:', testData);
}
```

## ✅ Checklist de Configuração

- [ ] Script criado no Google Apps Script
- [ ] Email de destino configurado
- [ ] Deploy da Web App realizado
- [ ] URL copiada para o JavaScript
- [ ] Teste realizado com sucesso
- [ ] Email de teste recebido
- [ ] Anexo PDF funcionando
- [ ] Validações testadas

---

**🎯 Resultado Final:**
- Currículos chegam diretamente no email
- Formato profissional e organizado
- Anexos PDF incluídos
- Sistema totalmente gratuito
- Limite de 1.500 emails por dia 