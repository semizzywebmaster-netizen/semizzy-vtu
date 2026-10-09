<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ProviderConnection;
use App\Models\ProviderCredential;
use App\Models\ProviderEndpoint;
use App\Models\ProviderService;
use App\Models\ProviderServiceImport;
use App\Models\ProviderServiceMapping;
use App\Models\ProviderServiceProduct;
use App\Models\ServiceProduct;
use App\Services\Audit\AuditLogger;
use App\Models\ProviderHealthCheck;
use App\Models\ProviderOperationLog;
use App\Models\ProviderCategory;
use App\Models\ProviderSubcategory;
use App\Models\Service;
use App\Services\Providers\ProviderUrlGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProviderEngineController extends Controller
{
    private const AUTH_TYPES = [
        'none','api_token','token','api_key','api_key_secret','username_password',
        'username_password_pin','client_id_secret','bearer','basic','custom',
    ];

    private function redactForLog(mixed $value): mixed
    {
        if (is_array($value)) {
            $out=[];
            foreach ($value as $key=>$item) {
                $name=strtolower((string)$key);
                $out[$key]=preg_match('/token|secret|password|passwd|pin|api[_-]?key|authorization|auth|credential|private[_-]?key|signature|cookie|session|jwt|webhook|client[_-]?id|username/i',$name) ? '[REDACTED]' : $this->redactForLog($item);
            }
            return $out;
        }
        return is_string($value) && strlen($value)>200 ? substr($value,0,200).'…' : $value;
    }

    public function catalogueManager(Request $request, ApiProvider $provider): Response
    {
        $provider->loadMissing('categories');
        return Inertia::render('Admin/ProviderCatalogueManager', [
            'canMapProducts' => $request->user()?->hasPermission('providers.manage') && $request->user()?->hasPermission('catalogue.manage'),
            'provider' => [
                'id' => $provider->id,
                'identifier' => $provider->identifier,
                'display_name' => $provider->display_name,
                'verification_status' => $provider->verification_status,
                'integration_status' => $provider->integration_status,
                'enabled' => (bool) $provider->enabled,
                'paused' => (bool) $provider->paused,
                'service_categories' => $provider->service_categories ?? [],
                'capabilities' => $provider->capabilities ?? [],
            ],
            'platformServices' => Service::query()
                ->with([
                    'category',
                    'products' => fn ($query) => $query->where('enabled', false)->where('publication_status', '!=', 'published')->orderBy('name'),
                ])
                ->where('enabled', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'key' => $service->key,
                    'name' => $service->name,
                    'category' => $service->category?->name ?? 'Uncategorised',
                    'products' => $service->products->map(fn (ServiceProduct $product) => [
                        'id' => $product->id,
                        'key' => $product->key,
                        'name' => $product->name,
                        'publication_status' => $product->publication_status,
                    ])->values(),
                ])
                ->values(),
        ]);
    }

    public function connections(ApiProvider $provider): JsonResponse
    {
        return response()->json([
            'data'=>$provider->connections()->with('credentials')->orderByDesc('is_default')->latest()->get()
                ->map(fn(ProviderConnection $connection)=>$this->connectionSummary($connection))
                ->values(),
        ]);
    }

    public function authSchema(): JsonResponse
    {
        return response()->json(['data'=>[
            ['value'=>'none','label'=>'No authentication','fields'=>[]],
            ['value'=>'api_token','label'=>'API Token','fields'=>[['key'=>'api_token','label'=>'API Token','type'=>'password','secret'=>true,'required'=>true,'placement'=>'header','header_name'=>'Authorization','prefix'=>'Bearer']]],
            ['value'=>'token','label'=>'Token','fields'=>[['key'=>'token','label'=>'Token','type'=>'password','secret'=>true,'required'=>true,'placement'=>'header','header_name'=>'Authorization','prefix'=>'Token']]],
            ['value'=>'api_key','label'=>'API Key','fields'=>[['key'=>'api_key','label'=>'API Key','type'=>'password','secret'=>true,'required'=>true,'placement'=>'header','header_name'=>'X-API-Key','prefix'=>'']]],
            ['value'=>'api_key_secret','label'=>'API Key + Secret','fields'=>[['key'=>'api_key','label'=>'API Key','type'=>'password','secret'=>true,'required'=>true,'placement'=>'header','header_name'=>'X-API-Key','prefix'=>''],['key'=>'api_secret','label'=>'API Secret','type'=>'password','secret'=>true,'required'=>true,'placement'=>'header','header_name'=>'X-API-Secret','prefix'=>'']]],
            ['value'=>'username_password','label'=>'Username + Password','fields'=>[['key'=>'username','label'=>'Username','type'=>'text','secret'=>false,'required'=>true,'placement'=>'body','body_path'=>'username'],['key'=>'password','label'=>'Password','type'=>'password','secret'=>true,'required'=>true,'placement'=>'body','body_path'=>'password']]],
            ['value'=>'username_password_pin','label'=>'Username + Password + PIN','fields'=>[['key'=>'username','label'=>'Username','type'=>'text','secret'=>false,'required'=>true,'placement'=>'body','body_path'=>'username'],['key'=>'password','label'=>'Password','type'=>'password','secret'=>true,'required'=>true,'placement'=>'body','body_path'=>'password'],['key'=>'pin','label'=>'PIN','type'=>'password','secret'=>true,'required'=>true,'placement'=>'body','body_path'=>'pin']]],
            ['value'=>'client_id_secret','label'=>'Client ID + Secret','fields'=>[['key'=>'client_id','label'=>'Client ID','type'=>'text','secret'=>false,'required'=>true,'placement'=>'body','body_path'=>'client_id'],['key'=>'client_secret','label'=>'Client Secret','type'=>'password','secret'=>true,'required'=>true,'placement'=>'body','body_path'=>'client_secret']]],
            ['value'=>'bearer','label'=>'Bearer / Access Token','fields'=>[['key'=>'access_token','label'=>'Access Token','type'=>'password','secret'=>true,'required'=>true,'placement'=>'header','header_name'=>'Authorization','prefix'=>'Bearer']]],
            ['value'=>'basic','label'=>'HTTP Basic','fields'=>[['key'=>'username','label'=>'Username','type'=>'text','secret'=>false,'required'=>true,'placement'=>'authorization'],['key'=>'password','label'=>'Password','type'=>'password','secret'=>true,'required'=>true,'placement'=>'authorization']]],
            ['value'=>'custom','label'=>'Custom authentication','fields'=>[]],
        ]]);
    }

    public function storeConnection(Request $request, ApiProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'name'=>'required|string|max:120',
            'environment'=>'required|in:sandbox,production',
            'base_url'=>'required|url|max:2048',
            'api_version'=>'nullable|string|max:100',
            'api_prefix'=>'nullable|string|max:255',
            'auth_type'=>'nullable|in:'.implode(',',self::AUTH_TYPES),
            'auth_options'=>'nullable|array',
            'connect_timeout_seconds'=>'nullable|integer|min:1|max:120',
            'request_timeout_seconds'=>'nullable|integer|min:1|max:300',
            'verify_ssl'=>'nullable|boolean',
            'headers'=>'nullable|array',
            'query_params'=>'nullable|array',
            'proxy'=>'nullable|array',
            'is_default'=>'nullable|boolean',
        ]);

        app(ProviderUrlGuard::class)->validate($data['base_url']);

        $connection = DB::transaction(function() use ($provider,$data) {
            if (($data['is_default'] ?? false)) {
                $provider->connections()->update(['is_default'=>false]);
            }
            return $provider->connections()->create($data);
        });

        return response()->json(['data'=>$this->connectionSummary($connection->fresh('credentials'))],201);
    }

    public function storeAuthentication(Request $request, ApiProvider $provider, ProviderConnection $connection): JsonResponse
    {
        if ((int) $connection->api_provider_id !== (int) $provider->id) {
            return response()->json(['message'=>'Provider connection does not belong to this provider.'],404);
        }
        $data=$request->validate([
            'auth_type'=>'required|in:'.implode(',',self::AUTH_TYPES),
            'auth_options'=>'nullable|array',
            'credentials'=>'nullable|array',
            'credentials.*.field_key'=>'required|string|max:120|regex:/^[A-Za-z0-9_.-]+$/',
            'credentials.*.label'=>'required|string|max:160',
            'credentials.*.field_type'=>'nullable|string|max:40',
            'credentials.*.required'=>'nullable|boolean',
            'credentials.*.secret'=>'nullable|boolean',
            'credentials.*.placement'=>'required|in:header,query,body,form,authorization',
            'credentials.*.header_name'=>'nullable|string|max:255',
            'credentials.*.query_name'=>'nullable|string|max:255',
            'credentials.*.body_path'=>'nullable|string|max:255',
            'credentials.*.prefix'=>'nullable|string|max:100',
            'credentials.*.value'=>'nullable|string|max:10000',
        ]);

        DB::transaction(function() use ($connection,$data) {
            $connection->update([
                'auth_type'=>$data['auth_type'],
                'auth_options'=>$data['auth_options'] ?? [],
            ]);

            foreach (($data['credentials'] ?? []) as $credentialData) {
                // A masked value means “keep the existing secret”; never store the mask itself.
                if (($credentialData['value'] ?? null) === '••••••••') {
                    unset($credentialData['value']);
                }
                $key=$credentialData['field_key'];
                $existing=$connection->credentials()->where('field_key',$key)->first();
                if (!$existing && !filled($credentialData['value'] ?? null) && ($credentialData['required'] ?? false)) {
                    continue;
                }
                $connection->credentials()->updateOrCreate(['field_key'=>$key],$credentialData);
            }
        });

        return $this->credentialSummary($connection->fresh('credentials'));
    }

    public function storeCredential(Request $request, ApiProvider $provider, ProviderConnection $connection): JsonResponse
    {
        if ((int) $connection->api_provider_id !== (int) $provider->id) {
            return response()->json(['message'=>'Provider connection does not belong to this provider.'],404);
        }
        $data=$request->validate([
            'field_key'=>'required|string|max:120|regex:/^[A-Za-z0-9_.-]+$/',
            'label'=>'required|string|max:160',
            'field_type'=>'nullable|string|max:40',
            'required'=>'nullable|boolean',
            'secret'=>'nullable|boolean',
            'placement'=>'required|in:header,query,body,form,authorization',
            'header_name'=>'nullable|string|max:255',
            'query_name'=>'nullable|string|max:255',
            'body_path'=>'nullable|string|max:255',
            'prefix'=>'nullable|string|max:100',
            'value'=>'nullable|string|max:10000',
        ]);

        if (($data['value'] ?? null) === '••••••••') unset($data['value']);
        $credential=$connection->credentials()->updateOrCreate(['field_key'=>$data['field_key']],$data);
        return $this->credentialSummary($connection->fresh('credentials'));
    }

    public function operationLogs(ApiProvider $provider): JsonResponse
    {
        return response()->json(['data'=>ProviderOperationLog::query()->where('api_provider_id',$provider->id)->latest()->limit(100)->get(['id','provider_connection_id','operation','method','endpoint','internal_reference','http_status','duration_ms','result','error_code','safe_message','safe_metadata','created_at'])]);
    }

    public function credentials(ApiProvider $provider, ProviderConnection $connection): JsonResponse
    {
        if ((int) $connection->api_provider_id !== (int) $provider->id) {
            return response()->json(['message'=>'Provider connection does not belong to this provider.'],404);
        }
        return $this->credentialSummary($connection->load('credentials'));
    }

    public function testConnection(Request $request, ApiProvider $provider): JsonResponse
    {
        $connection=$provider->connections()->where('enabled',true)->orderByDesc('is_default')->first();
        if(!$connection) return response()->json(['status'=>'configuration_required','message'=>'Configure an enabled connection first.'],422);

        $readOnlyOperations = ['health_check','health','status','balance_inquiry','catalogue_retrieval','catalogue','services','products','categories'];
        $endpoint=$provider->endpoints()
            ->where('enabled',true)
            ->whereIn('operation',$readOnlyOperations)
            ->orderByRaw("CASE
                WHEN operation = 'health_check' THEN 0
                WHEN operation = 'health' THEN 1
                WHEN operation = 'status' THEN 2
                WHEN operation = 'balance_inquiry' THEN 3
                WHEN operation = 'catalogue_retrieval' THEN 4
                WHEN operation = 'catalogue' THEN 5
                WHEN operation = 'services' THEN 6
                WHEN operation = 'products' THEN 7
                WHEN operation = 'categories' THEN 8
                ELSE 99 END")
            ->orderBy('id')
            ->first();
        if(!$endpoint) return response()->json(['status'=>'configuration_required','message'=>'Configure an enabled read-only health, status, balance, or catalogue endpoint for connection testing.'],422);

        $started=microtime(true);
        $safeUrl = null;
        try{
            [$headers,$query,$body]=$this->authenticationPayload($connection,(array)($endpoint->request_mapping ?? []),(string)$endpoint->auth_mode);
            $headers=array_merge($headers,(array)($endpoint->headers ?? []));
            $query=array_merge($query,(array)($endpoint->query_params ?? []));
            $url=$this->endpointUrl($connection,$endpoint);
            $safeUrl = $url;
            $client=Http::withHeaders($headers)->connectTimeout($connection->connect_timeout_seconds)->timeout($connection->request_timeout_seconds);
            if(!$connection->verify_ssl)$client=$client->withoutVerifying();
            $response=match($endpoint->method){
                'POST','PUT','PATCH','DELETE'=>$this->sendEndpointRequest($client,$endpoint,$url,$body,$query),
                default=>$client->get($url,$query),
            };
            $duration=(int)((microtime(true)-$started)*1000);
            $status=$response->successful()?'SUCCESS':'FAILED';
            ProviderHealthCheck::create(['api_provider_id'=>$provider->id,'provider_connection_id'=>$connection->id,'status'=>$status,'http_status'=>$response->status(),'response_time_ms'=>$duration,'message'=>$status==='SUCCESS'?'Connection test succeeded.':'Connection test returned an unsuccessful HTTP response.','checked_at'=>now()]);
            $connection->update(['last_tested_at'=>now(),'last_test_status'=>$status,'last_test_message'=>$status==='SUCCESS'?'Connection test succeeded.':'Connection test failed.']);
            $provider->update(['last_tested_at'=>now(),'last_test_status'=>$status,'last_test_summary'=>$status==='SUCCESS'?'Connection test succeeded.':'Connection test failed.','last_successful_request_at'=>$status==='SUCCESS'?now():$provider->last_successful_request_at]);
            $this->writeOperationLog($provider, $connection, 'connection_test', $endpoint->method, $url, $status, $response->status(), $duration, null, $status==='SUCCESS'?'Connection test succeeded.':'Connection test failed.');
            return response()->json(['status'=>$status,'http_status'=>$response->status(),'response_time_ms'=>$duration,'message'=>$status==='SUCCESS'?'Connection test succeeded.':'Connection test failed.']);
        }catch(\Throwable $e){
            Log::warning('Provider operation failed.', ['exception_class' => get_class($e)]);
            $duration=(int)((microtime(true)-$started)*1000);
            ProviderHealthCheck::create(['api_provider_id'=>$provider->id,'provider_connection_id'=>$connection->id,'status'=>'FAILED','response_time_ms'=>$duration,'message'=>'Connection test failed safely.','checked_at'=>now()]);
            $connection->update(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_message'=>'Connection test failed safely.']);
            $provider->update(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_summary'=>'Connection test failed safely.']);
            $this->writeOperationLog($provider, $connection, 'connection_test', $endpoint->method, $safeUrl, 'FAILED', null, $duration, get_class($e), 'Connection test failed safely.');
            return response()->json(['status'=>'FAILED','response_time_ms'=>$duration,'message'=>'Connection test failed safely.'],502);
        }
    }

    public function sync(Request $request, ApiProvider $provider): JsonResponse
    {
        $started=now();
        $syncId=DB::table('provider_syncs')->insertGetId([
            'api_provider_id'=>$provider->id,'trigger'=>'manual','status'=>'running',
            'discovered_count'=>0,'new_count'=>0,'updated_count'=>0,'removed_count'=>0,
            'price_changed_count'=>0,'failed_count'=>0,'summary'=>json_encode([]),'started_at'=>$started,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        try {
            $before=$provider->providerServices()->get()->keyBy('external_service_id');
            $result=$this->discovery($request,$provider);
            $resultData=$result->getData(true);
            if (($resultData['status'] ?? null) !== 'success') {
                throw new \RuntimeException('Provider discovery did not complete successfully.');
            }
            $after=$provider->providerServices()->get()->keyBy('external_service_id');
            $new=0;$updated=0;$priceChanged=0;$removed=0;
            foreach($after as $key=>$service){
                if(!$before->has($key)){ $new++; continue; }
                $old=$before->get($key);
                $changed=($old->name!==$service->name)||($old->description!==$service->description)||($old->status!==$service->status)||((string)$old->provider_price!==(string)$service->provider_price)||($old->currency!==$service->currency)||($old->network!==$service->network)||($old->provider_category_id!==$service->provider_category_id)||($old->provider_subcategory_id!==$service->provider_subcategory_id);
                if($changed) $updated++;
                if((string)$old->provider_price!==(string)$service->provider_price) $priceChanged++;
                if($changed){
                    $import=ProviderServiceImport::where('api_provider_id',$provider->id)->where('provider_service_id',$service->id)->first();
                    if($import && $import->approved && $import->imported && $import->auto_sync_allowed){
                        $import->update(['state'=>'imported','last_imported_at'=>now()]);
                    }
                }
            }
            // Never mark services removed unless discovery explicitly proved that the
            // returned catalogue is complete. Partial/paginated discovery must not cause
            // destructive false-removals.
            $discoveryComplete=(bool)($resultData['discovery_complete'] ?? false);
            if ($discoveryComplete) {
                foreach($before as $key=>$old){
                    if(!$after->has($key)){
                        $old->update(['status'=>'removed']);
                        $import=ProviderServiceImport::where('api_provider_id',$provider->id)->where('provider_service_id',$old->id)->first();
                        if($import) $import->update(['state'=>'removed','auto_sync_allowed'=>false]);
                        $removed++;
                    }
                }
            } else {
                $this->writeOperationLog(
                    $provider,
                    null,
                    'sync_removal_skipped',
                    null,
                    null,
                    'SKIPPED',
                    null,
                    0,
                    'INCOMPLETE_DISCOVERY',
                    'Removal detection skipped because discovery completeness was not proven.',
                    ['sync_id'=>$syncId,'before_count'=>$before->count(),'after_count'=>$after->count()]
                );
            }
            $summary=['discovered'=>$after->count(),'new'=>$new,'updated'=>$updated,'removed'=>$removed,'price_changed'=>$priceChanged,'pending_approval'=>ProviderServiceImport::where('api_provider_id',$provider->id)->where('approved',false)->count()];
            DB::table('provider_syncs')->where('id',$syncId)->update([
                'status'=>'completed','discovered_count'=>$after->count(),'new_count'=>$new,'updated_count'=>$updated,
                'removed_count'=>$removed,'price_changed_count'=>$priceChanged,'summary'=>json_encode($summary),
                'finished_at'=>now(),'updated_at'=>now()
            ]);
            return response()->json(['status'=>'success','sync_id'=>$syncId,'summary'=>$summary,'discovery_status'=>$resultData['status']??'success']);
        } catch(\Throwable $e) {
            Log::warning('Provider operation failed.', ['exception_class' => get_class($e)]);
            DB::table('provider_syncs')->where('id',$syncId)->update(['status'=>'failed','failed_count'=>1,'error_message'=>'Provider sync failed safely.','finished_at'=>now(),'updated_at'=>now()]);
            return response()->json(['status'=>'failed','sync_id'=>$syncId,'message'=>'Provider sync failed safely. Review server-side diagnostics.'],502);
        }
    }

    public function syncHistory(ApiProvider $provider): JsonResponse
    {
        $rows = DB::table('provider_syncs')->where('api_provider_id',$provider->id)->latest('id')->limit(50)->get();
        return response()->json(['data'=>$rows]);
    }

    public function syncSummary(ApiProvider $provider): JsonResponse
    {
        $services=$provider->providerServices()->get();
        $pending=ProviderServiceImport::where('api_provider_id',$provider->id)->where('approved',false)->count();
        $approvedNotImported=ProviderServiceImport::where('api_provider_id',$provider->id)->where('approved',true)->where('imported',false)->count();
        $latestSync=DB::table('provider_syncs')->where('api_provider_id',$provider->id)->latest('id')->first();
        return response()->json(['data'=>[
            'provider_id'=>$provider->id,
            'services'=>$services->count(),
            'pending_approval'=>$pending,
            'approved_not_imported'=>$approvedNotImported,
            'last_tested_at'=>$provider->last_tested_at,
            'last_test_status'=>$provider->last_test_status,
            'last_successful_request_at'=>$provider->last_successful_request_at,
            'latest_sync'=>$latestSync,
        ]]);
    }

    public function health(ApiProvider $provider): JsonResponse
    {
        $checks=$provider->healthChecks()->latest('checked_at')->limit(50)->get()->map(fn(ProviderHealthCheck $h)=>[
            'id'=>$h->id,'connection_id'=>$h->provider_connection_id,'status'=>$h->status,'http_status'=>$h->http_status,
            'response_time_ms'=>$h->response_time_ms,'checked_at'=>$h->checked_at,
        ]);
        return response()->json(['data'=>$checks]);
    }

    public function storeEndpoint(Request $request, ApiProvider $provider): JsonResponse
    {
        $data=$request->validate([
            'name'=>'required|string|max:160',
            'operation'=>'nullable|string|max:100',
            'method'=>'required|in:GET,POST,PUT,PATCH,DELETE',
            'path'=>'nullable|string|max:2048',
            'full_url'=>'nullable|url|max:2048',
            'content_type'=>'required|in:json,form-data,x-www-form-urlencoded,query,raw',
            'auth_mode'=>'required|in:connection,none,custom',
            'headers'=>'nullable|array',
            'query_params'=>'nullable|array',
            'request_mapping'=>'nullable|array',
            'response_mapping'=>'nullable|array',
            'error_mapping'=>'nullable|array',
            'webhook_config'=>'nullable|array',
            'enabled'=>'nullable|boolean',
        ]);
        if (blank($data['path'] ?? null) && blank($data['full_url'] ?? null)) {
            return response()->json(['message'=>'Provide either a relative path or a full URL.'],422);
        }
        if (filled($data['full_url'] ?? null)) {
            app(ProviderUrlGuard::class)->validate($data['full_url']);
        }
        $saved = $provider->endpoints()->create($data);
        return response()->json(['data'=>$this->endpointSummary($saved->fresh())],201);
    }

    public function updateEndpoint(Request $request, ApiProvider $provider, ProviderEndpoint $endpoint): JsonResponse
    {
        if ((int) $endpoint->api_provider_id !== (int) $provider->id) {
            return response()->json(['message' => 'Provider endpoint does not belong to this provider.'], 404);
        }

        $data = $request->validate([
            'name'=>'required|string|max:160',
            'operation'=>'nullable|string|max:100',
            'method'=>'required|in:GET,POST,PUT,PATCH,DELETE',
            'path'=>'nullable|string|max:2048',
            'full_url'=>'nullable|url|max:2048',
            'content_type'=>'required|in:json,form-data,x-www-form-urlencoded,query,raw',
            'auth_mode'=>'required|in:connection,none,custom',
            'headers'=>'nullable|array',
            'query_params'=>'nullable|array',
            'request_mapping'=>'nullable|array',
            'response_mapping'=>'nullable|array',
            'error_mapping'=>'nullable|array',
            'webhook_config'=>'nullable|array',
            'enabled'=>'nullable|boolean',
        ]);

        if (blank($data['path'] ?? null) && blank($data['full_url'] ?? null)) {
            return response()->json(['message'=>'Provide either a relative path or a full URL.'], 422);
        }
        if (filled($data['full_url'] ?? null)) {
            app(ProviderUrlGuard::class)->validate($data['full_url']);
        }

        $endpoint->update($data);

        return response()->json(['data'=>$this->endpointSummary($endpoint->fresh())]);
    }

    public function endpoints(ApiProvider $provider): JsonResponse
    {
        return response()->json(['data'=>$provider->endpoints()->orderByDesc('enabled')->latest()->get()->map(fn(ProviderEndpoint $e)=>[
            'id'=>$e->id,'name'=>$e->name,'operation'=>$e->operation,'method'=>$e->method,
            'path'=>$e->path,'full_url'=>$this->safeUrlForDisplay($e->full_url),'content_type'=>$e->content_type,
            'auth_mode'=>$e->auth_mode,'headers'=>$this->safeKeyValueMap((array)($e->headers ?? [])),'query_params'=>$this->safeKeyValueMap((array)($e->query_params ?? [])),
            'request_mapping'=>$e->request_mapping ?? [],'response_mapping'=>$e->response_mapping ?? [],
            'error_mapping'=>$e->error_mapping ?? [],'webhook_config'=>$this->redactForLog((array)($e->webhook_config ?? [])),
            'enabled'=>$e->enabled,
        ])]);
    }

    public function testEndpoint(Request $request, ApiProvider $provider, ProviderEndpoint $endpoint): JsonResponse
    {
        if ($endpoint->api_provider_id !== $provider->id) return response()->json(['message'=>'Endpoint does not belong to this provider.'],404);
        $connection=$provider->connections()->where('enabled',true)->orderByDesc('is_default')->first();
        if (!$connection) return response()->json(['status'=>'configuration_required','message'=>'Configure an enabled connection first.'],422);

        $input=$request->validate(['variables'=>'nullable|array']);
        $started=microtime(true);
        $safeUrl = null;
        try {
            $body=(array)($endpoint->request_mapping ?? []);
            foreach(($input['variables'] ?? []) as $key=>$value) data_set($body,$key,$value);
            [$headers,$query,$body]=$this->authenticationPayload($connection,$body,(string)$endpoint->auth_mode);
            $headers=array_merge($headers,(array)($endpoint->headers ?? []));
            $query=array_merge($query,(array)($endpoint->query_params ?? []));
            $url=$this->endpointUrl($connection,$endpoint);
            $safeUrl = $url;

            $client=Http::withHeaders($headers)->connectTimeout($connection->connect_timeout_seconds)->timeout($connection->request_timeout_seconds);
            if(!$connection->verify_ssl) $client=$client->withoutVerifying();

            $response=match($endpoint->method){
                'POST'=>$this->sendEndpointRequest($client,$endpoint,$url,$body,$query),
                'PUT'=>$this->sendEndpointRequest($client,$endpoint,$url,$body,$query),
                'PATCH'=>$this->sendEndpointRequest($client,$endpoint,$url,$body,$query),
                'DELETE'=>$this->sendEndpointRequest($client,$endpoint,$url,$body,$query),
                default=>$client->get($url,$query),
            };

            $duration=(int)((microtime(true)-$started)*1000);
            $payload=$response->json();
            $safeStatus=$response->successful()?'SUCCESS':'FAILED';
            $this->writeOperationLog($provider, $connection, 'endpoint_test', $endpoint->method, $url, $safeStatus, $response->status(), $duration, null, 'Endpoint request completed.');
            return response()->json([
                'status'=>$safeStatus,'http_status'=>$response->status(),'duration_ms'=>$duration,
                'message'=>$response->successful()?'Endpoint request succeeded.':'Endpoint request returned an error.',
                'mapped_response'=>$this->redactForLog($this->mapResponse($payload,(array)($endpoint->response_mapping ?? []))),
                'mapped_error'=>$response->successful()?null:$this->redactForLog($this->mapResponse($payload,(array)($endpoint->error_mapping ?? []))),
            ],$response->successful()?200:502);
        } catch (\Throwable $e) {
            Log::warning('Provider operation failed.', ['exception_class' => get_class($e)]);
            $this->writeOperationLog($provider, $connection, 'endpoint_test', $endpoint->method, $safeUrl, 'FAILED', null, (int)((microtime(true)-$started)*1000), get_class($e), 'Endpoint request failed safely.');
            return response()->json(['status'=>'FAILED','message'=>'Endpoint request failed safely. Review server-side diagnostics.'],502);
        }
    }

    public function destroyEndpoint(ApiProvider $provider, ProviderEndpoint $endpoint): JsonResponse
    {
        if ($endpoint->api_provider_id !== $provider->id) return response()->json(['message'=>'Endpoint does not belong to this provider.'],404);
        $endpoint->delete();
        return response()->json(['status'=>'deleted']);
    }

    public function discovery(Request $request, ApiProvider $provider): JsonResponse
    {
        $endpoint=$provider->endpoints()->where('enabled',true)
            ->orderByRaw("CASE WHEN operation = 'catalogue_retrieval' THEN 0 WHEN operation IN ('services','products','categories') THEN 1 ELSE 2 END")
            ->first();
        if (!$endpoint) return response()->json(['status'=>'manual_required','message'=>'No enabled discovery endpoint is configured.'],422);

        $connection=$provider->connections()->where('enabled',true)->orderByDesc('is_default')->first();
        if (!$connection) return response()->json(['status'=>'configuration_required','message'=>'Configure an enabled provider connection first.'],422);

        $started=microtime(true);
        $safeUrl = null;
        try {
            [$headers,$query,$body]=$this->authenticationPayload($connection,(array)($endpoint->request_mapping ?? []),(string)$endpoint->auth_mode);
            $headers=array_merge($headers,(array)($endpoint->headers ?? []));
            $query=array_merge($query,(array)($endpoint->query_params ?? []));
            $url=$this->endpointUrl($connection,$endpoint);
            $safeUrl = $url;
            $client=Http::withHeaders($headers)->connectTimeout($connection->connect_timeout_seconds)->timeout($connection->request_timeout_seconds);
            if(!$connection->verify_ssl)$client=$client->withoutVerifying();
            $response=$this->sendEndpointRequest($client,$endpoint,$url,$body,$query);

            if(!$response->successful()){
                $connection->update(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_message'=>'Provider discovery returned an unsuccessful HTTP status.']);
                return response()->json(['status'=>'failed','http_status'=>$response->status(),'message'=>'Provider discovery request failed.'],502);
            }

            $payload=$response->json();
            if(!is_array($payload)) return response()->json(['status'=>'failed','message'=>'Provider discovery returned an unsupported response format.'],502);
            $mapping=(array)($endpoint->response_mapping ?? []);
            $normalizedPayload=$mapping ? $this->mapResponse($payload,$mapping) : $payload;
            $items=$this->extractItems($normalizedPayload['items'] ?? $normalizedPayload);
            $stored=0;

            foreach($items as $item){
                if(!is_array($item)) $item=['value'=>$item];
                $normalized=$this->normalizeDiscoveredService($item);
                $categoryRow=null;
                if($normalized['category']!==null){
                    $categoryRow=ProviderCategory::updateOrCreate(
                        ['api_provider_id'=>$provider->id,'external_id'=>$normalized['category_id'] ?? Str::slug($normalized['category'])],
                        ['external_name'=>$normalized['category'],'normalized_key'=>Str::slug($normalized['category']),'status'=>'discovered','metadata'=>$normalized['category_metadata'],'last_synced_at'=>now()]
                    );
                }
                $subcategoryRow=null;
                if($categoryRow && $normalized['subcategory']!==null){
                    $subcategoryRow=ProviderSubcategory::updateOrCreate(
                        ['provider_category_id'=>$categoryRow->id,'external_id'=>$normalized['subcategory_id'] ?? Str::slug($normalized['subcategory'])],
                        ['external_name'=>$normalized['subcategory'],'normalized_key'=>Str::slug($normalized['subcategory']),'status'=>'discovered','metadata'=>$normalized['subcategory_metadata'],'last_synced_at'=>now()]
                    );
                }

                $service=ProviderService::updateOrCreate(
                    ['api_provider_id'=>$provider->id,'external_service_id'=>$normalized['external_service_id']],
                    [
                        'provider_category_id'=>$categoryRow?->id,
                        'provider_subcategory_id'=>$subcategoryRow?->id,
                        'external_service_code'=>$normalized['external_service_code'],
                        'name'=>$normalized['name'],
                        'description'=>$normalized['description'],
                        'service_type'=>$normalized['service_type'],
                        'network'=>$normalized['network'],
                        'provider_price'=>$normalized['provider_price'],
                        'currency'=>$normalized['currency'],
                        'status'=>$normalized['status'],
                        'metadata'=>$normalized['metadata'],
                        'raw_provider_data'=>$item,
                        'last_synced_at'=>now(),
                    ]
                );
                ProviderServiceImport::firstOrCreate(
                    ['api_provider_id'=>$provider->id,'provider_service_id'=>$service->id],
                    ['selection_scope'=>'product','imported'=>false,'approved'=>false,'auto_sync_allowed'=>false,'state'=>'awaiting_approval']
                );
                $stored++;
            }

            $duration=(int)((microtime(true)-$started)*1000);
            $discoveryComplete=$this->discoveryCompleteness($payload);
            $connection->update(['last_tested_at'=>now(),'last_test_status'=>'SUCCESS','last_test_message'=>'Service discovery succeeded.']);
            $this->writeOperationLog($provider,$connection,'service_discovery',$endpoint->method,$url,'SUCCESS',$response->status(),$duration,null,'Service discovery succeeded.',[
                'discovered'=>$stored,
                'discovery_complete'=>$discoveryComplete,
            ]);
            return response()->json([
                'status'=>'success','discovered'=>$stored,'discovery_complete'=>$discoveryComplete,
                'duration_ms'=>$duration,
                'categories'=>$provider->categories()->count(),
                'services'=>$provider->providerServices()->count(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Provider operation failed.', ['exception_class' => get_class($e)]);
            $duration=(int)((microtime(true)-$started)*1000);
            $connection->update(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_message'=>'Service discovery failed safely.']);
            $this->writeOperationLog($provider,$connection,'service_discovery',$endpoint->method,$safeUrl,'FAILED',null,$duration,get_class($e),'Service discovery failed safely.');
            return response()->json(['status'=>'failed','message'=>'Service discovery failed safely. Review server-side diagnostics.'],502);
        }
    }

    private function discoveryCompleteness(array $payload): bool
    {
        foreach (['discovery_complete','complete','is_complete'] as $key) {
            if (array_key_exists($key,$payload) && is_bool($payload[$key])) return $payload[$key];
        }
        foreach (['has_more','hasMore','more'] as $key) {
            if (array_key_exists($key,$payload) && is_bool($payload[$key])) return !$payload[$key];
        }
        foreach (['next_cursor','nextCursor','next_page_token','nextPageToken','next_url','nextUrl'] as $key) {
            if (array_key_exists($key,$payload)) return blank($payload[$key]);
        }
        return false;
    }

    public function catalogueProducts(): JsonResponse
    {
        return response()->json(['data'=>\App\Models\ServiceProduct::query()->with(['service.category'])->get()]);
    }

    public function createMapping(Request $request, ApiProvider $provider): JsonResponse
    {
        $data=$request->validate(['provider_service_id'=>'required|integer|exists:provider_services,id','catalogue_product_id'=>'required|integer|exists:service_products,id','priority'=>'nullable|integer|min:1|max:100000']);
        if(!$provider->providerServices()->whereKey($data['provider_service_id'])->exists()) return response()->json(['message'=>'Provider service does not belong to this provider.'],422);
        $exists=DB::table('provider_product_mappings_v2')->where('provider_service_id',$data['provider_service_id'])->where('catalogue_product_id',$data['catalogue_product_id'])->exists();
        if($exists) return response()->json(['message'=>'Mapping already exists.'],409);
        DB::table('provider_product_mappings_v2')->insert([
            'api_provider_id'=>$provider->id,'provider_service_id'=>$data['provider_service_id'],'catalogue_product_id'=>$data['catalogue_product_id'],
            'catalogue_product_type'=>'service_product','priority'=>$data['priority']??100,'enabled'=>false,'mapping_status'=>'pending',
            'metadata'=>json_encode([]),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return response()->json(['status'=>'created'],201);
    }

    public function toggleMapping(Request $request, ApiProvider $provider, int $mapping): JsonResponse
    {
        $data=$request->validate(['enabled'=>'required|boolean']);
        $row=DB::table('provider_product_mappings_v2')->where('id',$mapping)->where('api_provider_id',$provider->id)->first();
        if(!$row) return response()->json(['message'=>'Provider mapping not found.'],404);
        if($data['enabled']){
            if (!$provider->enabled || $provider->paused || $provider->verification_status !== 'live_verified' || $provider->integration_status !== 'live_verified') {
                return response()->json(['message'=>'Product mapping cannot be activated until the provider is enabled, unpaused and live-verified.'],422);
            }
            $service=ProviderService::query()->find($row->provider_service_id);
            $import=ProviderServiceImport::where('api_provider_id',$provider->id)->where('provider_service_id',$row->provider_service_id)->first();
            if(!$service || $service->status==='removed' || !$import || !$import->approved || !$import->imported){
                return response()->json(['message'=>'Mapping cannot be activated until the provider service is approved and imported.'],422);
            }
            $sourceMapping = ProviderServiceProduct::query()
                ->where('api_provider_id', $provider->id)
                ->where('provider_product_id', $service->external_service_id)
                ->where('service_product_id', $row->catalogue_product_id)
                ->where('enabled', true)
                ->exists();
            if (! $sourceMapping) {
                return response()->json(['message'=>'A valid provider source-cost mapping to this draft product is required before activating the product-level mapping.'],422);
            }
            DB::table('provider_product_mappings_v2')->where('id',$mapping)->update(['enabled'=>true,'mapping_status'=>'active','updated_at'=>now()]);
        } else {
            DB::table('provider_product_mappings_v2')->where('id',$mapping)->update(['enabled'=>false,'mapping_status'=>'disabled','updated_at'=>now()]);
        }
        return response()->json(['status'=>'updated','enabled'=>(bool)$data['enabled'],'mapping_status'=>$data['enabled']?'active':'disabled']);
    }

    public function providerMappings(ApiProvider $provider): JsonResponse
    {
        $rows=\Illuminate\Support\Facades\DB::table('provider_product_mappings_v2 as m')
            ->join('provider_services as ps','ps.id','=','m.provider_service_id')
            ->leftJoin('service_products as cp','cp.id','=','m.catalogue_product_id')
            ->where('m.api_provider_id',$provider->id)->orderBy('m.priority')
            ->get(['m.id','m.provider_service_id','m.catalogue_product_id','m.priority','m.enabled','m.mapping_status','ps.name as provider_service_name','ps.external_service_code','cp.name as catalogue_product_name']);
        return response()->json(['data'=>$rows]);
    }

    public function services(ApiProvider $provider): JsonResponse
    {
        $services=$provider->providerServices()->with(['category','subcategory'])->latest()->get()->map(fn(ProviderService $s)=>[
            'id'=>$s->id,'external_service_id'=>$s->external_service_id,'external_service_code'=>$s->external_service_code,
            'name'=>$s->name,'description'=>$s->description,'service_type'=>$s->service_type,'network'=>$s->network,
            'provider_price'=>$s->provider_price,'currency'=>$s->currency,'status'=>$s->status,
            'category'=>$s->category?->external_name,'subcategory'=>$s->subcategory?->external_name,
        ]);
        return response()->json(['data'=>$services]);
    }

    public function importPreview(ApiProvider $provider): JsonResponse
    {
        $rows = ProviderServiceImport::query()
            ->where('api_provider_id', $provider->id)
            ->with(['service.category', 'service.subcategory'])
            ->latest()
            ->get();

        $productMappings = DB::table('provider_product_mappings_v2')
            ->where('api_provider_id', $provider->id)
            ->whereIn('provider_service_id', $rows->pluck('provider_service_id'))
            ->get()
            ->keyBy('provider_service_id');

        $mappedProducts = ServiceProduct::query()
            ->whereIn('id', $productMappings->pluck('catalogue_product_id')->unique())
            ->get(['id', 'service_id'])
            ->keyBy('id');
        $serviceMappings = ProviderServiceMapping::query()
            ->where('api_provider_id', $provider->id)
            ->whereIn('service_id', $mappedProducts->pluck('service_id')->unique())
            ->get()
            ->keyBy('service_id');

        return response()->json(['data' => $rows->map(function (ProviderServiceImport $import) use ($productMappings, $mappedProducts, $serviceMappings): array {
            $service = $import->service;
            $productMapping = $productMappings->get($import->provider_service_id);
            $mappedProduct = $productMapping ? $mappedProducts->get($productMapping->catalogue_product_id) : null;
            $platformMapping = $mappedProduct ? $serviceMappings->get($mappedProduct->service_id) : null;

            return [
                'id' => $import->id,
                'provider_service_id' => $import->provider_service_id,
                'imported' => (bool) $import->imported,
                'approved' => (bool) $import->approved,
                'auto_sync_allowed' => (bool) $import->auto_sync_allowed,
                'state' => $import->state,
                'service' => $service?->only(['id', 'external_service_id', 'external_service_code', 'name', 'description', 'service_type', 'network', 'provider_price', 'currency', 'status', 'last_synced_at']),
                'category' => $service?->category?->external_name,
                'subcategory' => $service?->subcategory?->external_name,
                'platform_mapping' => $platformMapping ? [
                    'id' => $platformMapping->id,
                    'service_id' => $platformMapping->service_id,
                    'service_key' => $platformMapping->service_key,
                    'provider_service_id' => $platformMapping->provider_service_id,
                    'enabled' => (bool) $platformMapping->enabled,
                    'capabilities' => $platformMapping->capabilities ?? [],
                ] : null,
                'product_mapping' => $productMapping ? [
                    'id' => $productMapping->id,
                    'catalogue_product_id' => $productMapping->catalogue_product_id,
                    'enabled' => (bool) $productMapping->enabled,
                    'mapping_status' => $productMapping->mapping_status,
                ] : null,
            ];
        })->values()]);
    }

    public function approveImport(Request $request, ApiProvider $provider): JsonResponse
    {
        $data=$request->validate([
            'provider_service_ids'=>'required|array|min:1',
            'provider_service_ids.*'=>'integer',
            'auto_sync_allowed'=>'nullable|boolean',
        ]);
        $ids=$provider->providerServices()->whereIn('id',$data['provider_service_ids'])->pluck('id');
        $updated=ProviderServiceImport::where('api_provider_id',$provider->id)->whereIn('provider_service_id',$ids)->update([
            'approved'=>true,
            'state'=>'approved',
            'auto_sync_allowed'=>(bool)($data['auto_sync_allowed'] ?? false),
        ]);
        return response()->json(['status'=>'approved','approved'=>$updated]);
    }

    public function mapServiceToPlatform(Request $request, ApiProvider $provider, ProviderService $providerService, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'provider_service_id' => ['required', 'string', 'max:120'],
            'service_product_id' => ['nullable', 'integer', 'exists:service_products,id'],
        ]);

        $providerService = $provider->providerServices()->whereKey($providerService->id)->firstOrFail();
        $import = ProviderServiceImport::query()
            ->where('api_provider_id', $provider->id)
            ->where('provider_service_id', $providerService->id)
            ->first();

        if (! $import || ! $import->approved || ! $import->imported) {
            return response()->json(['message' => 'Approve and import this provider catalogue row before mapping it to My Services.'], 422);
        }

        $externalId = trim((string) $providerService->external_service_id);
        if ($externalId === '') {
            return response()->json(['message' => 'This provider catalogue row has no external service/product ID. Refresh the official catalogue or configure a documented manual mapping.'], 422);
        }

        $platformService = Service::query()->findOrFail((int) $data['service_id']);
        $providerServiceCode = trim((string) $data['provider_service_id']);
        if ($providerServiceCode === '') {
            return response()->json(['message' => 'Enter the provider service identifier documented for this platform service. The external product ID is stored separately.'], 422);
        }
        $currency = strtoupper(trim((string) $providerService->currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            return response()->json(['message' => 'The provider catalogue row has no valid three-letter currency. Correct the provider catalogue before mapping.'], 422);
        }

        $existingProductMap = ProviderServiceProduct::query()
            ->where('api_provider_id', $provider->id)
            ->where('provider_product_id', $externalId)
            ->first();
        $existingV2Map = DB::table('provider_product_mappings_v2')
            ->where('api_provider_id', $provider->id)
            ->where('provider_service_id', $providerService->id)
            ->first();

        $requestedProductId = ! empty($data['service_product_id']) ? (int) $data['service_product_id'] : null;
        if ($existingProductMap && $requestedProductId && (int) $existingProductMap->service_product_id !== $requestedProductId) {
            return response()->json(['message' => 'This provider external ID is already mapped to a different platform product. Resolve that mapping explicitly before continuing.'], 409);
        }
        if ($existingV2Map && $requestedProductId && (int) $existingV2Map->catalogue_product_id !== $requestedProductId) {
            return response()->json(['message' => 'This provider service already has a product-level mapping to a different platform product. Review the existing mapping before changing it.'], 409);
        }
        if ($existingProductMap && $existingV2Map
            && (int) $existingProductMap->service_product_id !== (int) $existingV2Map->catalogue_product_id) {
            return response()->json(['message' => 'The Core provider-product mapping and product-level mapping disagree. Reconcile the existing records before proceeding.'], 409);
        }

        $targetProductId = $requestedProductId
            ?? ($existingV2Map->catalogue_product_id ?? null)
            ?? ($existingProductMap->service_product_id ?? null);
        $targetProduct = $targetProductId ? ServiceProduct::query()->findOrFail((int) $targetProductId) : null;

        if ($targetProduct && (int) $targetProduct->service_id !== (int) $platformService->id) {
            return response()->json(['message' => 'The selected product variant does not belong to the selected platform service.'], 422);
        }
        if ($targetProduct && ($targetProduct->enabled || $targetProduct->publication_status === 'published')) {
            return response()->json(['message' => 'A published product cannot receive a new provider mapping from this draft-mapping flow. Unpublish it and review its routing before changing mappings.'], 409);
        }
        if ($targetProduct && strtoupper((string) $targetProduct->currency) !== $currency) {
            return response()->json(['message' => 'Provider and platform product currencies must match. Currency conversion is not inferred.'], 422);
        }

        $base = substr(Str::slug($providerService->name ?: 'provider-product'), 0, 80);
        $suffix = substr(hash('sha256', $provider->identifier . '|' . $externalId), 0, 10);
        $productKey = ($base !== '' ? $base : 'provider-product') . '-' . $suffix;
        $existingByKey = $targetProduct ?: ServiceProduct::query()
            ->where('service_id', $platformService->id)
            ->where('key', $productKey)
            ->first();

        if ($existingByKey && ($existingByKey->enabled || $existingByKey->publication_status === 'published')) {
            return response()->json(['message' => 'The matching product key belongs to a published product. Select an existing draft variant or review the live product first.'], 409);
        }
        if ($existingByKey && strtoupper((string) $existingByKey->currency) !== $currency) {
            return response()->json(['message' => 'The existing draft product has a different currency from this provider catalogue row.'], 422);
        }

        $cost = $providerService->provider_price === null ? null : (string) $providerService->provider_price;
        $validCost = $cost !== null
            && preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/', $cost) === 1;
        $providerMappingEnabled = $externalId !== '' && $validCost;

        try {
            $result = DB::transaction(function () use (
                $request, $provider, $providerService, $platformService, $import, $externalId,
                $currency, $cost, $validCost, $providerMappingEnabled, $productKey, $existingByKey, $providerServiceCode
            ): array {
                $lockedService = ProviderService::query()->lockForUpdate()->findOrFail($providerService->id);
                $lockedImport = ProviderServiceImport::query()
                    ->where('api_provider_id', $provider->id)
                    ->where('provider_service_id', $lockedService->id)
                    ->lockForUpdate()->firstOrFail();

                if (! $lockedImport->approved || ! $lockedImport->imported) {
                    throw new \DomainException('The provider catalogue row must remain approved and imported while it is being mapped.');
                }

                $product = $existingByKey;
                $created = false;
                if (! $product) {
                    $product = ServiceProduct::query()->firstOrCreate(
                        ['service_id' => $platformService->id, 'key' => $productKey],
                        [
                            'name' => $lockedService->name ?: ('Provider product ' . $externalId),
                            'currency' => $currency,
                            'enabled' => false,
                            'publication_status' => 'draft',
                            'metadata' => [
                                'catalogue_source' => 'provider_discovery',
                                'provider_identifier' => $provider->identifier,
                                'provider_service_id' => $externalId,
                            ],
                        ],
                    );
                    $created = $product->wasRecentlyCreated;
                } else {
                    $product = ServiceProduct::query()->lockForUpdate()->findOrFail($product->id);
                }

                if ($product->enabled || $product->publication_status === 'published') {
                    throw new \DomainException('The selected product became published while mapping. Reload the catalogue and review the live product before changing mappings.');
                }
                if (strtoupper((string) $product->currency) !== $currency) {
                    throw new \DomainException('Provider and platform product currencies must match. Currency conversion is not inferred.');
                }

                $currentProviderProduct = ProviderServiceProduct::query()
                    ->where('api_provider_id', $provider->id)
                    ->where('provider_product_id', $externalId)
                    ->lockForUpdate()->first();
                if ($currentProviderProduct && (int) $currentProviderProduct->service_product_id !== (int) $product->id) {
                    throw new \DomainException('This provider external ID is already mapped to a different platform product.');
                }

                $currentV2Map = DB::table('provider_product_mappings_v2')
                    ->where('api_provider_id', $provider->id)
                    ->where('provider_service_id', $lockedService->id)
                    ->lockForUpdate()->first();
                if ($currentV2Map && (int) $currentV2Map->catalogue_product_id !== (int) $product->id) {
                    throw new \DomainException('This provider service already has a product-level mapping to a different platform product.');
                }

                $providerProduct = ProviderServiceProduct::query()->updateOrCreate(
                    ['api_provider_id' => $provider->id, 'provider_product_id' => $externalId],
                    [
                        'service_product_id' => $product->id,
                        'provider_cost' => $validCost ? $cost : null,
                        'currency' => $currency,
                        'raw_catalogue' => $lockedService->raw_provider_data ?? [],
                        'enabled' => $providerMappingEnabled,
                        'last_synced_at' => $lockedService->last_synced_at,
                    ],
                );

                // The provider service identifier is distinct from the product ID and comes from verified provider documentation.
                $currentServiceMapping = ProviderServiceMapping::query()
                    ->where('api_provider_id', $provider->id)
                    ->where('service_id', $platformService->id)
                    ->lockForUpdate()->first();

                if ($currentServiceMapping) {
                    if (trim((string) $currentServiceMapping->provider_service_id) !== ''
                        && trim((string) $currentServiceMapping->provider_service_id) !== $providerServiceCode) {
                        throw new \DomainException('An existing provider service mapping uses a different provider service identifier. Reconcile it explicitly before mapping another product variant.');
                    }
                    if (trim((string) $currentServiceMapping->provider_service_id) === '') {
                        if ($currentServiceMapping->enabled) {
                            throw new \DomainException('Disable the service route before adding its documented provider service identifier.');
                        }
                        $currentServiceMapping->update(['provider_service_id' => $providerServiceCode]);
                    }
                } else {
                    ProviderServiceMapping::query()->create([
                        'api_provider_id' => $provider->id,
                        'service_id' => $platformService->id,
                        'service_key' => $platformService->key,
                        'provider_service_id' => $providerServiceCode,
                        'capabilities' => [],
                        'enabled' => false,
                    ]);
                }

                if (! $currentV2Map) {
                    DB::table('provider_product_mappings_v2')->insert([
                        'api_provider_id' => $provider->id,
                        'provider_service_id' => $lockedService->id,
                        'catalogue_product_id' => $product->id,
                        'catalogue_product_type' => 'service_product',
                        'priority' => 100,
                        'enabled' => false,
                        'mapping_status' => 'pending',
                        'metadata' => json_encode(['mapped_by' => $request->user()->id]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $lockedService->update(['status' => 'imported']);
                $lockedImport->update(['state' => 'imported', 'last_imported_at' => now()]);

                return ['product' => $product->fresh(), 'provider_product' => $providerProduct, 'created' => $created];
            }, 3);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        try {
            $audit->record('catalogue.provider_service.mapped_to_draft', $result['product'], [
                'api_provider_id' => $provider->id,
                'provider_service_id' => $providerService->id,
                'external_product_id' => $externalId,
                'provider_product_mapping_id' => $result['provider_product']->id,
                'published' => false,
                'routing_enabled_by_mapping_action' => false,
            ], $request);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'status' => 'mapped_to_draft',
            'created' => $result['created'],
            'product' => [
                'id' => $result['product']->id,
                'name' => $result['product']->name,
                'key' => $result['product']->key,
                'publication_status' => $result['product']->publication_status,
            ],
            'provider_mapping_enabled' => (bool) $result['provider_product']->enabled,
            'route_enabled' => false,
            'message' => 'Mapped to a draft platform product. Configure tier prices and explicitly verify/enable routing, then use Add to My Services when readiness checks pass.',
        ]);
    }

    public function updatePlatformServiceMappingCapabilities(Request $request, ApiProvider $provider, ProviderServiceMapping $mapping, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'capabilities' => ['present', 'array'],
            'capabilities.*' => ['string', \Illuminate\Validation\Rule::in(['catalogue_retrieval', 'transaction_initiation', 'transaction_status', 'refund', 'webhook'])],
        ]);

        if ((int) $mapping->api_provider_id !== (int) $provider->id) {
            return response()->json(['message' => 'Provider service mapping not found.'], 404);
        }
        if ($mapping->enabled) {
            return response()->json(['message' => 'Disable the service route before changing its capabilities.'], 409);
        }

        $selected = array_values(array_unique($data['capabilities']));
        $providerCapabilities = (array) ($provider->capabilities ?? []);
        $unsupported = array_values(array_diff($selected, $providerCapabilities));
        if ($unsupported !== []) {
            return response()->json([
                'message' => 'These operations are not declared for this provider: ' . implode(', ', $unsupported) . '. Update the provider capability declaration only after verifying its official documentation.',
            ], 422);
        }

        $mapping->update(['capabilities' => $selected]);
        try {
            $audit->record('catalogue.mapping.capabilities_updated', $mapping->fresh(), [
                'provider_id' => $provider->id,
                'service_id' => $mapping->service_id,
                'capabilities' => $selected,
            ], $request);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json(['status' => 'updated', 'capabilities' => $selected, 'message' => 'Service mapping capabilities saved. Enable the route separately after the product-level mapping is active.']);
    }

    public function togglePlatformServiceMapping(Request $request, ApiProvider $provider, ProviderServiceMapping $mapping, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        if ((int) $mapping->api_provider_id !== (int) $provider->id) {
            return response()->json(['message' => 'Provider service mapping not found.'], 404);
        }

        $result = DB::transaction(function () use ($provider, $mapping, $data): array {
            $lockedProvider = ApiProvider::query()->lockForUpdate()->findOrFail($provider->id);
            $lockedMapping = ProviderServiceMapping::query()->lockForUpdate()->findOrFail($mapping->id);

            if ($data['enabled']) {
                if (! $lockedProvider->enabled || $lockedProvider->paused
                    || $lockedProvider->verification_status !== 'live_verified'
                    || $lockedProvider->integration_status !== 'live_verified') {
                    return ['ok' => false, 'status' => 422, 'message' => 'A service route can only be enabled for an enabled, unpaused, live-verified provider.'];
                }
                if (! in_array('transaction_initiation', (array) $lockedMapping->capabilities, true)) {
                    return ['ok' => false, 'status' => 422, 'message' => 'Transaction-initiation capability is not verified/configured for this provider service mapping. Configure the provider capability first.'];
                }
                $hasSourceCost = ProviderServiceProduct::query()
                    ->where('api_provider_id', $lockedProvider->id)
                    ->where('enabled', true)
                    ->whereHas('product', fn ($query) => $query->where('service_id', $lockedMapping->service_id))
                    ->exists();
                $hasActiveProductMapping = DB::table('provider_product_mappings_v2 as m')
                    ->join('provider_services as ps', 'ps.id', '=', 'm.provider_service_id')
                    ->join('provider_service_imports as i', function ($join): void {
                        $join->on('i.provider_service_id', '=', 'm.provider_service_id')
                            ->on('i.api_provider_id', '=', 'm.api_provider_id');
                    })
                    ->join('service_products as p', 'p.id', '=', 'm.catalogue_product_id')
                    ->where('m.api_provider_id', $lockedProvider->id)
                    ->where('m.enabled', true)
                    ->where('m.mapping_status', 'active')
                    ->where('i.approved', true)
                    ->where('i.imported', true)
                    ->where('ps.status', '!=', 'removed')
                    ->where('p.service_id', $lockedMapping->service_id)
                    ->exists();

                if (! $hasSourceCost || ! $hasActiveProductMapping) {
                    return ['ok' => false, 'status' => 422, 'message' => 'Enable a valid source-cost mapping and activate an approved product-level mapping for this platform service before enabling its service route.'];
                }
            }

            $lockedMapping->update(['enabled' => (bool) $data['enabled']]);
            return [
                'ok' => true,
                'enabled' => (bool) $data['enabled'],
                'mapping_id' => $lockedMapping->id,
                'service_id' => $lockedMapping->service_id,
            ];
        }, 3);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        try {
            $audit->record($result['enabled'] ? 'catalogue.mapping.enabled' : 'catalogue.mapping.disabled', $mapping->fresh(), [
                'provider_id' => $provider->id,
                'service_id' => $result['service_id'],
                'provider_service_id' => $mapping->provider_service_id,
            ], $request);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'status' => 'updated',
            'enabled' => $result['enabled'],
            'message' => $result['enabled'] ? 'Service route enabled after readiness checks.' : 'Service route disabled.',
        ]);
    }

    public function importSelected(Request $request, ApiProvider $provider): JsonResponse
    {
        $data=$request->validate(['provider_service_ids'=>'required|array|min:1','provider_service_ids.*'=>'integer']);
        $services=$provider->providerServices()->whereIn('id',$data['provider_service_ids'])->get();
        $imported=0;
        foreach($services as $service){
            $import=ProviderServiceImport::where('api_provider_id',$provider->id)->where('provider_service_id',$service->id)->first();
            if(!$import || !$import->approved) continue;
            $import->update(['imported'=>true,'state'=>'imported','last_imported_at'=>now()]);
            $service->update(['status'=>'imported']);
            $imported++;
        }
        return response()->json(['status'=>'success','imported'=>$imported,'skipped_unapproved'=>count($data['provider_service_ids'])-$imported]);
    }

    private function normalizeDiscoveredService(array $item): array
    {
        $categoryValue=$item['category'] ?? $item['category_name'] ?? $item['service_category'] ?? $item['categoryName'] ?? null;
        $subcategoryValue=$item['subcategory'] ?? $item['subcategory_name'] ?? $item['sub_category'] ?? $item['subCategory'] ?? null;
        $category=$this->normalizeLabel($categoryValue);
        $subcategory=$this->normalizeLabel($subcategoryValue);

        $price=$item['price'] ?? $item['amount'] ?? $item['cost'] ?? $item['provider_price'] ?? $item['providerPrice'] ?? null;
        return [
            'external_service_id'=>(string)($item['id'] ?? $item['service_id'] ?? $item['serviceId'] ?? $item['product_id'] ?? $item['productId'] ?? $item['code'] ?? hash('sha256', json_encode([
                $item['category'] ?? $item['category_name'] ?? $item['service_category'] ?? null,
                $item['subcategory'] ?? $item['subcategory_name'] ?? $item['sub_category'] ?? null,
                $item['service_type'] ?? $item['serviceType'] ?? $item['type'] ?? null,
                $item['network'] ?? $item['operator'] ?? $item['network_name'] ?? $item['networkName'] ?? null,
                $item['name'] ?? $item['service_name'] ?? $item['serviceName'] ?? $item['product_name'] ?? $item['productName'] ?? $item['title'] ?? null,
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))),
            'external_service_code'=>$this->normalizeScalar($item['code'] ?? $item['service_code'] ?? $item['serviceCode'] ?? $item['product_code'] ?? $item['productCode']),
            'name'=>$this->normalizeScalar($item['name'] ?? $item['service_name'] ?? $item['serviceName'] ?? $item['product_name'] ?? $item['productName'] ?? $item['title']) ?: 'Unnamed provider service',
            'description'=>$this->normalizeScalar($item['description'] ?? $item['details'] ?? $item['service_description']),
            'service_type'=>$this->normalizeScalar($item['service_type'] ?? $item['serviceType'] ?? $item['type']),
            'network'=>$this->normalizeScalar($item['network'] ?? $item['operator'] ?? $item['network_name'] ?? $item['networkName']),
            'provider_price'=>is_numeric($price) ? $price : null,
            'currency'=>$this->normalizeScalar($item['currency'] ?? $item['currency_code'] ?? 'NGN') ?: 'NGN',
            'status'=>$this->normalizeScalar($item['status'] ?? $item['active'] ?? 'discovered') ?: 'discovered',
            'category'=>$category,
            'category_id'=>$this->normalizeScalar(is_array($categoryValue) ? ($categoryValue['id'] ?? $categoryValue['code'] ?? null) : null),
            'category_metadata'=>is_array($categoryValue) ? $categoryValue : [],
            'subcategory'=>$subcategory,
            'subcategory_id'=>$this->normalizeScalar(is_array($subcategoryValue) ? ($subcategoryValue['id'] ?? $subcategoryValue['code'] ?? null) : null),
            'subcategory_metadata'=>is_array($subcategoryValue) ? $subcategoryValue : [],
            'metadata'=>[
                'discovery_normalized'=>true,
                'source_fields'=>array_keys($item),
            ],
        ];
    }

    private function normalizeLabel(mixed $value): ?string
    {
        if(is_array($value)) $value=$value['name'] ?? $value['title'] ?? $value['label'] ?? $value['code'] ?? null;
        $value=$this->normalizeScalar($value);
        return $value!==null && $value!=='' ? $value : null;
    }

    private function normalizeScalar(mixed $value): ?string
    {
        if(is_bool($value)) return $value?'active':'inactive';
        if(is_scalar($value)) return trim((string)$value);
        return null;
    }

    private function endpointUrl(ProviderConnection $connection, ProviderEndpoint $endpoint): string
    {
        if (filled($endpoint->full_url)) {
            app(ProviderUrlGuard::class)->validate($endpoint->full_url);
            return $endpoint->full_url;
        }

        app(ProviderUrlGuard::class)->validate($connection->base_url);
        $prefix=trim((string)$connection->api_prefix,'/');
        $path=trim((string)$endpoint->path,'/');
        return rtrim($connection->base_url,'/').($prefix?'/'.$prefix:'').($path?'/'.$path:'');
    }

    private function sendEndpointRequest($client, ProviderEndpoint $endpoint, string $url, array $body, array $query)
    {
        return match($endpoint->content_type){
            'query'=>$client->request($endpoint->method,$url,$query),
            'form-data','x-www-form-urlencoded'=>$client->asForm()->request($endpoint->method,$url,$body),
            'raw'=>$client->withBody((string)($body['raw'] ?? ''),'text/plain')->request($endpoint->method,$url),
            default=>$client->request($endpoint->method,$url,$body),
        };
    }

    private function mapResponse(mixed $payload, array $mapping): array
    {
        $mapped=[];
        foreach($mapping as $target=>$source){
            if(!is_string($source)) continue;
            $mapped[$target]=data_get($payload,$source);
        }
        return $mapped;
    }

    private function writeOperationLog(
        ApiProvider $provider,
        ?ProviderConnection $connection,
        string $operation,
        ?string $method,
        ?string $endpoint,
        string $result,
        ?int $httpStatus,
        ?int $durationMs,
        ?string $errorCode = null,
        ?string $message = null,
        ?array $safeMetadata = null
    ): void {
        try {
            ProviderOperationLog::create([
                'api_provider_id' => $provider->id,
                'provider_connection_id' => $connection?->id,
                'operation' => $operation,
                'method' => $method,
                'endpoint' => $endpoint ? $this->safeUrlForDisplay($endpoint) : null,
                'internal_reference' => (string) Str::uuid(),
                'http_status' => $httpStatus,
                'duration_ms' => $durationMs,
                'result' => $result,
                'error_code' => $errorCode,
                'safe_message' => $message,
                'safe_metadata' => $this->redactForLog($safeMetadata),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Provider operation failed.', ['exception_class' => get_class($e)]);
        }
    }

    private function connectionSummary(ProviderConnection $connection): array
    {
        return [
            'id'=>$connection->id,
            'name'=>$connection->name,
            'environment'=>$connection->environment,
            'base_url'=>$this->safeUrlForDisplay($connection->base_url),
            'api_version'=>$connection->api_version,
            'api_prefix'=>$connection->api_prefix,
            'auth_type'=>$connection->auth_type,
            'auth_options'=>$this->safeAuthOptions((array)($connection->auth_options ?? [])),
            'verify_ssl'=>$connection->verify_ssl,
            'enabled'=>$connection->enabled,
            'is_default'=>$connection->is_default,
            'headers'=>$this->safeKeyValueMap((array)($connection->headers ?? [])),
            'query_params'=>$this->safeKeyValueMap((array)($connection->query_params ?? [])),
            'proxy'=>$this->safeKeyValueMap((array)($connection->proxy ?? [])),
            'credentials'=>$connection->credentials->map(fn(ProviderCredential $credential)=>[
                'id'=>$credential->id,'field_key'=>$credential->field_key,'label'=>$credential->label,
                'field_type'=>$credential->field_type,'required'=>$credential->required,'secret'=>$credential->secret,
                'placement'=>$credential->placement,'header_name'=>$credential->header_name,
                'query_name'=>$credential->query_name,'body_path'=>$credential->body_path,
                'prefix'=>$credential->prefix,'has_value'=>filled($credential->value),
                'value'=>filled($credential->value) ? '••••••••' : null,
            ])->values(),
        ];
    }

    private function endpointSummary(ProviderEndpoint $endpoint): array
    {
        return [
            'id'=>$endpoint->id,
            'name'=>$endpoint->name,
            'operation'=>$endpoint->operation,
            'method'=>$endpoint->method,
            'path'=>$endpoint->path,
            'full_url'=>$this->safeUrlForDisplay($endpoint->full_url),
            'content_type'=>$endpoint->content_type,
            'auth_mode'=>$endpoint->auth_mode,
            'headers'=>$this->safeKeyValueMap((array)($endpoint->headers ?? [])),
            'query_params'=>$this->safeKeyValueMap((array)($endpoint->query_params ?? [])),
            'request_mapping'=>$endpoint->request_mapping ?? [],
            'response_mapping'=>$endpoint->response_mapping ?? [],
            'error_mapping'=>$endpoint->error_mapping ?? [],
            'webhook_config'=>$this->redactForLog((array)($endpoint->webhook_config ?? [])),
            'enabled'=>$endpoint->enabled,
        ];
    }

    private function safeKeyValueMap(array $values): array
    {
        $safe = [];
        foreach ($values as $key => $value) {
            $safe[(string)$key] = '[CONFIGURED]';
        }
        return $safe;
    }

    private function safeUrlForDisplay(?string $url): ?string
    {
        if (blank($url)) return $url;
        $parts = parse_url($url);
        if ($parts === false) return '[CONFIGURED URL]';
        $host = $parts['host'] ?? null;
        $scheme = $parts['scheme'] ?? 'https';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        return $host ? $scheme.'://'.$host.$port.$path : '[CONFIGURED URL]';
    }

    private function safeAuthOptions(array $options): array
    {
        $safe = [];
        foreach ($options as $key => $value) {
            $name = strtolower((string) $key);
            $safe[$key] = preg_match('/token|secret|password|passwd|pin|api[_-]?key|authorization|auth|credential|private[_-]?key|signature|cookie|session|jwt|webhook|client[_-]?id|username/i', $name)
                ? '[REDACTED]'
                : (is_scalar($value) || $value === null ? $value : '[CONFIGURED]');
        }
        return $safe;
    }

    private function credentialSummary(ProviderConnection $connection): JsonResponse
    {
        $data=$connection->credentials->map(fn(ProviderCredential $c)=>[
            'id'=>$c->id,'field_key'=>$c->field_key,'label'=>$c->label,'field_type'=>$c->field_type,
            'required'=>$c->required,'secret'=>$c->secret,'placement'=>$c->placement,
            'header_name'=>$c->header_name,'query_name'=>$c->query_name,'body_path'=>$c->body_path,
            'prefix'=>$c->prefix,'has_value'=>filled($c->value),
            'value'=>filled($c->value) ? '••••••••' : null,
        ])->values();
        return response()->json(['data'=>$data]);
    }

    private function authenticationPayload(ProviderConnection $connection, array $body, string $authMode = 'connection'): array
    {
        if ($authMode === 'none') {
            return [[], [], $body];
        }
        if ($authMode === 'custom') {
            return [[], [], $body];
        }
        $headers=(array)($connection->headers ?? []);
        $query=(array)($connection->query_params ?? []);

        $credentials=$connection->credentials()->get();
        if ($connection->auth_type==='basic') {
            $username=$credentials->firstWhere('field_key','username')?->value;
            $password=$credentials->firstWhere('field_key','password')?->value;
            if ($username!==null && $password!==null) $headers['Authorization']='Basic '.base64_encode($username.':'.$password);
        } else {
            foreach ($credentials as $credential) {
                if (!filled($credential->value)) continue;
                $value=$credential->value;
                if ($credential->prefix) $value=$credential->prefix.' '.$value;
                if ($credential->placement==='authorization') $headers['Authorization']=$value;
                elseif ($credential->placement==='header' && $credential->header_name) $headers[$credential->header_name]=$value;
                elseif ($credential->placement==='query' && $credential->query_name) $query[$credential->query_name]=$value;
                elseif (in_array($credential->placement,['body','form'],true) && $credential->body_path) data_set($body,$credential->body_path,$credential->value);
            }
        }
        return [$headers,$query,$body];
    }

    private function extractItems(mixed $payload): array
    {
        if (!is_array($payload)) return [];
        foreach (['data','services','products','items','results','variations'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) return array_is_list($payload[$key]) ? $payload[$key] : [$payload[$key]];
        }
        return array_is_list($payload) ? $payload : [];
    }
}