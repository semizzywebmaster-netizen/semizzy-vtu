<?php
namespace Semizzy\Addons\P2p\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Semizzy\Addons\P2p\Models\P2pTransfer;
use Semizzy\Addons\P2p\Services\P2pTransferService;

final class P2pTransferController extends Controller
{
    private function amountToMinor(string $amount): string
    {
        $amount = trim($amount);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            abort(422, 'Amount must be a valid NGN amount with at most 2 decimal places.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');
        return ltrim($whole . $fraction, '0') ?: '0';
    }

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
            'amount' => 'required|string|max:30',
            'note' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:120',
        ]);

        $tx = $service->transfer(
            $request->user()->id,
            $data['recipient'],
            $this->amountToMinor($data['amount']),
            $data['note'] ?? null,
            $data['idempotency_key'] ?? Str::uuid()->toString()
        );

        return redirect()->route('p2p.transfers')
            ->with('success', 'Transfer of ₦' . number_format(((int) $tx->amount_minor) / 100, 2) . " completed. Reference: {$tx->reference}");
    }

    public function apiStore(Request $request, P2pTransferService $service): JsonResponse
    {
        $data = $request->validate([
            'recipient' => 'required|string|max:190',
            'amount' => 'required|string|max:30',
            'note' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:120',
        ]);

        $tx = $service->transfer(
            $request->user()->id,
            $data['recipient'],
            $this->amountToMinor($data['amount']),
            $data['note'] ?? null,
            $data['idempotency_key'] ?? Str::uuid()->toString()
        );

        return response()->json([
            'success' => true,
            'reference' => $tx->reference,
            'status' => $tx->status,
            'amount_minor' => (string) $tx->amount_minor,
            'fee_minor' => (string) $tx->fee_minor,
            'currency' => $tx->currency,
        ], 201);
    }
}
