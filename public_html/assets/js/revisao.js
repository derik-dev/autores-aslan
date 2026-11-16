// Revisão de textos: formulário, estimativa e revisão trecho a trecho (português de Portugal).
(function () {
  var raiz = document.getElementById('trad-app');
  if (!raiz) return;

  var csrf = raiz.getAttribute('data-csrf');
  var PAGINA = 1800;   // caracteres por página (estimativa)
  var TRECHO = 3500;   // tem de ser igual a TRAD_TRECHO no PHP

  var $ = function (id) { return document.getElementById(id); };
  var resumo = $('resumo'), erroForm = $('erro-form'), gerar = $('gerar');
  var arquivo = $('arquivo'), soltar = $('rv-soltar'), soltarTitulo = $('rv-soltar-titulo'), rotuloArquivo = $('rotulo-arquivo');
  var painel = $('andamento'), andTitulo = $('and-titulo'), andBarra = $('and-barra');
  var andTexto = $('and-texto'), andErro = $('and-erro'), andTentar = $('and-tentar'), andVoltar = $('and-voltar'), andPct = $('and-pct');

  var rodando = false;
  var ultimoId = null, ultimoTitulo = '';
  var textoCarregado = '', tituloCarregado = '';
  var TEXTO_INICIAL = rotuloArquivo.textContent;

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
    var n = textoCarregado.trim().length;
    if (!n) { resumo.hidden = true; return; }
    var trechos = Math.ceil(n / TRECHO);
    var paginas = Math.max(1, Math.round(n / PAGINA));
    var minutos = Math.max(1, Math.ceil(trechos * 40 / 60));
    resumo.hidden = false;
    resumo.textContent = fmt(n) + ' caracteres (≈ ' + fmt(paginas) + ' páginas) · ' + fmt(trechos) + (trechos === 1 ? ' trecho' : ' trechos') + ' · demora ≈ ' + minutos + ' min a rever';
  }

  function lerFicheiro(f) {
    if (!f || rodando) return;
    erroForm.hidden = true;
    soltarTitulo.textContent = 'A ler o ficheiro…';
    var fd = new FormData();
    fd.append('arquivo', f);
    chamar('audio_api.php?acao=extrair', fd).then(function (j) {
      arquivo.value = '';
      if (!j.ok) {
        soltarTitulo.textContent = 'Carregar um ficheiro';
        mostrarErro(erroForm, j.erro);
        return;
      }
      textoCarregado = j.texto;
      tituloCarregado = f.name.replace(/.[^.]+$/, '');
      soltar.classList.add('rv-soltar-ok');
      soltarTitulo.textContent = f.name;
      rotuloArquivo.textContent = 'Carregue ou arraste outro ficheiro para o trocar.';
      gerar.disabled = false;
      atualizarResumo();
    });
  }
  arquivo.addEventListener('change', function () { lerFicheiro(arquivo.files[0]); });
  ['dragenter', 'dragover'].forEach(function (nome) {
    soltar.addEventListener(nome, function (ev) { ev.preventDefault(); soltar.classList.add('rv-soltar-sobre'); });
  });
  ['dragleave', 'drop'].forEach(function (nome) {
    soltar.addEventListener(nome, function (ev) { ev.preventDefault(); soltar.classList.remove('rv-soltar-sobre'); });
  });
  soltar.addEventListener('drop', function (ev) {
    if (ev.dataTransfer && ev.dataTransfer.files && ev.dataTransfer.files[0]) lerFicheiro(ev.dataTransfer.files[0]);
  });
  soltar.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); arquivo.click(); }
  });

  gerar.addEventListener('click', function () {
    if (rodando) return;
    erroForm.hidden = true;
    if (!textoCarregado.trim()) { mostrarErro(erroForm, 'Carregue primeiro um ficheiro.'); return; }

    gerar.disabled = true;
    gerar.textContent = 'A preparar o texto…';
    chamar('traducao_api.php?acao=criar', {
      titulo: tituloCarregado || 'Texto sem título', idioma: 'rev', instrucoes: '', texto: textoCarregado
    }).then(function (j) {
      gerar.disabled = false;
      gerar.textContent = 'Rever texto';
      if (!j.ok) { mostrarErro(erroForm, j.erro); return; }
      processar(j.id, tituloCarregado);
    });
  });

  function mostrarProgresso(feitos, total, segundosPorTrecho) {
    var pct = total ? Math.round(feitos / total * 100) : 0;
    andBarra.style.width = pct + '%';
    andPct.textContent = pct + '%';
    var msg = 'Trecho ' + Math.min(feitos + 1, total) + ' de ' + total;
    if (segundosPorTrecho && feitos < total) {
      var min = Math.ceil((total - feitos) * segundosPorTrecho / 60);
      msg += ' · faltam ≈ ' + min + ' min';
    }
    andTexto.textContent = msg;
  }

  function abrirCarga() {
    painel.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function fecharCarga() {
    painel.hidden = true;
    document.body.style.overflow = '';
  }

  function processar(id, nome) {
    if (rodando) return;
    rodando = true;
    ultimoId = id; ultimoTitulo = nome;
    abrirCarga();
    andTitulo.textContent = nome;
    andErro.hidden = true;
    andTentar.hidden = true;
    andVoltar.hidden = true;
    mostrarProgresso(0, 1, 0);
    andTexto.textContent = 'A começar…';

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
          andVoltar.hidden = false;
          return;
        }
        if (j.concluido) {
          rodando = false;
          andBarra.style.width = '100%';
          andPct.textContent = '100%';
          andTexto.textContent = 'Concluído! A abrir as correções…';
          setTimeout(function () { window.location.href = 'revisao_ver.php?id=' + id; }, 900);
          return;
        }
        passo();
      });
    }
    passo();
  }

  andVoltar.addEventListener('click', function () { fecharCarga(); window.location.reload(); });
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
