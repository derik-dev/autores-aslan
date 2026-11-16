<?php
// Tela "Autores" (só a editora): cria contas de autor e redefine senhas.

require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/http.php';

$usuario = exigir_login();
if (!eh_editora($usuario)) {
    http_response_code(403);
    layout_inicio('Autores', 'autores', $usuario);
    ?>
        <div class="vazio"><strong>Acesso restrito</strong>Só a editora pode gerenciar autores.</div>
<?php
    layout_fim();
    exit;
}

$lista = db()->query('SELECT id, nome, email, criado_em FROM usuarios ORDER BY nome')->fetchAll();

layout_inicio('Autores', 'autores', $usuario);
?>
        <p class="texto-2">Crie a conta de cada autor. O sistema gera uma senha temporária, que aparece uma única vez. Envie para o autor por um canal seguro.</p>

        <div id="aut-app" data-csrf="<?= e(csrf_token()) ?>">
            <section class="bloco">
                <h2 class="bloco-titulo">Novo autor</h2>
                <div class="form">
                    <div class="duas-colunas">
                        <div class="coluna">
                            <label for="a-nome">Nome</label>
                            <input type="text" id="a-nome" maxlength="120" autocomplete="off">
                        </div>
                        <div class="coluna">
                            <label for="a-email">E-mail (é o login)</label>
                            <input type="email" id="a-email" maxlength="190" autocomplete="off">
                        </div>
                    </div>
                    <p id="a-erro" class="alerta" role="alert" hidden></p>
                    <p id="a-senha" class="alerta" role="status" hidden></p>
                    <div>
                        <button type="button" id="a-criar" class="btn btn-primary">Criar conta</button>
                    </div>
                </div>
            </section>

            <section class="bloco">
                <h2 class="bloco-titulo">Contas existentes</h2>
                <div class="tabela-rolagem">
                    <table class="tabela">
                        <thead><tr><th>Nome</th><th>E-mail</th><th>Criada em</th><th></th></tr></thead>
                        <tbody>
<?php foreach ($lista as $u): ?>
                            <tr data-id="<?= (int) $u['id'] ?>">
                                <td><?= e($u['nome']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><?= e(date('d/m/Y', strtotime($u['criado_em']))) ?></td>
                                <td><button type="button" class="btn btn-sec btn-sm" data-acao="redefinir">Redefinir senha</button></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <script>
            (function () {
                var raiz = document.getElementById('aut-app');
                var csrf = raiz.getAttribute('data-csrf');
                var erro = document.getElementById('a-erro');
                var senha = document.getElementById('a-senha');

                function chamar(acao, dados) {
                    return fetch('autores_api.php?acao=' + acao, {
                        method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: new URLSearchParams(dados)
                    }).then(function (r) { return r.json(); })
                      .catch(function () { return { ok: false, erro: 'Sem conexão com o servidor. Tente de novo.' }; });
                }
                function mostrarSenha(j) {
                    senha.textContent = 'Login: ' + j.email + ' · Senha temporária: ' + j.senha + ' · Anote agora, ela não aparece de novo.';
                    senha.hidden = false;
                }

                document.getElementById('a-criar').addEventListener('click', function () {
                    erro.hidden = true;
                    senha.hidden = true;
                    var b = this;
                    b.disabled = true;
                    chamar('criar', { nome: document.getElementById('a-nome').value, email: document.getElementById('a-email').value }).then(function (j) {
                        b.disabled = false;
                        if (!j.ok) { erro.textContent = j.erro; erro.hidden = false; return; }
                        mostrarSenha(j);
                        document.getElementById('a-nome').value = '';
                        document.getElementById('a-email').value = '';
                    });
                });

                raiz.addEventListener('click', function (ev) {
                    var b = ev.target.closest('button[data-acao="redefinir"]');
                    if (!b) return;
                    if (b.getAttribute('data-confirma') !== '1') {
                        b.setAttribute('data-confirma', '1');
                        b.textContent = 'Confirmar: gerar nova senha';
                        setTimeout(function () { b.removeAttribute('data-confirma'); b.textContent = 'Redefinir senha'; }, 4000);
                        return;
                    }
                    b.disabled = true;
                    erro.hidden = true;
                    chamar('redefinir', { id: b.closest('tr').getAttribute('data-id') }).then(function (j) {
                        b.disabled = false;
                        b.textContent = 'Redefinir senha';
                        b.removeAttribute('data-confirma');
                        if (!j.ok) { erro.textContent = j.erro; erro.hidden = false; return; }
                        mostrarSenha(j);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                });
            })();
        </script>
<?php layout_fim(); ?>
