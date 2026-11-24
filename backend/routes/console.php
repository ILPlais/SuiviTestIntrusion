<?php

use App\Models\AuditEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
	$this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purge quotidienne du journal d'audit selon la rétention (identite-6, suivi.audit.retention_days).
Schedule::command('model:prune', ['--model' => [AuditEvent::class]])->daily();
