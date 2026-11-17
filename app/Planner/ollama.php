<?php
declare(strict_types=1);

function ollama_base_url(): string {
    global $config;
    $url=rtrim((string)($config['ollama_url'] ?? 'https://ollama.com'),'/');
    if ($url!=='https://ollama.com') {
        throw new AiProviderError('Ollama Cloud must use https://ollama.com.',503);
    }
    return $url;
}

function ollama_model_name(): string {
    global $config;
    $model=(string)($config['ollama_model'] ?? 'gemma4:31b');
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:\/-]{0,119}$/D',$model)) {
        throw new AiProviderError('Choose an Ollama Cloud vision model, such as gemma4:31b.',503);
    }
    return $model;
}

function ollama_post_json(string $path, array $payload, int $timeout): array {
    global $config;
    $key=trim((string)($config['ollama_api_key'] ?? ''));
    if($key==='')throw new AiProviderError('Ollama Cloud needs an API key. Ask the administrator to set OLLAMA_API_KEY on the server.',503);
    $ch=curl_init(ollama_base_url().$path);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>$timeout,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    $body=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if ($body===false) {
        error_log('Ollama connection failed; cURL error '.$errno);
        if ($errno===CURLE_OPERATION_TIMEDOUT) throw new AiProviderError('Ollama Cloud analysis took too long. Please try again.',504);
        throw new AiProviderError('Ollama Cloud is unreachable. Check the server internet connection and TLS certificates.',503);
    }
    if ($status===401 || $status===403) throw new AiProviderError('Ollama Cloud rejected the API key or model access. Check the server API key and account access.',503);
    if ($status===429) throw new AiProviderError('Ollama Cloud usage or rate limit reached. Check your account usage or try again later.',503);
    if ($status===404) throw new AiProviderError('The configured Ollama Cloud model is unavailable. Ask the administrator to choose an available vision model.',503);
    if ($status<200 || $status>=300) {
        error_log('Ollama HTTP '.$status);
        throw new AiProviderError('Ollama Cloud could not run the model (HTTP '.$status.'). Please try again later.',502);
    }
    $response=json_decode($body,true);
    if (!is_array($response) || isset($response['error'])) throw new AiProviderError('Ollama returned an invalid response. Please try again.',502);
    return $response;
}

function ollama_plan_payload(array $blueprint, string $image): array {
    return [
        'model'=>ollama_model_name(),
        'messages'=>[['role'=>'user','content'=>plan_prompt($blueprint).' Return only the JSON object. Keep each reason to one short sentence and the summary to two sentences. JSON schema: '.json_encode(plan_schema()),'images'=>[$image]]],
        // Cloud does not support the local structured-output format option.
        'stream'=>false,'think'=>false,
        'options'=>['temperature'=>0,'num_predict'=>4500],
    ];
}

function parse_ollama_plan(array $response): array {
    if (($response['done'] ?? false)!==true || ($response['done_reason'] ?? 'stop')!=='stop') {
        throw new AiProviderError('Ollama did not finish the plan. Try a clearer, simpler floor-plan image.',502);
    }
    $text=$response['message']['content'] ?? null;
    if(is_string($text))$text=preg_replace('/^```(?:json)?\s*([\s\S]*?)\s*```$/i','$1',trim($text));
    $plan=is_string($text) ? json_decode($text,true) : null;
    if (!is_array($plan)) throw new AiProviderError('Ollama returned an invalid plan. Try the analysis again.',502);
    return validate_plan($plan);
}

function ollama_plan(array $blueprint, string $image): array {
    $response=ollama_post_json('/api/chat',ollama_plan_payload($blueprint,$image),ollama_timeout_seconds());
    return parse_ollama_plan($response);
}
