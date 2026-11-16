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
    var grudado = true; // acompanha o fim da conversa enquanto o usuário não rolar para cima

    document.querySelectorAll('[data-md]').forEach(function (el) { el.innerHTML = md(el.textContent); });
    thread.scrollTop = thread.scrollHeight;
    thread.addEventListener('scroll', function () {
      grudado = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;
    });
    function descer() { if (grudado) thread.scrollTop = thread.scrollHeight; }

    function mensagem(papel, html, ehHtml) {
      var vazio = $('an-vazio');
      if (vazio) vazio.remove();
      var m = document.createElement('div');
      m.className = 'an-msg an-msg-nova ' + (papel === 'user' ? 'an-msg-user' : 'an-msg-ia');
      var p = document.createElement('div');
      p.className = 'an-msg-papel';
      p.textContent = papel === 'user' ? 'Você' : 'LIA';
      var t = document.createElement('div');
      t.className = 'an-msg-texto';
      if (ehHtml) t.innerHTML = html; else t.textContent = html;
      m.appendChild(p);
      m.appendChild(t);
      thread.appendChild(m);
      grudado = true;
      descer();
      return m;
    }

    function ajustarCaixa() {
      caixa.style.height = 'auto';
      caixa.style.height = Math.min(caixa.scrollHeight + 2, 160) + 'px';
    }

    // ---------- Efeito de escrita: o texto que chega vai aparecendo aos poucos, num ritmo natural ----------
    // Fecha marcas abertas e segura linhas de tabela incompletas, para o texto não "pular" enquanto aparece.
    function paraExibir(t) {
      var linhas = t.split('\n');
      var ult = linhas[linhas.length - 1];
      if (/^\s*\|/.test(ult) && !/\|\s*$/.test(ult)) linhas.pop();
      var n = linhas.length;
      if (n && /^\s*\|.*\|\s*$/.test(linhas[n - 1]) && !(n > 1 && /^\s*\|/.test(linhas[n - 2]))) linhas.pop();
      var s = linhas.join('\n');
      if (((s.match(/\*\*/g) || []).length) % 2 === 1) s += '**';
      return s;
    }

    function Escritor(alvo) {
      this.alvo = alvo;
      this.fila = '';
      this.mostrado = 0;
      this.acum = 0;
      this.fim = false;
      this.rodando = false;
      this.ultimo = 0;
      this.ultimoRender = 0;
      this.aoFim = null;
    }
    Escritor.prototype.add = function (t) { this.fila += t; this.iniciar(); };
    Escritor.prototype.terminar = function (cb) { this.fim = true; this.aoFim = cb; this.iniciar(); };
    Escritor.prototype.iniciar = function () {
      if (this.rodando) return;
      this.rodando = true;
      this.ultimo = performance.now();
      var self = this;
      requestAnimationFrame(function passo(agora) { self.passo(agora, passo); });
    };
    Escritor.prototype.passo = function (agora, proximo) {
      var dt = Math.min(0.1, (agora - this.ultimo) / 1000);
      this.ultimo = agora;
      var faltam = this.fila.length - this.mostrado;
      if (faltam > 0) {
        // Ritmo base de escrita; acelera quando há muito texto acumulado, para não ficar atrasado.
        var cps = Math.min(900, 60 + faltam * 2.4);
        this.acum += cps * dt;
        var n = Math.floor(this.acum);
        if (n > 0) {
          this.acum -= n;
          this.mostrado = Math.min(this.fila.length, this.mostrado + n);
          if (agora - this.ultimoRender > 32) this.renderizar(false, agora);
        }
        requestAnimationFrame(proximo);
        return;
      }
      if (this.fim) {
        this.renderizar(true, agora);
        this.rodando = false;
        if (this.aoFim) this.aoFim();
        return;
      }
      this.rodando = false; // sem nada novo por ora: espera o próximo trecho
    };
    Escritor.prototype.renderizar = function (final, agora) {
      this.ultimoRender = agora;
      var texto = this.fila.slice(0, this.mostrado);
      this.alvo.innerHTML = md(final ? this.fila : paraExibir(texto));
      if (!final) {
        var ult = this.alvo.lastElementChild;
        if (ult && (ult.tagName === 'UL' || ult.tagName === 'OL') && ult.lastElementChild) ult = ult.lastElementChild;
        if (ult && ult.tagName !== 'DIV') {
          var c = document.createElement('span');
          c.className = 'an-cursor';
          ult.appendChild(c);
        }
      }
      descer();
    };

    // ---------- Leitura do fluxo de eventos do servidor ----------
    function lerEventos(res, aoEvento) {
      function processar(bloco) {
        bloco.split('\n').forEach(function (l) {
          if (l.indexOf('data:') === 0) {
            var d = null;
            try { d = JSON.parse(l.slice(5).trim()); } catch (e) { d = null; }
            if (d) aoEvento(d);
          }
        });
      }
      if (!res.body || !res.body.getReader) {
        return res.text().then(function (t) { t.split('\n\n').forEach(processar); });
      }
      var leitor = res.body.getReader();
      var dec = new TextDecoder('utf-8');
      var buf = '';
      function passo() {
        return leitor.read().then(function (r) {
          if (r.done) { processar(buf); return; }
          buf += dec.decode(r.value, { stream: true });
          var partes = buf.split('\n\n');
          buf = partes.pop();
          partes.forEach(processar);
          return passo();
        });
      }
      return passo();
    }

    // ---------- Título e lista de conversas ----------
    function atualizarTitulo(titulo) {
      var t = $('an-chat-titulo');
      if (!t || !titulo) return;
      t.classList.add('an-sumindo');
      setTimeout(function () { t.textContent = titulo; t.classList.remove('an-sumindo'); }, 170);
    }
    function atualizarLista(id, titulo) {
      var painel = $('an-painel-conversas'), lista = $('an-lista-conversas');
      if (!painel || !lista || !id) return;
      painel.hidden = false;
      var sem = $('an-sem-conversas');
      if (sem) sem.remove();
      lista.querySelectorAll('a').forEach(function (a) { a.classList.remove('ativo'); });
      var li = lista.querySelector('li[data-id="' + id + '"]');
      var novo = !li;
      if (!li) {
        li = document.createElement('li');
        li.setAttribute('data-id', id);
        li.className = 'an-msg-nova';
        var a = document.createElement('a');
        a.href = 'analises.php?modo=conversa&c=' + id;
        a.appendChild(document.createElement('span'));
        var s = document.createElement('small');
        var hoje = new Date();
        s.textContent = String(hoje.getDate()).padStart(2, '0') + '/' + String(hoje.getMonth() + 1).padStart(2, '0') + '/' + hoje.getFullYear();
        a.appendChild(s);
        li.appendChild(a);
      }
      var link = li.querySelector('a');
      link.classList.add('ativo');
      if (titulo) link.querySelector('span').textContent = titulo;
      if (novo || lista.firstChild !== li) lista.insertBefore(li, lista.firstChild);
    }

    // ---------- Enviar uma pergunta ----------
    function perguntar(texto) {
      texto = texto.trim();
      if (!texto || ocupado) return;
      ocupado = true;
      erroChat.hidden = true;
      enviar.disabled = true;
      enviar.textContent = 'Analisando…';
      var minha = mensagem('user', texto, false);
      var resposta = mensagem('ia', '<span class="an-digitando">Analisando os números<i></i><i></i><i></i></span>', true);
      var alvo = resposta.querySelector('.an-msg-texto');
      caixa.value = '';
      ajustarCaixa();

      var escritor = null;
      var recebeuFim = false;
      var falhou = false;

      function liberar() {
        ocupado = false;
        enviar.disabled = false;
        enviar.textContent = 'Enviar pergunta';
        caixa.focus();
      }
      function falhar(msg) {
        if (falhou) return;
        falhou = true;
        minha.remove();
        resposta.remove();
        caixa.value = texto;
        ajustarCaixa();
        erroChat.textContent = msg;
        erroChat.hidden = false;
        liberar();
      }
      function aoEvento(ev) {
        if (falhou) return;
        if (ev.tipo === 'inicio') {
          if (!conversaId) {
            conversaId = ev.conversa_id;
            var sel = $('an-autor');
            if (sel) sel.disabled = true;
            try { history.replaceState(null, '', 'analises.php?modo=conversa&c=' + conversaId); } catch (e) {}
            atualizarLista(conversaId, texto.length > 40 ? texto.slice(0, 40) + '…' : texto);
          }
        } else if (ev.tipo === 'texto') {
          if (!escritor) { alvo.innerHTML = ''; escritor = new Escritor(alvo); }
          escritor.add(ev.t);
        } else if (ev.tipo === 'fim') {
          recebeuFim = true;
          if (escritor) escritor.terminar(liberar); else liberar();
        } else if (ev.tipo === 'titulo') {
          atualizarTitulo(ev.titulo);
          atualizarLista(ev.conversa_id || conversaId, ev.titulo);
        } else if (ev.tipo === 'erro') {
          falhar(ev.erro || 'Não consegui responder agora. Tente de novo.');
        }
      }

      fetch('analises_stream.php', {
        method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf },
        body: new URLSearchParams({ conversa_id: conversaId, mensagem: texto, autor: ($('an-autor') ? $('an-autor').value : 0) })
      }).then(function (res) {
        var tipo = res.headers.get('content-type') || '';
        if (tipo.indexOf('application/json') !== -1) {
          return res.json().then(function (j) { falhar(j.erro || 'Não consegui responder agora. Tente de novo.'); });
        }
        return lerEventos(res, aoEvento);
      }).catch(function () {
        falhar('Sem conexão com o servidor. Verifique a internet e tente de novo.');
      }).then(function () {
        // O fluxo acabou sem o evento "fim": termina com o que chegou, ou avisa o erro.
        if (!falhou && !recebeuFim) {
          if (escritor) escritor.terminar(liberar); else falhar('A resposta foi interrompida. Tente de novo.');
        }
      });
    }

    form.addEventListener('submit', function (ev) { ev.preventDefault(); perguntar(caixa.value); });
    caixa.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) { ev.preventDefault(); perguntar(caixa.value); }
    });
    caixa.addEventListener('input', ajustarCaixa);

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

    function dataBr(d) { var p = String(d).split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }

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

    function renderRelatorio(r, id, desatualizado) {
      var m = r.meta || {}, f = r.fatos || {}, ia = r.ia || {};
      var t = f.totais || {};
      view.innerHTML = '';
      view.setAttribute('data-id', id || '');
      var grade = el('div', 'an-rel');

      // Cabeçalho
      var cab = el('header', 'an-rel-cabeca todas');
      var tit = el('div');
      tit.appendChild(el('h2', 'an-rel-titulo', ia.titulo || 'Análise de vendas'));
      tit.appendChild(el('p', 'meta', (m.foco_rotulo || '') + ' · ' + (m.periodo_rotulo || '') + ' · ' + (m.escopo || '') + ' · gerado em ' + (m.gerado_em || '') + (f.periodo && f.periodo.fim ? ' · vendas até ' + dataBr(f.periodo.fim) : '')));
      cab.appendChild(tit);
      var acoes = el('div', 'linha-acoes');
      var perguntar = el('button', 'btn btn-sec btn-sm', 'Falar com a LIA sobre este relatório');
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
      var atualizar = el('button', 'btn btn-primary btn-sm', 'Atualizar relatório');
      atualizar.type = 'button';
      atualizar.addEventListener('click', function () { atualizarRelatorio(id, m); });
      if (id) acoes.appendChild(atualizar);
      if (id) acoes.appendChild(perguntar);
      acoes.appendChild(imprimir);
      cab.appendChild(acoes);
      grade.appendChild(cab);
      if (desatualizado) {
        grade.appendChild(el('p', 'alerta todas', 'Chegaram vendas novas desde que este relatório foi gerado. Clique em Atualizar relatório para ver os números mais recentes.'));
      }

      // Resumo do relatório: leitura rápida no topo, com convite para seguir o relatório completo
      var pRes = painel('Resumo do relatório', 'todas');
      pRes.appendChild(el('p', 'an-resumo', ia.resumo || ''));
      var pontos = (ia.pontos_chave && ia.pontos_chave.length) ? ia.pontos_chave : (ia.achados || []).slice(0, 3).map(function (x) { return x.titulo; });
      if (pontos.length) {
        pRes.appendChild(el('p', 'an-resumo-rotulo', 'O essencial em poucas linhas'));
        var ulRes = el('ul', 'an-pontos an-pontos-resumo');
        pontos.forEach(function (x) { if (x) ulRes.appendChild(el('li', '', x)); });
        pRes.appendChild(ulRes);
      }
      var rodRes = el('div', 'an-resumo-rodape');
      rodRes.appendChild(el('span', '', 'Para entender melhor, siga o relatório abaixo.'));
      var ir = el('a', 'an-ir', 'Ver o relatório completo');
      ir.href = '#an-detalhe';
      ir.insertAdjacentHTML('beforeend', '<svg class="icone" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>');
      ir.addEventListener('click', function (ev) {
        ev.preventDefault();
        var alvo = document.getElementById('an-detalhe');
        if (alvo) alvo.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      rodRes.appendChild(ir);
      pRes.appendChild(rodRes);
      grade.appendChild(pRes);

      var secao = el('div', 'an-secao todas');
      secao.id = 'an-detalhe';
      secao.appendChild(el('h3', '', 'Relatório completo'));
      secao.appendChild(el('span', 'meta', 'Números, tendência, achados, riscos e recomendações'));
      grade.appendChild(secao);

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
        renderRelatorio(j.relatorio, id, j.desatualizado);
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

    function registrarNaLista(id, relatorio, substituido) {
      if (!lista) return;
      var vazio = $('an-sem-relatorios');
      if (vazio) vazio.remove();
      if (substituido) {
        var velho = lista.querySelector('li[data-id="' + substituido + '"]');
        if (velho) velho.remove();
      }
      var li = document.createElement('li');
      li.setAttribute('data-id', id);
      var a = document.createElement('a');
      a.href = '#';
      a.setAttribute('data-abrir', id);
      var s1 = document.createElement('span');
      s1.textContent = relatorio.meta.foco_rotulo + ' · ' + relatorio.meta.periodo_rotulo;
      var s2 = document.createElement('small');
      s2.textContent = relatorio.meta.gerado_em;
      a.appendChild(s1);
      a.appendChild(s2);
      li.appendChild(a);
      lista.querySelectorAll('a').forEach(function (x) { x.classList.remove('ativo'); });
      a.classList.add('ativo');
      lista.insertBefore(li, lista.firstChild);
    }

    function mostrarCarregando(titulo, texto) {
      view.innerHTML = '';
      var p = el('section', 'bloco an-carregando');
      p.setAttribute('aria-live', 'polite');
      p.appendChild(el('h2', 'bloco-titulo', titulo));
      p.appendChild(el('p', 'texto-2', texto));
      var linhas = el('div', 'an-esqueleto');
      for (var i = 0; i < 6; i++) linhas.appendChild(el('span'));
      p.appendChild(linhas);
      view.appendChild(p);
    }

    function mostrarErro(msg, tentar, voltarId) {
      view.innerHTML = '';
      var p = el('section', 'bloco');
      p.appendChild(el('h2', 'bloco-titulo', 'Não consegui gerar o relatório'));
      var e = el('p', 'alerta', msg);
      e.setAttribute('role', 'alert');
      p.appendChild(e);
      var acoes = el('div', 'linha-acoes an-acoes-erro');
      var b = el('button', 'btn btn-primary btn-sm', 'Tentar de novo');
      b.type = 'button';
      b.addEventListener('click', tentar);
      acoes.appendChild(b);
      if (voltarId) {
        var v = el('button', 'btn btn-sec btn-sm', 'Voltar ao relatório anterior');
        v.type = 'button';
        v.addEventListener('click', function () { abrir(voltarId); });
        acoes.appendChild(v);
      }
      p.appendChild(acoes);
      view.appendChild(p);
    }

    var gerando = false;
    function gerarRelatorio(params, titulo, texto, voltarId) {
      if (gerando) return;
      gerando = true;
      erroRel.hidden = true;
      if (gerar) { gerar.disabled = true; gerar.textContent = 'Gerando…'; }
      mostrarCarregando(titulo, texto);
      chamar('gerar_relatorio', params).then(function (j) {
        gerando = false;
        if (gerar) { gerar.disabled = false; gerar.textContent = 'Gerar outro relatório'; }
        if (!j.ok) {
          mostrarErro(j.erro, function () { gerarRelatorio(params, titulo, texto, voltarId); }, voltarId);
          return;
        }
        renderRelatorio(j.relatorio, j.id, false);
        registrarNaLista(j.id, j.relatorio, params.substituir);
      });
    }

    // "Atualizar relatório": refaz o mesmo relatório com os dados de agora e substitui o anterior.
    function atualizarRelatorio(id, meta) {
      gerarRelatorio(
        { foco: meta.foco || 'geral', meses: meta.meses || 0, autor: meta.autor_id || 0, substituir: id },
        'Atualizando o relatório',
        'O sistema recalcula os indicadores com os dados mais recentes e a LIA reescreve a análise. Leva de 20 a 60 segundos. Não feche esta aba.',
        id
      );
    }

    if (gerar) {
      gerar.addEventListener('click', function () {
        var autor = $('an-autor');
        gerarRelatorio(
          { foco: $('an-foco').value, meses: $('an-periodo').value, autor: autor ? autor.value : 0 },
          'Gerando o relatório',
          'O sistema calcula os indicadores e a LIA escreve a análise. Leva de 20 a 60 segundos. Não feche esta aba.'
        );
        if (window.innerWidth < 1100) view.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }

    var pedido = parseInt(app.getAttribute('data-relatorio') || '0', 10);
    var ultimo = parseInt(app.getAttribute('data-ultimo-relatorio') || '0', 10);
    if (pedido > 0) abrir(pedido);
    else if (ultimo > 0) abrir(ultimo);
    else {
      // Primeira visita: o relatório já começa a ser gerado, sem esperar um clique.
      gerarRelatorio(
        { foco: 'geral', meses: 0, autor: 0 },
        'Preparando o seu relatório',
        'É a sua primeira visita, então estou gerando o relatório de vendas: o sistema calcula os indicadores e a LIA escreve a análise. Leva de 20 a 60 segundos. Não feche esta aba.'
      );
    }
  }
})();
