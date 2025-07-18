# Auto Posto Estrela D'Alva - Site de Currículos

Site institucional para recebimento de currículos do Auto Posto Estrela D'Alva em Boa Vista/RR.

## 🚀 Funcionalidades

- **Formulário de Candidatura**: Permite que candidatos enviem seus dados pessoais e currículo
- **Validação Completa**: Validação de todos os campos obrigatórios
- **Segurança**: Proteção contra spam e validação de arquivos PDF
- **Responsivo**: Funciona perfeitamente em desktop e mobile
- **Design Moderno**: Interface clean e profissional

## 📁 Estrutura do Projeto

```
site posto puro/
├── index.html          # Página principal
├── style.css           # Estilos CSS
├── script.js           # JavaScript principal
├── logos/              # Logotipos das seções
│   ├── acai.png
│   ├── barbearia.jpeg
│   ├── conveniencia.png
│   └── logoposto.png
├── acai.png           # Imagem da seção açaí
├── conveniencia.png   # Imagem da seção conveniência
├── layout.png         # Layout de referência
├── Logotipo.png       # Logo principal
└── README.md          # Este arquivo
```

## 🛠️ Tecnologias Utilizadas

- **HTML5**: Estrutura semântica
- **CSS3**: Estilos modernos e responsivos
- **JavaScript**: Interatividade e validações
- **Font Awesome**: Ícones
- **Google Fonts**: Tipografia (Poppins)

## 📧 Como Funciona

1. **Candidato preenche o formulário** com seus dados pessoais
2. **Sistema valida** todos os campos obrigatórios
3. **Candidato anexa** currículo em PDF (máximo 1MB)
4. **Sistema abre cliente de email** com dados preenchidos
5. **Candidato anexa o PDF** e envia o email

## 🔧 Configuração

O site é **100% estático** e funciona sem necessidade de servidor. Basta:

1. **Abrir o arquivo `index.html`** em qualquer navegador
2. **Ou servir via HTTP** para funcionalidades completas

## 🌐 Deploy

### GitHub Pages
```bash
git add .
git commit -m "Site do Auto Posto Estrela D'Alva"
git push origin main
```

### Railway
```bash
# O site pode ser facilmente deployado no Railway
# Apenas conecte o repositório GitHub
```

### Vercel/Netlify
```bash
# Deploy direto via drag & drop ou GitHub
```

## 📱 Funcionalidades Responsivas

- ✅ **Desktop**: Layout completo com sidebar
- ✅ **Tablet**: Adaptação do layout
- ✅ **Mobile**: Menu hambúrguer e layout otimizado

## 🎨 Seções do Site

1. **Hero**: Apresentação principal
2. **Sobre**: Informações sobre o posto
3. **Serviços**: Conveniência, Açaí, Barbearia
4. **Localização**: Mapa e endereço
5. **Trabalhe Conosco**: Formulário de currículos

## 📞 Contato

- **Telefone**: (95) 99174-0090
- **Email**: leonardobrsvicente@gmail.com
- **Endereço**: R. Estrela D'álva, 1794 - Boa Vista/RR
- **Instagram**: @autopostoestreladalvarr

## 🔒 Segurança

- Validação de tipos de arquivo (apenas PDF)
- Limite de tamanho de arquivo (1MB)
- Sanitização de entrada de dados
- Rate limiting para prevenir spam
- Proteção contra bots

## 🚀 Próximos Passos

Este projeto está preparado para futuras evoluções:

- **Integração com banco de dados** (PlanetScale)
- **Painel administrativo** para gerenciar currículos
- **API REST** para processamento de dados
- **Deploy automatizado** via Railway/Vercel

---

**Desenvolvido para Auto Posto Estrela D'Alva - Boa Vista/RR** 