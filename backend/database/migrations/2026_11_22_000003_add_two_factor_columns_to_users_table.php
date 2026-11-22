<?php

/**
 * Double authentification TOTP des comptes locaux (identite-3, 11.06).
 *
 * - `two_factor_secret` : secret TOTP, chiffré par la clé de l'application (le
 *   serveur doit pouvoir le lire pour vérifier les codes) ;
 * - `two_factor_confirmed_at` : date de confirmation ; tant qu'elle est nulle,
 *   l'enrôlement est en cours et la connexion n'exige pas de code ;
 * - `two_factor_recovery_codes` : empreintes SHA-256 des codes de secours restants ;
 * - `two_factor_last_timestep` : dernier pas de temps accepté, pour qu'un code ne
 *   serve jamais deux fois.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
	/**
	 * Ajoute les colonnes du second facteur.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->text('two_factor_secret')->nullable();
			$table->timestamp('two_factor_confirmed_at')->nullable();
			$table->text('two_factor_recovery_codes')->nullable();
			$table->unsignedBigInteger('two_factor_last_timestep')->nullable();
		});
	}

	/**
	 * Retire les colonnes du second facteur.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes', 'two_factor_last_timestep']);
		});
	}
};
