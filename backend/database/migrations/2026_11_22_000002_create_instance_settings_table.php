<?php

/**
 * Identifiant public de l'instance (13.03) : un UUID tiré une fois, à
 * l'installation, qui entre dans le sel de la valeur d'authentification.
 *
 * Il vit dans la base, et non dans .env, pour être sauvegardé avec les comptes :
 * le perdre rendrait toutes les valeurs d'authentification invalides.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
	public function up(): void
	{
		Schema::create('instance_settings', function (Blueprint $table) {
			$table->id();
			$table->uuid('instance_id')->unique();
			$table->timestamps();
		});

		DB::table('instance_settings')->insert([
			'instance_id' => (string) Str::uuid(),
			'created_at' => now(),
			'updated_at' => now(),
		]);
	}

	public function down(): void
	{
		Schema::dropIfExists('instance_settings');
	}
};
