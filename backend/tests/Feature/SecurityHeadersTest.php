<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * En-têtes de sécurité HTTP (socle-6, 13.04, 13.06) : CSP stricte de la SPA avec
 * Trusted Types et rapports, CSP de l'API qui interdit toute interprétation,
 * nosniff, Referrer-Policy, HSTS envoyé par l'application, CORS fermé par défaut,
 * cookies SameSite=Strict, point de réception des rapports de violation.
 */
class SecurityHeadersTest extends TestCase
{
	/** Fichier index.html temporaire tenant lieu de SPA compilée. */
	private string $index;

	protected function setUp(): void
	{
		parent::setUp();

		$this->index = tempnam(sys_get_temp_dir(), 'spa').'.html';
		file_put_contents($this->index, '<!doctype html><div id="app"></div>');
		config(['app.spa_index' => $this->index]);
	}

	protected function tearDown(): void
	{
		@unlink($this->index);

		parent::tearDown();
	}

	public function test_api_responses_can_never_be_interpreted_as_a_page_or_script(): void
	{
		$this->getJson('/api/v1/health')
			->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; sandbox")
			->assertHeader('X-Content-Type-Options', 'nosniff')
			->assertHeader('Referrer-Policy', 'no-referrer');
	}

	public function test_the_spa_receives_the_strict_policy_of_the_decision(): void
	{
		$policy = (string) $this->get('/engagements')->headers->get('Content-Security-Policy');

		foreach ([
			"default-src 'none'",
			"script-src 'self' 'wasm-unsafe-eval'",
			"style-src 'self' 'unsafe-inline'",
			"img-src 'self' blob:",
			"connect-src 'self'",
			"worker-src 'self'",
			"object-src 'none'",
			"base-uri 'none'",
			"form-action 'self'",
			"frame-ancestors 'none'",
			"require-trusted-types-for 'script'",
			'trusted-types sti-render sti-worker dompurify default',
			'report-uri /api/v1/csp-reports',
			'report-to csp',
		] as $directive) {
			$this->assertStringContainsString($directive, $policy);
		}
		$this->assertStringNotContainsString("'unsafe-eval'", $policy);
	}

	public function test_the_spa_declares_its_reporting_endpoint(): void
	{
		$this->get('/')
			->assertHeader('Reporting-Endpoints', 'csp="'.url('/api/v1/csp-reports').'"')
			->assertHeader('X-Content-Type-Options', 'nosniff')
			->assertHeader('Referrer-Policy', 'no-referrer');
	}

	public function test_hsts_is_sent_by_the_application_over_https_only(): void
	{
		$this->get('https://localhost/api/v1/health')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
		$this->get('http://localhost/api/v1/health')->assertHeaderMissing('Strict-Transport-Security');
	}

	public function test_cors_is_closed_by_default(): void
	{
		$this->withHeader('Origin', 'https://autre-site.example')
			->getJson('/api/v1/health')
			->assertHeaderMissing('Access-Control-Allow-Origin');
	}

	public function test_cors_origins_are_configurable(): void
	{
		config(['cors.allowed_origins' => ['https://client.example']]);

		$this->withHeader('Origin', 'https://client.example')
			->getJson('/api/v1/health')
			->assertHeader('Access-Control-Allow-Origin', 'https://client.example');
	}

	public function test_session_cookies_are_strict_and_http_only(): void
	{
		$this->assertSame('strict', config('session.same_site'));
		$this->assertTrue(config('session.http_only'));
	}

	public function test_violation_reports_are_received_and_logged(): void
	{
		$log = Log::spy();

		$this->call('POST', '/api/v1/csp-reports', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode([
			'csp-report' => ['document-uri' => 'https://pentest.example.org/', 'violated-directive' => 'script-src-elem', 'blocked-uri' => 'inline'],
		]))->assertNoContent();

		$this->call('POST', '/api/v1/csp-reports', [], [], [], ['CONTENT_TYPE' => 'application/reports+json'], json_encode([
			['type' => 'csp-violation', 'body' => ['documentURL' => 'https://pentest.example.org/', 'effectiveDirective' => 'img-src', 'blockedURL' => 'https://tiers.example/x.png']],
		]))->assertNoContent();

		$log->shouldHaveReceived('warning')->twice();
	}

	public function test_the_expected_refusal_of_the_vue_policy_is_not_logged(): void
	{
		$log = Log::spy();

		$this->call('POST', '/api/v1/csp-reports', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode([
			'csp-report' => ['violated-directive' => 'trusted-types', 'blocked-uri' => 'trusted-types-policy', 'script-sample' => 'vue'],
		]))->assertNoContent();

		$log->shouldNotHaveReceived('warning');
	}

	public function test_reports_never_log_link_tokens_nor_query_strings(): void
	{
		$log = Log::spy();
		$token = str_repeat('a', 64);

		$this->call('POST', '/api/v1/csp-reports', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode([
			'csp-report' => ['document-uri' => "https://pentest.example.org/activation/{$token}?x=secret#frag", 'violated-directive' => 'img-src', 'blocked-uri' => 'https://tiers.example/x.png?jeton=secret'],
		]))->assertNoContent();

		$log->shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => ! str_contains((string) json_encode($context), $token)
			&& ! str_contains((string) json_encode($context), 'secret'))->once();
	}

	public function test_error_responses_of_early_middlewares_carry_the_headers(): void
	{
		// 413 produit par la limite de taille, avant le routage.
		$this->call('POST', '/api/v1/auth/login', [], [], [], ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => (string) (10 * 1024 * 1024)], '{}')
			->assertStatus(413)
			->assertHeader('X-Content-Type-Options', 'nosniff')
			->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; sandbox");
	}

	public function test_reports_are_limited_in_size(): void
	{
		$this->call('POST', '/api/v1/csp-reports', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], str_repeat('a', 70 * 1024))
			->assertStatus(413);
	}

	public function test_reports_need_no_session_nor_csrf_token(): void
	{
		// Les navigateurs envoient les rapports sans cookie ; l'origine de l'instance les accompagne.
		$this->withHeader('Origin', 'http://localhost')
			->call('POST', '/api/v1/csp-reports', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], '{"csp-report":{}}')
			->assertNoContent();
	}
}
