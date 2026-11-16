// Telas Análises e Mercado: gerar relatório com IA e excluir relatórios antigos.
(function () {
  var raiz = document.getElementById('rel-app');
  if (!raiz) return;
  var csrf = raiz.getAttribute('data-csrf');
  var acao = raiz.getAttribute('data-acao');
  var botao = document.getElementById('rel-gerar');
  var erro = document.getElementById('rel-erro');
  var estado = document.getElementById('rel-estado');
  var rotulo = botao.textContent;

  function chamar(a, dados) {
    return fetch('relatorios_api.php?acao=' + a, {
      method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: new URLSearchParams(dados || {})
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inesperada do servidor (código ' + r.status + ').' }; }); })
      .catch(function () { return { ok: false, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' }; });
  }

  function valor(id) { var el = document.getElementById(id); return el ? el.value : ''; }

  botao.addEventListener('click', function () {
    erro.hidden = true;
    var dados = {};
    if (acao === 'gerar_mercado') {
      dados = { titulo: valor('m-titulo'), genero: valor('m-genero'), publico: valor('m-publico'), sinopse: valor('m-sinopse') };
      if (!dados.titulo.trim() || !dados.sinopse.trim()) { erro.textContent = 'Preencha o título e a sinopse do livro.'; erro.hidden = false; return; }
    }
    botao.disabled = true;
    botao.textContent = 'Gerando…';
    estado.textContent = 'A IA está trabalhando, pode levar até 1 minuto. Não feche esta aba.';
    chamar(acao, dados).then(function (j) {
      botao.disabled = false;
      botao.textContent = rotulo;
      if (!j.ok) { estado.textContent = ''; erro.textContent = j.erro; erro.hidden = false; return; }
      estado.textContent = 'Pronto! Atualizando…';
      window.location.reload();
    });
  });

  raiz.addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-acao="excluir-rel"]');
    if (!b) return;
    if (b.getAttribute('data-confirma') !== '1') {
      b.setAttribute('data-confirma', '1');
      b.textContent = 'Confirmar exclusão';
      setTimeout(function () { b.removeAttribute('data-confirma'); b.textContent = 'Excluir'; }, 4000);
      return;
    }
    b.disabled = true;
    b.textContent = 'Excluindo…';
    var linha = b.closest('.linha-audio');
    chamar('excluir', { id: linha.getAttribute('data-id') }).then(function (j) {
      if (j.ok) { linha.remove(); }
      else { b.disabled = false; b.textContent = 'Excluir'; erro.textContent = j.erro; erro.hidden = false; }
    });
  });
})();
