<?php

namespace App\Models;

use App\Enums\TestStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrée de l'historique des statuts d'un test (engagement-2) : qui a fait
 * passer le test de quel statut à quel autre, quand, et pourquoi.
 *
 * Écrite uniquement par PenetrationTest ; jamais modifiée ensuite. Le journal
 * d'audit général (identite-6) reprendra ces entrées.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $test_id
 * @property TestStatus|null $from_status
 * @property TestStatus $to_status
 * @property int|null $user_id
 * @property string|null $comment
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon $created_at
 */
#[Fillable(['from_status', 'to_status', 'comment'])]
class TestStatusChange extends Model
{
	use BelongsToTenant;
	use HasUlids;

	/**
	 * Auteur du changement ; null si son compte a été purgé.
	 *
	 * @return BelongsTo<User, $this>
	 */
	public function author(): BelongsTo
	{
		return $this->belongsTo(User::class, 'user_id');
	}

	/**
	 * Conversions des colonnes.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'from_status' => TestStatus::class,
			'to_status' => TestStatus::class,
		];
	}
}
