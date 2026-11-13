// Script principal do front-end (interações da interface).

// Abre e fecha o menu lateral no celular.
document.querySelectorAll('[data-menu-toggle]').forEach(function (botao) {
  botao.addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('aberto');
  });
});

// Durante o envio de um formulário, mostra o que está acontecendo e evita clique duplo.
document.querySelectorAll('form').forEach(function (form) {
  form.addEventListener('submit', function () {
    var botao = form.querySelector('button[data-carregando]');
    if (!botao) return;
    botao.textContent = botao.getAttribute('data-carregando');
    // Desabilita depois do envio começar, para o navegador ainda mandar o formulário.
    setTimeout(function () { botao.disabled = true; }, 0);
  });
});
