<?php
// Copy to config.local.php. Never commit credentials.
return [
    'app_url' => 'http://localhost/SAD', // Use your public HTTPS URL in production.
    'mail_from' => '', // Valid sender address; configure PHP mail() in php.ini.
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'ncpcs_isms',
    'db_user' => 'root',
    'db_password' => '',
    'openai_api_key' => '',
    'openai_model' => 'gpt-4o-mini',
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-3.8-flash',
    'meta_api_key' => '',
    'meta_api_keys' => [], // Optional backup keys, tried only after an auth or quota error.
    'meta_model' => 'muse-spark-1.3',
    'ollama_api_key' => '', // Server-side Ollama Cloud key.
    'ollama_enabled' => false, // Enable after adding your Ollama Cloud API key.
    'ollama_url' => 'https://ollama.com',
    'ollama_model' => 'gemma4:31b',
    'ollama_timeout' => 300, // Request timeout in seconds.
    'ai_default_provider' => 'ollama',
];
