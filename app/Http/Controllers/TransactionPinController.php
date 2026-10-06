<?php
namespace App\Http\Controllers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
class TransactionPinController extends Controller {
 public function index(Request $request): Response { return Inertia::render('TransactionPin',['hasPin'=>filled($request->user()->transaction_pin_hash)]); }
 public function store(Request $request): RedirectResponse {
  $data=$request->validate(['pin'=>['required','digits:4'],'pin_confirmation'=>['required','same:pin'],'current_pin'=>['nullable','digits:4']]);
  $user=$request->user();
  if(filled($user->transaction_pin_hash)){
   if(!filled($data['current_pin']) || !Hash::check($data['current_pin'],(string)$user->transaction_pin_hash))
    throw ValidationException::withMessages(['current_pin'=>'Enter your current transaction PIN to change it.']);
  }
  $user->forceFill(['transaction_pin_hash'=>Hash::make($data['pin'])])->saveOrFail();
  return back()->with('success',filled($user->getOriginal('transaction_pin_hash'))?'Transaction PIN changed successfully.':'Transaction PIN set successfully.');
 }
}