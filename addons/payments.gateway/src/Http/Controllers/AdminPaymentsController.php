<?php

namespace Semizzy\Addons\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Semizzy\Addons\Payments\Models\PaymentIntent;

final class AdminPaymentsController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = PaymentIntent::query()->latest('id')->paginate(25)->through(fn (PaymentIntent $p): array => [
            'reference'=>$p->reference,'user_id'=>$p->user_id,'amount_minor'=>$p->amount_minor,'currency'=>$p->currency,
            'status'=>$p->status,'provider_reference'=>$p->provider_reference,'expires_at'=>$p->expires_at?->toISOString(),
            'paid_at'=>$p->paid_at?->toISOString(),'created_at'=>$p->created_at?->toISOString(),
        ]);
        return Inertia::render('Admin/Payments', ['payments'=>$payments]);
    }
}