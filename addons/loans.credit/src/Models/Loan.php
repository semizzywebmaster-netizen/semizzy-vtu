<?php
namespace Semizzy\Addons\Loans\Models;
use IlluminateDatabaseEloquentModel;
class Loan extends Model { protected $table='loans'; protected $guarded=[]; protected $casts=['principal_minor'=>'integer','interest_minor'=>'integer','total_due_minor'=>'integer','repaid_minor'=>'integer','approved_at'=>'datetime','disbursed_at'=>'datetime','due_at'=>'datetime','closed_at'=>'datetime','metadata'=>'array']; public function product(){return $this->belongsTo(LoanProduct::class,'loan_product_id');} public function user(){return $this->belongsTo(\App\Models\User::class);} }