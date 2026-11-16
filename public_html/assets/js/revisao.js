// Revisão de textos: formulário, estimativa e revisão trecho a trecho (português de Portugal).
(function () {
  var raiz = document.getElementById('trad-app');
  if (!raiz) return;

  var csrf = raiz.getAttribute('data-csrf');
  var PAGINA = 1800;   // caracteres por página (estimativa)
  var TRECHO = 3500;   // tem de ser igual a TRAD_TRECHO no PHP

  var $ = function (id) { return document.getElementById(id); };
  var titulo = $('titulo'), texto = $('texto');
  var resumo = $('resumo'), erroForm = $('erro-form'), gerar = $('gerar');
  var arquivo = $('arquivo'), rotuloArquivo = $('rotulo-arquivo');
  var painel = $('andamento'), andTitulo = $('and-titulo'), andBarra = $('and-barra');
  var andTexto = $('and-texto'), andErro = $('and-erro'), andTentar = $('and-tentar');

  var rodando = false;
  var ultimoId = null, ultimoTitulo = '';

  function fmt(n) { return Number(n).toLocaleString('pt-PT'); }

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
        return { ok: false, erro: 'Sem ligação ao servidor. Verifique a internet e tente de novo.' };
      });
  }

  function mostrarErro(el, mensagem) { el.textContent = mensagem; el.hidden = false; }

  function atualizarResumo() {
    var n = texto.value.trim().length;
    if (!n) { resumo.textContent = 'Ainda não há texto.'; return; }
    var trechos = Math.ceil(n / TRECHO);
    var paginas = Math.max(1, Math.round(n / PAGINA));
    var minutos = Math.max(1, Math.ceil(trechos * 40 / 60));
    resumo.textContent = fmt(n) + ' caracteres (≈ ' + fmt(paginas) + ' páginas) · ' + fmt(trechos) + (trechos === 1 ? ' trecho' : ' trechos') + ' · demora ≈ ' + minutos + ' min a rever';
  }
  texto.addEventListener('input', atualizarResumo);

  arquivo.addEventListener('change', function () {
    var f = arquivo.files[0];
    if (!f) return;
    erroForm.hidden = true;
    var rotuloOriginal = rotuloArquivo.textContent;
    rotuloArquivo.textContent = 'A ler o ficheiro…';
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

  gerar.addEventListener('click', function () {
    if (rodando) return;
    erroForm.hidden = true;
    if (!titulo.value.trim()) { mostrarErro(erroForm, 'Dê um título ao texto.'); titulo.focus(); return; }
    if (!texto.value.trim()) { mostrarErro(erroForm, 'Cole ou carregue o texto.'); texto.focus(); return; }

    gerar.disabled = true;
    gerar.textContent = 'A preparar o texto…';
    chamar('traducao_api.php?acao=criar', {
      titulo: titulo.value.trim(), idioma: 'rev', instrucoes: '', texto: texto.value
    }).then(function (j) {
      gerar.disabled = false;
      gerar.textContent = 'Rever texto';
      if (!j.ok) { mostrarErro(erroForm, j.erro); return; }
      processar(j.id, titulo.value.trim());
    });
  });

  function mostrarProgresso(feitos, total, segundosPorTrecho) {
    var pct = total ? Math.round(feitos / total * 100) : 0;
    andBarra.style.width = pct + '%';
    var msg = 'A rever o trecho ' + Math.min(feitos + 1, total) + ' de ' + total + ' (' + pct + '%). Não feche esta página.';
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
    andTitulo.textContent = 'A rever: ' + nome;
    andErro.hidden = true;
    andTentar.hidden = true;
    mostrarProgresso(0, 1, 0);
    andTexto.textContent = 'A começar…';
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
          andTexto.textContent = 'Concluído! A abrir as correções…';
          setTimeout(function () { window.location.href = 'revisao_ver.php?id=' + id; }, 900);
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
        b.textContent = 'Confirmar eliminação';
        setTimeout(function () { b.removeAttribute('data-confirma'); b.textContent = 'Eliminar'; }, 4000);
        return;
      }
      b.disabled = true;
      b.textContent = 'A eliminar…';
      chamar('traducao_api.php?acao=excluir', { id: id }).then(function (j) {
        if (j.ok) { linha.remove(); }
        else { b.disabled = false; b.textContent = 'Eliminar'; mostrarErro(erroForm, j.erro); }
      });
    }
  });
})();
