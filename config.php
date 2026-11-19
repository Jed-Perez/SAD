<?php
declare(strict_types=1);
$config = [
    'app_url' => 'https://ncpcs.shop', // Canonical public base URL, e.g. https://example.com/SAD
    'notification_email_enabled' => false,
    'mail_from' => '', // Sender address; for Gmail SMTP, use the same Gmail address as smtp_username.
    'mail_transport' => 'mail', // Set to smtp to use Gmail SMTP for password-reset delivery.
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl', // ssl (implicit TLS) or tls (STARTTLS).
    'smtp_username' => '', // Your Gmail address.
    'smtp_password' => '', // Google App Password, not your normal Google password.
    'google_client_id' => '',
    'google_client_secret' => '', // Server-side OAuth secret, never sent to the browser.
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_port' => getenv('DB_PORT') ?: '3306',
    'db_name' => getenv('DB_NAME') ?: 'u920307677_sad_db',
    'db_user' => getenv('DB_USER') ?: 'u920307677_sad_user',
    'db_password' => 'YOUR_DATABASE_PASSWORD',
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'openai_model' => getenv('OPENAI_MODEL') ?: 'gpt-4o-mini',
    'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
    'gemini_model' => getenv('GEMINI_MODEL') ?: 'gemini-3.8-flash',
    'meta_api_key' => getenv('META_API_KEY') ?: '',
    'meta_api_keys' => [],
    'meta_model' => getenv('META_MODEL') ?: 'muse-spark-1.3',
    'ollama_api_key' => '', // Server-side Ollama Cloud key.
    'ollama_enabled' => false,
    'ollama_url' => 'https://ollama.com',
    'ollama_model' => 'gemma4:31b',
    'ollama_timeout' => 300,
    'ai_default_provider' => 'auto',
];
if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace($config, require __DIR__ . '/config.local.php');
}
// Explicit environment values take precedence, including an empty API key in tests.
foreach (['DB_HOST'=>'db_host','DB_PORT'=>'db_port','DB_NAME'=>'db_name','DB_USER'=>'db_user','DB_PASSWORD'=>'db_password','OPENAI_API_KEY'=>'openai_api_key','OPENAI_MODEL'=>'openai_model','GEMINI_API_KEY'=>'gemini_api_key','GEMINI_MODEL'=>'gemini_model','META_API_KEY'=>'meta_api_key','META_MODEL'=>'meta_model'] as $env=>$key) {
    if (getenv($env)!==false) $config[$key]=getenv($env);
}
if (getenv('META_API_KEYS')!==false) $config['meta_api_keys']=explode(',',getenv('META_API_KEYS'));
foreach (['MAIL_TRANSPORT'=>'mail_transport','SMTP_HOST'=>'smtp_host','SMTP_PORT'=>'smtp_port','SMTP_ENCRYPTION'=>'smtp_encryption','SMTP_USERNAME'=>'smtp_username','SMTP_PASSWORD'=>'smtp_password'] as $env=>$key) {
    if (getenv($env)!==false) $config[$key]=getenv($env);
}
foreach (['APP_URL'=>'app_url','MAIL_FROM'=>'mail_from','GOOGLE_CLIENT_ID'=>'google_client_id','GOOGLE_CLIENT_SECRET'=>'google_client_secret'] as $env=>$key) {
    if (getenv($env)!==false) $config[$key]=getenv($env);
}
foreach (['OLLAMA_API_KEY'=>'ollama_api_key','OLLAMA_URL'=>'ollama_url','OLLAMA_MODEL'=>'ollama_model','OLLAMA_TIMEOUT'=>'ollama_timeout','AI_DEFAULT_PROVIDER'=>'ai_default_provider'] as $env=>$key) {
    if (getenv($env)!==false) $config[$key]=getenv($env);
}
if (getenv('OLLAMA_ENABLED')!==false) $config['ollama_enabled']=filter_var(getenv('OLLAMA_ENABLED'),FILTER_VALIDATE_BOOL);
return $config;
