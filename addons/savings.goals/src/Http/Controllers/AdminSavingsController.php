<?php
namespace Semizzy\\Addons\\Savings\\Http\\Controllers;
use App\\Http\\Controllers\\Controller;
use Inertia\\Inertia;
use Semizzy\\Addons\\Savings\\Models\\{SavingsAccount,SavingsPlan};
class AdminSavingsController extends Controller { public function index(){return Inertia::render('Admin/Savings',['plans'=>SavingsPlan::latest()->get(),'accounts'=>SavingsAccount::with('plan')->latest()->paginate(25)]);} }