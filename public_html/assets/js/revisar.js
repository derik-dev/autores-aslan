// Revisão da tradução (estilo pull request): editar, aprovar, traduzir de novo e filtrar.
(function () {
  var raiz = document.getElementById('revisao');
  if (!raiz) return;

  var csrf = raiz.getAttribute('data-csrf');
  var tradId = raiz.getAttribute('data-id');
  var contagem = document.getElementById('rev-contagem');
  var barra = document.getElementById('rev-barra');
  var aviso = document.getElementById('rev-aviso');
  var erroGeral = document.getElementById('rev-erro');
  var botaoTodos = document.getElementById('rev-todos');
  var filtro = 'todos';
  var pendentesSalvando = 0;

  function chamar(acao, dados) {
    dados.id = tradId;
    return fetch('traducao_api.php?acao=' + acao, {
      method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: new URLSearchParams(dados)
    })
      .then(function (r) {
        return r.json().catch(function () { return { ok: false, erro: 'Resposta inesperada do servidor (código ' + r.status + ').' }; });
      })
      .catch(function () { return { ok: false, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' }; });
  }

  function autoajustar(t) { t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; }

  function atualizarContagem(aprovados, total) {
    contagem.textContent = aprovados + ' de ' + total + ' trechos aprovados';
    barra.style.width = (total ? Math.round(aprovados / total * 100) : 0) + '%';
  }

  function estado(bloco, texto) { bloco.querySelector('.trad-estado').textContent = texto; }

  function marcarAprovado(bloco, aprovado) {
    bloco.classList.toggle('aprovado', !!aprovado);
    var chip = bloco.querySelector('.trad-chip');
    chip.textContent = aprovado ? 'Aprovado' : 'Pendente';
    chip.className = 'chip trad-chip ' + (aprovado ? 'chip-ok' : 'chip-neutro');
    bloco.querySelector('[data-acao="aprovar"]').textContent = aprovado ? 'Desfazer aprovação' : 'Aprovar trecho';
    aplicarFiltro();
  }

  function aplicarFiltro() {
    var blocos = raiz.querySelectorAll('.trad-bloco');
    var visiveis = 0;
    blocos.forEach(function (b) {
      var apr = b.classList.contains('aprovado');
      var mostrar = filtro === 'todos' || (filtro === 'aprovados' && apr) || (filtro === 'pendentes' && !apr);
      b.hidden = !mostrar;
      if (mostrar) visiveis++;
    });
    aviso.textContent = visiveis === 0 ? 'Nenhum trecho nesta lista.' : '';
  }

  // Salva a edição quando o revisor sai do campo.
  function salvar(bloco) {
    var t = bloco.querySelector('.trad-texto');
    if (!t || t.getAttribute('data-sujo') !== '1') return Promise.resolve();
    var ordem = bloco.getAttribute('data-ordem');
    estado(bloco, 'Salvando…');
    pendentesSalvando++;
    return chamar('salvar', { ordem: ordem, texto: t.value }).then(function (j) {
      pendentesSalvando--;
      if (!j.ok) { estado(bloco, 'Não salvou: ' + j.erro); return; }
      t.removeAttribute('data-sujo');
      estado(bloco, 'Salvo');
    });
  }

  raiz.addEventListener('input', function (ev) {
    var t = ev.target;
    if (!t.classList || !t.classList.contains('trad-texto')) return;
    t.setAttribute('data-sujo', '1');
    autoajustar(t);
    estado(t.closest('.trad-bloco'), 'Edição ainda não salva');
  });

  raiz.addEventListener('focusout', function (ev) {
    var t = ev.target;
    if (t.classList && t.classList.contains('trad-texto')) salvar(t.closest('.trad-bloco'));
  });

  raiz.addEventListener('click', function (ev) {
    var f = ev.target.closest('.filtro');
    if (f) {
      filtro = f.getAttribute('data-filtro');
      raiz.querySelectorAll('.filtro').forEach(function (x) { x.classList.toggle('ativo', x === f); });
      aplicarFiltro();
      return;
    }

    var b = ev.target.closest('button[data-acao]');
    if (!b) return;
    var bloco = b.closest('.trad-bloco');
    var ordem = bloco.getAttribute('data-ordem');
    var acao = b.getAttribute('data-acao');
    erroGeral.hidden = true;

    if (acao === 'aprovar') {
      var novo = bloco.classList.contains('aprovado') ? 0 : 1;
      b.disabled = true;
      salvar(bloco).then(function () {
        return chamar('aprovar', { ordem: ordem, valor: novo });
      }).then(function (j) {
        b.disabled = false;
        if (!j.ok) { estado(bloco, 'Não aprovou: ' + j.erro); return; }
        marcarAprovado(bloco, j.aprovado === 1);
        estado(bloco, j.aprovado === 1 ? 'Aprovado' : '');
        atualizarContagem(j.aprovados, j.total);
      });
    } else if (acao === 'pedir') {
      var painel = bloco.querySelector('.trad-novo');
      painel.hidden = !painel.hidden;
      if (!painel.hidden) painel.querySelector('input').focus();
    } else if (acao === 'retraduzir') {
      var campo = bloco.querySelector('.trad-novo input');
      var texto = bloco.querySelector('.trad-texto');
      b.disabled = true;
      b.textContent = 'Traduzindo…';
      estado(bloco, 'Traduzindo de novo, pode levar até 1 minuto…');
      chamar('retraduzir', { ordem: ordem, ajuste: campo.value }).then(function (j) {
        b.disabled = false;
        b.textContent = 'Traduzir este trecho de novo';
        if (!j.ok) { estado(bloco, 'Não deu certo: ' + j.erro); return; }
        texto.value = j.texto;
        texto.removeAttribute('data-sujo');
        autoajustar(texto);
        marcarAprovado(bloco, false);
        campo.value = '';
        bloco.querySelector('.trad-novo').hidden = true;
        estado(bloco, 'Trecho traduzido de novo. Leia e aprove.');
        atualizarContagem(j.aprovados, j.total);
      });
    }
  });

  botaoTodos.addEventListener('click', function () {
    if (botaoTodos.getAttribute('data-confirma') !== '1') {
      botaoTodos.setAttribute('data-confirma', '1');
      botaoTodos.textContent = 'Confirmar: aprovar todos';
      setTimeout(function () { botaoTodos.removeAttribute('data-confirma'); botaoTodos.textContent = 'Aprovar todos os trechos'; }, 4000);
      return;
    }
    botaoTodos.removeAttribute('data-confirma');
    botaoTodos.disabled = true;
    botaoTodos.textContent = 'Aprovando…';
    var blocos = raiz.querySelectorAll('.trad-bloco[data-feito="1"]');
    var salvando = [];
    blocos.forEach(function (b) { salvando.push(salvar(b)); });
    Promise.all(salvando).then(function () { return chamar('aprovar_todos', {}); }).then(function (j) {
      botaoTodos.disabled = false;
      botaoTodos.textContent = 'Aprovar todos os trechos';
      if (!j.ok) { erroGeral.textContent = j.erro; erroGeral.hidden = false; return; }
      blocos.forEach(function (b) { marcarAprovado(b, true); estado(b, ''); });
      atualizarContagem(j.aprovados, j.total);
    });
  });

  window.addEventListener('beforeunload', function (e) {
    if (pendentesSalvando > 0 || raiz.querySelector('.trad-texto[data-sujo="1"]')) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  raiz.querySelectorAll('.trad-texto').forEach(autoajustar);
})();
