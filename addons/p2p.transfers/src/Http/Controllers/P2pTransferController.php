<?php
namespace Semizzy\Addons\P2p\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\P2p\Models\P2pTransfer;
use Semizzy\Addons\P2p\Services\P2pTransferService;

final class P2pTransferController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('P2p/Transfers', [
            'transfers' => P2pTransfer::where('sender_id', $request->user()->id)
                ->orWhere('recipient_id', $request->user()->id)
                ->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, P2pTransferService $service)
    {
        $data = $request->validate([
            'recipient' => 'required|string|max:190',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:120',
        ]);

        $amountMinor = (string) round(((float) $data['amount']) * 100);
        $tx = $service->transfer(
            $request->user()->id,
            $data['recipient'],
            $amountMinor,
            $data['note'] ?? null,
            $data['idempotency_key'] ?? Str::uuid()->toString()
        );

        return redirect()->route('p2p.transfers')
            ->with('success', "Transfer of ₦" . number_format(((int) $tx->amount_minor) / 100, 2) . " completed. Reference: {$tx->reference}");
    }
}
