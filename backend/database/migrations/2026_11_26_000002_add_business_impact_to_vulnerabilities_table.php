<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Impact business d'une vulnérabilité (vulnerabilites-4, 11.09) : quatre axes
| notés de 0 (non applicable) à 4, chacun dans sa colonne pour que le profil
| d'impact d'un test (maximum par axe, 11.12) se calcule en SQL.
*/
return new class () extends Migration {
	/**
	 * Ajoute les quatre axes, à 0 pour les fiches existantes.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::table('vulnerabilities', function (Blueprint $table) {
			$table->unsignedTinyInteger('impact_operational')->default(0);
			$table->unsignedTinyInteger('impact_legal')->default(0);
			$table->unsignedTinyInteger('impact_financial')->default(0);
			$table->unsignedTinyInteger('impact_reputation')->default(0);
		});
	}

	/**
	 * Retire les quatre axes.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::table('vulnerabilities', function (Blueprint $table) {
			$table->dropColumn(['impact_operational', 'impact_legal', 'impact_financial', 'impact_reputation']);
		});
	}
};
