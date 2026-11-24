<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Journal d'audit (identite-6, 11.02) : une ligne par action sensible, jamais
| modifiée, purgée selon la rétention paramétrable (suivi.audit.retention_days).
|
| `tenant_id` n'a volontairement pas de clé étrangère : la suppression d'une
| organisation est elle-même auditée, et ses événements doivent lui survivre
| jusqu'à la purge. L'auteur suit la règle des autres tables : un compte
| supprimé est pseudonymisé (11.06), la clé ne tombe qu'à sa purge.
*/
return new class () extends Migration {
	/**
	 * Crée la table du journal d'audit.
	 *
	 * @return void
	 */
	public function up(): void
	{
		Schema::create('audit_events', function (Blueprint $table) {
			$table->ulid('id')->primary();
			// Organisation concernée ; nulle pour un événement d'instance (connexion, compte).
			$table->ulid('tenant_id')->nullable();
			$table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
			$table->string('action', 64);
			// Objet visé : type court (« test », « user »…) et identifiant (ULID ou entier).
			$table->string('subject_type', 32)->nullable();
			$table->string('subject_id', 64)->nullable();
			// Résumé avant / après, débarrassé des secrets par App\Audit\AuditLog.
			$table->json('before')->nullable();
			$table->json('after')->nullable();
			$table->string('ip_address', 45)->nullable();
			$table->timestamp('created_at')->useCurrent();

			$table->index(['tenant_id', 'created_at']);
			$table->index(['action', 'created_at']);
			$table->index(['subject_type', 'subject_id']);
			$table->index('created_at');
		});
	}

	/**
	 * Supprime la table du journal d'audit.
	 *
	 * @return void
	 */
	public function down(): void
	{
		Schema::dropIfExists('audit_events');
	}
};
