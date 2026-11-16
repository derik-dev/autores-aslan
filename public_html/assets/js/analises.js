// Tela Análises: conversa com o analista e relatório estruturado.
(function () {
  var app = document.getElementById('an-app');
  if (!app) return;

  var csrf = app.getAttribute('data-csrf');
  var modo = app.getAttribute('data-modo');
  var editora = app.getAttribute('data-editora') === '1';
  var conversaId = parseInt(app.getAttribute('data-conversa') || '0', 10);
  var $ = function (id) { return document.getElementById(id); };

  function chamar(acao, dados) {
    return fetch('analises_api.php?acao=' + acao, {
      method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: new URLSearchParams(dados || {})
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, erro: 'Resposta inesperada do servidor (código ' + r.status + ').' }; }); })
      .catch(function () { return { ok: false, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' }; });
  }

  // ---------- Markdown simples e seguro (negrito, itálico, listas, títulos, tabelas) ----------
  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function inline(s) {
    s = esc(s);
    s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/(^|[^*\w])\*(?!\s)([^*]+?)\*(?![*\w])/g, '$1<em>$2</em>');
    s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
    return s;
  }
  function celulas(linha) {
    return linha.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map(function (c) { return c.trim(); });
  }
  function md(texto) {
    var linhas = String(texto).replace(/\r/g, '').split('\n');
    var html = '';
    var i = 0;
    while (i < linhas.length) {
      var l = linhas[i];
      if (/^\s*$/.test(l)) { i++; continue; }

      if (/^\s*\|.*\|\s*$/.test(l) && i + 1 < linhas.length && /^\s*\|?\s*:?-{2,}/.test(linhas[i + 1])) {
        var cab = celulas(l);
        i += 2;
        html += '<div class="an-tabela"><table><thead><tr>' + cab.map(function (c) { return '<th>' + inline(c) + '</th>'; }).join('') + '</tr></thead><tbody>';
        while (i < linhas.length && /^\s*\|.*\|\s*$/.test(linhas[i])) {
          html += '<tr>' + celulas(linhas[i]).map(function (c) { return '<td>' + inline(c) + '</td>'; }).join('') + '</tr>';
          i++;
        }
        html += '</tbody></table></div>';
        continue;
      }

      var t = l.match(/^#{1,4}\s+(.*)$/);
      if (t) { html += '<h4>' + inline(t[1]) + '</h4>'; i++; continue; }

      if (/^\s*[-*•]\s+/.test(l)) {
        html += '<ul>';
        while (i < linhas.length && /^\s*[-*•]\s+/.test(linhas[i])) {
          html += '<li>' + inline(linhas[i].replace(/^\s*[-*•]\s+/, '')) + '</li>';
          i++;
        }
        html += '</ul>';
        continue;
      }

      if (/^\s*\d+[.)]\s+/.test(l)) {
        html += '<ol>';
        while (i < linhas.length && /^\s*\d+[.)]\s+/.test(linhas[i])) {
          html += '<li>' + inline(linhas[i].replace(/^\s*\d+[.)]\s+/, '')) + '</li>';
          i++;
        }
        html += '</ol>';
        continue;
      }

      var par = [];
      while (i < linhas.length && !/^\s*$/.test(linhas[i]) && !/^\s*[-*•]\s+/.test(linhas[i]) && !/^\s*\d+[.)]\s+/.test(linhas[i]) && !/^#{1,4}\s+/.test(linhas[i]) && !/^\s*\|.*\|\s*$/.test(linhas[i])) {
        par.push(linhas[i]);
        i++;
      }
      if (!par.length) { par.push(linhas[i]); i++; }
      html += '<p>' + par.map(inline).join('<br>') + '</p>';
    }
    return html;
  }

  // =====================================================================
  // CONVERSA
  // =====================================================================
  var thread = $('an-thread');
  if (thread) {
    var form = $('an-form'), caixa = $('an-pergunta'), enviar = $('an-enviar'), erroChat = $('an-chat-erro');
    var ocupado = false;

    document.querySelectorAll('[data-md]').forEach(function (el) { el.innerHTML = md(el.textContent); });
    function rolar() { thread.scrollTop = thread.scrollHeight; }
    rolar();

    function mensagem(papel, html, ehHtml) {
      var vazio = $('an-vazio');
      if (vazio) vazio.remove();
      var m = document.createElement('div');
      m.className = 'an-msg ' + (papel === 'user' ? 'an-msg-user' : 'an-msg-ia');
      var p = document.createElement('div');
      p.className = 'an-msg-papel';
      p.textContent = papel === 'user' ? 'Você' : 'Analista';
      var t = document.createElement('div');
      t.className = 'an-msg-texto';
      if (ehHtml) t.innerHTML = html; else t.textContent = html;
      m.appendChild(p);
      m.appendChild(t);
      thread.appendChild(m);
      rolar();
      return m;
    }

    function ajustarCaixa() {
      caixa.style.height = 'auto';
      caixa.style.height = Math.min(caixa.scrollHeight + 2, 160) + 'px';
    }

    function perguntar(texto) {
      texto = texto.trim();
      if (!texto || ocupado) return;
      ocupado = true;
      erroChat.hidden = true;
      enviar.disabled = true;
      enviar.textContent = 'Analisando…';
      var minha = mensagem('user', texto, false);
      var resposta = mensagem('ia', '<span class="an-digitando">Analisando os números<i></i><i></i><i></i></span>', true);
      caixa.value = '';
      ajustarCaixa();

      var autor = $('an-autor');
      chamar('chat_enviar', { conversa_id: conversaId, mensagem: texto, autor: autor ? autor.value : 0 }).then(function (j) {
        ocupado = false;
        enviar.disabled = false;
        enviar.textContent = 'Enviar pergunta';
        if (!j.ok) {
          minha.remove();
          resposta.remove();
          caixa.value = texto;
          ajustarCaixa();
          erroChat.textContent = j.erro;
          erroChat.hidden = false;
          return;
        }
        resposta.querySelector('.an-msg-texto').innerHTML = md(j.resposta);
        if (!conversaId) {
          conversaId = j.conversa_id;
          if (autor) autor.disabled = true;
          try { history.replaceState(null, '', 'analises.php?modo=conversa&c=' + conversaId); } catch (e) {}
        }
        var titulo = $('an-chat-titulo');
        if (titulo && j.titulo) titulo.textContent = j.titulo;
        rolar();
        caixa.focus();
      });
    }

    form.addEventListener('submit', function (ev) { ev.preventDefault(); perguntar(caixa.value); });
    caixa.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) { ev.preventDefault(); perguntar(caixa.value); }
    });
    caixa.addEventListener('input', ajustarCaixa);
    document.querySelectorAll('.an-sugestao').forEach(function (b) {
      b.addEventListener('click', function () { perguntar(b.getAttribute('data-pergunta')); });
    });

    var excluir = $('an-excluir-conversa');
    if (excluir) {
      excluir.addEventListener('click', function () {
        if (excluir.getAttribute('data-confirma') !== '1') {
          excluir.setAttribute('data-confirma', '1');
          excluir.textContent = 'Confirmar exclusão';
          setTimeout(function () { excluir.removeAttribute('data-confirma'); excluir.textContent = 'Excluir conversa'; }, 4000);
          return;
        }
        excluir.disabled = true;
        chamar('chat_excluir', { id: conversaId }).then(function (j) {
          if (j.ok) window.location.href = 'analises.php?modo=conversa';
          else { excluir.disabled = false; excluir.textContent = 'Excluir conversa'; erroChat.textContent = j.erro; erroChat.hidden = false; }
        });
      });
    }
  }

  // =====================================================================
  // RELATÓRIO
  // =====================================================================
  var view = $('an-relatorio-view');
  if (view) {
    var gerar = $('an-gerar'), estado = $('an-rel-estado'), erroRel = $('an-rel-erro');

    var NIVEL = { alta: 'Alta', media: 'Média', baixa: 'Baixa', alto: 'Alto', medio: 'Médio', baixo: 'Baixo' };
    function brl(n) { return 'R$ ' + Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function num(n) { return Number(n || 0).toLocaleString('pt-BR'); }
    function pct(n) { return (n > 0 ? '+' : '') + Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%'; }
    function mesRot(m) { var p = String(m).split('-'); return p[1] + '/' + p[0]; }
    function proximoMes(m, k) { var p = m.split('-'); var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1 + k, 1); return mesRot(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')); }

    function el(tag, cls, texto) {
      var e = document.createElement(tag);
      if (cls) e.className = cls;
      if (texto !== undefined && texto !== null) e.textContent = texto;
      return e;
    }
    function painel(titulo, cls) {
      var p = el('section', 'bloco ' + (cls || ''));
      p.appendChild(el('h2', 'bloco-titulo', titulo));
      return p;
    }
    function chip(texto, tipo) { return el('span', 'chip ' + (tipo || 'chip-neutro'), texto); }
    function variacaoEl(v) {
      var s = el('span', v === null || v === undefined ? 'meta' : (v >= 0 ? 'status-sucesso' : 'status-erro'), v === null || v === undefined ? '—' : pct(v));
      s.style.fontWeight = '600';
      return s;
    }

    function renderRelatorio(r, id) {
      var m = r.meta || {}, f = r.fatos || {}, ia = r.ia || {};
      var t = f.totais || {};
      view.innerHTML = '';
      view.setAttribute('data-id', id || '');
      var grade = el('div', 'an-rel');

      // Cabeçalho
      var cab = el('header', 'an-rel-cabeca todas');
      var tit = el('div');
      tit.appendChild(el('h2', 'an-rel-titulo', ia.titulo || 'Análise de vendas'));
      tit.appendChild(el('p', 'meta', (m.foco_rotulo || '') + ' · ' + (m.periodo_rotulo || '') + ' · ' + (m.escopo || '') + ' · gerado em ' + (m.gerado_em || '')));
      cab.appendChild(tit);
      var acoes = el('div', 'linha-acoes');
      var perguntar = el('button', 'btn btn-sec btn-sm', 'Perguntar sobre este relatório');
      perguntar.type = 'button';
      perguntar.addEventListener('click', function () {
        perguntar.disabled = true;
        chamar('chat_novo', { relatorio_id: id, autor: m.autor_id || 0 }).then(function (j) {
          if (j.ok) window.location.href = 'analises.php?modo=conversa&c=' + j.id;
          else { perguntar.disabled = false; erroRel.textContent = j.erro; erroRel.hidden = false; }
        });
      });
      var imprimir = el('button', 'btn btn-sec btn-sm', 'Imprimir');
      imprimir.type = 'button';
      imprimir.addEventListener('click', function () { window.print(); });
      var apagar = el('button', 'btn btn-sec btn-sm', 'Excluir relatório');
      apagar.type = 'button';
      apagar.addEventListener('click', function () {
        if (apagar.getAttribute('data-confirma') !== '1') {
          apagar.setAttribute('data-confirma', '1');
          apagar.textContent = 'Confirmar exclusão';
          setTimeout(function () { apagar.removeAttribute('data-confirma'); apagar.textContent = 'Excluir relatório'; }, 4000);
          return;
        }
        chamar('excluir_relatorio', { id: id }).then(function (j) {
          if (j.ok) window.location.href = 'analises.php?modo=relatorio';
        });
      });
      if (id) acoes.appendChild(perguntar);
      acoes.appendChild(imprimir);
      if (id) acoes.appendChild(apagar);
      cab.appendChild(acoes);
      grade.appendChild(cab);

      // Resumo executivo
      var pRes = painel('Resumo executivo', 'todas');
      pRes.appendChild(el('p', 'an-resumo', ia.resumo || ''));
      grade.appendChild(pRes);

      // Números principais: os três do primeiro nível, do mesmo tamanho
      var nums = el('div', 'numeros todas');
      [[brl(t.receita_autor), 'receita do autor'], [brl(t.vendas_brutas), 'vendas brutas'], [num(t.exemplares), 'exemplares vendidos']].forEach(function (x) {
        var d = el('div');
        d.appendChild(el('strong', '', x[0]));
        d.appendChild(el('span', '', x[1]));
        nums.appendChild(d);
      });
      grade.appendChild(nums);

      // Receita por mês + tendência e projeção
      var meses = f.mensal || [];
      var pMes = painel('Receita por mês', 'col-7');
      var max = 0;
      meses.forEach(function (x) { if (x.receita_autor > max) max = x.receita_autor; });
      var ul = el('ul', 'lista-barras');
      meses.forEach(function (x) {
        var li = el('li');
        li.appendChild(el('span', 'barra-rotulo', mesRot(x.mes)));
        var bar = el('span', 'barra-h');
        var i = el('i');
        i.style.width = (max > 0 ? Math.max(2, Math.round(x.receita_autor / max * 100)) : 0) + '%';
        bar.appendChild(i);
        li.appendChild(bar);
        var val = el('span', 'barra-valor');
        val.appendChild(document.createTextNode(brl(x.receita_autor) + ' · ' + num(x.exemplares) + ' un. '));
        if (x.variacao_mes_pct !== null && x.variacao_mes_pct !== undefined) val.appendChild(variacaoEl(x.variacao_mes_pct));
        li.appendChild(val);
        ul.appendChild(li);
      });
      pMes.appendChild(ul);
      if (f.periodo && f.periodo.mes_parcial_fora_das_comparacoes) {
        pMes.appendChild(el('p', 'meta an-nota', mesRot(f.periodo.mes_parcial_fora_das_comparacoes) + ' ainda não terminou e não entra nas comparações.'));
      }
      grade.appendChild(pMes);

      var pTen = painel('Tendência e projeção', 'col-5');
      var mini = el('div', 'mini-numeros');
      function par(valor, legenda, nodo) {
        var d = el('div');
        var s = nodo || el('strong', '', valor);
        d.appendChild(s);
        d.appendChild(el('span', '', legenda));
        mini.appendChild(d);
      }
      var v3 = f.variacao_3m_contra_3m_anteriores;
      if (v3 && v3.variacao_pct !== null) {
        var sv = el('strong', v3.variacao_pct >= 0 ? 'status-sucesso' : 'status-erro', pct(v3.variacao_pct));
        sv.style.fontWeight = '600';
        par('', 'últimos 3 meses contra os 3 anteriores', sv);
      } else {
        par('—', 'comparação de 3 meses exige 6 meses completos');
      }
      par(brl(f.media_mensal_receita_autor), 'receita média por mês');
      if (f.melhor_mes) par(mesRot(f.melhor_mes.mes), 'melhor mês · ' + brl(f.melhor_mes.receita_autor));
      par(brl(t.vendas_brutas_por_exemplar), 'vendas brutas por exemplar');
      pTen.appendChild(mini);
      var pr = f.projecao;
      if (pr && pr.proximos_3_meses_receita_autor) {
        var ultimoCompleto = meses.length ? meses[meses.length - 1].mes : null;
        if (ultimoCompleto && f.periodo && f.periodo.mes_parcial_fora_das_comparacoes === ultimoCompleto && meses.length > 1) ultimoCompleto = meses[meses.length - 2].mes;
        var blocoPr = el('div', 'an-projecao');
        blocoPr.appendChild(el('strong', '', 'Receita estimada dos próximos 3 meses'));
        var linha = el('div', 'an-proj-linha');
        pr.proximos_3_meses_receita_autor.forEach(function (v, k) {
          var c = el('div');
          c.appendChild(el('span', 'meta', ultimoCompleto ? proximoMes(ultimoCompleto, k + 1) : 'mês ' + (k + 1)));
          c.appendChild(el('strong', '', brl(v)));
          linha.appendChild(c);
        });
        blocoPr.appendChild(linha);
        blocoPr.appendChild(el('p', 'meta an-nota', 'Estimativa estatística, não é garantia. Método: ' + (pr.metodo || 'tendência dos meses completos') + '.'));
        pTen.appendChild(blocoPr);
      }
      grade.appendChild(pTen);

      // Achados
      var pAch = painel('Principais achados', 'todas');
      (ia.achados || []).forEach(function (a, k) {
        var art = el('article', 'an-achado');
        art.appendChild(el('span', 'an-ordem', String(k + 1)));
        var corpo = el('div');
        var topo = el('div', 'an-achado-topo');
        topo.appendChild(el('strong', '', a.titulo));
        topo.appendChild(el('span', 'meta', 'Confiança ' + (NIVEL[a.confianca] || '').toLowerCase()));
        corpo.appendChild(topo);
        var ev = el('p', 'an-linha');
        ev.appendChild(el('span', 'an-etiqueta', 'Evidência'));
        ev.appendChild(document.createTextNode(a.evidencia));
        corpo.appendChild(ev);
        var le = el('p', 'an-linha');
        le.appendChild(el('span', 'an-etiqueta', 'Leitura'));
        le.appendChild(document.createTextNode(a.leitura));
        corpo.appendChild(le);
        art.appendChild(corpo);
        pAch.appendChild(art);
      });
      if (!(ia.achados || []).length) pAch.appendChild(el('p', 'texto-2', 'A análise não trouxe achados.'));
      grade.appendChild(pAch);

      // Riscos e oportunidades
      var pRis = painel('Riscos e alertas', 'col-6');
      (ia.riscos || []).forEach(function (x) {
        var d = el('div', 'an-item');
        var topo = el('div', 'an-achado-topo');
        topo.appendChild(el('strong', '', x.titulo));
        topo.appendChild(chip(NIVEL[x.gravidade] || '', x.gravidade === 'alta' ? 'chip-erro' : 'chip-neutro'));
        d.appendChild(topo);
        d.appendChild(el('p', 'texto-2', x.detalhe));
        pRis.appendChild(d);
      });
      if (!(ia.riscos || []).length) pRis.appendChild(el('p', 'texto-2', 'Nenhum risco relevante identificado.'));
      grade.appendChild(pRis);

      var pOp = painel('Oportunidades', 'col-6');
      (ia.oportunidades || []).forEach(function (x) {
        var d = el('div', 'an-item');
        d.appendChild(el('strong', '', x.titulo));
        d.appendChild(el('p', 'texto-2', x.detalhe));
        pOp.appendChild(d);
      });
      if (!(ia.oportunidades || []).length) pOp.appendChild(el('p', 'texto-2', 'Nenhuma oportunidade clara nos dados.'));
      grade.appendChild(pOp);

      // Recomendações priorizadas
      var pRec = painel('Recomendações, da mais para a menos prioritária', 'todas');
      var tab = el('div', 'an-rec');
      var cabRec = el('div', 'an-rec-linha an-rec-cab');
      ['', 'Ação', 'Impacto', 'Esforço'].forEach(function (x) { cabRec.appendChild(el('span', '', x)); });
      tab.appendChild(cabRec);
      (ia.recomendacoes || []).forEach(function (x, k) {
        var l = el('div', 'an-rec-linha');
        l.appendChild(el('span', 'an-ordem', String(k + 1)));
        var c = el('div');
        c.appendChild(el('strong', '', x.acao));
        c.appendChild(el('p', 'texto-2', x.motivo));
        l.appendChild(c);
        l.appendChild(el('span', '', NIVEL[x.impacto] || ''));
        l.appendChild(el('span', '', NIVEL[x.esforco] || ''));
        tab.appendChild(l);
      });
      pRec.appendChild(tab);
      grade.appendChild(pRec);

      // Limites e perguntas de validação
      var pLim = painel('O que os dados não permitem concluir', 'col-6');
      var ulL = el('ul', 'an-pontos');
      (ia.limites || []).forEach(function (x) { ulL.appendChild(el('li', '', x)); });
      pLim.appendChild(ulL);
      grade.appendChild(pLim);

      var pPer = painel('Perguntas para validar', 'col-6');
      var ulP = el('ul', 'an-pontos');
      (ia.perguntas || []).forEach(function (x) { ulP.appendChild(el('li', '', x)); });
      pPer.appendChild(ulP);
      grade.appendChild(pPer);

      grade.appendChild(el('p', 'meta an-rodape todas', 'Os números são calculados pelo sistema a partir das vendas importadas. As interpretações e recomendações são geradas por IA: confira antes de decidir.'));
      view.appendChild(grade);

      document.querySelectorAll('#an-lista-relatorios a').forEach(function (a) {
        a.classList.toggle('ativo', String(a.getAttribute('data-abrir')) === String(id));
      });
    }
    window.AN_renderRelatorio = renderRelatorio;

    function abrir(id) {
      view.classList.add('carregando');
      chamar('abrir_relatorio', { id: id }).then(function (j) {
        view.classList.remove('carregando');
        if (!j.ok) { erroRel.textContent = j.erro; erroRel.hidden = false; return; }
        renderRelatorio(j.relatorio, id);
      });
    }

    var lista = $('an-lista-relatorios');
    if (lista) {
      lista.addEventListener('click', function (ev) {
        var a = ev.target.closest('a[data-abrir]');
        if (!a) return;
        ev.preventDefault();
        abrir(a.getAttribute('data-abrir'));
      });
    }

    if (gerar) {
      gerar.addEventListener('click', function () {
        erroRel.hidden = true;
        gerar.disabled = true;
        gerar.textContent = 'Analisando…';
        estado.textContent = 'O sistema está calculando os indicadores e a IA escrevendo a análise. Leva de 20 a 60 segundos. Não feche esta aba.';
        var autor = $('an-autor');
        chamar('gerar_relatorio', { foco: $('an-foco').value, meses: $('an-periodo').value, autor: autor ? autor.value : 0 }).then(function (j) {
          gerar.disabled = false;
          gerar.textContent = 'Gerar relatório';
          estado.textContent = '';
          if (!j.ok) { erroRel.textContent = j.erro; erroRel.hidden = false; return; }
          renderRelatorio(j.relatorio, j.id);
          var vazio = $('an-sem-relatorios');
          if (vazio) vazio.remove();
          var li = document.createElement('li');
          li.setAttribute('data-id', j.id);
          var a = document.createElement('a');
          a.href = '#';
          a.setAttribute('data-abrir', j.id);
          var s1 = document.createElement('span');
          s1.textContent = j.relatorio.meta.foco_rotulo + ' · ' + j.relatorio.meta.periodo_rotulo;
          var s2 = document.createElement('small');
          s2.textContent = j.relatorio.meta.gerado_em;
          a.appendChild(s1);
          a.appendChild(s2);
          li.appendChild(a);
          lista.insertBefore(li, lista.firstChild);
          a.classList.add('ativo');
          if (window.innerWidth < 1100) view.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });
    }

    var pedido = parseInt(app.getAttribute('data-relatorio') || '0', 10);
    var ultimo = parseInt(app.getAttribute('data-ultimo-relatorio') || '0', 10);
    if (pedido > 0) abrir(pedido); else if (ultimo > 0) abrir(ultimo);
  }
})();
