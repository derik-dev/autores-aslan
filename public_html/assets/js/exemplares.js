// Exemplares antecipados: criar campanha e gerir leitores.
(function () {
  var raiz = document.getElementById('arc-app');
  if (!raiz) return;
  var csrf = raiz.getAttribute('data-csrf');
  var $ = function (id) { return document.getElementById(id); };
  var erro = $('a-erro');

  function chamar(acao, dados) {
    return fetch('exemplares_api.php?acao=' + acao, {
      method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: new URLSearchParams(dados)
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inesperada do servidor (código ' + r.status + ').' }; }); })
      .catch(function () { return { ok: false, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' }; });
  }

  function copiar(texto) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(texto);
    return Promise.reject();
  }

  $('a-criar').addEventListener('click', function () {
    erro.hidden = true;
    if (!$('a-titulo').value.trim()) { erro.textContent = 'Dê um título à campanha.'; erro.hidden = false; $('a-titulo').focus(); return; }
    var b = this;
    b.disabled = true;
    b.textContent = 'Criando…';
    chamar('criar', {
      titulo: $('a-titulo').value, descricao: $('a-descricao').value,
      entrega_id: $('a-entrega').value, vagas: $('a-vagas').value
    }).then(function (j) {
      b.disabled = false;
      b.textContent = 'Criar campanha';
      if (!j.ok) { erro.textContent = j.erro; erro.hidden = false; return; }
      window.location.reload();
    });
  });

  raiz.addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-acao]');
    if (!b) return;
    var acao = b.getAttribute('data-acao');
    var campanha = b.closest('.linha-audio');
    var msg = campanha ? campanha.querySelector('.arc-msg') : null;
    var linha = b.closest('tr');

    if (acao === 'copiar') {
      copiar(b.getAttribute('data-url')).then(function () {
        var t = b.textContent;
        b.textContent = 'Copiado';
        setTimeout(function () { b.textContent = t; }, 2000);
      }, function () { window.prompt('Copie o endereço:', b.getAttribute('data-url')); });
      return;
    }

    if (acao === 'excluir-arc') {
      if (b.getAttribute('data-confirma') !== '1') {
        b.setAttribute('data-confirma', '1');
        b.textContent = 'Confirmar exclusão';
        setTimeout(function () { b.removeAttribute('data-confirma'); b.textContent = 'Excluir campanha'; }, 4000);
        return;
      }
      chamar('excluir', { id: campanha.getAttribute('data-id') }).then(function (j) {
        if (j.ok) campanha.remove(); else { msg.textContent = j.erro; }
      });
      return;
    }

    if (acao === 'alternar') {
      chamar('alternar', { id: campanha.getAttribute('data-id') }).then(function (j) {
        if (j.ok) window.location.reload(); else { msg.textContent = j.erro; }
      });
      return;
    }

    // Ações sobre um leitor
    if (!linha) return;
    b.disabled = true;
    chamar(acao, { leitor_id: linha.getAttribute('data-leitor') }).then(function (j) {
      b.disabled = false;
      if (!j.ok) { msg.textContent = j.erro; return; }
      if (acao === 'aprovar') {
        var titulo = linha.getAttribute('data-titulo');
        var nome = linha.getAttribute('data-nome');
        var email = linha.getAttribute('data-email');
        var corpo = 'Olá, ' + nome + '! Você foi escolhido(a) para ler "' + titulo + '" antes do lançamento.\n\nBaixe o livro aqui (o link tem validade e limite de downloads):\n' + j.url + '\n\nQuando terminar, conte pra gente o que achou!';
        try { sessionStorage.setItem('ultimo-link', j.url); } catch (e) {}
        copiar(j.url).catch(function () {});
        window.location.href = 'mailto:' + encodeURIComponent(email) + '?subject=' + encodeURIComponent('Seu exemplar de ' + titulo) + '&body=' + encodeURIComponent(corpo);
        setTimeout(function () { window.location.reload(); }, 1500);
      } else {
        window.location.reload();
      }
    });
  });
})();
