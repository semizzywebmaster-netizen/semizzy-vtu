<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Semizzy\Addons\Exams\Services\ExamResultService;
Schedule::call(fn()=>app(ExamResultService::class)->reconcile())->everyFiveMinutes()->withoutOverlapping();
Artisan::command('exams:reconcile',fn()=> $this->info('Reconciled '.app(ExamResultService::class)->reconcile().' exam transactions.'));
