<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ProviderConnection;
use App\Models\ProviderCredential;
use App\Models\ProviderEndpoint;
use App\Models\ProviderService;
use App\Models\ProviderServiceImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProviderEngineController extends Controller
{
    public function storeConnection(Request $request, ApiProvider $provider): JsonResponse
    {
        $data = $request->validate([
            'name'=>'required|string|max:120',
            'environment'=>'required|in:sandbox,production',
            'base_url'=>'required|url|max:2048',
            'api_version'=>'nullable|string|max:100',
            'api_prefix'=>'nullable|string|max:255',
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

        $credential=$connection->credentials()->updateOrCreate(
            ['field_key'=>$data['field_key']],
            $data
        );

        return response()->json(['data'=>[
            'id'=>$credential->id,
            'field_key'=>$credential->field_key,
            'label'=>$credential->label,
            'field_type'=>$credential->field_type,
            'required'=>$credential->required,
            'secret'=>$credential->secret,
            'placement'=>$credential->placement,
            'header_name'=>$credential->header_name,
            'query_name'=>$credential->query_name,
            'body_path'=>$credential->body_path,
            'prefix'=>$credential->prefix,
            'value'=>filled($credential->value) ? '••••••••' : null,
        ]]);
    }

    public function storeEndpoint(Request $request, ApiProvider $provider): JsonResponse
    {
        $data=$request->validate([
            'name'=>'required|string|max:160',
            'operation'=>'nullable|string|max:100',
            'method'=>'required|in:GET,POST,PUT,PATCH,DELETE',
            'path'=>'nullable|string|max:2048',
            'full_url'=>'nullable|url|max:2048',
            'content_type'=>'nullable|in:json,form-data,x-www-form-urlencoded,query,raw',
            'auth_mode'=>'nullable|string|max:40',
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
        return response()->json(['data'=>$provider->endpoints()->create($data)],201);
    }

    public function discovery(Request $request, ApiProvider $provider): JsonResponse
    {
        $endpoint=$provider->endpoints()->where('operation','catalogue_retrieval')->where('enabled',true)->first()
            ?? $provider->endpoints()->where('enabled',true)->whereIn('operation',['services','products','categories'])->first();

        if (!$endpoint) {
            return response()->json(['status'=>'manual_required','message'=>'No catalogue discovery endpoint is configured. Add an endpoint or create provider services manually.'],422);
        }

        $connection=$provider->connections()->where('enabled',true)->orderByDesc('is_default')->first();
        if (!$connection) {
            return response()->json(['status'=>'configuration_required','message'=>'Configure an enabled provider connection first.'],422);
        }

        $started=microtime(true);
        try {
            $url=$endpoint->full_url ?: rtrim($connection->base_url,'/').'/'.ltrim($endpoint->path ?? '','/');
            $headers=(array)($connection->headers ?? []);
            $query=(array)($connection->query_params ?? []);
            foreach ($connection->credentials as $credential) {
                if (!$credential->value) continue;
                $value=$credential->value;
                if ($credential->prefix) $value=$credential->prefix.' '.$value;
                if ($credential->placement==='authorization') $headers['Authorization']=$value;
                elseif ($credential->placement==='header' && $credential->header_name) $headers[$credential->header_name]=$value;
                elseif ($credential->placement==='query' && $credential->query_name) $query[$credential->query_name]=$value;
            }

            $client=Http::withHeaders($headers)
                ->connectTimeout($connection->connect_timeout_seconds)
                ->timeout($connection->request_timeout_seconds);
            if (!$connection->verify_ssl) $client=$client->withoutVerifying();

            $response=match($endpoint->method){
                'POST'=>$client->post($url,(array)($endpoint->request_mapping ?? [])),
                'PUT'=>$client->put($url,(array)($endpoint->request_mapping ?? [])),
                'PATCH'=>$client->patch($url,(array)($endpoint->request_mapping ?? [])),
                'DELETE'=>$client->delete($url,(array)($endpoint->request_mapping ?? [])),
                default=>$client->get($url,$query),
            };

            $payload=$response->json();
            if (!$response->successful()) {
                return response()->json(['status'=>'failed','http_status'=>$response->status(),'message'=>'Provider discovery request failed.'],502);
            }

            $items=$this->extractItems($payload);
            $stored=0;
            foreach ($items as $item) {
                $externalId=(string)($item['id'] ?? $item['service_id'] ?? $item['serviceId'] ?? $item['code'] ?? Str::uuid());
                $name=(string)($item['name'] ?? $item['service_name'] ?? $item['serviceName'] ?? $item['product_name'] ?? 'Unnamed provider service');
                $category=(string)($item['category'] ?? $item['category_name'] ?? $item['service_category'] ?? 'Uncategorized');
                $categoryRow=$provider->categories()->firstOrCreate(
                    ['external_id'=>Str::slug($category)],
                    ['external_name'=>$category,'normalized_key'=>Str::slug($category),'status'=>'discovered']
                );
                ProviderService::updateOrCreate(
                    ['api_provider_id'=>$provider->id,'external_service_id'=>$externalId],
                    [
                        'provider_category_id'=>$categoryRow->id,
                        'external_service_code'=>(string)($item['code'] ?? $item['service_code'] ?? ''),
                        'name'=>$name,
                        'description'=>$item['description'] ?? null,
                        'service_type'=>$item['service_type'] ?? null,
                        'network'=>$item['network'] ?? $item['operator'] ?? null,
                        'provider_price'=>is_numeric($item['price'] ?? null) ? $item['price'] : null,
                        'currency'=>$item['currency'] ?? 'NGN',
                        'status'=>($item['status'] ?? 'discovered'),
                        'metadata'=>is_array($item) ? $item : [],
                        'raw_provider_data'=>is_array($item) ? $item : ['value'=>$item],
                        'last_synced_at'=>now(),
                    ]
                );
                $stored++;
            }

            $connection->update(['last_tested_at'=>now(),'last_test_status'=>'SUCCESS','last_test_message'=>'Service discovery succeeded.']);
            return response()->json(['status'=>'success','discovered'=>$stored,'duration_ms'=>(int)((microtime(true)-$started)*1000)]);
        } catch (\Throwable $e) {
            report($e);
            $connection->update(['last_tested_at'=>now(),'last_test_status'=>'FAILED','last_test_message'=>'Service discovery failed safely.']);
            return response()->json(['status'=>'failed','message'=>'Service discovery failed safely. Review server-side diagnostics.'],502);
        }
    }

    public function services(ApiProvider $provider): JsonResponse
    {
        $services=$provider->providerServices()->with(['category','subcategory'])->latest()->get()->map(fn(ProviderService $s)=>[
            'id'=>$s->id,
            'external_service_id'=>$s->external_service_id,
            'external_service_code'=>$s->external_service_code,
            'name'=>$s->name,
            'description'=>$s->description,
            'service_type'=>$s->service_type,
            'network'=>$s->network,
            'provider_price'=>$s->provider_price,
            'currency'=>$s->currency,
            'status'=>$s->status,
            'category'=>$s->category?->external_name,
            'subcategory'=>$s->subcategory?->external_name,
        ]);
        return response()->json(['data'=>$services]);
    }

    public function importSelected(Request $request, ApiProvider $provider): JsonResponse
    {
        $data=$request->validate(['provider_service_ids'=>'required|array|min:1','provider_service_ids.*'=>'integer']);
        $services=$provider->providerServices()->whereIn('id',$data['provider_service_ids'])->get();
        $imported=0;
        foreach($services as $service){
            ProviderServiceImport::updateOrCreate(
                ['api_provider_id'=>$provider->id,'provider_service_id'=>$service->id],
                ['selection_scope'=>'product','imported'=>true,'approved'=>true,'auto_sync_allowed'=>true,'state'=>'imported','last_imported_at'=>now()]
            );
            $service->update(['status'=>'imported']);
            $imported++;
        }
        return response()->json(['status'=>'success','imported'=>$imported]);
    }

    private function extractItems(mixed $payload): array
    {
        if (!is_array($payload)) return [];
        foreach (['data','services','products','items','results','variations'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : [$payload[$key]];
            }
        }
        return array_is_list($payload) ? $payload : [];
    }
}
