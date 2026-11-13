# Editora

Sistema web para autores: converter texto em áudio, acompanhar vendas e revisar textos.
Stack: HTML, CSS, JavaScript puro, PHP e MySQL (Hostinger).

## Estrutura na Hostinger

A pasta `app` fica **ao lado** da `public_html`, e não dentro dela. Assim o navegador não consegue acessar os arquivos de configuração e de lógica. Só o conteúdo de `public_html` é público.

Para configurar: copie `app/config.example.php` para `app/config.php` e preencha os dados do banco. O `config.php` não vai para o Git.
