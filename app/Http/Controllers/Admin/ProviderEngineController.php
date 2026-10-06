<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\ApiProvider;
use App\\Models\\ProviderConnection;
use App\\Models\\ProviderCredential;
use App\\Models\\ProviderEndpoint;
use App\\Models\\ProviderService;
use App\\Models\\ProviderServiceImport;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Http;
use Illuminate\\Support\\Str;

class ProviderEngineController extends Controller
{
    private const AUTH_TYPES = [
        'none','api_token','token','api_key','api_key_secret','username_password',
        'username_password_pin','client_id_secret','bearer','basic','custom',
    ];

    public function connections(ApiProvider $provider): JsonResponse
    {
        $connections=$provider->connections()->with('credentials')->orderByDesc('is_default')->latest()->get()->map(fn(ProviderConnection $connection)=>[
            'id'=>$connection->id,
            'name'=>$connection->name,
            'environment'=>$connection->environment,
            'base_url'=>$connection->base_url,
            'api_version'=>$connection->api_version,
            'api_prefix'=>$connection->api_prefix,
            'auth_type'=>$connection->auth_type,
            'auth_options'=>$connection->auth_options ?? [],
            'verify_ssl'=>$connection->verify_ssl,
            'enabled'=>$connection->enabled,
            'is_default'=>$connection->is_default,
            'credentials'=>$connection->credentials->map(fn(ProviderCredential $credential)=>[
                'id'=>$credential->id,'field_key'=>$credential->field_key,'label'=>$credential->label,
                'field_type'=>$credential->field_type,'required'=>$credential->required,'secret'=>$credential->secret,
                'placement'=>$credential->placement,'header_name'=>$credential->header_name,
                'query_name'=>$credential->query_name,'body_path'=>$credential->body_path,
                'prefix'=>$credential->prefix,'has_value'=>filled($credential->value),
                'value'=>filled($credential->value) ? '••••••••' : null,
            ])->values(),
        ])->values();
        return response()->json(['data'=>$connections]);
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

        return response()->json(['data'=>DB::transaction(function() use ($provider,$data){
            if (($data['is_default'] ?? false)) {
                $provider->connections()->update(['is_default'=>false]);
            }
            return $provider->connections()->create($data);
        })],201);
    }

    public function storeAuthentication(Request $request, ProviderConnection $connection): JsonResponse
    {
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

    public function storeCredential(Request $request, ProviderConnection $connection): JsonResponse
    {
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

    public function credentials(ProviderConnection $connection): JsonResponse
    {
        return $this->credentialSummary($connection->load('credentials'));
    }

    public function testConnection(Request $request, ApiProvider $provider): JsonResponse
    {
        $connection=$provider->connections()->where('enabled',true)->orderByDesc('is_default')->first();
        if(!$connection) return response()->json(['status'=>'configuration_required','message'=>'Configure an enabled connection first.'],422);

        $endpoint=$provider->endpoints()->where('enabled',true)->orderByRaw("CASE WHEN operation = 'health' THEN 0 WHEN operation = 'services' THEN 1 ELSE 2 END")->first();
        if(!$endpoint) return response()->json(['status'=>'configuration_required','message'=>'Configure an enabled endpoint for connection testing.'],422);

        $started=microtime(true);
        try{
            [$headers,$query,$body]=$this->authenticationPayload($connection,(array)($endpoint->request_mapping ?? []));
            $headers=array_merge($headers,(array)($endpoint->headers ?? []));
            $query=array_merge($query,(array)($endpoint->query_params ?? []));
            $url=$this->endpointUrl($connection,$endpoint);
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
            return response()->json(['status'=>$status,'http_status'=>$response->status(),'response_time_ms'=>$duration,'message'=>$status==='SUCCESS'?'Connection test succeeded.':'Connection test failed.']);
        }catch(\Throwable $e){
            report($e);
            $duration=(int)((microtime(true)-$started)*1000);
            ProviderHealthCheck::create(['api_provider_id'=>$provider->id,'provider_connection_id'=>$connection->id,'status'=>'FAILED','response_time_ms'=>$duration,'message'=>'Connection test failed safely.','checked_at'=>now()]);
            $connection->update(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_message'=>'Connection test failed safely.']);
            $provider->update(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_summary'=>'Connection test failed safely.']);
            return response()->json(['status'=>'FAILED','response_time_ms'=>$duration,'message'=>'Connection test failed safely.'],502);
        }
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
        if ($endpoint && $endpoint->api_provider_id !== $provider->id) {
            return response()->json(['message'=>'Endpoint does not belong to this provider.'],404);
        }
        $saved=$endpoint ? tap($endpoint)->update($data) : $provider->endpoints()->create($data);
        return response()->json(['data'=>$saved->fresh()], $endpoint ? 200 : 201);
    }

    public function endpoints(ApiProvider $provider): JsonResponse
    {
        return response()->json(['data'=>$provider->endpoints()->orderByDesc('enabled')->latest()->get()->map(fn(ProviderEndpoint $e)=>[
            'id'=>$e->id,'name'=>$e->name,'operation'=>$e->operation,'method'=>$e->method,
            'path'=>$e->path,'full_url'=>$e->full_url,'content_type'=>$e->content_type,
            'auth_mode'=>$e->auth_mode,'headers'=>$e->headers ?? [],'query_params'=>$e->query_params ?? [],
            'request_mapping'=>$e->request_mapping ?? [],'response_mapping'=>$e->response_mapping ?? [],
            'error_mapping'=>$e->error_mapping ?? [],'webhook_config'=>$e->webhook_config ?? [],
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
        try {
            $body=(array)($endpoint->request_mapping ?? []);
            foreach(($input['variables'] ?? []) as $key=>$value) data_set($body,$key,$value);
            [$headers,$query,$body]=$this->authenticationPayload($connection,$body);
            $headers=array_merge($headers,(array)($endpoint->headers ?? []));
            $query=array_merge($query,(array)($endpoint->query_params ?? []));
            $url=$this->endpointUrl($connection,$endpoint);

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
            return response()->json([
                'status'=>$safeStatus,'http_status'=>$response->status(),'duration_ms'=>$duration,
                'message'=>$response->successful()?'Endpoint request succeeded.':'Endpoint request returned an error.',
                'mapped_response'=>$this->mapResponse($payload,(array)($endpoint->response_mapping ?? [])),
                'mapped_error'=>$response->successful()?null:$this->mapResponse($payload,(array)($endpoint->error_mapping ?? [])),
            ],$response->successful()?200:502);
        } catch (\Throwable $e) {
            report($e);
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
        try {
            [$headers,$query,$body]=$this->authenticationPayload($connection,(array)($endpoint->request_mapping ?? []));
            $headers=array_merge($headers,(array)($endpoint->headers ?? []));
            $query=array_merge($query,(array)($endpoint->query_params ?? []));
            $url=$this->endpointUrl($connection,$endpoint);
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

            $connection->update(['last_tested_at'=>now(),'last_test_status'=>'SUCCESS','last_test_message'=>'Service discovery succeeded.']);
            return response()->json([
                'status'=>'success','discovered'=>$stored,
                'duration_ms'=>(int)((microtime(true)-$started)*1000),
                'categories'=>$provider->categories()->count(),
                'services'=>$provider->providerServices()->count(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $connection->update(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_message'=>'Service discovery failed safely.']);
            return response()->json(['status'=>'failed','message'=>'Service discovery failed safely. Review server-side diagnostics.'],502);
        }
    }

    public function catalogueProducts(): JsonResponse
    {
        return response()->json(['data'=>\App\Models\ServiceProduct::query()->with(['service.category'])->get()]);
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
        $rows=ProviderServiceImport::query()
            ->where('api_provider_id',$provider->id)
            ->with(['service.category','service.subcategory'])
            ->latest()
            ->get()
            ->map(fn(ProviderServiceImport $i)=>[
                'id'=>$i->id,'provider_service_id'=>$i->provider_service_id,
                'imported'=>$i->imported,'approved'=>$i->approved,'auto_sync_allowed'=>$i->auto_sync_allowed,'state'=>$i->state,
                'service'=>$i->service?->only(['id','external_service_id','external_service_code','name','description','service_type','network','provider_price','currency','status']),
                'category'=>$i->service?->category?->external_name,
                'subcategory'=>$i->service?->subcategory?->external_name,
            ]);
        return response()->json(['data'=>$rows]);
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
            'external_service_id'=>(string)($item['id'] ?? $item['service_id'] ?? $item['serviceId'] ?? $item['product_id'] ?? $item['productId'] ?? $item['code'] ?? Str::uuid()),
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
        if (filled($endpoint->full_url)) return $endpoint->full_url;
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

    private function authenticationPayload(ProviderConnection $connection, array $body): array
    {
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
