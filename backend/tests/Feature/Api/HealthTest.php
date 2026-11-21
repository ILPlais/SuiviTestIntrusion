<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrôle de santé de l'API : route versionnée, heure en UTC, panne de base
 * signalée sans détail technique (11.03).
 */
class HealthTest extends TestCase
{
	public function test_health_endpoint_is_versioned_and_reports_ok(): void
	{
		$response = $this->getJson('/api/v1/health');

		$response->assertOk()
			->assertExactJson([
				'status' => 'ok',
				'database' => 'ok',
				'time' => $response->json('time'),
			]);
	}

	public function test_health_time_is_utc_iso_8601(): void
	{
		$time = $this->getJson('/api/v1/health')->json('time');

		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $time);
	}

	public function test_unversioned_route_does_not_exist(): void
	{
		$this->getJson('/api/health')->assertNotFound();
	}

	public function test_health_reports_database_failure_without_technical_details(): void
	{
		// Quelle que soit la base testée en CI, la connexion par défaut devient une base SQLite introuvable.
		config([
			'database.default' => 'sqlite',
			'database.connections.sqlite.database' => '/inexistant/base.sqlite',
		]);
		DB::purge('sqlite');

		$response = $this->getJson('/api/v1/health');

		$response->assertStatus(503)
			->assertJsonPath('status', 'degraded')
			->assertJsonPath('database', 'error');
		$this->assertStringNotContainsString('inexistant', $response->getContent());
	}
}
