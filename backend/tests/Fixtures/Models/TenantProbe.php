<?php

namespace Tests\Fixtures\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Objet métier d'essai rattaché à une organisation (identite-5) : sa table est
 * créée par TenantIsolationTest, en attendant les vrais objets métier.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $label
 */
#[Fillable(['tenant_id', 'label'])]
class TenantProbe extends Model
{
	use BelongsToTenant;
	use HasUlids;
}
