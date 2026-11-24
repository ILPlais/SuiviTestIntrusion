<?php

namespace App\Cvss;

use InvalidArgumentException;

/**
 * Vecteur CVSS illisible ou hors du périmètre de 11.10 (métriques de base v3.1
 * seulement). Les contrôleurs la traduisent en erreur de validation.
 */
final class InvalidCvssVector extends InvalidArgumentException
{
}
