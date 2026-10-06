<?php
namespace Semizzy\Addons\Loans\Models;
use Illuminate\Database\Eloquent\Model;
class LoanRepayment extends Model { protected $table='loan_repayments'; protected $guarded=[]; protected $casts=['amount_minor'=>'integer','balance_after_minor'=>'integer','metadata'=>'array']; public function loan(){return $this->belongsTo(Loan::class);} }