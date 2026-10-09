<?php
namespace Semizzy\Addons\AIChatbot\Services;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
class AIProviderService {
 public function enabledProviders():array{return DB::table('ai_chatbot_providers')->where('enabled',true)->orderBy('priority')->orderBy('id')->get()->map(fn($p)=>(array)$p)->all();}
 public function test(array $p):array{try{$r=$this->complete($p,[['role'=>'user','content'=>'Reply with OK.']],'Connection test only.');return ['ok'=>trim($r['content'])!=='','message'=>'Provider returned a valid response.'];}catch(Throwable $e){return ['ok'=>false,'message'=>$this->safeError($e)];}}
 public function answer(array $messages,string $system):array{$providers=$this->enabledProviders();if(!$providers)throw new RuntimeException('The AI assistant is not configured yet.');$last=null;foreach($providers as $p){try{return $this->complete($p,$messages,$system);}catch(Throwable $e){$last=$e;}}throw new RuntimeException($last?$this->safeError($last):'No configured AI provider is available.');}
 private function complete(array $p,array $messages,string $system):array{
  $driver=strtolower((string)($p['driver']??''));$key=Crypt::decryptString((string)($p['api_key_encrypted']??''));$model=trim((string)($p['model']??''));if(!$key||!$model)throw new RuntimeException('Provider credentials or model are missing.');
  $timeout=max(5,min(60,(int)($p['timeout_seconds']??20)));$max=max(32,min(4000,(int)($p['max_output_tokens']??600)));
  $messages=array_map(static fn($m)=>['role'=>in_array($m['role']??'',['assistant','user'],true)?$m['role']:'user','content'=>mb_substr((string)($m['content']??''),0,4000)],$messages);
  if($driver==='openai'){$r=Http::timeout($timeout)->connectTimeout(8)->withToken($key)->acceptJson()->post('https://api.openai.com/v1/chat/completions',['model'=>$model,'messages'=>array_merge([['role'=>'system','content'=>$system]],$messages),'max_tokens'=>$max]);$r->throw();$content=(string)$r->json('choices.0.message.content','');$u=$r->json('usage',[]);}
  elseif($driver==='anthropic'){$r=Http::timeout($timeout)->connectTimeout(8)->withHeaders(['x-api-key'=>$key,'anthropic-version'=>'2023-06-01'])->acceptJson()->post('https://api.anthropic.com/v1/messages',['model'=>$model,'system'=>$system,'messages'=>$messages,'max_tokens'=>$max]);$r->throw();$content=(string)collect($r->json('content',[]))->where('type','text')->pluck('text')->implode("\n");$u=$r->json('usage',[]);}
  elseif($driver==='gemini'){$items=array_map(static fn($m)=>['role'=>$m['role']==='assistant'?'model':'user','parts'=>[['text'=>$m['content']]]],$messages);$r=Http::timeout($timeout)->connectTimeout(8)->withHeaders(['x-goog-api-key'=>$key])->acceptJson()->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent',['systemInstruction'=>['parts'=>[['text'=>$system]]],'contents'=>$items,'generationConfig'=>['maxOutputTokens'=>$max]]);$r->throw();$content=(string)$r->json('candidates.0.content.parts.0.text','');$u=$r->json('usageMetadata',[]);}
  else throw new RuntimeException('Unsupported AI provider.');
  if(trim($content)==='')throw new RuntimeException('AI provider returned an empty response.');
  return ['content'=>mb_substr(trim($content),0,16000),'provider'=>$driver,'model'=>$model,'input_tokens'=>$u['prompt_tokens']??$u['input_tokens']??$u['promptTokenCount']??null,'output_tokens'=>$u['completion_tokens']??$u['output_tokens']??$u['candidatesTokenCount']??null];
 }
 private function safeError(Throwable $e):string{$m=strtolower($e->getMessage());if(str_contains($m,'401')||str_contains($m,'403')||str_contains($m,'api key')||str_contains($m,'unauthorized'))return 'The AI provider rejected its credentials. Check the provider configuration.';if(str_contains($m,'429')||str_contains($m,'rate limit'))return 'The AI provider is rate-limiting requests. Please try again shortly.';if(str_contains($m,'timeout'))return 'The AI provider did not respond in time. Please try again.';return 'The AI provider is temporarily unavailable. Please try again later.';}
}
