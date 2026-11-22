<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Organisations (tenants) et rôle de chaque utilisateur par organisation
| (identite-5, 14.01). Le rôle est une colonne texte contrôlée par l'énumération
| App\Enums\TenantRole, portable sur les quatre SGBD.
*/
return new class () extends Migration {
	/**
	 * Crée les tables des organisations et des rattachements.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create('tenants', function (Blueprint $table) {
			$table->ulid('id')->primary();
			$table->string('name');
			$table->string('legal_name')->nullable();
			$table->text('presentation')->nullable();
			$table->timestamps();
		});

		Schema::create('tenant_user', function (Blueprint $table) {
			$table->id();
			$table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			$table->string('role', 32);
			$table->timestamps();

			// Un seul rôle par utilisateur et par organisation.
			$table->unique(['tenant_id', 'user_id']);
			$table->index('user_id');
		});
	}

	/**
	 * Supprime les tables des organisations et des rattachements.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('tenant_user');
		Schema::dropIfExists('tenants');
	}
};
