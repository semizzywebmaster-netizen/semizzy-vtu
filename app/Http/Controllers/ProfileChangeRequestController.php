<?php

namespace App\Http\Controllers;

use App\Models\ProfileChangeRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileChangeRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('ProfileChangeRequests', [
            'requests' => ProfileChangeRequest::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get()
                ->map(fn (ProfileChangeRequest $item): array => $this->payload($item))
                ->values(),
            'latestPending' => $this->pending($user),
            'canRequestBusiness' => (int) $user->tier >= 3,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'request_type' => ['required', Rule::in(['personal','business'])],
            'reason' => ['required','string','min:5','max:2000'],
            'name' => ['nullable','string','max:120'],
            'email' => ['nullable','email','max:255'],
            'phone' => ['nullable','string','max:30'],
            'date_of_birth' => ['nullable','date','before:today'],
            'gender' => ['nullable', Rule::in(['male','female','non_binary','prefer_not_to_say'])],
            'address' => ['nullable','string','max:1000'],
            'city' => ['nullable','string','max:100'],
            'state' => ['nullable','string','max:100'],
            'country' => ['nullable','string','max:100'],
            'postal_code' => ['nullable','string','max:30'],
            'occupation' => ['nullable','string','max:120'],
            'business_entity_type' => ['nullable', Rule::in(['business_name','private_company','public_company','llp','lp','incorporated_trustees'])],
            'business_registered_name' => ['nullable','string','max:180'],
            'business_registration_number' => ['nullable','string','max:120'],
            'business_tax_id' => ['nullable','string','max:120'],
            'business_registered_address' => ['nullable','string','max:500'],
            'business_state' => ['nullable','string','max:100'],
            'business_country' => ['nullable','string','max:100'],
            'business_documents' => ['nullable','array','min:1','max:8'],
            'business_documents.*' => ['file','mimes:pdf,jpg,jpeg,png,webp','max:10240'],
            'transaction_pin' => ['required','digits:4'],
        ]);

        if ($data['request_type'] === 'business' && (int) $user->tier < 3) {
            return back()->withErrors(['request_type' => 'Business/company name requests are available from Tier 3 upward.']);
        }

        if (ProfileChangeRequest::query()->where('user_id',$user->id)->where('status','pending')->exists()) {
            return back()->withErrors(['request' => 'You already have a pending profile-change request. Wait for it to be reviewed before creating another.']);
        }

        $pinHash = $user->transaction_pin_hash;
        if (! $pinHash || ! \Illuminate\Support\Facades\Hash::check($data['transaction_pin'], $pinHash)) {
            return back()->withErrors(['transaction_pin' => 'The transaction PIN is incorrect.']);
        }

        unset($data['transaction_pin']);

        if ($data['request_type'] === 'personal') {
            $changes = collect([
                'name','email','phone','date_of_birth','gender','address','city','state','country','postal_code','occupation',
            ])->filter(fn ($key) => array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '')->mapWithKeys(fn ($key) => [$key => $key === 'phone' ? preg_replace('/[^0-9+]/','',(string)$data[$key]) : $data[$key]])->all();

            if ($changes === []) {
                return back()->withErrors(['request' => 'Select at least one personal detail to change.']);
            }

            $requestModel = ProfileChangeRequest::create([
                'user_id'=>$user->id,'request_type'=>'personal','status'=>'pending',
                'requested_changes'=>$changes,'reason'=>trim($data['reason']),
            ]);
        } else {
            $files = [];
            foreach ($request->file('business_documents', []) as $file) {
                $files[] = $file->store('users/business-change-requests/'.$user->id, 'local');
            }

            if (empty($files) || blank($data['business_registered_name'] ?? null) || blank($data['business_registration_number'] ?? null) || blank($data['business_entity_type'] ?? null)) {
                return back()->withErrors(['business_documents' => 'Business/company registered name, registration number, entity type and at least one CAC document are required.']);
            }

            $requestModel = ProfileChangeRequest::create([
                'user_id'=>$user->id,'request_type'=>'business','status'=>'pending',
                'requested_changes'=>['name'=>$data['business_registered_name']],
                'reason'=>trim($data['reason']),
                'business_entity_type'=>$data['business_entity_type'],
                'business_registration_number'=>trim($data['business_registration_number']),
                'business_tax_id'=>filled($data['business_tax_id'] ?? null) ? trim($data['business_tax_id']) : null,
                'business_registered_name'=>trim($data['business_registered_name']),
                'business_registered_address'=>trim((string)($data['business_registered_address'] ?? '')) ?: null,
                'business_state'=>trim((string)($data['business_state'] ?? '')) ?: null,
                'business_country'=>trim((string)($data['business_country'] ?? 'Nigeria')) ?: 'Nigeria',
                'business_documents'=>$files,
            ]);
        }

        try {
            $audit->record('profile.change.requested', $user, [
                'request_id'=>$requestModel->id,'request_type'=>$requestModel->request_type,
            ], $request);
        } catch (\Throwable $e) { report($e); }

        return back()->with('success', 'Your profile change request has been submitted for administrator review.');
    }

    private function pending($user): ?array
    {
        $item = ProfileChangeRequest::query()->where('user_id',$user->id)->where('status','pending')->latest()->first();
        return $item ? $this->payload($item) : null;
    }

    private function payload(ProfileChangeRequest $item): array
    {
        return [
            'id'=>$item->id,'type'=>$item->request_type,'status'=>$item->status,
            'changes'=>$item->requested_changes,'reason'=>$item->reason,
            'businessEntityType'=>$item->business_entity_type,
            'businessRegisteredName'=>$item->business_registered_name,
            'businessRegistrationNumber'=>$item->business_registration_number,
            'businessTaxId'=>$item->business_tax_id,
            'businessRegisteredAddress'=>$item->business_registered_address,
            'businessState'=>$item->business_state,'businessCountry'=>$item->business_country,
            'documentCount'=>count($item->business_documents ?? []),
            'adminNote'=>$item->admin_note,'reviewedAt'=>$item->reviewed_at?->toISOString(),
            'createdAt'=>$item->created_at?->toISOString(),
        ];
    }
}
