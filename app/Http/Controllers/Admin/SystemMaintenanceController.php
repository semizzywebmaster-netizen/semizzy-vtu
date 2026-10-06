<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\System\WebsiteBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class SystemMaintenanceController extends Controller
{
    public function backup(Request $request, WebsiteBackupService $backups, AuditLogger $audit): BinaryFileResponse|RedirectResponse
    {
        try {
            $path=$backups->create();
            $audit->record('system.backup.created',null,['name'=>basename($path)],$request);
            return response()->download($path,basename($path),['Content-Type'=>'application/zip'])->deleteFileAfterSend(false);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error','Website backup could not be created. Ensure PHP ZIP is enabled and storage is writable.');
        }
    }

    public function restore(Request $request, WebsiteBackupService $backups, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate(['backup'=>'required|file|mimes:zip|max:524288']);
        try {
            $backups->restore($data['backup']->getRealPath());
            Artisan::call('optimize:clear');
            $audit->record('system.backup.restored',null,['original_name'=>$data['backup']->getClientOriginalName()],$request);
            return back()->with('success','Backup restored successfully and application cache cleared.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error',$e instanceof RuntimeException?$e->getMessage():'Backup restore failed safely.');
        }
    }

    public function clearCache(Request $request, AuditLogger $audit): RedirectResponse
    {
        try {
            Artisan::call('optimize:clear');
            $audit->record('system.cache.cleared',null,['output'=>trim(Artisan::output())],$request);
            return back()->with('success','Application cache cleared successfully.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error','Application cache could not be cleared safely.');
        }
    }
}
