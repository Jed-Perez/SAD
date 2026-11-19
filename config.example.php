<?php
// Copy to config.local.php. Never commit credentials.
return [
    'app_url' => 'http://localhost/SAD', // Use your public HTTPS URL in production.
    'mail_from' => '', // For Gmail SMTP, use the same Gmail address as smtp_username.
    'mail_transport' => 'mail', // Set to smtp for Gmail password-reset delivery.
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl', // ssl (implicit TLS) or tls (STARTTLS); encryption is required.
    'smtp_username' => '',
    'smtp_password' => '', // Google App Password, not your normal Google password.
    'notification_email_enabled' => false, // Enable only after testing mail delivery; run app/Advanced/reminders.php on a schedule.
    'google_client_id' => '', // Google OAuth client of type Web application.
    'google_client_secret' => '', // Redirect URI: app_url + /google-auth.php.
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
