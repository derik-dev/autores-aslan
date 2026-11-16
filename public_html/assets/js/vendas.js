// Painel de vendas (editora): importar CSV e desfazer importação.
(function () {
  var raiz = document.getElementById('vendas-app');
  if (!raiz) return;
  var csrf = raiz.getAttribute('data-csrf');
  var input = document.getElementById('csv');
  var rotulo = document.getElementById('rotulo-csv');
  var resultado = document.getElementById('imp-resultado');
  var erro = document.getElementById('imp-erro');

  function chamar(acao, corpo) {
    return fetch('vendas_api.php?acao=' + acao, {
      method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: corpo
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inesperada do servidor (código ' + r.status + ').' }; }); })
      .catch(function () { return { ok: false, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' }; });
  }

  input.addEventListener('change', function () {
    var f = input.files[0];
    if (!f) return;
    erro.hidden = true;
    resultado.textContent = '';
    var original = rotulo.textContent;
    rotulo.textContent = 'Importando…';
    var fd = new FormData();
    fd.append('arquivo', f);
    chamar('importar', fd).then(function (j) {
      rotulo.textContent = original;
      input.value = '';
      if (!j.ok) { erro.textContent = j.erro; erro.hidden = false; return; }
      var t = j.linhas + ' linhas importadas';
      if (j.ignoradas) t += ' e ' + j.ignoradas + ' ignoradas (' + j.problemas.join('; ') + ')';
      resultado.textContent = t + '. Atualizando…';
      setTimeout(function () { window.location.reload(); }, 1500);
    });
  });

  raiz.addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-acao="excluir-imp"]');
    if (!b) return;
    if (b.getAttribute('data-confirma') !== '1') {
      b.setAttribute('data-confirma', '1');
      b.textContent = 'Confirmar: apagar as vendas desta importação';
      setTimeout(function () { b.removeAttribute('data-confirma'); b.textContent = 'Desfazer importação'; }, 4000);
      return;
    }
    b.disabled = true;
    b.textContent = 'Desfazendo…';
    chamar('excluir_importacao', new URLSearchParams({ id: b.closest('.linha-audio').getAttribute('data-id') })).then(function (j) {
      if (j.ok) { window.location.reload(); }
      else { b.disabled = false; b.textContent = 'Desfazer importação'; erro.textContent = j.erro; erro.hidden = false; }
    });
  });
})();
