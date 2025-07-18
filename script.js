// Variáveis globais
let mobileMenuOpen = false;

// Configurações de segurança
const SECURITY_CONFIG = {
    maxFileSize: 1 * 1024 * 1024, // 1MB
    maxTextLength: 1000,
    allowedFileTypes: ['application/pdf'],
    rateLimit: {
        maxAttempts: 3,
        timeWindow: 60000 // 1 minuto
    }
};

// Controle de rate limiting
let submitAttempts = 0;
let lastSubmitTime = 0;

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

// Função para validar e sanitizar entrada de texto
function sanitizeInput(input) {
    if (typeof input !== 'string') return '';
    
    // Remover caracteres perigosos e scripts
    let sanitized = input
        .replace(/[<>]/g, '') // Remove < e >
        .replace(/javascript:/gi, '') // Remove javascript:
        .replace(/on\w+=/gi, '') // Remove event handlers
        .replace(/data:/gi, '') // Remove data URLs
        .replace(/vbscript:/gi, '') // Remove vbscript
        .replace(/expression\(/gi, '') // Remove CSS expressions
        .replace(/url\(/gi, '') // Remove URL functions
        .replace(/eval\(/gi, '') // Remove eval
        .replace(/document\./gi, '') // Remove document access
        .replace(/window\./gi, '') // Remove window access
        .replace(/localStorage\./gi, '') // Remove localStorage access
        .replace(/sessionStorage\./gi, '') // Remove sessionStorage access
        .replace(/cookie/gi, '') // Remove cookie access
        .trim();
    
    // Limitar tamanho
    if (sanitized.length > SECURITY_CONFIG.maxTextLength) {
        sanitized = sanitized.substring(0, SECURITY_CONFIG.maxTextLength);
    }
    
    return sanitized;
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

// Função para validar arquivo PDF
function validatePDFFile(file) {
    // Verificar se é um arquivo
    if (!file || !file.name) {
        return { valid: false, message: 'Por favor, selecione um arquivo.' };
    }
    
    // Verificar extensão
    const fileName = file.name.toLowerCase();
    if (!fileName.endsWith('.pdf')) {
        return { valid: false, message: 'Apenas arquivos PDF são permitidos.' };
    }
    
    // Verificar tamanho
    if (file.size > SECURITY_CONFIG.maxFileSize) {
        return { valid: false, message: 'O arquivo deve ter no máximo 1MB.' };
    }
    
    // Verificar tipo MIME
    if (file.type && !SECURITY_CONFIG.allowedFileTypes.includes(file.type)) {
        return { valid: false, message: 'Tipo de arquivo inválido. Apenas PDF é permitido.' };
    }
    
    // Verificar se o nome do arquivo não contém caracteres suspeitos
    const suspiciousChars = /[<>:"/\\|?*\x00-\x1f]/;
    if (suspiciousChars.test(file.name)) {
        return { valid: false, message: 'Nome do arquivo contém caracteres inválidos.' };
    }
    
    return { valid: true, message: 'Arquivo válido.' };
}

// Função para validar cargo
function validateCargo(cargo) {
    const cargosValidos = ['Frentista', 'Caixa', 'Atendente', 'Barbeiro', 'Auxiliar de Cozinha', 'Outro'];
    return cargosValidos.includes(cargo);
}

// Função para enviar currículo via Google Apps Script
async function enviarCurriculo(event) {
    event.preventDefault();
    
    // Rate limiting
    const now = Date.now();
    if (now - lastSubmitTime < SECURITY_CONFIG.rateLimit.timeWindow) {
        submitAttempts++;
        if (submitAttempts > SECURITY_CONFIG.rateLimit.maxAttempts) {
            showNotification('Muitas tentativas. Aguarde um momento antes de tentar novamente.', 'error');
            return;
        }
    } else {
        submitAttempts = 1;
        lastSubmitTime = now;
    }
    
    // Prevenir múltiplos envios
    const submitButton = event.target.querySelector('button[type="submit"]');
    if (submitButton.disabled) return;
    
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Enviando...</span>';
    
    try {
        // Verificar se é um bot
        if (detectBot()) {
            throw new Error('Acesso negado.');
        }
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Obter e sanitizar dados
        const nome = sanitizeInput(formData.get('nome'));
        const email = sanitizeInput(formData.get('email'));
        const telefone = sanitizeInput(formData.get('telefone'));
        const cargo = formData.get('cargo');
        const curriculo = formData.get('curriculo');
        
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
        
        // Adicionar IP do usuário (opcional)
        formData.append('ip', await getClientIP());
        
        // URL do Google Apps Script (será substituída pela URL real)
        const scriptUrl = 'https://script.google.com/macros/s/AKfycbxvA9Ygw-H5WSHRb4ShGGdNpOoaOCjYlMscMguHlW07DdcF6hMWfUdSoIBXcSjl2Z8/exec';
        
        // Tentar enviar para o Google Apps Script
        try {
            const response = await fetch(scriptUrl, {
                method: 'POST',
                body: formData,
                mode: 'no-cors' // Adicionar para evitar problemas de CORS
            });
            
            // Se chegou até aqui, consideramos sucesso
            // Limpar formulário
            form.reset();
            
            // Mostrar feedback de sucesso
            showNotification('Currículo enviado com sucesso! Verifique seu email.', 'success');
            
        } catch (error) {
            console.error('Erro no Google Apps Script:', error);
            
            // Fallback: usar mailto como antes
            const assunto = `Candidatura - ${cargo} - ${nome}`;
            const corpoEmail = `
Candidatura para vaga de emprego

Nome: ${nome}
E-mail: ${email}
Telefone: ${telefone}
Cargo de interesse: ${cargo}

---
Este currículo foi enviado através do site do Auto Posto Estrela D'Alva.
Data: ${new Date().toLocaleDateString('pt-BR')}
Hora: ${new Date().toLocaleTimeString('pt-BR')}
            `.trim();
            
            // Usar mailto como fallback
            const mailtoUrl = `mailto:leonardobrsvicente@gmail.com?subject=${encodeURIComponent(assunto)}&body=${encodeURIComponent(corpoEmail)}`;
            window.open(mailtoUrl, '_blank');
            
            // Limpar formulário
            form.reset();
            
            // Mostrar feedback
            showNotification('Abrindo seu cliente de email. Por favor, anexe o PDF do currículo antes de enviar.', 'info');
        }
        
    } catch (error) {
        // Mostrar erro
        showNotification(error.message, 'error');
        
    } finally {
        // Reativar botão
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-file-upload"></i><span>Enviar Currículo</span>';
    }
}

// Função para obter IP do cliente (opcional)
async function getClientIP() {
    try {
        const response = await fetch('https://api.ipify.org?format=json');
        const data = await response.json();
        return data.ip;
    } catch (error) {
        return 'Não disponível';
    }
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

// Função para detectar bots
function detectBot() {
    // Verificar se o JavaScript está habilitado
    if (typeof navigator === 'undefined') return true;
    
    // Verificar user agent suspeito
    const userAgent = navigator.userAgent.toLowerCase();
    const botPatterns = [
        'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 
        'python', 'java', 'perl', 'ruby', 'php', 'go', 'node'
    ];
    
    for (const pattern of botPatterns) {
        if (userAgent.includes(pattern)) return true;
    }
    
    // Verificar se tem plugins (bots geralmente não têm)
    if (navigator.plugins && navigator.plugins.length === 0) return true;
    
    // Verificar se tem cookies habilitados
    if (!navigator.cookieEnabled) return true;
    
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
    console.log('Event tracked:', eventName, eventData);
    
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
            console.warn('Erro ao carregar imagem:', this.src);
        });
    });
}

// Função de inicialização quando DOM carrega
function initializeApp() {
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
    
    console.log('Auto Posto Estrela D\'Alva - Site inicializado com sucesso!');
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