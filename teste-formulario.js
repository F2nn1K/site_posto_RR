// Teste simples do formulário
console.log('🧪 Script de teste carregado!');

// Função de teste simples
function testeEnvio(event) {
    console.log('🚀 TESTE: Função chamada!');
    console.log('Evento:', event);
    event.preventDefault();
    
    // Mostrar alerta simples
    alert('Formulário funcionando! Dados recebidos.');
    
    return false;
}

// Função original simplificada para teste
function enviarCurriculo(event) {
    console.log('🚀 Função enviarCurriculo iniciada!');
    console.log('Evento recebido:', event);
    
    event.preventDefault();
    
    console.log('📋 Iniciando validações...');
    
    // Obter dados do formulário
    const form = event.target;
    const formData = new FormData(form);
    
    console.log('📝 Dados do formulário:');
    console.log('- Nome:', formData.get('nome'));
    console.log('- Email:', formData.get('email'));
    console.log('- Telefone:', formData.get('telefone'));
    console.log('- Cargo:', formData.get('cargo'));
    console.log('- Arquivo:', formData.get('curriculo')?.name || 'Nenhum');
    
    // Mostrar notificação de teste
    alert('Formulário enviado com sucesso! (Teste)');
    
    // Limpar formulário
    form.reset();
    
    return false;
}

// Verificar se as funções estão disponíveis
console.log('✅ Função testeEnvio disponível:', typeof testeEnvio);
console.log('✅ Função enviarCurriculo disponível:', typeof enviarCurriculo);

// Adicionar listener global para debug
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 DOM carregado!');
    
    // Verificar se o formulário existe
    const form = document.querySelector('form');
    if (form) {
        console.log('✅ Formulário encontrado!');
        console.log('📝 onsubmit:', form.onsubmit);
        
        // Adicionar listener adicional para debug
        form.addEventListener('submit', function(e) {
            console.log('🎯 Evento submit capturado!');
        });
    } else {
        console.log('❌ Formulário não encontrado!');
    }
}); 