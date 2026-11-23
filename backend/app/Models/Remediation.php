<?php

namespace App\Models;

use App\Enums\RemediationPriority;
use App\Enums\RemediationStatus;
use App\Enums\Severity;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;

/**
 * Fiche de remédiation d'une vulnérabilité (vulnerabilites-5, 11.09, 14.01),
 * table `remediations` ; une par vulnérabilité, créée avec elle (forVulnerability()).
 *
 * La priorité suit la suggestion tirée du niveau CVSS (followSeverity()) tant
 * qu'elle n'est pas surchargée ; le statut ne change que par changeStatus(),
 * qui date le retest et en efface la trace à la réouverture.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $vulnerability_id
 * @property string|null $recommendations
 * @property RemediationPriority $priority
 * @property bool $priority_overridden
 * @property list<string> $resources
 * @property RemediationStatus $status
 * @property \Carbon\CarbonImmutable|\Illuminate\Support\Carbon|null $verified_at
 * @property int|null $verified_by
 * @property string|null $retest_result
 */
#[Fillable(['recommendations', 'resources'])]
class Remediation extends Model
{
	use BelongsToTenant;
	use HasUlids;

	/**
	 * Valeurs par défaut : à faire, sans ressource, priorité non surchargée.
	 *
	 * @var array<string, mixed>
	 */
	protected $attributes = [
		'resources' => '[]',
		'status' => 'todo',
		'priority_overridden' => false,
	];

	/**
	 * Nouvelle fiche (non enregistrée) pour une vulnérabilité, à faire, avec la
	 * priorité suggérée par son niveau.
	 *
	 * @param Vulnerability $vulnerability Vulnérabilité enregistrée.
	 * @return self
	 */
	public static function forVulnerability(Vulnerability $vulnerability): self
	{
		$remediation = new self();
		$remediation->tenant_id = $vulnerability->tenant_id;
		$remediation->vulnerability_id = $vulnerability->id;
		$remediation->followSeverity($vulnerability->severity);

		return $remediation;
	}

	/**
	 * Priorité suggérée pour le niveau courant de la vulnérabilité.
	 *
	 * @param Severity|null $severity Niveau, ou null si la vulnérabilité n'est pas cotée.
	 * @return RemediationPriority
	 */
	public function suggestedPriority(?Severity $severity): RemediationPriority
	{
		return RemediationPriority::suggestedFor($severity);
	}

	/**
	 * Aligne la priorité sur la suggestion, sauf si le chef de projet l'a surchargée.
	 *
	 * @param Severity|null $severity Niveau de la vulnérabilité.
	 * @return void
	 */
	public function followSeverity(?Severity $severity): void
	{
		if (! $this->priority_overridden) {
			$this->priority = RemediationPriority::suggestedFor($severity);
		}
	}

	/**
	 * Surcharge la priorité, ou revient à la suggestion avec null.
	 *
	 * @param RemediationPriority|null $priority Priorité retenue, ou null pour suivre la suggestion.
	 * @param Severity|null $severity Niveau de la vulnérabilité.
	 * @return void
	 */
	public function prioritize(?RemediationPriority $priority, ?Severity $severity): void
	{
		$this->priority_overridden = $priority !== null;
		$this->priority = $priority ?? RemediationPriority::suggestedFor($severity);
	}

	/**
	 * Change le statut : « Vérifié » date le retest et garde son résultat et son
	 * auteur ; tout autre statut efface la trace d'un retest antérieur.
	 *
	 * @param RemediationStatus $status Nouveau statut.
	 * @param string|null $retestResult Résultat du retest (exigé pour « Vérifié », validé en amont).
	 * @param User $author Auteur du changement.
	 * @return void
	 */
	public function changeStatus(RemediationStatus $status, ?string $retestResult, User $author): void
	{
		$this->status = $status;
		if ($status === RemediationStatus::Verified) {
			$this->verified_at = Date::now();
			$this->verified_by = $author->id;
			$this->retest_result = $retestResult;
		} else {
			$this->verified_at = null;
			$this->verified_by = null;
			$this->retest_result = null;
		}
	}

	/**
	 * Vulnérabilité remédiée.
	 *
	 * @return BelongsTo<Vulnerability, $this>
	 */
	public function vulnerability(): BelongsTo
	{
		return $this->belongsTo(Vulnerability::class);
	}

	/**
	 * Testeur qui a vérifié la correction.
	 *
	 * @return BelongsTo<User, $this>
	 */
	public function verifier(): BelongsTo
	{
		return $this->belongsTo(User::class, 'verified_by');
	}

	/**
	 * Conversions des colonnes.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'priority' => RemediationPriority::class,
			'priority_overridden' => 'boolean',
			'resources' => 'array',
			'status' => RemediationStatus::class,
			'verified_at' => 'datetime',
		];
	}
}
