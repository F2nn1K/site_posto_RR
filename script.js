// Variáveis globais
let mobileMenuOpen = false;

// Configuração do Supabase
const SUPABASE_URL = 'https://ezpjdywoywtpxtiynseg.supabase.co';
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImV6cGpkeXdveXd0cHh0aXluc2VnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI4NjE3MjgsImV4cCI6MjA2ODQzNzcyOH0.qiVX5Lti7kpOoJEEAlU795P9OmXFBDqOKKxUBW4cFc8';

// Inicializar Supabase
const supabase = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

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

// Função para enviar currículo para o Supabase
async function enviarCurriculo(event) {
    console.log('🚀 Função enviarCurriculo iniciada!');
    console.log('Evento recebido:', event);
    
    event.preventDefault();
    
    console.log('📋 Iniciando validações...');
    
    // Rate limiting
    const now = Date.now();
    console.log('⏰ Rate limiting check...');
    if (now - lastSubmitTime < SECURITY_CONFIG.rateLimit.timeWindow) {
        submitAttempts++;
        console.log('⚠️ Tentativa número:', submitAttempts);
        if (submitAttempts > SECURITY_CONFIG.rateLimit.maxAttempts) {
            console.log('❌ Muitas tentativas, bloqueando...');
            showNotification('Muitas tentativas. Aguarde um momento antes de tentar novamente.', 'error');
            return;
        }
    } else {
        submitAttempts = 1;
        lastSubmitTime = now;
        console.log('✅ Rate limiting OK');
    }
    
    console.log('🔒 Verificando botão...');
    // Prevenir múltiplos envios
    const submitButton = event.target.querySelector('button[type="submit"]');
    
    console.log('🔒 Desabilitando botão...');
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Enviando...</span>';
    
    try {
        console.log('🔍 Verificando se é bot...');
        // Verificar se é um bot (DESABILITADO PARA TESTE)
        // if (detectBot()) {
        //     console.log('❌ Bot detectado! Acesso negado.');
        //     throw new Error('Acesso negado.');
        // }
        console.log('✅ Verificação de bot desabilitada para teste...');
        
        console.log('📝 Obtendo dados do formulário...');
    
        const form = event.target;
        const formData = new FormData(form);
        
        // Obter e sanitizar dados
        const nome = sanitizeInput(formData.get('nome'));
        const email = sanitizeInput(formData.get('email'));
        const telefone = sanitizeInput(formData.get('telefone'));
        const cargo = formData.get('cargo');
        const curriculo = formData.get('curriculo');
        
        console.log('📄 Dados capturados:');
        console.log('- Nome:', nome);
        console.log('- Email:', email);
        console.log('- Telefone:', telefone);
        console.log('- Cargo:', cargo);
        console.log('- Arquivo:', curriculo ? curriculo.name : 'Nenhum');
        console.log('- Tamanho do arquivo:', curriculo ? curriculo.size + ' bytes' : 'N/A');
        console.log('- Tipo do arquivo:', curriculo ? curriculo.type : 'N/A');
        
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
        
        console.log('✅ Todas as validações passaram!');
        
        // Upload do arquivo para o Supabase Storage
        let arquivoUrl = null;
        if (curriculo) {
            console.log('📁 Fazendo upload do arquivo...');
            const fileName = `curriculos/${Date.now()}-${curriculo.name}`;
            
            const { data: uploadData, error: uploadError } = await supabase.storage
                .from('curriculos')
                .upload(fileName, curriculo);
            
            if (uploadError) {
                console.error('❌ Erro no upload:', uploadError);
                throw new Error('Erro ao fazer upload do arquivo: ' + uploadError.message);
            }
            
            // Obter URL pública do arquivo
            const { data: urlData } = supabase.storage
                .from('curriculos')
                .getPublicUrl(fileName);
            
            arquivoUrl = urlData.publicUrl;
            console.log('✅ Upload concluído:', arquivoUrl);
        }
        
        // Salvar dados no banco
        console.log('💾 Salvando no banco...');
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
            console.error('❌ Erro ao salvar no banco:', error);
            throw new Error('Erro ao salvar currículo: ' + error.message);
        }
        
        console.log('✅ Currículo salvo com sucesso!', data);
        
        // Limpar formulário
        form.reset();
        
        // Mostrar feedback de sucesso
        showNotification('Currículo enviado com sucesso! Entraremos em contato em breve.', 'success');
        
    } catch (error) {
        console.error('❌ Erro:', error);
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
    
    // Adicionar listener direto no formulário para garantir funcionamento
    const form = document.querySelector('form');
    if (form) {
        console.log('🔧 Adicionando listener direto no formulário...');
        form.addEventListener('submit', function(e) {
            console.log('🎯 Submit capturado pelo listener direto!');
            enviarCurriculo(e);
        });
        
        // Adicionar listener também no botão para garantir
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            console.log('🔧 Adicionando listener no botão também...');
            submitButton.addEventListener('click', function(e) {
                console.log('🎯 Clique no botão capturado!');
                
                // Verificar se o formulário é válido
                const formValid = form.checkValidity();
                console.log('📋 Formulário válido:', formValid);
                
                if (!formValid) {
                    console.log('❌ Formulário inválido! Campos com problema:');
                    const invalidFields = form.querySelectorAll(':invalid');
                    invalidFields.forEach(field => {
                        console.log('- Campo:', field.name, 'Valor:', field.value, 'Tipo:', field.type);
                    });
                } else {
                    console.log('✅ Formulário válido, submit deve acontecer...');
                    
                    // Chamar a função diretamente
                    console.log('🚀 Chamando enviarCurriculo diretamente...');
                    const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                    form.dispatchEvent(submitEvent);
                }
                
                // Não prevenir o comportamento padrão aqui, deixar o submit acontecer
            });
        }
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