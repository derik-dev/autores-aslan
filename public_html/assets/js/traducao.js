// Tela "Tradução": formulário, estimativa e tradução trecho por trecho.
(function () {
  var raiz = document.getElementById('trad-app');
  if (!raiz) return;

  var csrf = raiz.getAttribute('data-csrf');
  var PAGINA = 1800;   // caracteres por página (estimativa)
  var TRECHO = 3500;   // deve ser igual a TRAD_TRECHO no PHP

  var $ = function (id) { return document.getElementById(id); };
  var titulo = $('titulo'), idioma = $('idioma'), instrucoes = $('instrucoes'), texto = $('texto');
  var resumo = $('resumo'), erroForm = $('erro-form'), gerar = $('gerar');
  var arquivo = $('arquivo'), rotuloArquivo = $('rotulo-arquivo');
  var painel = $('andamento'), andTitulo = $('and-titulo'), andBarra = $('and-barra');
  var andTexto = $('and-texto'), andErro = $('and-erro'), andTentar = $('and-tentar');

  var rodando = false;
  var ultimoId = null, ultimoTitulo = '';

  function fmt(n) { return Number(n).toLocaleString('pt-BR'); }

  // Chamada à API. Sempre resolve com um objeto {ok, ...}.
  function chamar(url, dados) {
    var opcoes = { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf } };
    opcoes.body = dados instanceof FormData ? dados : new URLSearchParams(dados);
    return fetch(url, opcoes)
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

  // ---------- Resumo ----------
  function atualizarResumo() {
    var n = texto.value.trim().length;
    if (!n) { resumo.textContent = 'Nenhum texto ainda.'; return; }
    var trechos = Math.ceil(n / TRECHO);
    var paginas = Math.max(1, Math.round(n / PAGINA));
    var minutos = Math.max(1, Math.ceil(trechos * 25 / 60));
    resumo.textContent = fmt(n) + ' caracteres (≈ ' + fmt(paginas) + ' páginas) · ' + fmt(trechos) + ' trechos · leva ≈ ' + minutos + ' min para traduzir';
  }
  texto.addEventListener('input', atualizarResumo);

  // ---------- Enviar arquivo (.txt / .docx): reaproveita a leitura da tela de áudio ----------
  arquivo.addEventListener('change', function () {
    var f = arquivo.files[0];
    if (!f) return;
    erroForm.hidden = true;
    var rotuloOriginal = rotuloArquivo.textContent;
    rotuloArquivo.textContent = 'Lendo arquivo…';
    var fd = new FormData();
    fd.append('arquivo', f);
    chamar('audio_api.php?acao=extrair', fd).then(function (j) {
      rotuloArquivo.textContent = rotuloOriginal;
      arquivo.value = '';
      if (!j.ok) { mostrarErro(erroForm, j.erro); return; }
      texto.value = j.texto;
      if (!titulo.value.trim()) titulo.value = f.name.replace(/\.[^.]+$/, '');
      atualizarResumo();
    });
  });

  // ---------- Traduzir: cria e processa trecho por trecho ----------
  gerar.addEventListener('click', function () {
    if (rodando) return;
    erroForm.hidden = true;
    if (!titulo.value.trim()) { mostrarErro(erroForm, 'Dê um título ao livro.'); titulo.focus(); return; }
    if (!texto.value.trim()) { mostrarErro(erroForm, 'Cole ou envie o texto do livro.'); texto.focus(); return; }

    gerar.disabled = true;
    gerar.textContent = 'Preparando o texto…';
    chamar('traducao_api.php?acao=criar', {
      titulo: titulo.value.trim(), idioma: idioma.value, instrucoes: instrucoes.value, texto: texto.value
    }).then(function (j) {
      gerar.disabled = false;
      gerar.textContent = 'Traduzir livro';
      if (!j.ok) { mostrarErro(erroForm, j.erro); return; }
      processar(j.id, titulo.value.trim());
    });
  });

  function mostrarProgresso(feitos, total, segundosPorTrecho) {
    var pct = total ? Math.round(feitos / total * 100) : 0;
    andBarra.style.width = pct + '%';
    var msg = 'Traduzindo trecho ' + Math.min(feitos + 1, total) + ' de ' + total + ' (' + pct + '%). Não feche esta aba.';
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
    andTitulo.textContent = 'Traduzindo: ' + nome;
    andErro.hidden = true;
    andTentar.hidden = true;
    mostrarProgresso(0, 1, 0);
    andTexto.textContent = 'Começando…';
    painel.scrollIntoView({ behavior: 'smooth', block: 'start' });

    var inicio = Date.now(), feitosNoInicio = null;

    function passo() {
      chamar('traducao_api.php?acao=processar', { id: id }).then(function (j) {
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
          andTexto.textContent = 'Pronto! Abrindo a revisão…';
          setTimeout(function () { window.location.href = 'traducao_revisar.php?id=' + id; }, 900);
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
      chamar('traducao_api.php?acao=excluir', { id: id }).then(function (j) {
        if (j.ok) { linha.remove(); }
        else { b.disabled = false; b.textContent = 'Excluir'; mostrarErro(erroForm, j.erro); }
      });
    }
  });
})();
