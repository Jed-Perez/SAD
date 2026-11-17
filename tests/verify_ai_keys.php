<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';

function check_model_access(string $label,string $url,array $headers,string $model): bool {
    $ch=curl_init($url);
    if (!$ch) { echo "$label: could not start connection\n"; return false; }
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20]);
    $body=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $connectionError=curl_error($ch);
    curl_close($ch);
    if ($body===false) { echo "$label: connection error ($connectionError)\n"; return false; }
    if ($status!==200) { echo "$label: HTTP $status\n"; return false; }
    $data=json_decode($body,true);
    $models=$data['data'] ?? $data['models'] ?? [];
    $found=false;
    foreach ($models as $entry) {
        if (in_array($entry['id'] ?? $entry['name'] ?? '',[$model,'models/'.$model],true)) { $found=true; break; }
    }
    echo "$label: authenticated; $model ".($found?'listed':'not listed on this page')."\n";
    return $found;
}

$ok=true;
foreach (meta_api_keys() as $index=>$key) {
    $ok=check_model_access('Meta key '.($index+1),'https://api.meta.ai/v1/models',['Authorization: Bearer '.$key],'muse-spark-1.3') && $ok;
}
if (!empty($config['gemini_api_key'])) {
    $ok=check_model_access('Gemini','https://generativelanguage.googleapis.com/v1beta/models',['x-goog-api-key: '.$config['gemini_api_key']],'gemini-3.8-flash') && $ok;
}
exit($ok?0:1);
