<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Périmètre d'un test (engagement-4, 11.07, 14.01) : une table commune et une
| table de détail par type d'objet (héritage par tables liées).
|
| La table commune porte `tenant_id` pour le filtre par organisation ; les tables
| de détail n'en ont pas besoin, car elles ne se lisent qu'à travers leur objet
| (14.01). Aucune colonne ne reçoit de secret : celui d'un compte de test sera
| chiffré dans le navigateur et rangé avec la GED chiffrée (ged-chiffree).
*/
return new class () extends Migration {
	/**
	 * Crée la table des objets du périmètre et leurs tables de détail.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create('scope_objects', function (Blueprint $table) {
			$table->ulid('id')->primary();
			$table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
			$table->foreignUlid('test_id')->constrained()->cascadeOnDelete();
			$table->string('type', 32);
			// Objet hôte : machine d'un service, machine éventuelle d'un compte. La
			// suppression d'une machine emporte ses services ; ses comptes sont
			// détachés avant par ScopeObject::remove().
			$table->foreignUlid('parent_id')->nullable()->constrained('scope_objects')->cascadeOnDelete();
			$table->string('label');
			$table->boolean('included')->default(true);
			$table->text('comment')->nullable();
			// Plages horaires interdites et contraintes de disponibilité, en texte libre (11.07).
			$table->text('constraints')->nullable();
			$table->timestamps();

			$table->index(['test_id', 'type']);
			$table->index('tenant_id');
			$table->index('parent_id');
		});

		Schema::create('scope_hosts', function (Blueprint $table) {
			$table->foreignUlid('scope_object_id')->primary()->constrained('scope_objects')->cascadeOnDelete();
			// Adresse IPv4 ou IPv6, ou plage CIDR (43 caractères au plus en IPv6).
			$table->string('address', 64);
			$table->string('hostname')->nullable();
			$table->string('operating_system')->nullable();
			$table->timestamps();
		});

		Schema::create('scope_services', function (Blueprint $table) {
			$table->foreignUlid('scope_object_id')->primary()->constrained('scope_objects')->cascadeOnDelete();
			$table->string('protocol', 8);
			$table->unsignedInteger('port');
			$table->string('name', 64)->nullable();
			$table->string('version')->nullable();
			$table->timestamps();
		});

		Schema::create('scope_accounts', function (Blueprint $table) {
			$table->foreignUlid('scope_object_id')->primary()->constrained('scope_objects')->cascadeOnDelete();
			$table->string('username');
			$table->string('role_description')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Supprime les tables, détails d'abord.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('scope_accounts');
		Schema::dropIfExists('scope_services');
		Schema::dropIfExists('scope_hosts');
		Schema::dropIfExists('scope_objects');
	}
};
