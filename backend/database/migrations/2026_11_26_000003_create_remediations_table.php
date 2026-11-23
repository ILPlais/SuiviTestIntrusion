<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fiches de remédiation (vulnerabilites-5, 11.09, 14.01) : une par
| vulnérabilité, créée avec elle. La priorité suit la suggestion tirée du
| niveau CVSS tant qu'elle n'est pas surchargée par le chef de projet.
*/
return new class () extends Migration {
	/**
	 * Crée la table des fiches de remédiation.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create('remediations', function (Blueprint $table) {
			$table->ulid('id')->primary();
			$table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
			$table->foreignUlid('vulnerability_id')->unique()->constrained()->cascadeOnDelete();
			// Markdown, rendu et assaini dans le navigateur (13.04).
			$table->text('recommendations')->nullable();
			$table->string('priority', 2);
			$table->boolean('priority_overridden')->default(false);
			// Liens (correctifs, avis, bulletins) : adresses http ou https.
			$table->json('resources');
			$table->string('status', 16);
			$table->dateTime('verified_at')->nullable();
			$table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
			$table->text('retest_result')->nullable();
			$table->timestamps();

			$table->index('tenant_id');
		});
	}

	/**
	 * Supprime la table.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('remediations');
	}
};
