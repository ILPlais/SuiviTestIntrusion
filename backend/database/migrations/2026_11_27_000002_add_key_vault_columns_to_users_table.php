<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Coffre de clés de l'utilisateur (ged-chiffree-1, 13.02, 13.03) : clé publique
| OpenPGP, empreinte, et deux copies de la clé privée chiffrées dans le
| navigateur, l'une par le mot de passe, l'autre par la clé de récupération.
|
| Le serveur stocke ces blocs sans jamais les interpréter : il ne détient rien
| qui permette de les ouvrir. `key_version` sert de verrou optimiste aux
| remplacements de la copie 1 (rotation du mot de passe, 13.02).
*/
return new class () extends Migration {
	/**
	 * Ajoute les colonnes du coffre de clés.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->text('public_key')->nullable();
			// Empreinte v6 : 32 octets en hexadécimal ; unique, une paire par compte.
			$table->string('key_fingerprint', 64)->nullable()->unique();
			$table->text('private_key_password_copy')->nullable();
			$table->text('private_key_recovery_copy')->nullable();
			$table->unsignedInteger('key_version')->default(0);
		});
	}

	/**
	 * Retire les colonnes du coffre de clés.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::table('users', function (Blueprint $table) {
			$table->dropUnique(['key_fingerprint']);
			$table->dropColumn(['public_key', 'key_fingerprint', 'private_key_password_copy', 'private_key_recovery_copy', 'key_version']);
		});
	}
};
