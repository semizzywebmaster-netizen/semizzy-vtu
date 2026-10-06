<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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

        return Inertia::render('Admin/Settings', [
            'settings' => [
                ...$settings,
                'smtp_env' => [
                    'mailer' => env('MAIL_MAILER', 'log'),
                    'host' => env('MAIL_HOST', ''),
                    'port' => (int) env('MAIL_PORT', 587),
                    'encryption' => env('MAIL_SCHEME', 'tls'),
                    'from_address' => env('MAIL_FROM_ADDRESS', ''),
                    'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'SEMIZZY ONE')),
                ],
                'smtp_providers' => [
                    [
                        'key'=>'sendpulse','name'=>'SendPulse','host'=>'smtp-pulse.com','port'=>587,'encryption'=>'tls',
                        'limit'=>'Free plan: up to 12,000/month',
                        'setup'=>'Create/activate SMTP in SendPulse, verify your domain/sender, then copy SMTP server, port, login and password from SMTP Settings > General.',
                    ],
                    [
                        'key'=>'gmail','name'=>'Google / Gmail','host'=>'smtp.gmail.com','port'=>587,'encryption'=>'tls',
                        'limit'=>'Best for testing / low-volume',
                        'setup'=>'Enable Google 2-Step Verification, create a Google App Password, then use the Gmail address as username and the 16-character App Password as SMTP password.',
                    ],
                    [
                        'key'=>'brevo','name'=>'Brevo','host'=>'smtp-relay.brevo.com','port'=>587,'encryption'=>'tls',
                        'limit'=>'300 emails/day',
                        'setup'=>'Create a Brevo SMTP key, verify your sender/domain, then enter the SMTP relay host, port, login and SMTP key.',
                    ],
                    [
                        'key'=>'smtp2go','name'=>'SMTP2GO','host'=>'mail.smtp2go.com','port'=>2525,'encryption'=>'tls',
                        'limit'=>'Free allowance varies by current plan',
                        'setup'=>'Create an SMTP user in SMTP2GO, verify your sender/domain and copy the SMTP credentials into this profile.',
                    ],
                    [
                        'key'=>'mailjet','name'=>'Mailjet','host'=>'in-v3.mailjet.com','port'=>587,'encryption'=>'tls',
                        'limit'=>'Free allowance varies by current plan',
                        'setup'=>'Verify your sender/domain, create/use the Mailjet API key and secret as SMTP username/password, then use the displayed SMTP host and port.',
                    ],
                    [
                        'key'=>'resend','name'=>'Resend SMTP','host'=>'smtp.resend.com','port'=>587,'encryption'=>'tls',
                        'limit'=>'Free allowance varies by current plan',
                        'setup'=>'Verify your sending domain in Resend and use an SMTP/API credential supported by your Resend account; username is typically resend and password is the API key.',
                    ],
                    [
                        'key'=>'mailersend','name'=>'MailerSend','host'=>'smtp.mailersend.net','port'=>587,'encryption'=>'tls',
                        'limit'=>'Free allowance varies by current plan',
                        'setup'=>'Verify your sending domain, create an SMTP token/credential in MailerSend and enter its host, port, username and password here.',
                    ],
                    [
                        'key'=>'cpanel','name'=>'Custom / cPanel SMTP','host'=>'mail.yourdomain.com','port'=>465,'encryption'=>'ssl',
                        'limit'=>'Depends on your hosting provider',
                        'setup'=>'In cPanel create an Email Account, open Connect Devices, choose Secure SSL/TLS settings and copy the outgoing SMTP server, port, email username and mailbox password.',
                    ],
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
            'smtp.strategy'=>['nullable','in:failover,roundrobin'],
            'smtp.profiles'=>['nullable','array','max:20'],
            'smtp.profiles.*.key'=>['required','string','regex:/^[a-z0-9_-]{2,40}$/'],
            'smtp.profiles.*.name'=>['required','string','max:80'],
            'smtp.profiles.*.provider'=>['required','string','max:40'],
            'smtp.profiles.*.enabled'=>['nullable','boolean'],
            'smtp.profiles.*.priority'=>['nullable','integer','min:1','max:999'],
            'smtp.profiles.*.weight'=>['nullable','integer','min:1','max:100'],
            'smtp.profiles.*.host'=>['required','string','max:255'],
            'smtp.profiles.*.port'=>['required','integer','between:1,65535'],
            'smtp.profiles.*.encryption'=>['required','in:tls,ssl,null'],
            'smtp.profiles.*.username'=>['nullable','string','max:255'],
            'smtp.profiles.*.password'=>['nullable','string','max:1000'],
            'smtp.profiles.*.from_address'=>['nullable','email','max:254'],
            'smtp.profiles.*.from_name'=>['nullable','string','max:120'],
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
        $data['smtp']=$this->prepareSmtp($data['smtp']??[]);

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
            $audit->record('admin.system_settings.updated',null,['setting_keys'=>self::KEYS,'smtp_strategy'=>$data['smtp']['strategy']??'failover'], $request);
            return back()->with('success','System settings saved and published globally.');
        }catch(\Throwable $e){
            report($e);
            return back()->with('error','System settings could not be saved safely.');
        }
    }

    private function prepareSmtp(array $smtp): array
    {
        $existingRaw = SystemSetting::query()->where('key','smtp')->value('value');
        $existing = is_string($existingRaw) ? (json_decode($existingRaw,true) ?: []) : [];
        $existingProfiles = [];

        if (isset($existing['profiles']) && is_array($existing['profiles'])) {
            $existingProfiles = $existing['profiles'];
        } elseif (isset($existing['host'])) {
            $existingProfiles = [[
                'key'=>'legacy',
                'name'=>(string)($existing['provider']??'Existing SMTP'),
                'provider'=>(string)($existing['provider']??'custom'),
                'enabled'=>(bool)($existing['enabled']??false),
                'priority'=>1,'weight'=>1,
                'host'=>(string)($existing['host']??''),
                'port'=>(int)($existing['port']??587),
                'encryption'=>(string)($existing['encryption']??'tls'),
                'username'=>(string)($existing['username']??''),
                'password'=>(string)($existing['password']??''),
                'from_address'=>(string)($existing['from_address']??''),
                'from_name'=>(string)($existing['from_name']??''),
            ]];
        }

        $oldByKey = [];
        foreach ($existingProfiles as $profile) if (is_array($profile) && !empty($profile['key'])) $oldByKey[(string)$profile['key']]=$profile;

        $clean=[];
        foreach (($smtp['profiles']??[]) as $profile) {
            $key=(string)$profile['key'];
            $old=$oldByKey[$key]??[];
            $password=(string)($profile['password']??'');
            if ($password==='') $password=(string)($old['password']??'');
            elseif (!empty($password)) {
                try { $password=Crypt::encryptString($password); } catch (\Throwable) {}
            }

            $clean[]=[
                'key'=>$key,
                'name'=>(string)$profile['name'],
                'provider'=>(string)$profile['provider'],
                'enabled'=>(bool)($profile['enabled']??false),
                'priority'=>(int)($profile['priority']??1),
                'weight'=>(int)($profile['weight']??1),
                'host'=>(string)$profile['host'],
                'port'=>(int)$profile['port'],
                'encryption'=>(string)$profile['encryption'],
                'username'=>(string)($profile['username']??''),
                'password'=>$password,
                'from_address'=>(string)($profile['from_address']??''),
                'from_name'=>(string)($profile['from_name']??''),
            ];
        }

        return [
            'enabled'=>(bool)($smtp['enabled']??false),
            'strategy'=>(string)($smtp['strategy']??'failover'),
            'profiles'=>$clean,
        ];
    }

    public function upload(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data=$request->validate([
            'asset'=>['required','in:logo,favicon,banner,hero'],
            'file'=>['required','file','mimes:png,jpg,jpeg,webp,ico','max:10240'],
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
        $data=$request->validate([
            'email'=>['required','email','max:254'],
            'profile_key'=>['required','string','max:40'],
        ]);

        $stored=SystemSetting::query()->where('key','smtp')->value('value');
        $smtp=is_string($stored)?(json_decode($stored,true)?:[]):[];
        $profile=collect($smtp['profiles']??[])->firstWhere('key',$data['profile_key']);

        if (!is_array($profile)) return back()->with('error','SMTP profile was not found.');
        try { $profile['password']=Crypt::decryptString((string)($profile['password']??'')); }
        catch (\Throwable) { return back()->with('error','Stored SMTP credentials could not be decrypted. Save the SMTP password again.'); }

        if(empty($profile['host']) || empty($profile['username']) || empty($profile['password'])){
            return back()->with('error','SMTP profile is not fully configured.');
        }

        config(['mail.mailers.smtp_test'=>[
            'transport'=>'smtp',
            'host'=>$profile['host'],
            'port'=>(int)$profile['port'],
            'encryption'=>$profile['encryption']==='null'?null:$profile['encryption'],
            'username'=>$profile['username'],
            'password'=>$profile['password'],
            'timeout'=>15,
        ]]);

        try{
            Mail::mailer('smtp_test')->raw(
                'SMTP test from '.($profile['from_name']??config('app.name','platform')),
                function($message)use($data,$profile){
                    $message->to($data['email'])
                        ->from($profile['from_address']??config('mail.from.address'),$profile['from_name']??config('mail.from.name'))
                        ->subject('SMTP connection test — '.($profile['name']??'SMTP'));
                }
            );
            return back()->with('success','SMTP test email sent successfully through '.($profile['name']??'selected provider').'.');
        }catch(\Throwable $e){
            report($e);
            return back()->with('error','SMTP test failed. Check credentials, sender verification, host, port, encryption and DNS.');
        }
    }
}
