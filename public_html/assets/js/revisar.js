// Revisão da tradução (estilo pull request): o texto do autor à esquerda e a tradução à direita, ambos editáveis.
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
  var salvando = 0;

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

  function estado(bloco, texto) { var el = bloco.querySelector('.trad-estado'); if (el) el.textContent = texto; }
  function estadoOrig(bloco, texto) { bloco.querySelector('.trad-estado-o').textContent = texto; }
  function ehFeito(bloco) { return bloco.getAttribute('data-feito') === '1'; }

  function marcarAprovado(bloco, aprovado) {
    bloco.classList.toggle('aprovado', !!aprovado);
    var chip = bloco.querySelector('.trad-chip');
    chip.textContent = aprovado ? 'Aprovado' : (ehFeito(bloco) ? 'Pendente' : 'Não traduzido');
    chip.className = 'chip trad-chip ' + (aprovado ? 'chip-ok' : 'chip-neutro');
    var b = bloco.querySelector('[data-acao="aprovar"]');
    if (b) b.textContent = aprovado ? 'Desfazer aprovação' : 'Aprovar trecho';
    aplicarFiltro();
  }

  // ---------- Alertas automáticos e dúvidas marcadas pela IA ----------
  var MARCA = /\{\{\?:\s*([^}]*)\}\}/g;

  function paragrafos(t) { return t.split(/\n+/).filter(function (l) { return l.trim() !== ''; }).length; }

  function revisarBloco(bloco) {
    var area = bloco.querySelector('.trad-texto');
    var caixa = bloco.querySelector('.trad-alertas');
    if (!area || !caixa) return;
    var orig = bloco.querySelector('.trad-orig').value;
    var trad = area.value;
    var itens = [];

    var duvidas = [];
    trad.replace(MARCA, function (_, d) { duvidas.push(d.trim()); return _; });
    bloco.setAttribute('data-duvidas', duvidas.length);
    duvidas.forEach(function (d) { itens.push({ erro: false, txt: 'Dúvida da IA: ' + d + ' (resolva e apague a marca {{?: …}} do texto)' }); });

    var po = paragrafos(orig), pt = paragrafos(trad);
    if (po !== pt) itens.push({ erro: true, txt: 'O original tem ' + po + ' parágrafo(s) e a tradução tem ' + pt + '. Confira se algo foi omitido ou unido.' });
    if (orig.trim().length > 200 && trad.trim().length < orig.trim().length * 0.7) {
      itens.push({ erro: true, txt: 'A tradução está bem mais curta que o original. Pode ter havido omissão.' });
    }

    caixa.innerHTML = '';
    itens.forEach(function (i) {
      var p = document.createElement('p');
      if (i.erro) p.className = 'aviso-erro';
      p.textContent = i.txt;
      caixa.appendChild(p);
    });
    atualizarDuvidas();
  }

  function atualizarDuvidas() {
    var total = 0, blocos = 0;
    raiz.querySelectorAll('.trad-bloco').forEach(function (b) {
      var n = parseInt(b.getAttribute('data-duvidas') || '0', 10);
      if (n > 0) { total += n; blocos++; }
    });
    document.getElementById('rev-duvidas').textContent = total
      ? total + ' dúvida(s) da IA em ' + blocos + ' trecho(s) para resolver'
      : '';
  }

  function aplicarFiltro() {
    var visiveis = 0;
    raiz.querySelectorAll('.trad-bloco').forEach(function (b) {
      var apr = b.classList.contains('aprovado');
      var mostrar = filtro === 'todos' || (filtro === 'aprovados' && apr) || (filtro === 'pendentes' && !apr);
      b.hidden = !mostrar;
      if (mostrar) visiveis++;
    });
    aviso.textContent = visiveis === 0 ? 'Nenhum trecho nesta lista.' : '';
  }

  // Salva a tradução quando o revisor sai do campo.
  function salvar(bloco) {
    var t = bloco.querySelector('.trad-texto');
    if (!t || t.getAttribute('data-sujo') !== '1') return Promise.resolve();
    estado(bloco, 'Salvando…');
    salvando++;
    return chamar('salvar', { ordem: bloco.getAttribute('data-ordem'), texto: t.value }).then(function (j) {
      salvando--;
      if (!j.ok) { estado(bloco, 'Não salvou: ' + j.erro); return; }
      t.removeAttribute('data-sujo');
      estado(bloco, 'Salvo');
    });
  }

  // Salva o texto original do autor. Mudou o original, a tradução precisa ser revista.
  function salvarOriginal(bloco) {
    var o = bloco.querySelector('.trad-orig');
    if (o.getAttribute('data-sujo') !== '1') return Promise.resolve();
    estadoOrig(bloco, 'Salvando…');
    salvando++;
    return chamar('salvar_original', { ordem: bloco.getAttribute('data-ordem'), texto: o.value }).then(function (j) {
      salvando--;
      if (!j.ok) { estadoOrig(bloco, 'Não salvou: ' + j.erro); return; }
      o.removeAttribute('data-sujo');
      if (ehFeito(bloco)) {
        marcarAprovado(bloco, false);
        atualizarContagem(j.aprovados, j.total);
        estadoOrig(bloco, 'Salvo. A tradução ao lado ficou desatualizada: clique em "Traduzir de novo…".');
      } else {
        estadoOrig(bloco, 'Salvo');
      }
    });
  }

  function salvarTudo(bloco) { return Promise.all([salvarOriginal(bloco), salvar(bloco)]); }

  raiz.addEventListener('input', function (ev) {
    var t = ev.target;
    if (!t.classList || !t.classList.contains('trad-area')) return;
    t.setAttribute('data-sujo', '1');
    autoajustar(t);
    var bloco = t.closest('.trad-bloco');
    if (t.classList.contains('trad-orig')) estadoOrig(bloco, 'Edição ainda não salva');
    else estado(bloco, 'Edição ainda não salva');
    revisarBloco(bloco);
  });

  raiz.addEventListener('focusout', function (ev) {
    var t = ev.target;
    if (!t.classList || !t.classList.contains('trad-area')) return;
    var bloco = t.closest('.trad-bloco');
    if (t.classList.contains('trad-orig')) salvarOriginal(bloco); else salvar(bloco);
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
    var orig = bloco.querySelector('.trad-orig');
    erroGeral.hidden = true;

    if (acao === 'aprovar') {
      var novo = bloco.classList.contains('aprovado') ? 0 : 1;
      b.disabled = true;
      salvarTudo(bloco).then(function () {
        return chamar('aprovar', { ordem: ordem, valor: novo });
      }).then(function (j) {
        b.disabled = false;
        if (!j.ok) { estado(bloco, 'Não aprovou: ' + j.erro); return; }
        marcarAprovado(bloco, j.aprovado === 1);
        estado(bloco, j.aprovado === 1 ? 'Aprovado' : '');
        atualizarContagem(j.aprovados, j.total);
      });
    } else if (acao === 'corrigir') {
      var anterior = orig.value;
      var desfazer = bloco.querySelector('[data-acao="desfazer"]');
      b.disabled = true;
      b.textContent = 'Corrigindo…';
      estadoOrig(bloco, 'Corrigindo, pode levar alguns segundos…');
      chamar('corrigir', { texto: anterior }).then(function (j) {
        b.disabled = false;
        b.textContent = 'Corrigir ortografia e gramática';
        if (!j.ok) { estadoOrig(bloco, 'Não deu certo: ' + j.erro); return; }
        if (j.texto.trim() === anterior.trim()) { estadoOrig(bloco, 'Nenhum erro encontrado neste trecho.'); return; }
        orig.setAttribute('data-anterior', anterior);
        orig.value = j.texto;
        orig.setAttribute('data-sujo', '1');
        autoajustar(orig);
        desfazer.hidden = false;
        salvarOriginal(bloco).then(function () {
          if (!ehFeito(bloco)) estadoOrig(bloco, 'Correção aplicada. Leia e, se não gostar, desfaça.');
        });
      });
    } else if (acao === 'desfazer') {
      orig.value = orig.getAttribute('data-anterior') || orig.value;
      orig.removeAttribute('data-anterior');
      orig.setAttribute('data-sujo', '1');
      autoajustar(orig);
      b.hidden = true;
      salvarOriginal(bloco);
    } else if (acao === 'conferir') {
      var caixaConf = bloco.querySelector('.trad-conferencia');
      b.disabled = true;
      b.textContent = 'Conferindo…';
      estado(bloco, 'A IA está comparando os dois lados, pode levar alguns segundos…');
      salvarTudo(bloco).then(function () {
        return chamar('conferir', { ordem: ordem });
      }).then(function (j) {
        b.disabled = false;
        b.textContent = 'Conferir com IA';
        if (!j.ok) { estado(bloco, 'Não deu certo: ' + j.erro); return; }
        caixaConf.querySelector('.trad-conf-texto').textContent = j.texto;
        caixaConf.hidden = false;
        estado(bloco, 'Conferência pronta, logo abaixo.');
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
      salvarOriginal(bloco).then(function () {
        return chamar('retraduzir', { ordem: ordem, ajuste: campo.value });
      }).then(function (j) {
        b.disabled = false;
        b.textContent = 'Traduzir este trecho de novo';
        if (!j.ok) { estado(bloco, 'Não deu certo: ' + j.erro); return; }
        texto.value = j.texto;
        texto.removeAttribute('data-sujo');
        autoajustar(texto);
        marcarAprovado(bloco, false);
        campo.value = '';
        bloco.querySelector('.trad-novo').hidden = true;
        estadoOrig(bloco, '');
        estado(bloco, 'Trecho traduzido de novo. Leia e aprove.');
        revisarBloco(bloco);
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
    var tarefas = [];
    blocos.forEach(function (b) { tarefas.push(salvarTudo(b)); });
    Promise.all(tarefas).then(function () { return chamar('aprovar_todos', {}); }).then(function (j) {
      botaoTodos.disabled = false;
      botaoTodos.textContent = 'Aprovar todos os trechos';
      if (!j.ok) { erroGeral.textContent = j.erro; erroGeral.hidden = false; return; }
      blocos.forEach(function (b) { marcarAprovado(b, true); estado(b, ''); });
      atualizarContagem(j.aprovados, j.total);
    });
  });

  window.addEventListener('beforeunload', function (e) {
    if (salvando > 0 || raiz.querySelector('.trad-area[data-sujo="1"]')) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  raiz.querySelectorAll('.trad-area').forEach(autoajustar);
  raiz.querySelectorAll('.trad-bloco[data-feito="1"]').forEach(revisarBloco);
})();
