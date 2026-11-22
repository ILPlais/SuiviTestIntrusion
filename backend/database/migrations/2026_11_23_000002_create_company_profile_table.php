<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Fiche de l'entreprise prestataire qui exploite l'instance (engagement-1, 11.05,
| 14.01) : une seule ligne, lue par tous et modifiée par les administrateurs.
*/
return new class () extends Migration {
	/**
	 * Crée la table de la fiche de l'entreprise.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create('company_profile', function (Blueprint $table) {
			$table->id();
			$table->string('name')->nullable();
			$table->string('legal_name')->nullable();
			$table->text('presentation')->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Supprime la table de la fiche de l'entreprise.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('company_profile');
	}
};
