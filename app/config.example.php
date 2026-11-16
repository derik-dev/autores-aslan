<?php // Configuração de exemplo: copie para config.php e preencha com os dados reais.

return [
    'db_host' => 'SEU_HOST',
    'db_name' => 'SEU_BANCO',
    'db_user' => 'SEU_USUARIO',
    'db_pass' => 'SUA_SENHA',

    // Chave da API do ElevenLabs (texto em áudio). Crie em elevenlabs.io > Developers > API Keys.
    'elevenlabs_key' => 'SUA_CHAVE_ELEVENLABS',


    // Chave da API da OpenAI (tradução de livros). Crie em platform.openai.com > API keys.
    'openai_key' => 'SUA_CHAVE_OPENAI',

    // Opcional: modelo da OpenAI usado na tradução (padrão: gpt-4.1).
    // 'openai_model' => 'gpt-4.1',

    // Opcional: e-mails que também administram o sistema (importam vendas de todos os autores).
    // O usuário de id 1 já é administrador.
    // 'admin_emails' => ['voce@exemplo.com'],
];
