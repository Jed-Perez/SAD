<?php
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/planner.php';
function verify(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo "PASS $message\n";}
$plan=['is_floor_plan'=>true,'summary'=>'Test floor plan','cameras'=>[['label'=>'Gate','type'=>'outdoor','x'=>10,'y'=>20,'direction'=>90,'reason'=>'Entry approach'],['label'=>'Hall','type'=>'indoor','x'=>50,'y'=>50,'direction'=>0,'reason'=>'Shared circulation']],'warnings'=>[]];
$valid=validate_plan($plan);verify(count($valid['cameras'])===2,'Camera plan validates');
foreach ([['x'=>101],['direction'=>-1],['type'=>'invented']] as $change){$bad=$plan;$bad['cameras'][0]=array_merge($bad['cameras'][0],$change);try{validate_plan($bad);throw new RuntimeException('Invalid camera accepted');}catch(AppError $e){verify(true,'Reject invalid camera '.json_encode($change));}}
$rates=['labor_per_camera'=>'850','setup_labor'=>'1500','cable_meters_per_camera'=>'20','contingency_percent'=>'10','price_note'=>'Test rates'];
$prices=['CAM-OUT-4MP'=>2450,'CAM-IN-4MP'=>2150,'NVR-8-POE'=>6800,'HDD-1TB-SUR'=>2850,'CAT6-M'=>25,'INSTALL-KIT'=>250];$catalog=[];
foreach($prices as $sku=>$price)$catalog[]=['sku'=>$sku,'name'=>$sku,'price'=>$price,'stock'=>1];
$estimate=build_estimate($valid,$rates,$catalog);
verify($estimate['equipment']==15750,'Equipment includes cameras, NVR, HDD, cable and mounting kits');
verify($estimate['installation_labor']==1700 && $estimate['setup_labor']==1500,'Labor calculation uses saved rates');
verify($estimate['total']==20845,'Total includes 10 percent contingency with cent rounding');
verify(count($estimate['warnings'])===2,'Cable and installation kits flag insufficient stock');
try{build_estimate($valid,$rates,[]);throw new RuntimeException('Missing catalog accepted');}catch(AppError $e){verify(true,'Missing catalog cannot produce a misleading total');}
$previousConfig=$config;
$config['ollama_enabled']=false;
$config['openai_api_key']='test-only';$config['gemini_api_key']='test-only';$config['meta_api_key']='test-only';
verify(choose_plan_providers('auto')===['openai','gemini','meta'],'Automatic mode uses all configured providers');
verify(choose_plan_providers('gemini')===['gemini'],'Gemini can be selected directly');
verify(choose_plan_providers('meta')===['meta'],'Meta can be selected directly');
$config['meta_api_key']='';$config['meta_api_keys']=['key-one','key-two','key-three'];
verify(meta_api_keys()===['key-one','key-two','key-three'] && in_array('meta',configured_ai_providers(),true),'Meta accepts multiple backup keys');
$attempted=[];$metaResult=with_meta_key_fallback(meta_api_keys(),function($key)use(&$attempted,$valid){$attempted[]=$key;if($key!=='key-three')throw new AiProviderHttpError('Key unavailable',401);return $valid;});
verify($attempted===['key-one','key-two','key-three'] && $metaResult===$valid,'Meta retries another key after authentication failures');
$attempted=[];try{with_meta_key_fallback(meta_api_keys(),function($key)use(&$attempted){$attempted[]=$key;throw new AiProviderHttpError('Service failure',500);});throw new RuntimeException('Meta retried server failure');}catch(AiProviderHttpError $e){verify($attempted===['key-one'],'Meta does not retry after server failure');}
try{choose_plan_providers('unknown');throw new RuntimeException('Invalid provider accepted');}catch(AppError $e){verify($e->getCode()===0 || $e->getCode()===422,'Unknown provider rejected');}
$config['openai_api_key']='';
verify(choose_plan_providers('auto')===['gemini','meta'],'Gemini and Meta work without an OpenAI key');
try{choose_plan_providers('openai');throw new RuntimeException('Unconfigured provider accepted');}catch(AppError $e){verify($e->getCode()===503,'Unconfigured provider rejected');}
$config['openai_api_key']='test-only';
$openai=['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($plan)]]]]];
$gemini=['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>json_encode($plan)]]]]]];
verify(count(parse_openai_plan($openai)['cameras'])===2,'OpenAI structured response parses');
verify(count(parse_responses_plan($openai,'Meta')['cameras'])===2,'Meta structured response parses');
verify(count(parse_gemini_plan($gemini)['cameras'])===2,'Gemini structured response parses');
$tried=[];$fallback=run_ai_providers(['openai','gemini'],function($provider)use(&$tried,$valid){$tried[]=$provider;if($provider==='openai')throw new AiProviderError('Provider unavailable',502);return $valid;});
verify($tried===['openai','gemini'] && $fallback['_provider']==='gemini','Automatic mode falls back after provider failure');
$tried=[];$fallback=run_ai_providers(['openai','gemini','meta'],function($provider)use(&$tried,$valid){$tried[]=$provider;if($provider!=='meta')throw new AiProviderError('Provider unavailable',502);return $valid;});
verify($tried===['openai','gemini','meta'] && $fallback['_provider']==='meta','Automatic mode can fall back to Meta');
$tried=[];try{run_ai_providers(['openai','gemini'],function($provider)use(&$tried){$tried[]=$provider;fail('Unreadable floor plan.',422);});throw new RuntimeException('Invalid plan accepted');}catch(AppError $e){verify($tried===['openai'] && $e->getCode()===422,'Unreadable plan does not trigger fallback or a second upload');}
try{parse_gemini_plan(['candidates'=>[['finishReason'=>'SAFETY']]]);throw new RuntimeException('Safety refusal accepted');}catch(AppError $e){verify($e->getCode()===422,'Gemini safety refusal stops analysis');}
$attempts=0;$delays=[];
$result=with_ai_http_retry(function()use(&$attempts){$attempts++;if($attempts===1)throw ai_http_error('gemini',503);return ['ok'=>true];},function($ms)use(&$delays){$delays[]=$ms;});
verify($result['ok'] && $attempts===2 && count($delays)===1 && $delays[0]>=1000 && $delays[0]<=1250,'Temporary Gemini failure retries once with bounded delay');
$attempts=0;
try{with_ai_http_retry(function()use(&$attempts){$attempts++;throw ai_http_error('gemini',503);},fn($ms)=>null);throw new RuntimeException('Persistent failure accepted');}catch(AiProviderHttpError $e){verify($attempts===2 && $e->providerStatus===503 && str_contains($e->getMessage(),'temporarily unavailable') && !str_contains($e->getMessage(),'billing'),'Persistent 503 stops after two attempts and identifies a temporary outage');}
foreach([400,401,403,404,429] as $status){$attempts=0;try{with_ai_http_retry(function()use(&$attempts,$status){$attempts++;throw ai_http_error('gemini',$status);},fn($ms)=>null);throw new RuntimeException('Error accepted');}catch(AiProviderHttpError $e){verify($attempts===1 && $e->providerStatus===$status,'Does not retry HTTP '.$status);}}
verify(str_contains(ai_http_error('gemini',429)->getMessage(),'rate or quota'),'Quota failure has a specific explanation');
verify(str_contains(ai_http_error('gemini',400,['error'=>['details'=>[['reason'=>'API_KEY_INVALID']]]])->getMessage(),'rejected the API key'),'Invalid key is distinguished from malformed requests');
verify(str_contains(ai_connection_error('gemini',CURLE_OPERATION_TIMEDOUT)->getMessage(),'too long'),'Network timeout has a specific explanation');
verify(str_contains(ai_connection_error('gemini',60)->getMessage(),'certificate'),'Certificate failure keeps TLS verification enabled and explains configuration');
try{run_ai_providers(['openai','gemini'],fn($provider)=>throw ai_http_error($provider,503));throw new RuntimeException('All failures accepted');}catch(AppError $e){verify(str_contains($e->getMessage(),'Gemini is temporarily unavailable'),'Automatic mode preserves the final provider failure explanation');}
$config['ollama_enabled']=true;$config['ai_default_provider']='ollama';$config['ollama_model']='gemma4:31b';$config['ollama_api_key']='test-only';
verify(choose_plan_providers('auto')===['ollama'],'Automatic prefers Ollama Cloud alone');
verify(choose_plan_providers('ollama')===['ollama'] && default_ai_provider()==='ollama','Ollama Cloud can be the default');
$tried=[];try{run_ai_providers(choose_plan_providers('auto'),function($provider)use(&$tried){$tried[]=$provider;throw new AiProviderError('Offline',503);});throw new RuntimeException('Failure accepted');}catch(AiProviderError $e){verify($tried===['ollama'],'Ollama failure does not send plans to another provider');}
$payload=ollama_plan_payload(['notes'=>'Test','budget'=>0],base64_encode('synthetic-image'));
verify($payload['messages'][0]['images']===[base64_encode('synthetic-image')] && !isset($payload['format']) && $payload['stream']===false,'Cloud payload includes image without unsupported format field');
verify(str_contains($payload['messages'][0]['content'],'JSON schema:') && $payload['options']['num_predict']===4500,'Cloud prompt requests bounded JSON output');
$response=['done'=>true,'done_reason'=>'stop','message'=>['content'=>json_encode($plan)]];
verify(count(parse_ollama_plan($response)['cameras'])===2,'Cloud response validates');
verify(count(parse_ollama_plan(['done'=>true,'message'=>['content'=>"```json\n".json_encode($plan)."\n```"]])['cameras'])===2,'Fenced JSON response validates');
foreach([['done'=>false],['done_reason'=>'length'],['message'=>['content'=>'broken JSON']]] as $change){try{parse_ollama_plan(array_replace($response,$change));throw new RuntimeException('Incomplete result accepted');}catch(AiProviderError $e){verify(true,'Incomplete or malformed output is rejected');}}
$bad=$plan;$bad['cameras'][0]['x']=101;
try{parse_ollama_plan(['done'=>true,'message'=>['content'=>json_encode($bad)]]);throw new RuntimeException('Invalid coordinates accepted');}catch(AppError $e){verify(true,'Cloud coordinates are validated');}
foreach(['https://example.com','http://127.0.0.1:11434','https://ollama.com@evil.test','https://ollama.com/path','https://ollama.com?proxy=1'] as $url){$config['ollama_url']=$url;try{ollama_base_url();throw new RuntimeException('Unsafe endpoint accepted');}catch(AiProviderError $e){verify(true,'Reject non-Ollama endpoint before sending key');}}
$config['ollama_url']='https://ollama.com';verify(ollama_base_url()===$config['ollama_url'],'Official HTTPS endpoint accepted');
$config['ollama_api_key']='';verify(!in_array('ollama',configured_ai_providers(),true),'Missing key does not advertise ready provider');
try{ollama_post_json('/api/chat',[],30);throw new RuntimeException('Missing key accepted');}catch(AiProviderError $e){verify($e->getCode()===503,'Missing key fails before network call');}
$config['ollama_enabled']=false;try{choose_plan_providers('ollama');throw new RuntimeException('Disabled Ollama accepted');}catch(AppError $e){verify($e->getCode()===503,'Disabled provider cannot be selected');}
$config=$previousConfig;
echo "Unit checks passed.\n";
