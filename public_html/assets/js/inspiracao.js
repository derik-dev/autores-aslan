// Área de inspiração: criar cartões, filtrar por tipo e excluir.
(function () {
  var raiz = document.getElementById('insp-app');
  if (!raiz) return;
  var csrf = raiz.getAttribute('data-csrf');
  var $ = function (id) { return document.getElementById(id); };
  var salvar = $('insp-salvar'), erro = $('insp-erro');

  function chamar(acao, corpo) {
    return fetch('inspiracao_api.php?acao=' + acao, {
      method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: corpo
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inesperada do servidor (código ' + r.status + ').' }; }); })
      .catch(function () { return { ok: false, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' }; });
  }

  salvar.addEventListener('click', function () {
    erro.hidden = true;
    if (!$('titulo').value.trim()) { erro.textContent = 'Dê um título ao cartão.'; erro.hidden = false; $('titulo').focus(); return; }
    var fd = new FormData();
    fd.append('tipo', $('tipo').value);
    fd.append('titulo', $('titulo').value);
    fd.append('conteudo', $('conteudo').value);
    fd.append('url', $('url').value);
    if ($('imagem').files[0]) fd.append('imagem', $('imagem').files[0]);
    salvar.disabled = true;
    salvar.textContent = 'Salvando…';
    chamar('criar', fd).then(function (j) {
      salvar.disabled = false;
      salvar.textContent = 'Salvar cartão';
      if (!j.ok) { erro.textContent = j.erro; erro.hidden = false; return; }
      window.location.reload();
    });
  });

  raiz.addEventListener('click', function (ev) {
    var f = ev.target.closest('.filtro');
    if (f) {
      var alvo = f.getAttribute('data-filtro');
      raiz.querySelectorAll('.filtro').forEach(function (x) { x.classList.toggle('ativo', x === f); });
      raiz.querySelectorAll('.cartao-insp').forEach(function (c) {
        c.hidden = !(alvo === 'todos' || c.getAttribute('data-tipo') === alvo);
      });
      return;
    }
    var b = ev.target.closest('button[data-acao="excluir-insp"]');
    if (!b) return;
    if (b.getAttribute('data-confirma') !== '1') {
      b.setAttribute('data-confirma', '1');
      b.textContent = 'Confirmar exclusão';
      setTimeout(function () { b.removeAttribute('data-confirma'); b.textContent = 'Excluir'; }, 4000);
      return;
    }
    b.disabled = true;
    b.textContent = 'Excluindo…';
    var cartao = b.closest('.cartao-insp');
    chamar('excluir', new URLSearchParams({ id: cartao.getAttribute('data-id') })).then(function (j) {
      if (j.ok) { cartao.remove(); }
      else { b.disabled = false; b.textContent = 'Excluir'; erro.textContent = j.erro; erro.hidden = false; }
    });
  });
})();
