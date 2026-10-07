<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\MailerSmtpController;
Route::prefix('admin/mailer-smtp')->middleware(['auth','verified','role:ADMIN,STAFF,SUPPORT'])->group(function():void{
 Route::get('/',[MailerSmtpController::class,'index'])->middleware('permission:mailer.view')->name('admin.mailer-smtp.index');
 Route::post('/profiles',[MailerSmtpController::class,'store'])->middleware('permission:mailer.manage')->name('admin.mailer-smtp.profiles.store');
 Route::put('/profiles/{profile}',[MailerSmtpController::class,'update'])->whereNumber('profile')->middleware('permission:mailer.manage')->name('admin.mailer-smtp.profiles.update');
 Route::post('/profiles/{profile}/toggle',[MailerSmtpController::class,'toggle'])->whereNumber('profile')->middleware('permission:mailer.manage')->name('admin.mailer-smtp.profiles.toggle');
 Route::post('/profiles/{profile}/test',[MailerSmtpController::class,'test'])->whereNumber('profile')->middleware(['permission:mailer.manage','throttle:5,10'])->name('admin.mailer-smtp.profiles.test');
 Route::delete('/profiles/{profile}',[MailerSmtpController::class,'destroy'])->whereNumber('profile')->middleware('permission:mailer.manage')->name('admin.mailer-smtp.profiles.destroy');
 Route::put('/settings',[MailerSmtpController::class,'settings'])->middleware('permission:mailer.settings.manage')->name('admin.mailer-smtp.settings');
});