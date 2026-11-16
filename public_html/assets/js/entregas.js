// Entregas: guardar arquivo, criar e cancelar links, copiar e excluir.
(function () {
  var raiz = document.getElementById('ent-app');
  if (!raiz) return;
  var csrf = raiz.getAttribute('data-csrf');
  var $ = function (id) { return document.getElementById(id); };

  function chamar(acao, corpo) {
    return fetch('entregas_api.php?acao=' + acao, {
      method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: corpo
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inesperada do servidor (código ' + r.status + '). Se o arquivo é grande, ele pode passar do limite de envio do servidor.' }; }); })
      .catch(function () { return { ok: false, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' }; });
  }

  function copiar(texto) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(texto);
    return Promise.reject();
  }

  // ---------- Guardar entrega ----------
  var salvar = $('e-salvar'), erro = $('e-erro'), estado = $('e-estado');
  salvar.addEventListener('click', function () {
    erro.hidden = true;
    var titulo = $('e-titulo').value.trim();
    var arq = $('e-arquivo').files[0];
    var audio = $('e-audio') ? $('e-audio').value : '0';
    if (!titulo) { erro.textContent = 'Dê um título à entrega.'; erro.hidden = false; $('e-titulo').focus(); return; }
    if (!arq && audio === '0') { erro.textContent = 'Escolha um arquivo ou um áudio pronto.'; erro.hidden = false; return; }
    var fd = new FormData();
    fd.append('titulo', titulo);
    if (arq) fd.append('arquivo', arq); else fd.append('audio_id', audio);
    salvar.disabled = true;
    salvar.textContent = 'Enviando…';
    estado.textContent = 'Enviando o arquivo, não feche esta aba.';
    chamar('criar', fd).then(function (j) {
      salvar.disabled = false;
      salvar.textContent = 'Guardar entrega';
      estado.textContent = '';
      if (!j.ok) { erro.textContent = j.erro; erro.hidden = false; return; }
      window.location.reload();
    });
  });

  // ---------- Ações da lista ----------
  raiz.addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-acao]');
    if (!b) return;
    var acao = b.getAttribute('data-acao');
    var item = b.closest('.linha-audio');

    if (acao === 'copiar') {
      copiar(b.getAttribute('data-url')).then(function () {
        b.textContent = 'Link copiado';
        setTimeout(function () { b.textContent = 'Copiar link'; }, 2000);
      }, function () { window.prompt('Copie o link:', b.getAttribute('data-url')); });
    } else if (acao === 'criar-link') {
      var dest = item.querySelector('.l-dest').value.trim();
      var res = item.querySelector('.l-resultado');
      if (!dest) { res.textContent = 'Diga para quem é o link.'; return; }
      b.disabled = true;
      res.textContent = 'Criando…';
      chamar('criar_link', new URLSearchParams({
        id: item.getAttribute('data-id'), destinatario: dest,
        dias: item.querySelector('.l-dias').value, limite: item.querySelector('.l-limite').value
      })).then(function (j) {
        b.disabled = false;
        if (!j.ok) { res.textContent = j.erro; return; }
        var assunto = encodeURIComponent(item.getAttribute('data-titulo'));
        var corpo = encodeURIComponent('Olá! Aqui está o seu link para baixar "' + item.getAttribute('data-titulo') + '":\n\n' + j.url);
        res.innerHTML = '';
        var a = document.createElement('a');
        a.href = 'mailto:' + (dest.indexOf('@') > 0 ? encodeURIComponent(dest) : '') + '?subject=' + assunto + '&body=' + corpo;
        a.textContent = 'Enviar por e-mail';
        res.appendChild(document.createTextNode('Link criado: ' + j.url + ' · '));
        res.appendChild(a);
        copiar(j.url).then(function () { res.appendChild(document.createTextNode(' · já copiado')); }, function () {});
      });
    } else if (acao === 'cancelar-link') {
      b.disabled = true;
      chamar('cancelar_link', new URLSearchParams({ link_id: b.closest('li').getAttribute('data-link') })).then(function (j) {
        if (j.ok) window.location.reload(); else { b.disabled = false; }
      });
    } else if (acao === 'excluir-ent') {
      if (b.getAttribute('data-confirma') !== '1') {
        b.setAttribute('data-confirma', '1');
        b.textContent = 'Confirmar: apaga o arquivo e os links';
        setTimeout(function () { b.removeAttribute('data-confirma'); b.textContent = 'Excluir'; }, 4000);
        return;
      }
      b.disabled = true;
      chamar('excluir', new URLSearchParams({ id: item.getAttribute('data-id') })).then(function (j) {
        if (j.ok) item.remove(); else { b.disabled = false; b.textContent = 'Excluir'; erro.textContent = j.erro; erro.hidden = false; }
      });
    }
  });
})();
