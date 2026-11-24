<?php

namespace App\Models;

use App\Audit\AuditLog;
use App\Enums\AuditAction;
use App\Enums\PhaseCode;
use App\Enums\TestExposure;
use App\Enums\TestStatus;
use App\Enums\TestType;
use App\Tenancy\BelongsToTenant;
use Database\Factories\PenetrationTestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Test d'intrusion commandé par une organisation cliente (engagement-2, 11.05,
 * 11.08, 14.01), table `tests`.
 *
 * Le modèle s'appelle PenetrationTest pour ne pas se confondre avec les tests
 * automatisés de l'application. Le statut n'est pas modifiable en masse : il ne
 * change que par changeStatus(), qui inscrit chaque transition dans
 * l'historique ; un test naît « Planifié » avec ses cinq phases (open()).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $reference
 * @property string $title
 * @property TestType $type
 * @property TestExposure $exposure
 * @property TestStatus $status
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $planned_start
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $planned_end
 * @property int|null $created_by
 */
#[Fillable(['tenant_id', 'reference', 'title', 'type', 'exposure', 'planned_start', 'planned_end'])]
class PenetrationTest extends Model
{
	use BelongsToTenant;
	/** @use HasFactory<PenetrationTestFactory> */
	use HasFactory;
	use HasUlids;

	/**
	 * Table définie par 14.01.
	 *
	 * @var string
	 */
	protected $table = 'tests';

	/**
	 * Crée un test « Planifié », ses cinq phases fixes et la première entrée de
	 * son historique, en une seule transaction.
	 *
	 * @param array<string, mixed> $attributes Champs de la fiche (organisation comprise).
	 * @param User $author Chef de projet ou administrateur qui crée le test.
	 * @return self
	 */
	public static function open(array $attributes, User $author): self
	{
		return DB::transaction(function () use ($attributes, $author): self {
			$test = new self($attributes);
			$test->status = TestStatus::Planned;
			$test->created_by = $author->id;
			$test->save();

			foreach (PhaseCode::cases() as $position => $code) {
				$phase = new Phase(['code' => $code, 'position' => $position + 1]);
				$phase->tenant_id = $test->tenant_id;
				$test->phases()->save($phase);
			}

			$test->recordStatusChange(null, TestStatus::Planned, $author, null);

			return $test;
		});
	}

	/**
	 * Change le statut et trace la transition ; toutes les transitions sont
	 * permises (décision du 2026-11-22), sauf vers le statut courant.
	 *
	 * La ligne est relue verrouillée dans la transaction : deux changements
	 * simultanés s'enchaînent, et chacun inscrit le vrai statut de départ.
	 *
	 * @param TestStatus $status Nouveau statut.
	 * @param User $author Auteur du changement.
	 * @param string|null $comment Commentaire facultatif (motif d'un retour, par exemple).
	 * @return bool Faux si le test avait déjà ce statut (rien n'est écrit).
	 */
	public function changeStatus(TestStatus $status, User $author, ?string $comment): bool
	{
		return DB::transaction(function () use ($status, $author, $comment): bool {
			$locked = static::query()->lockForUpdate()->findOrFail($this->id);
			$previous = $locked->status;
			if ($previous === $status) {
				$this->status = $previous;

				return false;
			}

			$this->status = $status;
			$this->save();
			$this->recordStatusChange($previous, $status, $author, $comment);
			// Même transaction que l'historique : le journal d'audit (identite-6) ne
			// peut pas diverger de test_status_changes.
			app(AuditLog::class)->record(AuditAction::TestStatusChanged, $this, ['status' => $previous->value], ['status' => $status->value, 'comment' => $comment], $author);

			return true;
		});
	}

	/**
	 * Inscrit une entrée dans l'historique des statuts.
	 *
	 * @param TestStatus|null $from Statut précédent ; null à la création.
	 * @param TestStatus $to Nouveau statut.
	 * @param User $author Auteur du changement.
	 * @param string|null $comment Commentaire facultatif.
	 * @return void
	 */
	private function recordStatusChange(?TestStatus $from, TestStatus $to, User $author, ?string $comment): void
	{
		$change = new TestStatusChange(['from_status' => $from, 'to_status' => $to, 'comment' => $comment]);
		$change->tenant_id = $this->tenant_id;
		$change->user_id = $author->id;
		$this->statusChanges()->save($change);
	}

	/**
	 * Phases du test, dans l'ordre de déroulement.
	 *
	 * @return HasMany<Phase, $this>
	 */
	public function phases(): HasMany
	{
		return $this->hasMany(Phase::class, 'test_id')->orderBy('position');
	}

	/**
	 * Historique des statuts, du plus récent au plus ancien ; l'ULID, trié par
	 * date, départage deux changements de la même seconde.
	 *
	 * @return HasMany<TestStatusChange, $this>
	 */
	public function statusChanges(): HasMany
	{
		return $this->hasMany(TestStatusChange::class, 'test_id')->latest()->orderByDesc('id');
	}

	/**
	 * Vulnérabilités du test (vulnerabilites-3).
	 *
	 * @return HasMany<Vulnerability, $this>
	 */
	public function vulnerabilities(): HasMany
	{
		return $this->hasMany(Vulnerability::class, 'test_id');
	}

	/**
	 * Compte qui a créé le test.
	 *
	 * @return BelongsTo<User, $this>
	 */
	public function creator(): BelongsTo
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	/**
	 * Conversions des colonnes.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'type' => TestType::class,
			'exposure' => TestExposure::class,
			'status' => TestStatus::class,
			'planned_start' => 'datetime',
			'planned_end' => 'datetime',
		];
	}
}
