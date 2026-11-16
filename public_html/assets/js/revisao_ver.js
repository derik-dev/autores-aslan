// Revisão de textos: texto com as correções marcadas, barra lateral para navegar e balão que explica cada uma.
(function () {
  var app = document.getElementById('rv-app');
  if (!app) return;

  var csrf = app.getAttribute('data-csrf');
  var tradId = app.getAttribute('data-id');
  var dados = JSON.parse(document.getElementById('rv-dados').textContent);
  var TIPOS = window.REV_TIPOS || {};
  var $ = function (id) { return document.getElementById(id); };
  var doc = $('rv-doc'), areaTexto = $('rv-texto'), lista = $('rv-lista'), filtrosEl = $('rv-filtros');
  var balao = $('rv-balao'), erro = $('rv-erro');

  var alts = [];       // todas as correções, pela ordem em que aparecem no texto
  var marcas = {};     // id da correção -> botão no texto
  var itens = {};      // id da correção -> item da barra lateral
  var atual = null;    // correção aberta no balão
  var filtro = { tipo: '', pendentes: false };
  var ocupado = false;

  function chamar(acao, dados) {
    return fetch('traducao_api.php?acao=' + acao, {
      method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: new URLSearchParams(dados)
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

  // ---------- Construção do texto ----------
  function criarMarca(a) {
    var b = el('button', 'rv-marca');
    b.type = 'button';
    b.setAttribute('data-id', a.id);
    b.setAttribute('data-estado', a.e);
    b.setAttribute('aria-label', 'Correção ' + a.n + ': de «' + a.o + '» para «' + a.c + '»');
    if (a.o !== '') b.appendChild(el('del', '', a.o));
    if (a.c !== '') b.appendChild(el('ins', '', a.c));
    return b;
  }

  var n = 0;
  dados.trechos.forEach(function (t) {
    var bloco = el('div', 'rv-trecho');
    if (!t.feito) {
      bloco.classList.add('rv-por-rever');
      bloco.appendChild(el('p', 'rv-aviso-trecho', 'Este trecho ainda não foi revisto.'));
      bloco.appendChild(document.createTextNode(t.texto));
    } else {
      var cursor = 0;
      t.alt.forEach(function (a) {
        a.n = ++n;
        alts.push(a);
        if (a.pos > cursor) bloco.appendChild(document.createTextNode(t.texto.slice(cursor, a.pos)));
        var m = criarMarca(a);
        marcas[a.id] = m;
        bloco.appendChild(m);
        cursor = a.pos + a.tam;
      });
      if (cursor < t.texto.length) bloco.appendChild(document.createTextNode(t.texto.slice(cursor)));
    }
    areaTexto.appendChild(bloco);
  });

  // ---------- Contagens ----------
  function contar() {
    var c = { pendente: 0, aceite: 0, rejeitada: 0 };
    alts.forEach(function (a) { c[a.e]++; });
    return c;
  }
  function atualizarContagem() {
    var c = contar();
    var total = alts.length;
    $('rv-titulo-contagem').textContent = total === 0 ? 'Sem correções' : total + (total === 1 ? ' correção' : ' correções');
    $('rv-detalhe-contagem').textContent = total === 0
      ? 'O texto não precisou de alterações.'
      : c.pendente + ' por rever · ' + c.aceite + (c.aceite === 1 ? ' aceite' : ' aceites') + ' · ' + c.rejeitada + (c.rejeitada === 1 ? ' mantida como estava' : ' mantidas como estavam');
    $('rv-aceitar-todas').disabled = c.pendente === 0;
    filtrosEl.querySelectorAll('[data-chip-pendentes] .rv-chip-n').forEach(function (s) { s.textContent = c.pendente; });
  }

  // ---------- Barra lateral ----------
  function descricaoItem(a) {
    if (a.o === '') return 'inserir «' + curto(a.c, 28) + '»';
    if (a.c === '') return 'remover «' + curto(a.o, 28) + '»';
    return curto(a.o, 22) + ' → ' + curto(a.c, 22);
  }
  function criarItem(a) {
    var li = el('li');
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
    li.setAttribute('data-id', a.id);
    return li;
  }
  alts.forEach(function (a) {
    var li = criarItem(a);
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

  // Filtros por tipo e "por rever"
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

  // ---------- Atualização de uma correção ----------
  function atualizarMarca(a) {
    var m = marcas[a.id];
    if (!m) return;
    m.setAttribute('data-estado', a.e);
    var ins = m.querySelector('ins');
    if (ins) ins.textContent = a.c;
  }
  function aplicarContagemServidor(c) {
    if (!c) return;
    // O servidor é a fonte da verdade: confere com o que está no ecrã.
    var local = contar();
    if (local.pendente !== c.pendente || local.aceite !== c.aceite || local.rejeitada !== c.rejeitada) {
      mostrarErro('Os números do ecrã estavam diferentes dos guardados. Recarregue a página para ver o estado certo.');
    }
  }
  function acharAlt(id) { for (var i = 0; i < alts.length; i++) if (String(alts[i].id) === String(id)) return alts[i]; return null; }

  function decidir(a, estado) {
    if (ocupado) return;
    ocupado = true;
    erro.hidden = true;
    chamar('alteracao_estado', { alteracao_id: a.id, estado: estado }).then(function (j) {
      ocupado = false;
      if (!j.ok) { mostrarErro(j.erro); return; }
      a.e = estado;
      atualizarMarca(a);
      atualizarItem(a);
      atualizarContagem();
      aplicarContagemServidor(j.contagem);
      if (!proximaPendente(a, true)) { fechar(); }
    });
  }

  // ---------- Balão ----------
  function renderBalao(a) {
    balao.innerHTML = '';
    var topo = el('div', 'rv-b-topo');
    var t1 = el('span', 'rv-b-titulo');
    t1.appendChild(el('strong', '', 'Correção ' + a.n + ' de ' + alts.length));
    t1.appendChild(el('span', 'rv-b-tipo', rotuloTipo(a.k)));
    topo.appendChild(t1);
    var x = el('button', 'rv-b-x', '×');
    x.type = 'button';
    x.setAttribute('aria-label', 'Fechar');
    x.addEventListener('click', fechar);
    topo.appendChild(x);
    balao.appendChild(topo);

    var troca = el('div', 'rv-b-troca');
    var l1 = el('div');
    l1.appendChild(el('span', 'rv-b-rotulo', 'Estava'));
    l1.appendChild(a.o !== '' ? el('del', '', a.o) : el('span', 'texto-2', '(nada)'));
    var l2 = el('div');
    l2.appendChild(el('span', 'rv-b-rotulo', 'Proposta'));
    l2.appendChild(a.c !== '' ? el('ins', '', a.c) : el('span', 'texto-2', '(retirar)'));
    troca.appendChild(l1);
    troca.appendChild(l2);
    balao.appendChild(troca);

    var porque = el('p', 'rv-b-motivo');
    porque.appendChild(el('strong', '', 'Porquê: '));
    porque.appendChild(document.createTextNode(a.m || 'A forma foi ajustada à norma.'));
    balao.appendChild(porque);

    var estado = el('p', 'rv-b-estado meta');
    estado.textContent = a.e === 'aceite' ? 'Já aceite.' : (a.e === 'rejeitada' ? 'Mantida como estava.' : 'Por rever.');
    balao.appendChild(estado);

    var edicao = el('div', 'rv-b-edicao');
    edicao.hidden = true;
    var campo = el('textarea');
    campo.rows = 2;
    campo.value = a.c;
    campo.setAttribute('aria-label', 'Texto da correção');
    edicao.appendChild(campo);
    var ea = el('div', 'rv-b-botoes');
    var guardar = el('button', 'btn btn-aprovar btn-sm', 'Guardar');
    guardar.type = 'button';
    var cancelar = el('button', 'btn btn-sec btn-sm', 'Cancelar');
    cancelar.type = 'button';
    ea.appendChild(guardar);
    ea.appendChild(cancelar);
    edicao.appendChild(ea);
    balao.appendChild(edicao);

    var botoes = el('div', 'rv-b-botoes');
    var aceitar = el('button', 'btn btn-sm ' + (a.e === 'aceite' ? 'btn-sec' : 'btn-aprovar'), a.e === 'aceite' ? 'Aceite' : 'Aceitar');
    aceitar.type = 'button';
    aceitar.addEventListener('click', function () { if (a.e !== 'aceite') decidir(a, 'aceite'); else proximaPendente(a, true); });
    var manter = el('button', 'btn btn-sec btn-sm', a.e === 'rejeitada' ? 'Mantida' : 'Manter o original');
    manter.type = 'button';
    manter.addEventListener('click', function () { if (a.e !== 'rejeitada') decidir(a, 'rejeitada'); });
    var editar = el('button', 'btn btn-sec btn-sm', 'Editar');
    editar.type = 'button';
    editar.addEventListener('click', function () { edicao.hidden = false; botoes.hidden = true; campo.focus(); campo.select(); });
    botoes.appendChild(aceitar);
    botoes.appendChild(manter);
    botoes.appendChild(editar);
    balao.appendChild(botoes);

    cancelar.addEventListener('click', function () { edicao.hidden = true; botoes.hidden = false; campo.value = a.c; });
    guardar.addEventListener('click', function () {
      guardar.disabled = true;
      chamar('alteracao_editar', { alteracao_id: a.id, corrigido: campo.value }).then(function (j) {
        guardar.disabled = false;
        if (!j.ok) { mostrarErro(j.erro); return; }
        a.c = j.corrigido;
        a.e = 'aceite';
        atualizarMarca(a);
        atualizarItem(a);
        atualizarContagem();
        renderBalao(a);
        posicionar(marcas[a.id]);
      });
    });

    var nav = el('div', 'rv-b-nav');
    var ant = el('button', 'rv-b-seta', '‹ Anterior');
    ant.type = 'button';
    ant.addEventListener('click', function () { vizinha(a, -1); });
    var seg = el('button', 'rv-b-seta', 'Seguinte ›');
    seg.type = 'button';
    seg.addEventListener('click', function () { vizinha(a, 1); });
    nav.appendChild(ant);
    nav.appendChild(seg);
    balao.appendChild(nav);
  }

  function posicionar(marca) {
    if (!marca || balao.hidden) return;
    if (window.innerWidth <= 760) { balao.style.left = ''; balao.style.top = ''; return; }
    var r = marca.getBoundingClientRect();
    var p = app.getBoundingClientRect();
    var largura = balao.offsetWidth || 340;
    var altura = balao.offsetHeight || 220;
    var left = r.left - p.left;
    left = Math.max(0, Math.min(left, p.width - largura));
    var top = r.bottom - p.top + 8;
    if (r.bottom + altura + 16 > window.innerHeight && r.top > altura + 24) top = r.top - p.top - altura - 8;
    balao.style.left = left + 'px';
    balao.style.top = top + 'px';
  }

  function abrir(id, rolar) {
    var a = acharAlt(id);
    if (!a) return;
    if (atual && marcas[atual]) marcas[atual].classList.remove('rv-ativa');
    if (atual && itens[atual]) itens[atual].classList.remove('rv-ativa');
    atual = a.id;
    var m = marcas[a.id];
    m.classList.add('rv-ativa');
    itens[a.id].classList.add('rv-ativa');
    itens[a.id].scrollIntoView({ block: 'nearest' });
    balao.hidden = false;
    balao.style.visibility = 'hidden';
    renderBalao(a);
    if (rolar) m.scrollIntoView({ block: 'center', behavior: 'smooth' });
    setTimeout(function () { posicionar(m); balao.style.visibility = ''; }, rolar ? 320 : 0);
  }
  function fechar() {
    if (atual && marcas[atual]) marcas[atual].classList.remove('rv-ativa');
    if (atual && itens[atual]) itens[atual].classList.remove('rv-ativa');
    atual = null;
    balao.hidden = true;
  }
  function vizinha(a, passo) {
    var i = alts.indexOf(a);
    var j = (i + passo + alts.length) % alts.length;
    abrir(alts[j].id, true);
  }
  // Depois de decidir, segue para a próxima correção por rever (devolve false se já não houver)
  function proximaPendente(a, rolar) {
    var i = alts.indexOf(a);
    for (var k = 1; k <= alts.length; k++) {
      var b = alts[(i + k) % alts.length];
      if (b.e === 'pendente') { abrir(b.id, rolar); return true; }
    }
    return false;
  }

  // ---------- Eventos ----------
  areaTexto.addEventListener('click', function (ev) {
    var m = ev.target.closest('.rv-marca');
    if (!m) return;
    ev.stopPropagation();
    abrir(m.getAttribute('data-id'), false);
  });
  lista.addEventListener('click', function (ev) {
    var b = ev.target.closest('.rv-item');
    if (b) abrir(b.getAttribute('data-id'), true);
  });
  document.addEventListener('click', function (ev) {
    if (balao.hidden) return;
    if (balao.contains(ev.target) || ev.target.closest('.rv-marca') || ev.target.closest('.rv-item')) return;
    fechar();
  });
  document.addEventListener('keydown', function (ev) {
    if (balao.hidden) return;
    if (ev.key === 'Escape') { fechar(); return; }
    var tag = (document.activeElement && document.activeElement.tagName) || '';
    if (tag === 'TEXTAREA' || tag === 'INPUT') return;
    var a = acharAlt(atual);
    if (!a) return;
    if (ev.key === 'ArrowRight') { ev.preventDefault(); vizinha(a, 1); }
    if (ev.key === 'ArrowLeft') { ev.preventDefault(); vizinha(a, -1); }
  });
  window.addEventListener('resize', function () { if (atual) posicionar(marcas[atual]); });
  window.addEventListener('scroll', function () { if (atual) posicionar(marcas[atual]); }, { passive: true });

  // Como ver o texto
  document.querySelectorAll('[data-modo]').forEach(function (b) {
    b.addEventListener('click', function () {
      doc.setAttribute('data-modo', b.getAttribute('data-modo'));
      document.querySelectorAll('[data-modo].btn').forEach(function (x) { x.classList.toggle('ativo', x === b); });
      if (atual) setTimeout(function () { posicionar(marcas[atual]); }, 0);
    });
  });

  // Aceitar todas (com confirmação)
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
      alts.forEach(function (a) { if (a.e === 'pendente') { a.e = 'aceite'; atualizarMarca(a); atualizarItem(a); } });
      atualizarContagem();
      if (atual) renderBalao(acharAlt(atual));
    });
  });

  // Copiar o texto corrigido (com as decisões já tomadas)
  $('rv-copiar').addEventListener('click', function () {
    var b = $('rv-copiar');
    var partes = dados.trechos.map(function (t) {
      var saida = '', cursor = 0;
      (t.alt || []).forEach(function (a) {
        saida += t.texto.slice(cursor, a.pos) + (a.e === 'rejeitada' ? a.o : a.c);
        cursor = a.pos + a.tam;
      });
      return saida + t.texto.slice(cursor);
    });
    var texto = partes.join('\n\n');
    function feito() { b.textContent = 'Copiado'; setTimeout(function () { b.textContent = 'Copiar texto corrigido'; }, 2000); }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texto).then(feito, function () { window.prompt('Copie o texto:', texto); });
    } else {
      window.prompt('Copie o texto:', texto);
    }
  });

  atualizarContagem();
  aplicarFiltro();
})();
