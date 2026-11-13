// Script principal do front-end (interações da interface).

// Abre e fecha o menu lateral no celular.
document.querySelectorAll('[data-menu-toggle]').forEach(function (botao) {
  botao.addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('aberto');
  });
});
