<?php

namespace App\Tenancy;

use RuntimeException;

/**
 * Écriture d'un objet métier hors des organisations de l'utilisateur courant
 * (identite-5). C'est une erreur de programmation, jamais une réponse normale de
 * l'API : les contrôleurs n'acceptent que des organisations déjà visibles.
 */
final class TenantMismatch extends RuntimeException
{
}
