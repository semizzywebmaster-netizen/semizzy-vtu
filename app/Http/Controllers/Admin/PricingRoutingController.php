<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\PriceRule;
use App\Models\ProviderRoutingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingRoutingController extends Controller
{
    public function priceRules(): JsonResponse
    {
        return response()->json(['data'=>PriceRule::query()->orderBy('priority')->latest()->get()]);
    }

    public function storePriceRule(Request $request): JsonResponse
    {
        $data=$request->validate([
            'scope_type'=>'required|in:GLOBAL,CATEGORY,SERVICE,PRODUCT',
            'scope_id'=>'nullable|integer',
            'customer_tier'=>'nullable|in:USER,AGENT,RESELLER,MERCHANT,CUSTOM',
            'rule_type'=>'required|in:fixed,percentage,fixed_percentage',
            'fixed_fee'=>'nullable|numeric|min:0',
            'percentage'=>'nullable|numeric|min:0',
            'minimum_price'=>'nullable|numeric|min:0',
            'maximum_price'=>'nullable|numeric|min:0',
            'rounding_increment'=>'nullable|integer|min:0',
            'priority'=>'nullable|integer|min:1',
            'enabled'=>'nullable|boolean',
            'effective_from'=>'nullable|date',
            'effective_to'=>'nullable|date|after_or_equal:effective_from',
        ]);
        if($data['scope_type']==='GLOBAL' && !empty($data['scope_id'])) return response()->json(['message'=>'Global rules cannot have a scope id.'],422);
        $rule=PriceRule::create($data+['created_by'=>$request->user()?->id]);
        return response()->json(['data'=>$rule],201);
    }

    public function routingRules(): JsonResponse
    {
        return response()->json(['data'=>ProviderRoutingRule::query()->with('provider:id,display_name')->orderBy('priority')->get()]);
    }

    public function storeRoutingRule(Request $request): JsonResponse
    {
        $data=$request->validate([
            'api_provider_id'=>'required|integer|exists:api_providers,id',
            'scope_type'=>'required|in:GLOBAL,CATEGORY,SERVICE,PRODUCT',
            'scope_id'=>'nullable|integer',
            'priority'=>'nullable|integer|min:1',
            'max_attempts'=>'nullable|integer|min:1|max:20',
            'timeout_seconds'=>'nullable|integer|min:1|max:300',
            'enabled'=>'nullable|boolean',
            'conditions'=>'nullable|array',
        ]);
        if($data['scope_type']==='GLOBAL') $data['scope_id']=null;
        $rule=ProviderRoutingRule::create($data);
        return response()->json(['data'=>$rule->load('provider:id,display_name')],201);
    }

    public function updateRoutingRule(Request $request, ProviderRoutingRule $rule): JsonResponse
    {
        $data=$request->validate([
            'priority'=>'nullable|integer|min:1',
            'max_attempts'=>'nullable|integer|min:1|max:20',
            'timeout_seconds'=>'nullable|integer|min:1|max:300',
            'enabled'=>'nullable|boolean',
            'conditions'=>'nullable|array',
        ]);
        $rule->update($data);
        return response()->json(['data'=>$rule->fresh()],200);
    }

    public function deleteRoutingRule(ProviderRoutingRule $rule): JsonResponse
    {
        $rule->delete();
        return response()->json(['status'=>'deleted']);
    }
}
