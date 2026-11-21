<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le modèle User porte les jetons Sanctum dont l'API a besoin (11.03).
 */
class UserTest extends TestCase
{
	use RefreshDatabase;

	public function test_un_utilisateur_peut_recevoir_un_jeton_api(): void
	{
		$user = User::factory()->create();

		$token = $user->createToken('essai');

		$this->assertNotEmpty($token->plainTextToken);
		$this->assertSame(1, $user->tokens()->count());
	}
}
