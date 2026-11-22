<?php

/**
 * Colonnes d'authentification et de profil des utilisateurs (identite-1, 14.01, 13.03).
 *
 * - `password` devient facultatif : un compte créé n'a pas de valeur
 *   d'authentification tant qu'il n'est pas activé.
 * - Verrouillage : compteur d'échecs consécutifs, date du dernier échec,
 *   échéance du verrouillage.
 * - Activation : empreinte SHA-256 du jeton du lien (jamais le jeton en clair)
 *   et échéance.
 * - Profil (« Testeur d'intrusion » de 11.05) et administrateur d'instance.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
	/**
	 * Ajoute les colonnes de la migration.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->string('password')->nullable()->change();
			$table->string('job_title')->nullable();
			$table->text('presentation')->nullable();
			$table->boolean('is_instance_admin')->default(false);
			$table->unsignedInteger('failed_login_count')->default(0);
			$table->timestamp('last_failed_login_at')->nullable();
			$table->timestamp('locked_until')->nullable();
			$table->string('activation_token_hash', 64)->nullable()->unique();
			$table->timestamp('activation_expires_at')->nullable();
		});
	}

	/**
	 * Retire les colonnes ajoutées par up().
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->dropUnique(['activation_token_hash']);
			$table->dropColumn([
				'job_title', 'presentation', 'is_instance_admin', 'failed_login_count',
				'last_failed_login_at', 'locked_until', 'activation_token_hash', 'activation_expires_at',
			]);
		});
	}
};
