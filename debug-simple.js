// Debug simples - teste direto
console.log('🔧 Script de debug carregado!');

// Função super simples
function enviarCurriculo(event) {
    console.log('🚀 FUNÇÃO CHAMADA!');
    alert('FUNÇÃO FUNCIONANDO!');
    event.preventDefault();
    return false;
}

// Verificar se a função existe
console.log('Função disponível:', typeof enviarCurriculo);

// Adicionar listener direto no formulário
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM carregado - procurando formulário...');
    
    const form = document.querySelector('form');
    if (form) {
        console.log('Formulário encontrado!');
        
        // Remover onsubmit antigo
        form.onsubmit = null;
        
        // Adicionar novo listener
        form.addEventListener('submit', function(e) {
            console.log('🎯 SUBMIT CAPTURADO!');
            enviarCurriculo(e);
        });
        
        console.log('Listener adicionado!');
    } else {
        console.log('❌ Formulário não encontrado!');
    }
}); 