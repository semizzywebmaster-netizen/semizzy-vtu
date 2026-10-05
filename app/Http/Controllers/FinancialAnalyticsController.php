<?php
namespace App\Http\Controllers;
use App\Services\Analytics\FinancialAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
class FinancialAnalyticsController extends Controller {
 public function index(Request $request,FinancialAnalyticsService $analytics){return Inertia::render('Analytics/Financial',['analytics'=>$analytics->summarize($request->user()->id,$request->query('from'),$request->query('to'))]);}
 public function export(Request $request,FinancialAnalyticsService $analytics):StreamedResponse{
  $rows=$analytics->exportRows($request->user()->id,$request->query('from'),$request->query('to'));
  return response()->streamDownload(function()use($rows):void{$out=fopen('php://output','w');fputcsv($out,['Date','Reference','Status','Category','Amount (minor)','Fee (minor)','Total (minor)','Currency']);foreach($rows as $row)fputcsv($out,$row);fclose($out);},'semizzy-financial-report-'.now()->format('Y-m-d').'.csv',['Content-Type'=>'text/csv']);
 }
}