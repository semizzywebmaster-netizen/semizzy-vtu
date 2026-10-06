<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    private const KEYS = [
        'platform_name','support_email','support_notice','default_timezone','theme_key','theme_primary','skin_default',
        'theme_custom_light','theme_custom_dark','business','social','assets','smtp',
    ];

    public function index(): Response
    {
        $settings = app(SystemSettingsService::class)->all();
        $smtp = $settings['smtp'];
        unset($smtp['password']);

        return Inertia::render('Admin/Settings', [
            'settings' => [
                ...$settings,
                'smtp' => $smtp,
                'smtp_env' => [
                    'mailer' => env('MAIL_MAILER', 'log'),
                    'host' => env('MAIL_HOST', ''),
                    'port' => (int) env('MAIL_PORT', 587),
                    'encryption' => env('MAIL_SCHEME', 'tls'),
                    'from_address' => env('MAIL_FROM_ADDRESS', ''),
                    'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'SEMIZZY ONE')),
                ],
                'smtp_providers' => [
                    ['key'=>'brevo','name'=>'Brevo','host'=>'smtp-relay.brevo.com','port'=>587,'encryption'=>'tls','limit'=>'300 emails/day'],
                    ['key'=>'smtp2go','name'=>'SMTP2GO','host'=>'mail.smtp2go.com','port'=>2525,'encryption'=>'tls','limit'=>'1,000 emails/month'],
                    ['key'=>'mailjet','name'=>'Mailjet','host'=>'in-v3.mailjet.com','port'=>587,'encryption'=>'tls','limit'=>'6,000 emails/month'],
                    ['key'=>'resend','name'=>'Resend SMTP','host'=>'smtp.resend.com','port'=>587,'encryption'=>'tls','limit'=>'3,000 emails/month'],
                    ['key'=>'mailersend','name'=>'MailerSend','host'=>'smtp.mailersend.net','port'=>587,'encryption'=>'tls','limit'=>'free allowance varies'],
                ],
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'platform_name'=>['required','string','min:2','max:80'],
            'support_email'=>['nullable','email','max:254'],
            'support_notice'=>['nullable','string','max:500'],
            'default_timezone'=>['required','timezone'],
            'theme_key'=>['required','in:opay-inspired,palmpay-inspired,kuda-inspired,moniepoint-inspired,stripe-inspired,premium-fintech,modern-corporate,clean-saas,vibrant-tech,luxury-executive,custom'],
            'theme_primary'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'skin_default'=>['required','in:light,dark'],
            'theme_custom_light'=>['nullable','array'],
            'theme_custom_dark'=>['nullable','array'],
            'business'=>['nullable','array'],
            'business.phone'=>['nullable','string','max:40'],
            'business.whatsapp'=>['nullable','string','max:40'],
            'business.email'=>['nullable','email','max:254'],
            'business.address'=>['nullable','string','max:255'],
            'business.website'=>['nullable','url','max:255'],
            'social'=>['nullable','array'],
            'social.*'=>['nullable','url','max:255'],
            'smtp'=>['nullable','array'],
            'smtp.enabled'=>['nullable','boolean'],
            'smtp.provider'=>['nullable','string','max:40'],
            'smtp.host'=>['nullable','string','max:255'],
            'smtp.port'=>['nullable','integer','between:1,65535'],
            'smtp.encryption'=>['nullable','in:tls,ssl,null'],
            'smtp.username'=>['nullable','string','max:255'],
            'smtp.password'=>['nullable','string','max:500'],
            'smtp.from_address'=>['nullable','email','max:254'],
            'smtp.from_name'=>['nullable','string','max:120'],
        ]);

        $paletteKeys=['primary','secondary','accent','background','surface','text','muted','border','success','warning','danger'];
        foreach(['theme_custom_light','theme_custom_dark'] as $field){
            $palette=$data[$field]??[];
            foreach($paletteKeys as $key){
                if(isset($palette[$key]) && !preg_match('/^#[0-9A-Fa-f]{6}$/',(string)$palette[$key])){
                    return back()->withErrors([$field=>'Every custom colour must be a valid 6-digit HEX colour.'])->withInput();
                }
            }
            $data[$field]=array_intersect_key($palette,array_flip($paletteKeys));
        }

        $data['business']=$data['business']??[];
        $data['social']=$data['social']??[];
        $smtp=$data['smtp']??[];
        $existing=SystemSetting::query()->where('key','smtp')->value('value');
        if(empty($smtp['password']) && is_string($existing)){
            $old=json_decode($existing,true);
            if(is_array($old) && !empty($old['password'])) $smtp['password']=$old['password'];
        }
        $data['smtp']=array_intersect_key($smtp,array_flip(['enabled','provider','host','port','encryption','username','password','from_address','from_name']));

        try {
            foreach(['platform_name','support_email','support_notice','default_timezone','theme_key','theme_primary','skin_default'] as $key){
                SystemSetting::query()->updateOrCreate(['key'=>$key],['value'=>$data[$key]??'','type'=>'string','is_secret'=>false]);
            }
            foreach(['theme_custom_light','theme_custom_dark','business','social','smtp'] as $key){
                SystemSetting::query()->updateOrCreate(['key'=>$key],[
                    'value'=>json_encode($data[$key]??[],JSON_UNESCAPED_SLASHES),
                    'type'=>'json',
                    'is_secret'=>$key==='smtp',
                ]);
            }
            $audit->record('admin.system_settings.updated',null,['setting_keys'=>self::KEYS],$request);
            return back()->with('success','System settings saved and published globally.');
        }catch(\Throwable $e){
            report($e);
            return back()->with('error','System settings could not be saved safely.');
        }
    }

    public function upload(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate([
            'asset'=>['required','in:logo,favicon,banner,hero'],
            'file'=>['required','file','mimes:png,jpg,jpeg,webp,svg,ico','max:10240'],
        ]);
        $key=$data['asset'];
        $stored=SystemSetting::query()->where('key','assets')->value('value');
        $assets=is_string($stored)?(json_decode($stored,true)?:[]):[];
        $old=$assets[$key]??'';
        $path=$request->file('file')->store('platform/'.$key,'public');
        $assets[$key]=Storage::disk('public')->url($path);
        SystemSetting::query()->updateOrCreate(['key'=>'assets'],['value'=>json_encode($assets),'type'=>'json','is_secret'=>false]);
        if($old && str_contains($old,'/storage/')){
            $oldPath=substr($old,strpos($old,'/storage/')+9);
            Storage::disk('public')->delete($oldPath);
        }
        $audit->record('admin.platform_asset.updated',null,['asset'=>$key],$request);
        return back()->with('success',ucfirst($key).' updated globally.');
    }

    public function testSmtp(Request $request): RedirectResponse
    {
        $data=$request->validate(['email'=>['required','email','max:254']]);
        $stored=SystemSetting::query()->where('key','smtp')->value('value');
        $smtp=is_string($stored)?(json_decode($stored,true)?:[]):[];
        if(empty($smtp['enabled']) || empty($smtp['host']) || empty($smtp['username']) || empty($smtp['password'])){
            return back()->with('error','SMTP is not fully configured. Add the SMTP credentials first.');
        }
        config(['mail.mailers.platform_smtp'=>[
            'transport'=>'smtp','host'=>$smtp['host'],'port'=>(int)($smtp['port']??587),
            'encryption'=>($smtp['encryption']??'tls')==='null'?null:($smtp['encryption']??'tls'),
            'username'=>$smtp['username'],'password'=>$smtp['password'],'timeout'=>15,
        ]]);
        try{
            Mail::mailer('platform_smtp')->raw('SMTP test from '.($smtp['from_name']??config('app.name','platform')),function($message)use($data,$smtp){
                $message->to($data['email'])->from($smtp['from_address']??config('mail.from.address'),$smtp['from_name']??config('mail.from.name'));
                $message->subject('SMTP connection test');
            });
            return back()->with('success','SMTP test email sent successfully.');
        }catch(\Throwable $e){
            report($e);
            return back()->with('error','SMTP test failed. Check host, port, encryption, username, password and DNS sender verification.');
        }
    }
}
