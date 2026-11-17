<?php
declare(strict_types=1);

function ai_http_error(string $provider, int $status, array $response = []): AiProviderHttpError {
    $label=['gemini'=>'Gemini','openai'=>'OpenAI','meta'=>'Meta'][$provider] ?? 'AI provider';
    $invalidKey=false;
    foreach ($response['error']['details'] ?? [] as $detail) {
        if (is_array($detail) && ($detail['reason'] ?? '')==='API_KEY_INVALID') $invalidKey=true;
    }
    $message=match (true) {
        $invalidKey || $status===401 => "$label rejected the API key. Ask the administrator to check its configuration.",
        $status===403 => "$label denied access. Ask the administrator to check the API key restrictions and model permissions.",
        $status===404 => "$label could not find the configured model. Ask the administrator to check the model setting.",
        $status===429 => "$label has reached a rate or quota limit. Try later, choose another configured provider, or ask the administrator to check the quota.",
        in_array($status,[500,502,503,504],true) => "$label is temporarily unavailable (HTTP $status). Please try again shortly or choose another configured provider.",
        $status===400 => "$label rejected the analysis request (HTTP 400). Ask the administrator to check the model and request configuration.",
        default => "$label could not complete the request (HTTP $status). Please contact the administrator.",
    };
    return new AiProviderHttpError($message,$status);
}

function ai_connection_error(string $provider, int $errno): AiProviderError {
    $label=['gemini'=>'Gemini','openai'=>'OpenAI','meta'=>'Meta'][$provider] ?? 'AI provider';
    // libcurl 60/77 are certificate verification / CA-file errors; PHP builds expose different aliases.
    $message=match ($errno) {
        CURLE_OPERATION_TIMEDOUT => "$label took too long to respond. Please try again or choose another configured provider.",
        CURLE_COULDNT_RESOLVE_HOST => "The server could not resolve $label's address. Ask the administrator to check the internet connection and DNS.",
        CURLE_COULDNT_CONNECT => "The server could not connect to $label. Ask the administrator to check the internet connection, firewall, or proxy.",
        60, 77 => "The server could not verify $label's secure connection. Ask the administrator to check PHP's CA certificate settings.",
        default => "$label's connection failed. Please try again or choose another configured provider.",
    };
    return new AiProviderError($message,502);
}

/** One short retry for transient HTTP failures; never repeat an auth/quota error. */
function with_ai_http_retry(callable $request, ?callable $wait = null): array {
    $wait ??= static fn(int $milliseconds) => usleep($milliseconds*1000);
    for ($attempt=0; $attempt<2; $attempt++) {
        try { return $request(); }
        catch (AiProviderHttpError $error) {
            if ($attempt===1 || !in_array($error->providerStatus,[500,502,503,504],true)) throw $error;
            error_log('AI temporary HTTP failure '.$error->providerStatus.'; retrying once.');
            $wait(1000+random_int(0,250));
        }
    }
    throw new AiProviderError('AI request did not finish.',502);
}

function ai_post_json(string $provider, string $url, array $headers, array $payload): array {
    // Shared across retries, backup keys, and automatic provider fallback in this request.
    // Leave time inside the 180-second analysis limit to save the result/error state.
    static $deadline=null;
    $deadline ??= microtime(true)+150;
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    return with_ai_http_retry(function() use ($provider,$url,$headers,$json,$deadline): array {
        $remaining=(int)floor($deadline-microtime(true));
        if ($remaining<1) throw new AiProviderError('The analysis time limit was reached. Please try again with one provider selected.',502);
        $ch=curl_init($url);
        if (!$ch) throw new AiProviderError('AI connection could not be started.',502);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>min(15,$remaining),CURLOPT_TIMEOUT=>min(80,$remaining),CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$json]);
        $body=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $errno=curl_errno($ch);
        curl_close($ch);
        if ($body===false) {
            // Do not log request headers, credentials, blueprint contents, or raw errors.
            error_log("$provider connection failed; cURL error $errno");
            throw ai_connection_error($provider,$errno);
        }
        $response=json_decode($body,true);
        if ($status<200 || $status>=300) {
            error_log("$provider HTTP $status");
            throw ai_http_error($provider,$status,is_array($response)?$response:[]);
        }
        if (!is_array($response)) throw new AiProviderError(ucfirst($provider).' returned an invalid response.',502);
        return $response;
    });
}
