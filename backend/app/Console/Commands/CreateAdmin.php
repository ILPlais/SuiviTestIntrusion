<?php

namespace App\Console\Commands;

use App\Auth\AuthenticationValue;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Crée un administrateur d'instance et affiche son lien d'activation (identite-1).
 *
 * Usage : php artisan suivi:create-admin <email> [--name=<nom>]
 *
 * Aucun e-mail n'est envoyé en V1 (13.06) : l'exploitant transmet le lien, à
 * usage unique et valable 24 heures, par un autre canal. Le premier compte créé
 * ainsi est le compte « root » de 11.06. Le jeton n'est stocké que par son
 * empreinte SHA-256.
 */
class CreateAdmin extends Command
{
	protected $signature = 'suivi:create-admin {email : Adresse e-mail du compte} {--name= : Nom affiché (par défaut, la partie avant @)}';

	protected $description = 'Crée un administrateur d\'instance et affiche son lien d\'activation';

	public function handle(): int
	{
		$email = AuthenticationValue::normalizeEmail((string) $this->argument('email'));

		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			$this->error("Adresse e-mail invalide : {$email}");

			return self::FAILURE;
		}

		if (User::query()->where('email', $email)->exists()) {
			$this->error("Un compte existe déjà pour {$email}.");

			return self::FAILURE;
		}

		$token = Str::random(64);
		$hours = (int) config('suivi.authentication.activation_hours');

		$user = new User();
		$user->forceFill([
			'name' => (string) ($this->option('name') ?: Str::before($email, '@')),
			'email' => $email,
			'password' => null,
			'is_instance_admin' => true,
			'activation_token_hash' => hash('sha256', $token),
			'activation_expires_at' => now()->addHours($hours),
		])->save();

		$this->info("Administrateur créé : {$email}");
		$this->line("Lien d'activation, valable {$hours} heures et utilisable une seule fois :");
		$this->line(rtrim((string) config('app.url'), '/')."/activation/{$token}");

		return self::SUCCESS;
	}
}
