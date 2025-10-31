# 🏢 Auto Posto Estrela D'Alva - Configuração Hostinger

## ✅ CONFIGURAÇÃO CONCLUÍDA

O site foi completamente configurado para funcionar com MySQL no Hostinger, removendo todas as dependências do Supabase.

## 📁 ARQUIVOS CRIADOS

### Arquivos PHP (Backend)
- `config.php` - Configurações do banco de dados e funções utilitárias
- `enviar_curriculo.php` - Processamento do formulário de currículos
- `admin.php` - Painel administrativo para visualizar currículos

### Configurações de Segurança
- Sistema de rate limiting implementado
- Validações de segurança contra bots e ataques
- PDFs armazenados diretamente no banco MySQL

## 🗄️ BANCO DE DADOS

### Conexão MySQL
- **Host:** localhost
- **Base:** u995570504_posto
- **User:** u995570504_root
- **Senha:** Str@da302

### Tabelas Criadas
- `curriculos` - Armazena os currículos enviados
- `logs` - Registra ações e eventos do sistema

## 🚀 COMO USAR

### 1. Upload dos Arquivos
Faça upload de todos os arquivos para o diretório público do seu site no Hostinger.

### 2. Modificar o Banco de Dados
Execute este SQL no phpMyAdmin:
```sql
-- Adicionar colunas para armazenar PDF no banco
ALTER TABLE `curriculos` 
ADD COLUMN `arquivo_conteudo` LONGBLOB NULL AFTER `arquivo_nome`,
ADD COLUMN `arquivo_mime_type` VARCHAR(100) NULL AFTER `arquivo_conteudo`;

-- Remover a coluna arquivo_url que não será mais usada
ALTER TABLE `curriculos` 
DROP COLUMN `arquivo_url`;
```

### 3. Testar o Formulário
1. Acesse seu site
2. Vá na seção "Trabalhe Conosco"
3. Preencha o formulário
4. Envie um currículo de teste

### 4. Verificar os Dados
Acesse: `seusite.com/admin.php?key=posto2025`

## 🔒 SEGURANÇA IMPLEMENTADA

### Proteções Contra Ataques
- ✅ Rate limiting (máximo 3 envios por IP por hora)
- ✅ Validação de arquivos (apenas PDF, máximo 1MB)
- ✅ Honeypot para detectar bots
- ✅ Sanitização de dados de entrada
- ✅ Proteção contra XSS e SQL Injection
- ✅ Validação de MIME types
- ✅ Headers de segurança

### Validações do Formulário
- ✅ Nome: 3-100 caracteres
- ✅ Email: formato válido
- ✅ Telefone: formato brasileiro
- ✅ Cargo: apenas opções válidas
- ✅ Arquivo: apenas PDF até 1MB

## 📊 FUNCIONALIDADES

### Para Visitantes
- ✅ Envio de currículo via formulário
- ✅ Upload de arquivo PDF
- ✅ Validações em tempo real
- ✅ Feedback visual de sucesso/erro

### Para Administradores
- ✅ Painel para visualizar currículos
- ✅ Download de arquivos enviados
- ✅ Estatísticas básicas
- ✅ Logs de sistema

## 🛠️ MANUTENÇÃO

### Logs do Sistema
Os logs são armazenados na tabela `logs` e incluem:
- Envios de currículos
- Tentativas de acesso suspeito
- Erros do sistema

### Limpeza de Dados
Recomenda-se implementar limpeza automática de:
- Logs antigos (após 90 dias)
- Arquivos não referenciados
- Tentativas de spam

## 🔧 PERSONALIZAÇÃO

### Alterar Senha do Admin
Edite o arquivo `admin.php` linha 12:
```php
$senha_admin = 'sua_nova_senha_aqui';
```

### Alterar Configurações de Segurança
Edite o arquivo `config.php` para:
- Aumentar/diminuir limite de arquivo
- Modificar rate limiting
- Adicionar novos tipos de arquivo

### Adicionar Novos Cargos
1. Edite `enviar_curriculo.php` linha 44
2. Edite `script.js` linha 387
3. Atualize o banco: `ALTER TABLE curriculos MODIFY cargo ENUM('Frentista','Auxiliar de Limpeza','Auxiliar Administrativo','Novo Cargo')`

## 📧 NOTIFICAÇÕES

Para receber notificações por email quando um currículo for enviado, adicione no final de `enviar_curriculo.php`:

```php
// Enviar email de notificação
$to = 'seu@email.com';
$subject = 'Novo Currículo Recebido - ' . $nome;
$message = "Nome: $nome\nEmail: $email\nCargo: $cargo";
$headers = 'From: noreply@autopostoestreladalva.com.br';
mail($to, $subject, $message, $headers);
```

## 🆘 SUPORTE

### URLs Importantes
- **Site:** autopostoestreladalva.com.br
- **Admin:** autopostoestreladalva.com.br/admin.php?key=posto2025
- **Uploads:** autopostoestreladalva.com.br/uploads/curriculos/

### Problemas Comuns

**Erro "Conexão com banco falhou"**
- Verifique credenciais em `config.php`
- Confirme que o banco está ativo no Hostinger

**Arquivos não são enviados**
- Verifique permissões da pasta `uploads/`
- Confirme que o diretório existe

**Formulário não funciona**
- Verifique se `enviar_curriculo.php` está acessível
- Confirme que PHP está ativo no servidor

## 🎯 STATUS ATUAL

✅ **PRONTO PARA PRODUÇÃO**

- [x] Banco de dados configurado
- [x] Formulário funcionando
- [x] Upload de arquivos seguro
- [x] Painel administrativo
- [x] Sistema de logs
- [x] Proteções de segurança
- [x] Validações completas

---

**🔗 Auto Posto Estrela D'Alva** - Sistema de Currículos v1.0
*Configurado para Hostinger MySQL*
