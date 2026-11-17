<?php
declare(strict_types=1);
function meta_api_keys(): array {
    global $config;
    $keys=$config['meta_api_keys'] ?? [];
    if (!is_array($keys)) $keys=[];
    array_unshift($keys,$config['meta_api_key'] ?? '');
    return array_values(array_unique(array_filter(array_map(fn($key)=>is_string($key)?trim($key):'', $keys))));
}
function configured_ai_providers(): array {
    global $config;
    $providers=[];
    if (filter_var($config['ollama_enabled'] ?? false,FILTER_VALIDATE_BOOL) && trim((string)($config['ollama_api_key'] ?? ''))!=='') $providers[]='ollama';
    if (!empty($config['openai_api_key'])) $providers[]='openai';
    if (!empty($config['gemini_api_key'])) $providers[]='gemini';
    if (meta_api_keys()) $providers[]='meta';
    return $providers;
}
function automatic_ai_providers(): array {
    $available=configured_ai_providers();
    // Prefer Ollama Cloud without silently switching to a different provider.
    return in_array('ollama',$available,true) ? ['ollama'] : $available;
}
function default_ai_provider(): string {
    global $config;
    $choice=(string)($config['ai_default_provider'] ?? 'auto');
    return in_array($choice,configured_ai_providers(),true) ? $choice : 'auto';
}
function ollama_timeout_seconds(): int {
    global $config;
    return max(30,min(600,(int)($config['ollama_timeout'] ?? 300)));
}
