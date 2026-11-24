<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Cycle de vie des comptes (identite-7, 11.06, 13.03).
|
| - `invitations` : qui invite qui, dans quelle organisation et avec quel rôle.
|   Le lien lui-même reste porté par le compte invité (`activation_token_hash`) :
|   un compte en attente n'a qu'un lien valide, qu'un renvoi remplace.
| - `users.deactivated_at` : désactivation réversible ; `pseudonymized_at` :
|   compte supprimé, dont seule la ligne pseudonymisée subsiste pour l'historique.
| - `users.reset_token_hash` : lien de réinitialisation du mot de passe émis par
|   un administrateur (condensat SHA-256, jamais le jeton).
*/
return new class () extends Migration {
	/**
	 * Crée la table des invitations et les colonnes du cycle de vie.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->timestamp('deactivated_at')->nullable();
			$table->timestamp('pseudonymized_at')->nullable();
			$table->string('reset_token_hash', 64)->nullable()->unique();
			$table->timestamp('reset_expires_at')->nullable();
		});

		Schema::create('invitations', function (Blueprint $table) {
			$table->ulid('id')->primary();
			// Compte invité : créé en attente d'activation, ou compte actif déjà existant.
			$table->foreignId('user_id')->constrained()->cascadeOnDelete();
			// Invitant : conservé même s'il disparaît, pour l'historique (null alors).
			$table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
			// Organisation et rôle ; absents pour un administrateur d'instance sans organisation.
			$table->foreignUlid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
			$table->string('role', 32)->nullable();
			$table->boolean('is_instance_admin')->default(false);
			$table->timestamp('expires_at')->nullable();
			$table->timestamp('accepted_at')->nullable();
			$table->timestamp('cancelled_at')->nullable();
			$table->timestamps();

			$table->index(['user_id', 'accepted_at', 'cancelled_at']);
			$table->index('invited_by');
		});
	}

	/**
	 * Supprime la table et les colonnes ajoutées par up().
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('invitations');

		Schema::table('users', function (Blueprint $table) {
			$table->dropUnique(['reset_token_hash']);
			$table->dropColumn(['deactivated_at', 'pseudonymized_at', 'reset_token_hash', 'reset_expires_at']);
		});
	}
};
