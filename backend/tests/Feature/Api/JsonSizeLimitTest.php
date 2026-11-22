<?php

namespace Tests\Feature\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Taille des messages JSON de l'API (11.03, socle-5) : 2 Mo par défaut, réglable ;
 * au-delà, 413 « payload_too_large ». Les fichiers de la GED (multipart) suivent
 * leur propre plafond (GED_MAX_FILE_SIZE) et ne sont pas concernés.
 */
class JsonSizeLimitTest extends TestCase
{
	/**
	 * Déclare une route de test qui accepte un corps JSON.
	 *
	 * @return void
	 */
	protected function setUp(): void
	{
		parent::setUp();

		Route::middleware('api')->post('api/v1/_taille', fn () => ['ok' => true]);
	}

	/**
	 * La limite par défaut est de 2 Mo.
	 *
	 * @return void
	 */
	public function test_the_default_limit_is_two_megabytes(): void
	{
		$this->assertSame(2 * 1024 * 1024, config('api.max_json_size'));
	}

	/**
	 * Un corps JSON sous la limite est accepté.
	 *
	 * @return void
	 */
	public function test_a_json_body_under_the_limit_is_accepted(): void
	{
		config(['api.max_json_size' => 1024]);

		$this->postJson('/api/v1/_taille', ['texte' => str_repeat('a', 500)])->assertOk();
	}

	/**
	 * Un corps JSON au-delà de la limite est refusé en 413.
	 *
	 * @return void
	 */
	public function test_a_json_body_over_the_limit_is_refused_with_413(): void
	{
		config(['api.max_json_size' => 1024]);

		$this->postJson('/api/v1/_taille', ['texte' => str_repeat('a', 2000)])
			->assertStatus(413)
			->assertJsonPath('code', 'payload_too_large');
	}

	/**
	 * La limite s'applique même sans en-tête Content-Length.
	 *
	 * @return void
	 */
	public function test_the_limit_applies_even_without_a_content_length_header(): void
	{
		config(['api.max_json_size' => 1024]);

		$this->call('POST', '/api/v1/_taille', [], [], [], [
			'CONTENT_TYPE' => 'application/json',
			'HTTP_ACCEPT' => 'application/json',
		], json_encode(['texte' => str_repeat('a', 2000)]))
			->assertStatus(413);
	}

	/**
	 * La limite ne dépend pas du Content-Type déclaré.
	 *
	 * @return void
	 */
	public function test_the_limit_does_not_depend_on_the_declared_content_type(): void
	{
		// Un client ne contourne pas la limite en annonçant un autre type que JSON.
		config(['api.max_json_size' => 1024]);

		$this->call('POST', '/api/v1/_taille', [], [], [], [
			'CONTENT_TYPE' => 'text/plain',
			'HTTP_ACCEPT' => 'application/json',
		], str_repeat('a', 2000))
			->assertStatus(413);
	}

	/**
	 * Une longueur annoncée au-delà de la limite est refusée avant toute lecture.
	 *
	 * @return void
	 */
	public function test_an_announced_length_over_the_limit_is_refused_before_reading(): void
	{
		config(['api.max_json_size' => 1024]);

		$this->call('POST', '/api/v1/_taille', [], [], [], [
			'CONTENT_TYPE' => 'application/json',
			'CONTENT_LENGTH' => '5000000',
			'HTTP_ACCEPT' => 'application/json',
		], '{}')
			->assertStatus(413);
	}

	/**
	 * Les envois de fichiers n'utilisent pas la limite JSON.
	 *
	 * @return void
	 */
	public function test_file_uploads_do_not_use_the_json_limit(): void
	{
		config(['api.max_json_size' => 1024]);

		$this->post('/api/v1/_taille', ['preuve' => UploadedFile::fake()->create('preuve.gpg', 10)], ['Accept' => 'application/json'])
			->assertOk();
	}
}
