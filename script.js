// Variáveis globais
let mobileMenuOpen = false;

// Configuração do Supabase - as credenciais reais agora estão nas variáveis do Railway
const SUPABASE_URL = 'https://ezpjdywoywtpxtiynseg.supabase.co';
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImV6cGpkeXdveXd0cHh0aXluc2VnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI4NjE3MjgsImV4cCI6MjA2ODQzNzcyOH0.qiVX5Lti7kpOoJEEAlU795P9OmXFBDqOKKxUBW4cFc8';

// Inicializar Supabase
const supabase = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Configurações de segurança avançadas
const SECURITY_CONFIG = {
    maxFileSize: 1 * 1024 * 1024, // 1MB
    maxTextLength: {
        nome: 100,
        email: 100,
        telefone: 20
    },
    allowedFileTypes: ['application/pdf'],
    allowedMimeTypes: ['application/pdf'],
    rateLimit: {
        maxAttempts: 3,
        timeWindow: 60000, // 1 minuto
        blockDuration: 300000 // 5 minutos de bloqueio após exceder
    },
    honeypot: true, // Campo honeypot para detectar bots
    csrfProtection: true,
    maxRequestsPerMinute: 5,
    blacklistedKeywords: [
        'script', 'javascript', 'vbscript', 'onload', 'onerror', 'onclick',
        'eval', 'expression', 'document.cookie', 'window.location',
        'alert', 'confirm', 'prompt', 'setTimeout', 'setInterval'
    ]
};

// Controle de segurança avançado
let submitAttempts = 0;
let lastSubmitTime = 0;
let blockedUntil = 0;
let requestHistory = [];
let sessionToken = null;
let formLoadTime = null;

// Função para scroll suave para seções
function scrollToSection(sectionId) {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
    // Fechar menu mobile se estiver aberto
    if (mobileMenuOpen) {
        toggleMobileMenu();
    }
}

// Toggle do menu mobile
function toggleMobileMenu() {
    const mobileNav = document.getElementById('mobile-nav');
    const menuIcon = document.getElementById('menu-icon');
    
    mobileMenuOpen = !mobileMenuOpen;
    
    if (mobileMenuOpen) {
        mobileNav.classList.add('active');
        menuIcon.classList.remove('fa-bars');
        menuIcon.classList.add('fa-times');
    } else {
        mobileNav.classList.remove('active');
        menuIcon.classList.remove('fa-times');
        menuIcon.classList.add('fa-bars');
    }
}

// Função para abrir WhatsApp
function abrirWhatsApp() {
    const numeroWhatsApp = '5595991740090';
    const mensagem = 'Olá! Gostaria de saber mais sobre as vagas de emprego disponíveis no Auto Posto Estrela D\'Alva';
    const url = `https://wa.me/${numeroWhatsApp}?text=${encodeURIComponent(mensagem)}`;
    window.open(url, '_blank');
}

// Função para abrir Instagram
function abrirInstagram() {
    const url = 'https://instagram.com/autopostoestreladalvarr';
    window.open(url, '_blank');
}

// Função para abrir Google Maps
function abrirMaps() {
    const endereco = 'R. Estrela D\'álva, 1794 - Profa. Araceli Souto Maior, Boa Vista - RR, 69315-076';
    const url = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(endereco)}`;
    window.open(url, '_blank');
}

// Função avançada para validar e sanitizar entrada de texto
function sanitizeInput(input, fieldType = 'default') {
    if (typeof input !== 'string') return '';
    
    // Verificar palavras-chave maliciosas
    const lowerInput = input.toLowerCase();
    for (const keyword of SECURITY_CONFIG.blacklistedKeywords) {
        if (lowerInput.includes(keyword.toLowerCase())) {
            throw new Error(`Entrada inválida: contém conteúdo não permitido`);
        }
    }
    
    // Remover caracteres perigosos e scripts
    let sanitized = input
        .replace(/[<>]/g, '') // Remove < e >
        .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '') // Remove caracteres de controle
        .replace(/javascript:/gi, '') // Remove javascript:
        .replace(/data:/gi, '') // Remove data URLs
        .replace(/vbscript:/gi, '') // Remove vbscript
        .replace(/on\w+\s*=/gi, '') // Remove event handlers
        .replace(/expression\s*\(/gi, '') // Remove CSS expressions
        .replace(/url\s*\(/gi, '') // Remove URL functions
        .replace(/eval\s*\(/gi, '') // Remove eval
        .replace(/setTimeout\s*\(/gi, '') // Remove setTimeout
        .replace(/setInterval\s*\(/gi, '') // Remove setInterval
        .replace(/Function\s*\(/gi, '') // Remove Function constructor
        .replace(/window\./gi, '') // Remove window access
        .replace(/document\./gi, '') // Remove document access
        .replace(/location\./gi, '') // Remove location access
        .replace(/history\./gi, '') // Remove history access
        .replace(/navigator\./gi, '') // Remove navigator access
        .replace(/XMLHttpRequest/gi, '') // Remove XMLHttpRequest
        .replace(/fetch\s*\(/gi, '') // Remove fetch
        .replace(/import\s*\(/gi, '') // Remove dynamic import
        .replace(/require\s*\(/gi, '') // Remove require
        .replace(/localStorage\./gi, '') // Remove localStorage access
        .replace(/sessionStorage\./gi, '') // Remove sessionStorage access
        .replace(/cookie/gi, '') // Remove cookie access
        .trim();
    
    // Validação específica por tipo de campo
    const maxLength = SECURITY_CONFIG.maxTextLength[fieldType] || 1000;
    
    if (sanitized.length > maxLength) {
        sanitized = sanitized.substring(0, maxLength);
    }
    
    // Validações específicas por tipo
    switch (fieldType) {
        case 'nome':
            // Permitir apenas letras, espaços, acentos e hífen
            sanitized = sanitized.replace(/[^a-zA-ZÀ-ÿ\s\-']/g, '');
            break;
        case 'telefone':
            // Permitir apenas números, parênteses, hífen e espaços
            sanitized = sanitized.replace(/[^0-9()\-\s]/g, '');
            break;
        case 'email':
            // Permitir apenas caracteres válidos para email
            sanitized = sanitized.replace(/[^a-zA-Z0-9@._\-]/g, '');
            break;
    }
    
    return sanitized;
}

// Função para gerar token CSRF simples
function generateCSRFToken() {
    return Math.random().toString(36).substring(2) + Date.now().toString(36);
}

// Função para verificar limite de requisições
function checkRequestLimit() {
    const now = Date.now();
    const oneMinuteAgo = now - 60000;
    
    // Remover requisições antigas
    requestHistory = requestHistory.filter(timestamp => timestamp > oneMinuteAgo);
    
    // Verificar se excedeu o limite
    if (requestHistory.length >= SECURITY_CONFIG.maxRequestsPerMinute) {
        return false;
    }
    
    // Adicionar nova requisição
    requestHistory.push(now);
    return true;
}

// Função para verificar se está bloqueado
function isBlocked() {
    const now = Date.now();
    return now < blockedUntil;
}

// Função para bloquear temporariamente
function blockUser() {
    blockedUntil = Date.now() + SECURITY_CONFIG.rateLimit.blockDuration;
}

// Função para inicializar todas as proteções de segurança
function initSecurity() {
    // Gerar token de sessão
    sessionToken = generateCSRFToken();
    
    // Registrar tempo de carregamento do formulário
    formLoadTime = Date.now();
    
    // Adicionar event listeners de segurança
    addSecurityEventListeners();
    
    // Configurar validação em tempo real
    setupRealtimeValidation();
    
    // Registrar carregamento da página para tracking
    console.log('🔒 Sistema de segurança iniciado');
}

// Função para adicionar event listeners de segurança
function addSecurityEventListeners() {
    // Prevenir ataques de devtools
    document.addEventListener('keydown', function(e) {
        // Bloquear F12, Ctrl+Shift+I, Ctrl+U, etc.
        if (e.key === 'F12' || 
            (e.ctrlKey && e.shiftKey && e.key === 'I') ||
            (e.ctrlKey && e.shiftKey && e.key === 'J') ||
            (e.ctrlKey && e.key === 'U')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Prevenir clique direito em produção (opcional)
    // document.addEventListener('contextmenu', function(e) {
    //     e.preventDefault();
    //     return false;
    // });
    
    // Detectar tentativas de cópia suspeitas
    document.addEventListener('copy', function(e) {
        const selection = window.getSelection().toString();
        if (selection.length > 1000) {
            console.warn('🚨 Tentativa de cópia em massa detectada');
        }
    });
}

// Função para configurar validação em tempo real
function setupRealtimeValidation() {
    const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"]');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            try {
                const fieldType = this.name;
                const sanitized = sanitizeInput(this.value, fieldType);
                
                // Se o valor foi alterado pela sanitização, atualizar
                if (sanitized !== this.value) {
                    this.value = sanitized;
                }
                
                // Validar campo
                validateField(this, fieldType);
                
            } catch (error) {
                this.value = '';
                showNotification(error.message, 'error');
            }
        });
        
        // Validar quando sair do campo
        input.addEventListener('blur', function() {
            validateField(this, this.name);
        });
    });
    
    // Validação específica para arquivo
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                validatePDFUpload(this);
            }
        });
    }
}

// Função para validar email
function validateEmail(email) {
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    return emailRegex.test(email);
}

// Função para validar telefone brasileiro
function validatePhone(phone) {
    const phoneRegex = /^\(?[1-9]{2}\)?[0-9]{4,5}-?[0-9]{4}$/;
    return phoneRegex.test(phone.replace(/\s/g, ''));
}

// Função avançada para validar arquivo PDF
function validatePDFFile(file) {
    // Verificar se é um arquivo
    if (!file || !file.name) {
        return { valid: false, message: 'Por favor, selecione um arquivo.' };
    }
    
    // Verificar se o arquivo não está vazio
    if (file.size === 0) {
        return { valid: false, message: 'O arquivo está vazio ou corrompido.' };
    }
    
    // Verificar extensão de forma mais rigorosa
    const fileName = file.name.toLowerCase();
    const allowedExtensions = ['.pdf'];
    const hasValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));
    
    if (!hasValidExtension) {
        return { valid: false, message: 'Apenas arquivos PDF são permitidos.' };
    }
    
    // Verificar múltiplas extensões (ex: file.pdf.exe)
    const extensionCount = (fileName.match(/\./g) || []).length;
    if (extensionCount > 1) {
        return { valid: false, message: 'Nome de arquivo suspeito. Use apenas um .pdf' };
    }
    
    // Verificar tamanho mínimo e máximo
    const minSize = 1024; // 1KB mínimo
    if (file.size < minSize) {
        return { valid: false, message: 'Arquivo muito pequeno. O arquivo deve ter pelo menos 1KB.' };
    }
    
    if (file.size > SECURITY_CONFIG.maxFileSize) {
        return { valid: false, message: 'O arquivo deve ter no máximo 1MB.' };
    }
    
    // Verificar tipo MIME de forma mais rigorosa
    if (!file.type || !SECURITY_CONFIG.allowedMimeTypes.includes(file.type)) {
        return { valid: false, message: 'Tipo MIME inválido. Apenas PDF é permitido.' };
    }
    
    // Verificar se o nome do arquivo não contém caracteres suspeitos
    const suspiciousChars = /[<>:"/\\|?*\x00-\x1f\x80-\xff]/;
    if (suspiciousChars.test(file.name)) {
        return { valid: false, message: 'Nome do arquivo contém caracteres inválidos.' };
    }
    
    // Verificar nomes de arquivo suspeitos
    const suspiciousNames = [
        'con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5',
        'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4',
        'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9'
    ];
    
    const fileNameOnly = fileName.replace('.pdf', '').toLowerCase();
    if (suspiciousNames.includes(fileNameOnly)) {
        return { valid: false, message: 'Nome de arquivo reservado não permitido.' };
    }
    
    // Verificar comprimento do nome
    if (fileName.length > 255) {
        return { valid: false, message: 'Nome do arquivo muito longo. Máximo 255 caracteres.' };
    }
    
    // Verificar se o nome não é apenas espaços ou caracteres especiais
    if (fileNameOnly.trim().length === 0) {
        return { valid: false, message: 'Nome do arquivo inválido.' };
    }
    
    return { valid: true, message: 'Arquivo válido.' };
}

// Função para validar cargo
function validateCargo(cargo) {
    const cargosValidos = ['Frentista', 'Auxiliar de Limpeza', 'Auxiliar Administrativo'];
    return cargosValidos.includes(cargo);
}

// Função para enviar currículo para o Supabase com proteções avançadas
async function enviarCurriculo(event) {
    event.preventDefault();
    
    // Verificar se está bloqueado temporariamente
    if (isBlocked()) {
        const remainingTime = Math.ceil((blockedUntil - Date.now()) / 1000 / 60);
        showNotification(`Acesso temporariamente bloqueado. Tente novamente em ${remainingTime} minutos.`, 'error');
        return;
    }
    
    // Verificar limite de requisições por minuto
    if (!checkRequestLimit()) {
        showNotification('Muitas tentativas por minuto. Aguarde um momento.', 'error');
        return;
    }
    
    // Verificar se o usuário é um bot
    if (detectBot()) {
        showNotification('Acesso negado: comportamento suspeito detectado.', 'error');
        return;
    }
    
    // Rate limiting aprimorado
    const now = Date.now();
    if (now - lastSubmitTime < SECURITY_CONFIG.rateLimit.timeWindow) {
        submitAttempts++;
        if (submitAttempts > SECURITY_CONFIG.rateLimit.maxAttempts) {
            blockUser();
            showNotification('Muitas tentativas. Acesso bloqueado temporariamente.', 'error');
            return;
        }
    } else {
        submitAttempts = 1;
        lastSubmitTime = now;
    }
    
    // Verificar tempo mínimo desde o carregamento da página (honeypot temporal)
    if (!formLoadTime || (now - formLoadTime) < 3000) {
        showNotification('Erro: formulário enviado muito rapidamente.', 'error');
        return;
    }
    
    // Prevenir múltiplos envios
    const submitButton = event.target.querySelector('button[type="submit"]');
    
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Enviando...</span>';
    
    try {
        const form = event.target;
        const formData = new FormData(form);
        
        // Obter e sanitizar dados com tipo específico
        const nome = sanitizeInput(formData.get('nome'), 'nome');
        const email = sanitizeInput(formData.get('email'), 'email');
        const telefone = sanitizeInput(formData.get('telefone'), 'telefone');
        const cargo = formData.get('cargo');
        const curriculo = formData.get('curriculo');
        
        // Verificar honeypot (campo oculto para detectar bots)
        const honeypot = formData.get('website');
        if (honeypot && honeypot.trim().length > 0) {
            showNotification('Acesso negado: bot detectado.', 'error');
            return;
        }
        
        // Validações
        if (!nome || nome.length < 3) {
            throw new Error('Nome deve ter pelo menos 3 caracteres.');
        }
        
        if (!validateEmail(email)) {
            throw new Error('E-mail inválido.');
        }
        
        if (!validatePhone(telefone)) {
            throw new Error('Telefone inválido. Use o formato: (95) 99999-9999');
        }
        
        if (!validateCargo(cargo)) {
            throw new Error('Por favor, selecione um cargo válido.');
        }
        
        // Validar arquivo PDF
        const fileValidation = validatePDFFile(curriculo);
        if (!fileValidation.valid) {
            throw new Error(fileValidation.message);
        }
        
        // Upload do arquivo para o Supabase Storage
        let arquivoUrl = null;
        if (curriculo) {
            const fileName = `curriculos/${Date.now()}-${curriculo.name}`;
            
            const { data: uploadData, error: uploadError } = await supabase.storage
                .from('curriculos')
                .upload(fileName, curriculo);
            
            if (uploadError) {
                throw new Error('Erro ao fazer upload do arquivo: ' + uploadError.message);
            }
            
            // Obter URL pública do arquivo
            const { data: urlData } = supabase.storage
                .from('curriculos')
                .getPublicUrl(fileName);
            
            arquivoUrl = urlData.publicUrl;
        }
        
        // Salvar dados no banco
        const { data, error } = await supabase
            .from('curriculos')
            .insert([
                {
                    nome: nome,
                    email: email,
                    telefone: telefone,
                    cargo: cargo,
                    arquivo_url: arquivoUrl
                }
            ]);
        
        if (error) {
            throw new Error('Erro ao salvar currículo: ' + error.message);
        }
        
        // Limpar formulário
        form.reset();
        
        // Mostrar feedback de sucesso
        showNotification('Currículo enviado com sucesso! Entraremos em contato em breve.', 'success');
        
    } catch (error) {
        // Mostrar erro
        showNotification(error.message, 'error');
        
    } finally {
        // Reativar botão
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-file-upload"></i><span>Enviar Currículo</span>';
    }
}

// Função simples para obter data/hora formatada
function getFormattedDateTime() {
    const now = new Date();
    return {
        date: now.toLocaleDateString('pt-BR'),
        time: now.toLocaleTimeString('pt-BR')
    };
}

// Função para mostrar notificações
function showNotification(message, type = 'info') {
    // Remover notificação anterior se existir
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Criar notificação
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Adicionar ao body
    document.body.appendChild(notification);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Função para validar arquivo em tempo real
function validateFileInput(input) {
    const file = input.files[0];
    const validation = validatePDFFile(file);
    
    // Remover classes de erro anteriores
    input.classList.remove('error', 'success');
    
    if (!validation.valid) {
        input.classList.add('error');
        showNotification(validation.message, 'error');
        input.value = ''; // Limpar input
    } else {
        input.classList.add('success');
        showNotification('Arquivo válido!', 'success');
    }
}

// Função para validar campos em tempo real
function validateField(input, fieldType) {
    const value = input.value.trim();
    
    // Remover classes de erro/sucesso anteriores
    input.classList.remove('error', 'success');
    
    // Validações específicas por tipo de campo
    switch (fieldType) {
        case 'nome':
            if (!value || value.length < 3) {
                input.classList.add('error');
                showNotification('Nome deve ter pelo menos 3 caracteres.', 'error');
                return false;
            }
            break;
            
        case 'email':
            if (!validateEmail(value)) {
                input.classList.add('error');
                showNotification('E-mail inválido.', 'error');
                return false;
            }
            break;
            
        case 'telefone':
            if (!validatePhone(value)) {
                input.classList.add('error');
                showNotification('Telefone inválido. Use o formato: (95) 99999-9999', 'error');
                return false;
            }
            break;
            

    }
    
    // Se chegou até aqui, o campo é válido
    input.classList.add('success');
    return true;
}

// Função para formatar telefone automaticamente
function formatPhone(input) {
    let value = input.value.replace(/\D/g, ''); // Remove tudo que não é dígito
    
    if (value.length <= 2) {
        value = `(${value}`;
    } else if (value.length <= 7) {
        value = `(${value.substring(0, 2)}) ${value.substring(2)}`;
    } else if (value.length <= 11) {
        value = `(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7)}`;
    } else {
        value = `(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7, 11)}`;
    }
    
    input.value = value;
}

// Função avançada para detectar bots
function detectBot() {
    // Verificar se o JavaScript está habilitado
    if (typeof navigator === 'undefined') return true;
    
    // Verificar user agent suspeito
    const userAgent = navigator.userAgent.toLowerCase();
    const botPatterns = [
        'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 
        'python', 'java', 'perl', 'ruby', 'php', 'go', 'node',
        'headless', 'phantom', 'selenium', 'webdriver', 'automation'
    ];
    
    for (const pattern of botPatterns) {
        if (userAgent.includes(pattern)) return true;
    }
    
    // Verificar se tem plugins (bots geralmente não têm)
    if (navigator.plugins && navigator.plugins.length === 0) return true;
    
    // Verificar se tem cookies habilitados
    if (!navigator.cookieEnabled) return true;
    
    // Verificar propriedades suspeitas do navigator
    if (navigator.webdriver) return true;
    
    // Verificar se está em modo headless
    if (navigator.hardwareConcurrency === 0) return true;
    
    // Verificar se tem webGL (bots geralmente não têm)
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        if (!gl) return true;
    } catch (e) {
        return true;
    }
    
    // Verificar se tem touchscreen em mobile (comportamento humano)
    const isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent);
    if (isMobileDevice && !('ontouchstart' in window)) return true;
    
    // Verificar se tem localStorage (bots podem não ter)
    try {
        localStorage.setItem('test', 'test');
        localStorage.removeItem('test');
    } catch (e) {
        return true;
    }
    
    return false;
}

// Função para adicionar animações quando elementos entram na tela
function observeElements() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    // Observar elementos para animação
    const elementsToObserve = document.querySelectorAll(
        '.hero-card, .sobre-card, .servico-card, .diferencial, .instagram-post, .info-card, .contato-card'
    );
    
    elementsToObserve.forEach(el => observer.observe(el));
}

// Função para adicionar efeitos de hover nos cards
function addCardEffects() {
    const cards = document.querySelectorAll('.hero-card, .sobre-card, .servico-card, .instagram-post');
    
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05) translateY(-5px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) translateY(0)';
        });
    });
}

// Função para atualizar o ano no footer
function updateFooterYear() {
    const currentYear = new Date().getFullYear();
    const footerText = document.querySelector('.footer-bottom p');
    if (footerText) {
        footerText.innerHTML = `&copy; ${currentYear} Auto Posto Estrela D'Alva. Todos os direitos reservados.`;
    }
}

// Função para smooth scroll no navegador
function initSmoothScroll() {
    // Adicionar comportamento smooth scroll para navegadores que não suportam CSS scroll-behavior
    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Função para lazy loading de imagens
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Função para detectar scroll e adicionar classe ao header
function initScrollHeader() {
    const header = document.querySelector('.header');
    let lastScrollTop = 0;
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 100) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
        
        lastScrollTop = scrollTop;
    });
}

// Função para adicionar efeito parallax sutil no hero
function initParallaxEffect() {
    const heroSection = document.querySelector('.hero');
    const heroBackground = document.querySelector('.hero-bg-img');
    
    if (heroSection && heroBackground) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const parallax = scrolled * 0.5;
            
            heroBackground.style.transform = `translateY(${parallax}px)`;
        });
    }
}

// Função para contar números (animação de contadores)
function animateCounters() {
    const counters = document.querySelectorAll('.counter');
    
    counters.forEach(counter => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-target');
            const count = +counter.innerText;
            const speed = 200;
            const inc = target / speed;
            
            if (count < target) {
                counter.innerText = Math.ceil(count + inc);
                setTimeout(updateCount, 1);
            } else {
                counter.innerText = target;
            }
        };
        
        updateCount();
    });
}

// Função para validar formulário
function validateForm() {
    const form = document.querySelector('form');
    if (!form) return;
    
    const inputs = form.querySelectorAll('input[required], textarea[required]');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (!this.value.trim()) {
                this.classList.add('error');
            } else {
                this.classList.remove('error');
            }
        });
        
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('error');
            }
        });
    });
}

// Função para adicionar efeito de loading aos botões
function addButtonLoadingEffect() {
    const buttons = document.querySelectorAll('button[type="submit"]');
    
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            this.disabled = true;
            
            // Restaurar após 3 segundos (tempo suficiente para redirecionamento)
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 3000);
        });
    });
}

// Função para detectar dispositivo móvel
function isMobile() {
    return window.innerWidth <= 768;
}

// Função para otimizar performance em dispositivos móveis
function optimizeForMobile() {
    if (isMobile()) {
        // Remover efeitos de hover em dispositivos móveis
        const hoverElements = document.querySelectorAll('.hero-card, .sobre-card, .servico-card');
        hoverElements.forEach(el => {
            el.style.transition = 'none';
        });
        
        // Reduzir animações em dispositivos móveis
        document.body.classList.add('mobile-device');
    }
}

// Função para preload de imagens importantes
function preloadImages() {
    const criticalImages = [
        '/Logotipo.png',
        '/layout.png',
        '/logos/logoposto.png'
    ];
    
    criticalImages.forEach(src => {
        const img = new Image();
        img.src = src;
    });
}

// Função para adicionar meta tags dinâmicas
function addMetaTags() {
    // Adicionar meta tag de theme-color
    const themeColorMeta = document.createElement('meta');
    themeColorMeta.name = 'theme-color';
    themeColorMeta.content = '#dc2626';
    document.head.appendChild(themeColorMeta);
    
    // Adicionar meta tag de apple-mobile-web-app-capable
    const appleMeta = document.createElement('meta');
    appleMeta.name = 'apple-mobile-web-app-capable';
    appleMeta.content = 'yes';
    document.head.appendChild(appleMeta);
}

// Função para gerenciar cookies (LGPD)
function manageCookies() {
    const cookieConsent = localStorage.getItem('cookieConsent');
    
    if (!cookieConsent) {
        // Aqui você pode adicionar um banner de cookies se necessário
        // Por enquanto, apenas aceitar automaticamente
        localStorage.setItem('cookieConsent', 'accepted');
    }
}

// Função para rastrear eventos (analytics)
function trackEvent(eventName, eventData = {}) {
    // Aqui você pode integrar com Google Analytics ou outra ferramenta
    
    // Exemplo para Google Analytics (gtag)
    if (typeof gtag !== 'undefined') {
        gtag('event', eventName, eventData);
    }
}

// Função para adicionar eventos de rastreamento
function addTrackingEvents() {
    // Rastrear cliques no WhatsApp
    document.querySelectorAll('[onclick*="abrirWhatsApp"]').forEach(el => {
        el.addEventListener('click', () => {
            trackEvent('whatsapp_click', {
                element: el.className || el.tagName
            });
        });
    });
    
    // Rastrear cliques no Instagram
    document.querySelectorAll('[onclick*="abrirInstagram"]').forEach(el => {
        el.addEventListener('click', () => {
            trackEvent('instagram_click', {
                element: el.className || el.tagName
            });
        });
    });
    
    // Rastrear envio de formulário
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', () => {
            trackEvent('form_submit', {
                form_type: 'contact'
            });
        });
    }
}

// Função para lidar com erros de imagem
function handleImageErrors() {
    const images = document.querySelectorAll('img');
    
    images.forEach(img => {
        img.addEventListener('error', function() {
            // Usar uma imagem placeholder ou esconder a imagem
            this.style.display = 'none';
        });
    });
}

// Função de inicialização quando DOM carrega
function initializeApp() {
    // Inicializar proteções de segurança PRIMEIRO
    initSecurity();
    
    // Inicializar todas as funcionalidades
    observeElements();
    addCardEffects();
    updateFooterYear();
    initSmoothScroll();
    initLazyLoading();
    initScrollHeader();
    validateForm();
    addButtonLoadingEffect();
    optimizeForMobile();
    preloadImages();
    addMetaTags();
    manageCookies();
    addTrackingEvents();
    handleImageErrors();
    
    // Adicionar efeito parallax apenas em desktop
    if (!isMobile()) {
        initParallaxEffect();
    }
    
    // Adicionar listener direto no formulário para garantir funcionamento
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            enviarCurriculo(e);
        });
        
        // Adicionar listener também no botão para garantir
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.addEventListener('click', function(e) {
                // Verificar se o formulário é válido
                const formValid = form.checkValidity();
                
                if (formValid) {
                    // Chamar a função diretamente
                    const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                    form.dispatchEvent(submitEvent);
                }
                
                // Não prevenir o comportamento padrão aqui, deixar o submit acontecer
            });
        }
    }
}

// Função para lidar com redimensionamento da janela
function handleResize() {
    // Fechar menu mobile se a tela for redimensionada para desktop
    if (window.innerWidth >= 768 && mobileMenuOpen) {
        toggleMobileMenu();
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', initializeApp);
window.addEventListener('resize', handleResize);

// Função para lidar com visibilidade da página
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        // Página ficou visível novamente
        trackEvent('page_visibility', { status: 'visible' });
    } else {
        // Página ficou oculta
        trackEvent('page_visibility', { status: 'hidden' });
    }
});

// Função para lidar com beforeunload (antes de sair da página)
window.addEventListener('beforeunload', function() {
    trackEvent('page_exit');
});

// Animações da Seção Sobre Moderna
function initModernSectionAnimations() {
    const modernCards = document.querySelectorAll('.modern-card');
    const floatingLogo = document.querySelector('.floating-logo');
    const floatingElements = document.querySelectorAll('.floating-element');
    
    // Intersection Observer para animações de entrada
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const cardObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0) scale(1)';
                }, index * 200);
            }
        });
    }, observerOptions);
    
    // Aplicar animações iniciais aos cards
    modernCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(50px) scale(0.9)';
        card.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        cardObserver.observe(card);
    });
    
    // Efeito parallax nos elementos flutuantes
    let ticking = false;
    
    function updateParallax() {
        const scrolled = window.pageYOffset;
        const parallaxSpeed = 0.5;
        
        floatingElements.forEach((element, index) => {
            const speed = parallaxSpeed * (index + 1) * 0.3;
            element.style.transform = `translateY(${scrolled * speed}px) rotate(${scrolled * 0.1}deg)`;
        });
        
        ticking = false;
    }
    
    function requestTick() {
        if (!ticking) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    }
    
    window.addEventListener('scroll', requestTick);
    
    // Animação de hover com efeito 3D
    modernCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'perspective(1000px) rotateX(10deg) rotateY(-5deg) translateY(-15px) scale(1.02)';
            this.style.boxShadow = '0 30px 60px rgba(0, 0, 0, 0.4)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px) scale(1)';
            this.style.boxShadow = '0 10px 30px rgba(0, 0, 0, 0.2)';
        });
        
        // Efeito de movimento do mouse
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;
            
            this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-15px) scale(1.02)`;
        });
    });
    
    // Animação da linha conectiva SVG - DESABILITADA
    const connectionPath = document.querySelector('.connection-path');
    if (connectionPath) {
        // Ocultar linhas SVG que estão causando problemas visuais
        connectionPath.style.display = 'none';
    }
    
        // Partículas flutuantes dinâmicas - DESABILITADAS
    function createDynamicParticles() {
        // Função desabilitada para evitar linhas e elementos visuais indesejados
        return;
    }
    
    // createDynamicParticles(); // Comentado para não criar partículas
    
    // Efeito de glow no logo baseado na posição do scroll
    function updateLogoGlow() {
        if (!floatingLogo) return;
        
        const scrolled = window.pageYOffset;
        const glowIntensity = Math.min(scrolled / 500, 1);
        const hue = 0 + (glowIntensity * 30); // De vermelho para laranja
        
        floatingLogo.style.boxShadow = `
            0 0 ${30 + glowIntensity * 20}px hsla(${hue}, 80%, 50%, ${0.3 + glowIntensity * 0.3}),
            0 0 ${60 + glowIntensity * 40}px hsla(${hue}, 80%, 50%, ${0.1 + glowIntensity * 0.2})
        `;
    }
    
    window.addEventListener('scroll', updateLogoGlow);
    
    // Adição das animações CSS via JavaScript
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pathDraw {
            to {
                stroke-dashoffset: 0;
            }
        }
        
        @keyframes floatParticle {
            0%, 100% {
                transform: translateY(0px) translateX(0px) scale(1);
                opacity: 0.3;
            }
            25% {
                transform: translateY(-20px) translateX(10px) scale(1.2);
                opacity: 0.8;
            }
            50% {
                transform: translateY(-10px) translateX(-10px) scale(0.8);
                opacity: 0.6;
            }
            75% {
                transform: translateY(-30px) translateX(5px) scale(1.1);
                opacity: 0.9;
            }
        }
    `;
    document.head.appendChild(style);
}

// Inicializar animações quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    // Delay pequeno para garantir que todos os elementos foram renderizados
    setTimeout(initModernSectionAnimations, 100);
});

// Carrossel de Promoções
let currentSlide = 0;
let autoplayInterval;

function initCarousel() {
    const slides = document.querySelectorAll('.promo-card');
    const indicators = document.querySelectorAll('.indicator');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const totalSlides = slides.length;

    if (totalSlides === 0) return; // Sair se não houver slides

    // Configurações baseadas no tamanho da tela - ajustado para mostrar os slides corretamente
    function getCarouselConfig() {
        const screenWidth = window.innerWidth;
        if (screenWidth <= 480) {
            return { cardWidth: 450, gap: 16, visibleCards: 1, centerMode: true };
        } else if (screenWidth <= 768) {
            return { cardWidth: 600, gap: 16, visibleCards: 1, centerMode: true };
        } else {
            return { cardWidth: 800, gap: 16, visibleCards: 1, centerMode: true };
        }
    }

    function updateCarousel() {
        const track = document.getElementById('carouselTrack');
        if (!track) return;
        
        const config = getCarouselConfig();
        const containerWidth = track.parentElement.offsetWidth;
        const cardWidth = config.cardWidth;
        const gap = config.gap;
        
        // Calcular posição para centralizar o slide atual
        const totalWidth = (cardWidth + gap) * totalSlides - gap;
        const startOffset = (containerWidth - cardWidth) / 2;
        const slideOffset = currentSlide * (cardWidth + gap);
        const translateX = startOffset - slideOffset;
        
        track.style.transform = `translateX(${translateX}px)`;
        
        // Atualizar indicadores
        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === currentSlide);
        });
        
        // Atualizar cards ativos (efeito blur)
        updateActiveCards();
    }
    
    function updateActiveCards() {
        slides.forEach((card, index) => {
            // Apenas o slide atual fica ativo (sem blur)
            const isActive = (index === currentSlide);
            card.classList.toggle('active', isActive);
        });
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % totalSlides;
        updateCarousel();
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
        updateCarousel();
    }

    // Event listeners para os botões
    if (nextBtn) nextBtn.addEventListener('click', nextSlide);
    if (prevBtn) prevBtn.addEventListener('click', prevSlide);

    // Event listeners para os indicadores
    indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
            currentSlide = index;
            updateCarousel();
        });
    });

    // Auto-play do carrossel
    function startAutoplay() {
        autoplayInterval = setInterval(nextSlide, 4000); // Reduzido para 4 segundos
    }

    function stopAutoplay() {
        if (autoplayInterval) {
            clearInterval(autoplayInterval);
        }
    }

    startAutoplay();

    // Pausar auto-play ao passar o mouse
    const carouselContainer = document.querySelector('.carousel-container');
    if (carouselContainer) {
        carouselContainer.addEventListener('mouseenter', stopAutoplay);
        carouselContainer.addEventListener('mouseleave', startAutoplay);
    }

    // Suporte para gestos touch (mobile)
    let startX = 0;
    let endX = 0;

    if (carouselContainer) {
        carouselContainer.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            stopAutoplay();
        });
        
        carouselContainer.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].clientX;
            const diffX = startX - endX;
            
            if (Math.abs(diffX) > 50) { // Mínimo de 50px para considerar um swipe
                if (diffX > 0) {
                    nextSlide();
                } else {
                    prevSlide();
                }
            }
            
            startAutoplay();
        });
    }

    // Controle por teclado
    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
            prevSlide();
        } else if (e.key === 'ArrowRight') {
            nextSlide();
        }
    });

    // Atualizar carrossel quando redimensionar a janela
    window.addEventListener('resize', () => {
        updateCarousel();
    });

    // Inicializar carrossel
    updateCarousel();
}

// Adicionar inicialização do carrossel à função de inicialização
function initializeAppWithCarousel() {
    // Inicializar proteções de segurança PRIMEIRO
    initSecurity();
    
    // Inicializar carrossel
    initCarousel();
    
    // Inicializar seção de combustíveis
    initCombustiveis();
    
    // Inicializar todas as outras funcionalidades
    observeElements();
    addCardEffects();
    updateFooterYear();
    initSmoothScroll();
    initLazyLoading();
    initScrollHeader();
    validateForm();
    addButtonLoadingEffect();
    optimizeForMobile();
    preloadImages();
    addMetaTags();
    manageCookies();
    addTrackingEvents();
    handleImageErrors();
    
    // Adicionar efeito parallax apenas em desktop
    if (!isMobile()) {
        initParallaxEffect();
    }
    
    // Adicionar listener direto no formulário para garantir funcionamento
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            enviarCurriculo(e);
        });
        
        // Adicionar listener também no botão para garantir
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.addEventListener('click', function(e) {
                // Verificar se o formulário é válido
                const formValid = form.checkValidity();
                
                if (formValid) {
                    // Chamar a função diretamente
                    const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                    form.dispatchEvent(submitEvent);
                }
                
                // Não prevenir o comportamento padrão aqui, deixar o submit acontecer
            });
        }
    }
}

// Substituir a inicialização original
document.removeEventListener('DOMContentLoaded', initializeApp);
document.addEventListener('DOMContentLoaded', initializeAppWithCarousel);

// Exportar funções para uso global (para compatibilidade com onclick)
window.scrollToSection = scrollToSection;
window.toggleMobileMenu = toggleMobileMenu;
window.abrirWhatsApp = abrirWhatsApp;
window.abrirInstagram = abrirInstagram;
window.abrirMaps = abrirMaps;
window.enviarCurriculo = enviarCurriculo;
window.validateFileInput = validateFileInput;
window.validateField = validateField;
window.formatPhone = formatPhone; 

// Função para inicializar a seção de combustíveis
function initCombustiveis() {
    const combustivelItems = document.querySelectorAll('.combustivel-item');
    const combustivelCard = document.getElementById('combustivel-info');
    
    if (!combustivelCard || combustivelItems.length === 0) return;
    
    // Dados dos combustíveis
    const combustiveisData = {
        comum: {
            logo: 'Gasolina Comum',
            tipos: 'GASOLINA COMUM PURA',
            descricao: '<strong>100% PURA, 0% MISTURA:</strong> Nossa gasolina comum é testada diariamente! Tanques limpos, filtros trocados semanalmente. Certificado de pureza semanal!'
        },
        aditivada: {
            logo: 'Gasolina Aditivada',
            tipos: 'GASOLINA PREMIUM COM ADITIVOS',
            descricao: '<strong>POTÊNCIA + PROTEÇÃO MÁXIMA:</strong> Gasolina aditivada que LIMPA seu motor enquanto você dirige! Exclusiva fórmula que aumenta a potência em até 15% e protege contra ferrugem. SÓ AQUI!'
        },
        s10: {
            logo: 'Diesel S-10',
            tipos: 'DIESEL S-10 ULTRA PURO',
            descricao: '<strong>DIESEL CAMPEÃO DE PUREZA:</strong> S-10 com menos de 10ppm de enxofre! Motor mais silencioso, menos fumaça, economia comprovada de até 20%. O diesel mais limpo de Roraima!'
        },
        s500: {
            logo: 'Diesel S500',
            tipos: 'DIESEL S500 PREMIUM',
            descricao: '<strong>DIESEL CONFIÁVEL E ECONÔMICO:</strong> S500 com qualidade garantida! Ideal para veículos pesados e utilitários. Filtragem rigorosa, combustível limpo e econômico para sua frota!'
        }
    };
    
    function updateCombustivelCard(tipo) {
        const data = combustiveisData[tipo];
        if (!data) return;
        
        // Adicionar efeito de fade
        combustivelCard.style.opacity = '0';
        combustivelCard.style.transform = 'translateY(10px)';
        
        setTimeout(() => {
            // Atualizar conteúdo
            const logoElement = combustivelCard.querySelector('.logo-text');
            const tiposElement = combustivelCard.querySelector('.combustivel-tipos span');
            const descricaoElement = combustivelCard.querySelector('.combustivel-descricao h3');
            
            if (logoElement) logoElement.textContent = data.logo;
            if (tiposElement) tiposElement.textContent = data.tipos;
            if (descricaoElement) descricaoElement.innerHTML = data.descricao;
            
            // Restaurar visibilidade com animação
            combustivelCard.style.opacity = '1';
            combustivelCard.style.transform = 'translateY(0)';
        }, 150);
    }
    
    // Adicionar event listeners
    combustivelItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remover active de todos os itens
            combustivelItems.forEach(i => i.classList.remove('active'));
            
            // Adicionar active ao item clicado
            this.classList.add('active');
            
            // Obter tipo do combustível
            const tipo = this.getAttribute('data-combustivel');
            
            // Atualizar card
            updateCombustivelCard(tipo);
        });
    });
    
    // Adicionar transições CSS
    if (combustivelCard) {
        combustivelCard.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    }
} 