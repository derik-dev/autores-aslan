// Revisão de textos, no estilo de uma pull request: linhas alteradas em diff,
// comentário da IA por baixo de cada alteração e decisão (aceitar / manter / editar) em cada uma.
(function () {
  var app = document.getElementById('rv-app');
  if (!app) return;

  var csrf = app.getAttribute('data-csrf');
  var tradId = app.getAttribute('data-id');
  var dados = JSON.parse(document.getElementById('rv-dados').textContent);
  var TIPOS = window.REV_TIPOS || {};
  var $ = function (id) { return document.getElementById(id); };
  var diff = $('pr-diff'), lista = $('rv-lista'), filtrosEl = $('rv-filtros'), erro = $('rv-erro');

  var alts = [];        // todas as correções, pela ordem do texto
  var linhas = [];      // linhas do texto (com as suas correções)
  var itens = {};       // id da correção -> item da barra lateral
  var cartoes = {};     // id da correção -> cartão de comentário
  var filtro = { tipo: '', pendentes: false };
  var ocupado = false;
  var CONTEXTO = 2;     // linhas sem alterações mostradas à volta de cada alteração

  function chamar(acao, d) {
    return fetch('traducao_api.php?acao=' + acao, {
      method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: new URLSearchParams(d)
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inesperada do servidor (código ' + r.status + ').' }; }); })
      .catch(function () { return { ok: false, erro: 'Sem ligação ao servidor. Verifique a internet e tente de novo.' }; });
  }
  function el(tag, cls, texto) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (texto !== undefined && texto !== null) e.textContent = texto;
    return e;
  }
  function mostrarErro(msg) { erro.textContent = msg; erro.hidden = false; }
  function curto(s, n) { s = String(s).replace(/\s+/g, ' ').trim(); return s.length > n ? s.slice(0, n - 1) + '…' : s; }
  function rotuloTipo(k) { return TIPOS[k] || 'Outra'; }
  function acharAlt(id) { for (var i = 0; i < alts.length; i++) if (String(alts[i].id) === String(id)) return alts[i]; return null; }

  // ---------- Dividir o texto em linhas e ligar cada correção à sua linha ----------
  var numLinha = 0, numAlt = 0;
  dados.trechos.forEach(function (t) {
    var partes = t.texto.split('\n');
    var ini = 0;
    var doTrecho = [];
    partes.forEach(function (p) {
      var l = { n: ++numLinha, ini: ini, fim: ini + p.length, texto: p, alts: [], porRever: !t.feito };
      doTrecho.push(l);
      linhas.push(l);
      ini += p.length + 1;
    });
    if (t.feito) {
      t.alt.forEach(function (a) {
        a.n = ++numAlt;
        alts.push(a);
        for (var i = 0; i < doTrecho.length; i++) {
          if (a.pos <= doTrecho[i].fim) { doTrecho[i].alts.push(a); break; }
        }
      });
    }
  });

  // ---------- Contagens ----------
  function contar() {
    var c = { pendente: 0, aceite: 0, rejeitada: 0 };
    alts.forEach(function (a) { c[a.e]++; });
    return c;
  }
  function atualizarContagem() {
    var c = contar(), total = alts.length, resolvidas = c.aceite + c.rejeitada;
    $('rv-titulo-contagem').textContent = String(total);
    $('rv-detalhe-contagem').textContent = total === 0
      ? 'O texto não precisou de alterações.'
      : resolvidas + ' de ' + total + ' correções resolvidas · ' + c.aceite + (c.aceite === 1 ? ' aceite' : ' aceites') + ' · ' + c.rejeitada + (c.rejeitada === 1 ? ' mantida como estava' : ' mantidas como estavam') + ' · ' + c.pendente + ' por rever';
    $('pr-barra').style.width = (total ? Math.round(resolvidas / total * 100) : 100) + '%';
    var est = $('pr-estado');
    var feita = c.pendente === 0;
    est.textContent = feita ? 'Revisão concluída' : 'Em revisão';
    est.className = 'chip ' + (feita ? 'chip-ok' : 'chip-neutro');
    $('rv-aceitar-todas').disabled = c.pendente === 0;
    filtrosEl.querySelectorAll('[data-chip-pendentes] .rv-chip-n').forEach(function (s) { s.textContent = c.pendente; });
  }

  // ---------- Linhas de diff ----------
  function textoLinha(l, lado) {
    // lado: 'antes' mostra o que estava escrito; 'depois' mostra o resultado das decisões
    var frag = document.createDocumentFragment();
    var cursor = l.ini;
    l.alts.forEach(function (a) {
      if (a.pos > cursor) frag.appendChild(document.createTextNode(l.texto.slice(cursor - l.ini, a.pos - l.ini)));
      var fimA = Math.min(a.pos + a.tam, l.fim);
      var conteudo, marcar = true;
      if (lado === 'antes') {
        conteudo = a.o.split('\n')[0];
        if (conteudo === '') marcar = false;
      } else if (a.e === 'rejeitada') {
        conteudo = a.o.split('\n')[0];
        marcar = false;
      } else {
        conteudo = a.c;
        if (conteudo === '') marcar = false;
      }
      if (conteudo !== '') {
        if (marcar) {
          var b = el('button', 'pr-marca ' + (lado === 'antes' ? 'pr-del' : 'pr-ins'), conteudo);
          b.type = 'button';
          b.setAttribute('data-id', a.id);
          b.setAttribute('aria-label', 'Ir ao comentário da correção ' + a.n);
          frag.appendChild(b);
        } else {
          frag.appendChild(document.createTextNode(conteudo));
        }
      }
      cursor = Math.max(cursor, fimA);
    });
    if (cursor < l.fim) frag.appendChild(document.createTextNode(l.texto.slice(cursor - l.ini)));
    if (!frag.childNodes.length) frag.appendChild(document.createTextNode(' '));
    return frag;
  }
  function criarLinha(n, sinal, cls) {
    var r = el('div', 'pr-linha ' + cls);
    r.appendChild(el('span', 'pr-num', sinal === '+' ? '' : String(n)));
    r.appendChild(el('span', 'pr-sinal', sinal === '=' ? '' : (sinal === '-' ? '−' : '+')));
    var c = el('div', 'pr-texto');
    r.appendChild(c);
    return { linha: r, texto: c };
  }

  // Cartão de comentário da IA com as decisões
  function criarCartao(a) {
    var c = el('article', 'pr-cartao');
    c.setAttribute('data-id', a.id);
    c.setAttribute('data-estado', a.e);
    var topo = el('div', 'pr-c-topo');
    topo.appendChild(el('span', 'pr-ia', 'IA'));
    topo.appendChild(el('strong', '', 'Correção ' + a.n));
    topo.appendChild(el('span', 'pr-c-tipo', rotuloTipo(a.k)));
    var est = el('span', 'pr-c-estado', a.e === 'aceite' ? 'Aceite' : (a.e === 'rejeitada' ? 'Mantida como estava' : 'Por rever'));
    topo.appendChild(est);
    c.appendChild(topo);

    var corpo = el('div', 'pr-c-corpo');
    var motivo = el('p', 'pr-c-motivo');
    motivo.appendChild(document.createTextNode(a.m || 'A forma foi ajustada à norma.'));
    corpo.appendChild(motivo);

    var sug = el('div', 'pr-sugestao');
    var s1 = el('div', 'pr-sug-linha pr-sug-del');
    s1.appendChild(el('span', 'pr-sinal', '−'));
    s1.appendChild(el('span', '', a.o !== '' ? a.o : '(nada)'));
    var s2 = el('div', 'pr-sug-linha pr-sug-ins');
    s2.appendChild(el('span', 'pr-sinal', '+'));
    s2.appendChild(el('span', '', a.c !== '' ? a.c : '(retirar)'));
    sug.appendChild(s1);
    sug.appendChild(s2);
    corpo.appendChild(sug);

    var edicao = el('div', 'pr-edicao');
    edicao.hidden = true;
    var campo = el('textarea');
    campo.rows = 2;
    campo.value = a.c;
    campo.setAttribute('aria-label', 'Texto da correção');
    edicao.appendChild(campo);
    var ed = el('div', 'rv-b-botoes');
    var guardar = el('button', 'btn btn-aprovar btn-sm', 'Guardar e aceitar');
    guardar.type = 'button';
    var cancelar = el('button', 'btn btn-sec btn-sm', 'Cancelar');
    cancelar.type = 'button';
    ed.appendChild(guardar);
    ed.appendChild(cancelar);
    edicao.appendChild(ed);
    corpo.appendChild(edicao);

    var botoes = el('div', 'rv-b-botoes');
    var aceitar = el('button', 'btn btn-sm ' + (a.e === 'aceite' ? 'btn-sec' : 'btn-aprovar'), a.e === 'aceite' ? 'Aceite ✓' : 'Aceitar');
    aceitar.type = 'button';
    aceitar.disabled = a.e === 'aceite';
    aceitar.addEventListener('click', function () { decidir(a, 'aceite'); });
    var manter = el('button', 'btn btn-sec btn-sm', a.e === 'rejeitada' ? 'Mantida ✓' : 'Manter o original');
    manter.type = 'button';
    manter.disabled = a.e === 'rejeitada';
    manter.addEventListener('click', function () { decidir(a, 'rejeitada'); });
    var editar = el('button', 'btn btn-sec btn-sm', 'Editar');
    editar.type = 'button';
    editar.addEventListener('click', function () { edicao.hidden = false; botoes.hidden = true; campo.focus(); campo.select(); });
    botoes.appendChild(aceitar);
    botoes.appendChild(manter);
    botoes.appendChild(editar);
    if (a.e !== 'pendente') {
      var desfazer = el('button', 'pr-desfazer', 'Voltar a por rever');
      desfazer.type = 'button';
      desfazer.addEventListener('click', function () { decidir(a, 'pendente'); });
      botoes.appendChild(desfazer);
    }
    corpo.appendChild(botoes);
    c.appendChild(corpo);

    cancelar.addEventListener('click', function () { edicao.hidden = true; botoes.hidden = false; campo.value = a.c; });
    guardar.addEventListener('click', function () {
      guardar.disabled = true;
      chamar('alteracao_editar', { alteracao_id: a.id, corrigido: campo.value }).then(function (j) {
        guardar.disabled = false;
        if (!j.ok) { mostrarErro(j.erro); return; }
        a.c = j.corrigido;
        a.e = 'aceite';
        redesenhar(a);
      });
    });
    return c;
  }

  // Um "grupo" é uma linha alterada: as duas linhas do diff e os comentários por baixo
  function criarGrupo(l) {
    var g = el('div', 'pr-grupo');
    g.setAttribute('data-linha', l.n);
    var antes = criarLinha(l.n, '-', 'pr-l-del');
    antes.texto.appendChild(textoLinha(l, 'antes'));
    var depois = criarLinha(l.n, '+', 'pr-l-ins');
    depois.texto.appendChild(textoLinha(l, 'depois'));
    g.appendChild(antes.linha);
    g.appendChild(depois.linha);
    var fio = el('div', 'pr-fio');
    l.alts.forEach(function (a) {
      var c = criarCartao(a);
      cartoes[a.id] = c;
      fio.appendChild(c);
    });
    g.appendChild(fio);
    l.grupo = g;
    return g;
  }
  function redesenhar(a) {
    var l = linhas.filter(function (x) { return x.alts.indexOf(a) >= 0; })[0];
    if (l && l.grupo) {
      var novo = criarGrupo(l);
      l.grupo.replaceWith(novo);
      l.grupo = novo;
    }
    atualizarItem(a);
    atualizarContagem();
    aplicarFiltro();
    prepararFinal();
  }
  function decidir(a, estado) {
    if (ocupado) return;
    ocupado = true;
    erro.hidden = true;
    chamar('alteracao_estado', { alteracao_id: a.id, estado: estado }).then(function (j) {
      ocupado = false;
      if (!j.ok) { mostrarErro(j.erro); return; }
      a.e = estado;
      redesenhar(a);
    });
  }

  // ---------- Construção do diff com linhas de contexto recolhidas ----------
  function construir() {
    diff.innerHTML = '';
    if (!alts.length) {
      var v = el('div', 'vazio');
      v.appendChild(el('strong', '', 'Sem correções'));
      v.appendChild(document.createTextNode('A IA não encontrou nada para corrigir neste texto.'));
      diff.appendChild(v);
      return;
    }
    // índices das linhas visíveis: as alteradas e CONTEXTO linhas à volta
    var visivel = new Array(linhas.length);
    linhas.forEach(function (l, i) {
      if (l.alts.length) {
        for (var k = Math.max(0, i - CONTEXTO); k <= Math.min(linhas.length - 1, i + CONTEXTO); k++) visivel[k] = true;
      }
    });
    var i = 0;
    while (i < linhas.length) {
      if (linhas[i].alts.length) {
        diff.appendChild(criarGrupo(linhas[i]));
        i++;
      } else if (visivel[i]) {
        diff.appendChild(linhaContexto(linhas[i]));
        i++;
      } else {
        var ini = i;
        while (i < linhas.length && !visivel[i]) i++;
        diff.appendChild(blocoOculto(ini, i));
      }
    }
  }
  function linhaContexto(l) {
    var r = criarLinha(l.n, '=', 'pr-l-ctx');
    r.texto.textContent = l.texto === '' ? ' ' : l.texto;
    return r.linha;
  }
  function blocoOculto(de, ate) {
    var n = ate - de;
    var caixa = el('div', 'pr-oculto');
    var b = el('button', 'pr-expandir', 'Mostrar ' + n + (n === 1 ? ' linha sem alterações' : ' linhas sem alterações'));
    b.type = 'button';
    var conteudo = el('div', 'pr-oculto-linhas');
    conteudo.hidden = true;
    for (var k = de; k < ate; k++) conteudo.appendChild(linhaContexto(linhas[k]));
    b.addEventListener('click', function () {
      conteudo.hidden = !conteudo.hidden;
      b.textContent = conteudo.hidden ? 'Mostrar ' + n + (n === 1 ? ' linha sem alterações' : ' linhas sem alterações') : 'Esconder as linhas sem alterações';
    });
    caixa.appendChild(b);
    caixa.appendChild(conteudo);
    return caixa;
  }

  // ---------- Barra lateral ----------
  function descricaoItem(a) {
    if (a.o === '') return 'inserir «' + curto(a.c, 28) + '»';
    if (a.c === '') return 'remover «' + curto(a.o, 28) + '»';
    return curto(a.o, 22) + ' → ' + curto(a.c, 22);
  }
  alts.forEach(function (a) {
    var li = el('li');
    li.setAttribute('data-id', a.id);
    var b = el('button', 'rv-item');
    b.type = 'button';
    b.setAttribute('data-id', a.id);
    b.appendChild(el('span', 'rv-n', String(a.n)));
    var corpo = el('span', 'rv-corpo');
    corpo.appendChild(el('span', 'rv-troca', descricaoItem(a)));
    corpo.appendChild(el('span', 'rv-tipo', rotuloTipo(a.k)));
    b.appendChild(corpo);
    var est = el('span', 'rv-estado');
    est.setAttribute('data-estado', a.e);
    b.appendChild(est);
    li.appendChild(b);
    itens[a.id] = li;
    lista.appendChild(li);
  });
  if (!alts.length) lista.appendChild(el('li', 'rv-vazio texto-2', 'Não há correções para mostrar.'));
  function atualizarItem(a) {
    var li = itens[a.id];
    if (!li) return;
    li.querySelector('.rv-estado').setAttribute('data-estado', a.e);
    li.querySelector('.rv-troca').textContent = descricaoItem(a);
  }

  var tiposPresentes = {};
  alts.forEach(function (a) { tiposPresentes[a.k] = (tiposPresentes[a.k] || 0) + 1; });
  function chip(rotulo, contagem, aoClicar, extra) {
    var b = el('button', 'rv-chip');
    b.type = 'button';
    if (extra) b.setAttribute(extra, '1');
    b.appendChild(document.createTextNode(rotulo + ' '));
    b.appendChild(el('span', 'rv-chip-n', String(contagem)));
    b.addEventListener('click', aoClicar);
    return b;
  }
  if (alts.length) {
    filtrosEl.appendChild(chip('Todas', alts.length, function () { filtro.tipo = ''; filtro.pendentes = false; aplicarFiltro(); }));
    filtrosEl.appendChild(chip('Por rever', contar().pendente, function () { filtro.pendentes = !filtro.pendentes; aplicarFiltro(); }, 'data-chip-pendentes'));
    Object.keys(tiposPresentes).forEach(function (k) {
      var c = chip(rotuloTipo(k), tiposPresentes[k], function () { filtro.tipo = filtro.tipo === k ? '' : k; aplicarFiltro(); });
      c.setAttribute('data-tipo', k);
      filtrosEl.appendChild(c);
    });
  }
  function aplicarFiltro() {
    alts.forEach(function (a) {
      var ok = (!filtro.tipo || a.k === filtro.tipo) && (!filtro.pendentes || a.e === 'pendente');
      itens[a.id].hidden = !ok;
    });
    filtrosEl.querySelectorAll('.rv-chip').forEach(function (c) {
      var t = c.getAttribute('data-tipo');
      var ativo = t ? filtro.tipo === t : (c.hasAttribute('data-chip-pendentes') ? filtro.pendentes : (!filtro.tipo && !filtro.pendentes));
      c.classList.toggle('ativo', ativo);
    });
  }

  // ---------- Ir para um comentário ----------
  var piscar = null;
  function irPara(id) {
    var c = cartoes[id];
    if (!c) return;
    // se estiver dentro de uma vista escondida, mostra o diff
    mudarVista('alteracoes');
    c.scrollIntoView({ block: 'center', behavior: 'smooth' });
    document.querySelectorAll('.pr-cartao.pr-ativo').forEach(function (x) { x.classList.remove('pr-ativo'); });
    document.querySelectorAll('.rv-item.rv-ativa').forEach(function (x) { x.classList.remove('rv-ativa'); });
    c.classList.add('pr-ativo');
    if (itens[id]) itens[id].querySelector('.rv-item').classList.add('rv-ativa');
    clearTimeout(piscar);
    piscar = setTimeout(function () { c.classList.remove('pr-ativo'); }, 2200);
  }
  diff.addEventListener('click', function (ev) {
    var m = ev.target.closest('.pr-marca');
    if (m) irPara(m.getAttribute('data-id'));
  });
  lista.addEventListener('click', function (ev) {
    var b = ev.target.closest('.rv-item');
    if (b) irPara(b.getAttribute('data-id'));
  });

  // ---------- Vistas: Alterações / Texto final / Original ----------
  function textoFinalDe(t) {
    var saida = '', cursor = 0;
    (t.alt || []).forEach(function (a) {
      saida += t.texto.slice(cursor, a.pos) + (a.e === 'rejeitada' ? a.o : a.c);
      cursor = a.pos + a.tam;
    });
    return saida + t.texto.slice(cursor);
  }
  function prepararFinal() {
    $('pr-final').textContent = dados.trechos.map(textoFinalDe).join('\n\n');
    var c = contar();
    $('pr-fim-resumo').textContent = c.pendente > 0
      ? 'Ainda há ' + c.pendente + (c.pendente === 1 ? ' correção por rever; conta' : ' correções por rever; contam') + ' como aceites neste texto.'
      : 'Todas as correções foram resolvidas. Este é o texto que vai para o ficheiro e para o áudio.';
  }
  $('pr-original').textContent = dados.trechos.map(function (t) { return t.texto; }).join('\n\n');

  function mudarVista(v) {
    ['alteracoes', 'final', 'original'].forEach(function (x) { $('pr-vista-' + x).hidden = x !== v; });
    document.querySelectorAll('.pr-aba').forEach(function (b) {
      var ativo = b.getAttribute('data-vista') === v;
      b.classList.toggle('ativo', ativo);
      b.setAttribute('aria-selected', ativo ? 'true' : 'false');
    });
    if (v === 'final') prepararFinal();
  }
  document.querySelectorAll('.pr-aba').forEach(function (b) {
    b.addEventListener('click', function () { mudarVista(b.getAttribute('data-vista')); });
  });
  $('pr-concluir').addEventListener('click', function () {
    mudarVista('final');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // ---------- Aceitar todas (com confirmação) ----------
  var todas = $('rv-aceitar-todas');
  todas.addEventListener('click', function () {
    if (todas.getAttribute('data-confirma') !== '1') {
      todas.setAttribute('data-confirma', '1');
      todas.textContent = 'Confirmar: aceitar todas';
      setTimeout(function () { todas.removeAttribute('data-confirma'); todas.textContent = 'Aceitar todas'; }, 4000);
      return;
    }
    todas.removeAttribute('data-confirma');
    todas.disabled = true;
    todas.textContent = 'A aceitar…';
    chamar('alteracoes_aceitar', { id: tradId }).then(function (j) {
      todas.textContent = 'Aceitar todas';
      if (!j.ok) { todas.disabled = false; mostrarErro(j.erro); return; }
      alts.forEach(function (a) { if (a.e === 'pendente') a.e = 'aceite'; atualizarItem(a); });
      construir();
      atualizarContagem();
      prepararFinal();
    });
  });

  // ---------- Copiar ----------
  $('rv-copiar').addEventListener('click', function () {
    var b = $('rv-copiar');
    var texto = dados.trechos.map(textoFinalDe).join('\n\n');
    function feito() { b.textContent = 'Copiado'; setTimeout(function () { b.textContent = 'Copiar texto corrigido'; }, 2000); }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texto).then(feito, function () { window.prompt('Copie o texto:', texto); });
    } else {
      window.prompt('Copie o texto:', texto);
    }
  });

  construir();
  atualizarContagem();
  aplicarFiltro();
  prepararFinal();
})();
