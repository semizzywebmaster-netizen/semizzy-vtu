<?php
namespace Semizzy\Addons\Investments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Semizzy\Addons\Investments\Models\InvestmentAccount;
use Semizzy\Addons\Investments\Models\InvestmentCorporateAction;
use Semizzy\Addons\Investments\Models\InvestmentHolding;
use Semizzy\Addons\Investments\Models\InvestmentProduct;
use Semizzy\Addons\Investments\Models\InvestmentSecurity;
use Semizzy\Addons\Investments\Services\InvestmentService;

class InvestmentController extends Controller
{
    public function index(Request $r)
    {
        return inertia('Investments', [
            'products' => InvestmentProduct::where('active', true)->get(),
            'investments' => InvestmentAccount::where('user_id', $r->user()->id)->with('product')->latest()->get(),
        ]);
    }

    public function market(Request $r)
    {
        return inertia('Investments/Market', [
            'securities' => InvestmentSecurity::where('status', 'published')
                ->with(['quotes' => fn ($q) => $q->latest('observed_at')->limit(1)])
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function marketApi(Request $r)
    {
        return response()->json([
            'data' => InvestmentSecurity::where('status', 'published')
                ->with(['quotes' => fn ($q) => $q->latest('observed_at')->limit(1)])
                ->orderBy('name')
                ->paginate(30),
        ]);
    }

    public function portfolioApi(Request $r)
    {
        return response()->json([
            'data' => InvestmentHolding::where('user_id', $r->user()->id)
                ->with(['security' => fn ($q) => $q->where('status', 'published')])
                ->orderByDesc('updated_at')
                ->get()
                ->filter(fn ($holding) => $holding->security !== null)
                ->values(),
        ]);
    }

    public function corporateActionsApi(Request $r)
    {
        return response()->json([
            'data' => InvestmentCorporateAction::whereHas('security', fn ($q) => $q->where('status', 'published'))
                ->with('security')
                ->whereIn('status', ['announced', 'confirmed', 'processed'])
                ->orderByRaw('COALESCE(payment_date, ex_date, record_date) ASC')
                ->paginate(30),
        ]);
    }

    public function apiIndex(Request $r)
    {
        return response()->json([
            'products' => InvestmentProduct::where('active', true)->get(),
            'investments' => InvestmentAccount::where('user_id', $r->user()->id)->with('product')->latest()->get(),
        ]);
    }

    public function create(Request $r, InvestmentService $s)
    {
        $d = $r->validate(['product_key' => 'required|string', 'amount_minor' => 'required|integer|min:1']);
        return response()->json($s->create($r->user()->id, $d), 201);
    }

    public function fund(Request $r, string $reference, InvestmentService $s)
    {
        $r->validate(['idempotency_key' => 'required|string|max:128']);
        $a = InvestmentAccount::where('reference', $reference)->firstOrFail();
        return response()->json($s->fund($a, $r->user()->id, $r->header('Idempotency-Key') ?? $r->string('idempotency_key')->toString()));
    }

    public function redeem(Request $r, string $reference, InvestmentService $s)
    {
        $r->validate(['idempotency_key' => 'required|string|max:128']);
        $a = InvestmentAccount::where('reference', $reference)->firstOrFail();
        return response()->json($s->redeem($a, $r->user()->id, $r->header('Idempotency-Key') ?? $r->string('idempotency_key')->toString()));
    }
}
