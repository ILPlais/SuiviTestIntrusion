<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fiches de vulnérabilité et composants concernés (vulnerabilites-3, 11.09,
| 11.10, 14.01).
|
| Le score et le niveau sont calculés par le moteur CVSS à partir du vecteur ;
| la version est gardée avec eux pour accueillir CVSS v4.0 en V2 sans migration.
| Un constat peut attendre sa cotation : vecteur, score et niveau sont alors nuls.
*/
return new class () extends Migration {
	/**
	 * Crée la table des vulnérabilités et sa liaison avec le périmètre.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create('vulnerabilities', function (Blueprint $table) {
			$table->ulid('id')->primary();
			$table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
			$table->foreignUlid('test_id')->constrained()->cascadeOnDelete();
			$table->foreignUlid('phase_id')->constrained()->cascadeOnDelete();
			// V-001, V-002… attribuée par Vulnerability::record(), unique dans le test.
			$table->string('reference', 16);
			$table->string('title');
			// Markdown, rendu et assaini dans le navigateur (13.04).
			$table->text('description')->nullable();
			$table->json('cve_ids');
			$table->json('cwe_ids');
			$table->string('cvss_version', 8)->nullable();
			$table->string('cvss_vector', 128)->nullable();
			$table->decimal('cvss_score', 3, 1)->nullable();
			$table->string('severity', 16)->nullable();
			// Un compte supprimé est pseudonymisé (11.06) : la clé reste, sauf purge.
			$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
			$table->timestamps();

			$table->unique(['test_id', 'reference']);
			$table->index(['test_id', 'severity']);
			$table->index('tenant_id');
		});

		Schema::create('scope_object_vulnerability', function (Blueprint $table) {
			$table->foreignUlid('scope_object_id')->constrained()->cascadeOnDelete();
			$table->foreignUlid('vulnerability_id')->constrained()->cascadeOnDelete();

			$table->primary(['scope_object_id', 'vulnerability_id']);
			$table->index('vulnerability_id');
		});
	}

	/**
	 * Supprime les tables, liaison d'abord.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('scope_object_vulnerability');
		Schema::dropIfExists('vulnerabilities');
	}
};
