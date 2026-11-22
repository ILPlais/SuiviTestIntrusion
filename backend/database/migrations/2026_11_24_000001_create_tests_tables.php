<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tests d'intrusion, phases fixes et historique des statuts (engagement-2, 14.01).
|
| Valeurs fermées (type, exposition, statut, phase) en colonnes texte contrôlées
| par les énumérations PHP de App\Enums, portables sur les quatre SGBD. Chaque
| table porte `tenant_id`, même déductible du test, pour le filtre par
| organisation ; les enfants d'un test disparaissent avec lui.
*/
return new class () extends Migration {
	/**
	 * Crée les tables des tests, des phases et de l'historique des statuts.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create('tests', function (Blueprint $table) {
			$table->ulid('id')->primary();
			$table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
			$table->string('reference', 64);
			$table->string('title');
			$table->string('type', 32);
			$table->string('exposure', 32);
			$table->string('status', 32);
			// Dates et heures prévues, en UTC (13.07) ; les dates réelles se liront
			// dans l'historique des statuts (11.08, Planning).
			$table->dateTime('planned_start')->nullable();
			$table->dateTime('planned_end')->nullable();
			// Un compte supprimé est pseudonymisé (11.06) : la clé reste, sauf purge.
			$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
			$table->timestamps();

			// Référence unique par organisation (14.01).
			$table->unique(['tenant_id', 'reference']);
			$table->index(['tenant_id', 'status']);
		});

		Schema::create('phases', function (Blueprint $table) {
			$table->ulid('id')->primary();
			$table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
			$table->foreignUlid('test_id')->constrained()->cascadeOnDelete();
			$table->string('code', 32);
			$table->unsignedTinyInteger('position');
			$table->timestamps();

			$table->unique(['test_id', 'code']);
			// PostgreSQL n'indexe pas les clés étrangères : le filtre par organisation en a besoin.
			$table->index('tenant_id');
		});

		Schema::create('test_status_changes', function (Blueprint $table) {
			$table->ulid('id')->primary();
			$table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
			$table->foreignUlid('test_id')->constrained()->cascadeOnDelete();
			// Null pour la création du test, qui ouvre l'historique.
			$table->string('from_status', 32)->nullable();
			$table->string('to_status', 32);
			$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
			$table->text('comment')->nullable();
			$table->timestamps();

			$table->index(['test_id', 'created_at']);
			$table->index('tenant_id');
		});
	}

	/**
	 * Supprime les tables, enfants d'abord.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('test_status_changes');
		Schema::dropIfExists('phases');
		Schema::dropIfExists('tests');
	}
};
