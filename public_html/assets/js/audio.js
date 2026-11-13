// Tela "Texto em áudio": formulário, estimativa de custo e geração trecho por trecho.
(function () {
  var raiz = document.getElementById('audio-app');
  if (!raiz) return;

  var csrf = raiz.getAttribute('data-csrf');
  var PAGINA = 1800;   // caracteres por página (estimativa)
  var TRECHO = 4500;   // deve ser igual a AUDIO_TRECHO no PHP

  var $ = function (id) { return document.getElementById(id); };
  var titulo = $('titulo'), voz = $('voz'), modelo = $('modelo'), texto = $('texto');
  var resumo = $('resumo'), erroForm = $('erro-form'), gerar = $('gerar');
  var arquivo = $('arquivo'), rotuloArquivo = $('rotulo-arquivo');
  var avisoApi = $('aviso-api'), creditosEl = $('creditos'), amostra = $('amostra');
  var painel = $('andamento'), andTitulo = $('and-titulo'), andBarra = $('and-barra');
  var andTexto = $('and-texto'), andErro = $('and-erro'), andTentar = $('and-tentar');

  var creditosRestantes = null;
  var rodando = false;
  var ultimoId = null, ultimoTitulo = '';

  function fmt(n) { return Number(n).toLocaleString('pt-BR'); }

  // Chamada à API interna. Sempre resolve com um objeto {ok, ...}.
  function chamar(acao, dados) {
    var opcoes = { credentials: 'same-origin', headers: { 'X-CSRF': csrf } };
    if (dados) {
      opcoes.method = 'POST';
      opcoes.body = dados instanceof FormData ? dados : new URLSearchParams(dados);
    }
    return fetch('audio_api.php?acao=' + acao, opcoes)
      .then(function (r) {
        return r.json().catch(function () {
          return { ok: false, erro: 'Resposta inesperada do servidor (código ' + r.status + ').' };
        });
      })
      .catch(function () {
        return { ok: false, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' };
      });
  }

  function mostrarErro(el, mensagem) { el.textContent = mensagem; el.hidden = false; }

  // ---------- Vozes e créditos ----------
  chamar('vozes').then(function (j) {
    if (!j.ok) {
      voz.innerHTML = '<option value="">Vozes indisponíveis</option>';
      mostrarErro(avisoApi, j.erro);
      return;
    }
    voz.innerHTML = '';
    j.vozes.forEach(function (v) {
      var o = document.createElement('option');
      o.value = v.id;
      o.textContent = v.nome + (v.categoria ? ' · ' + v.categoria : '');
      o.setAttribute('data-amostra', v.amostra || '');
      voz.appendChild(o);
    });
    voz.disabled = false;
    if (!j.vozes.length) {
      voz.innerHTML = '<option value="">Nenhuma voz na sua conta</option>';
      voz.disabled = true;
    }
    mostrarAmostra();
    if (j.creditos) {
      creditosRestantes = Math.max(0, j.creditos.limite - j.creditos.usados);
      creditosEl.textContent = 'Créditos no ElevenLabs: ' + fmt(creditosRestantes) + ' restantes de ' + fmt(j.creditos.limite) + '.';
      creditosEl.hidden = false;
      atualizarResumo();
    }
  });

  function mostrarAmostra() {
    var op = voz.options[voz.selectedIndex];
    var url = op ? op.getAttribute('data-amostra') : '';
    if (url) { amostra.src = url; amostra.hidden = false; }
    else { amostra.removeAttribute('src'); amostra.hidden = true; }
  }
  voz.addEventListener('change', mostrarAmostra);

  // ---------- Resumo e custo estimado ----------
  function atualizarResumo() {
    var n = texto.value.trim().length;
    if (!n) { resumo.textContent = 'Nenhum texto ainda.'; resumo.className = 'texto-2'; return; }
    var custo = parseFloat(modelo.options[modelo.selectedIndex].getAttribute('data-custo'));
    var creditos = Math.ceil(n * custo);
    var trechos = Math.ceil(n / TRECHO);
    var paginas = Math.max(1, Math.round(n / PAGINA));
    var minutos = Math.max(1, Math.ceil(trechos * 12 / 60));
    var t = fmt(n) + ' caracteres (≈ ' + fmt(paginas) + ' páginas) · gasta ≈ ' + fmt(creditos) + ' créditos · leva ≈ ' + minutos + ' min para gerar';
    var classe = 'texto-2';
    if (creditosRestantes !== null && creditos > creditosRestantes) {
      t += ' · você tem só ' + fmt(creditosRestantes) + ' créditos';
      classe = 'status-erro';
    }
    resumo.textContent = t;
    resumo.className = classe;
  }
  texto.addEventListener('input', atualizarResumo);
  modelo.addEventListener('change', atualizarResumo);
  atualizarResumo(); // caso o texto já venha preenchido (ex.: de uma tradução)

  // ---------- Enviar arquivo (.txt / .docx) ----------
  arquivo.addEventListener('change', function () {
    var f = arquivo.files[0];
    if (!f) return;
    erroForm.hidden = true;
    var rotuloOriginal = rotuloArquivo.textContent;
    rotuloArquivo.textContent = 'Lendo arquivo…';
    var fd = new FormData();
    fd.append('arquivo', f);
    chamar('extrair', fd).then(function (j) {
      rotuloArquivo.textContent = rotuloOriginal;
      arquivo.value = '';
      if (!j.ok) { mostrarErro(erroForm, j.erro); return; }
      texto.value = j.texto;
      if (!titulo.value.trim()) titulo.value = f.name.replace(/\.[^.]+$/, '');
      atualizarResumo();
    });
  });

  // ---------- Gerar: cria o áudio e processa trecho por trecho ----------
  gerar.addEventListener('click', function () {
    if (rodando) return;
    erroForm.hidden = true;
    if (!titulo.value.trim()) { mostrarErro(erroForm, 'Dê um título ao áudio.'); titulo.focus(); return; }
    if (!voz.value) { mostrarErro(erroForm, 'Escolha uma voz.'); return; }
    if (!texto.value.trim()) { mostrarErro(erroForm, 'Cole ou envie um texto para gerar o áudio.'); texto.focus(); return; }

    gerar.disabled = true;
    gerar.textContent = 'Preparando o texto…';
    chamar('criar', { titulo: titulo.value.trim(), texto: texto.value, voz: voz.value, modelo: modelo.value })
      .then(function (j) {
        gerar.disabled = false;
        gerar.textContent = 'Gerar áudio';
        if (!j.ok) { mostrarErro(erroForm, j.erro); return; }
        processar(j.id, titulo.value.trim());
      });
  });

  function mostrarProgresso(feitos, total, segundosPorTrecho) {
    var pct = total ? Math.round(feitos / total * 100) : 0;
    andBarra.style.width = pct + '%';
    var msg = 'Gerando trecho ' + Math.min(feitos + 1, total) + ' de ' + total + ' (' + pct + '%). Não feche esta aba.';
    if (segundosPorTrecho && feitos < total) {
      var min = Math.ceil((total - feitos) * segundosPorTrecho / 60);
      msg += ' Faltam ≈ ' + min + ' min.';
    }
    andTexto.textContent = msg;
  }

  function processar(id, nome) {
    if (rodando) return;
    rodando = true;
    ultimoId = id; ultimoTitulo = nome;
    painel.hidden = false;
    andTitulo.textContent = 'Gerando: ' + nome;
    andErro.hidden = true;
    andTentar.hidden = true;
    mostrarProgresso(0, 1, 0);
    andTexto.textContent = 'Começando…';
    painel.scrollIntoView({ behavior: 'smooth', block: 'start' });

    var inicio = Date.now(), feitosNoInicio = null;

    function passo() {
      chamar('processar', { id: id }).then(function (j) {
        if (typeof j.feitos === 'number') {
          if (feitosNoInicio === null) feitosNoInicio = j.ok ? j.feitos - 1 : j.feitos;
          var gerados = j.feitos - feitosNoInicio;
          var seg = gerados > 0 ? (Date.now() - inicio) / 1000 / gerados : 0;
          mostrarProgresso(j.feitos, j.total, seg);
        }
        if (!j.ok) {
          rodando = false;
          mostrarErro(andErro, j.erro);
          andTentar.hidden = false;
          return;
        }
        if (j.concluido) {
          rodando = false;
          andBarra.style.width = '100%';
          andTexto.textContent = 'Pronto! Seu áudio foi gerado.';
          setTimeout(function () { window.location.reload(); }, 900);
          return;
        }
        passo();
      });
    }
    passo();
  }

  andTentar.addEventListener('click', function () {
    if (ultimoId) processar(ultimoId, ultimoTitulo);
  });

  window.addEventListener('beforeunload', function (e) {
    if (rodando) { e.preventDefault(); e.returnValue = ''; }
  });

  // ---------- Ações da lista: continuar e excluir ----------
  raiz.addEventListener('click', function (ev) {
    var b = ev.target.closest('button[data-acao]');
    if (!b) return;
    var linha = b.closest('.linha-audio');
    var id = linha.getAttribute('data-id');
    var acao = b.getAttribute('data-acao');

    if (acao === 'continuar') {
      processar(id, linha.getAttribute('data-titulo'));
    } else if (acao === 'excluir') {
      if (b.getAttribute('data-confirma') !== '1') {
        b.setAttribute('data-confirma', '1');
        b.textContent = 'Confirmar exclusão';
        setTimeout(function () { b.removeAttribute('data-confirma'); b.textContent = 'Excluir'; }, 4000);
        return;
      }
      b.disabled = true;
      b.textContent = 'Excluindo…';
      chamar('excluir', { id: id }).then(function (j) {
        if (j.ok) { linha.remove(); }
        else { b.disabled = false; b.textContent = 'Excluir'; alert(j.erro); }
      });
    }
  });
})();
