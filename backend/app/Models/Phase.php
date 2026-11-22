<?php

namespace App\Models;

use App\Enums\PhaseCode;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase d'un test d'intrusion (11.08, 14.01) : cinq phases fixes, créées avec le
 * test par PenetrationTest::open(). Tâches, exécutions d'outils et
 * vulnérabilités s'y rattacheront (deroulement, vulnerabilites-3).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $test_id
 * @property PhaseCode $code
 * @property int $position
 */
#[Fillable(['code', 'position'])]
class Phase extends Model
{
	use BelongsToTenant;
	use HasUlids;

	/**
	 * Test auquel la phase appartient.
	 *
	 * @return BelongsTo<PenetrationTest, $this>
	 */
	public function test(): BelongsTo
	{
		return $this->belongsTo(PenetrationTest::class, 'test_id');
	}

	/**
	 * Conversions des colonnes.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'code' => PhaseCode::class,
			'position' => 'integer',
		];
	}
}
