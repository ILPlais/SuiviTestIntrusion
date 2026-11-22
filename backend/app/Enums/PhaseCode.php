<?php

namespace App\Enums;

/**
 * Phases fixes d'un test d'intrusion (11.08, 14.01), créées avec chaque test dans
 * l'ordre de déclaration ; la table `phases` permettra de les rendre configurables
 * plus tard sans reprendre les liens.
 */
enum PhaseCode: string
{
	/** Reconnaissance : collecte d'informations sur la cible. */
	case Reconnaissance = 'reconnaissance';

	/** Énumération : inventaire des services, comptes et versions. */
	case Enumeration = 'enumeration';

	/** Exploitation : mise en œuvre des vulnérabilités. */
	case Exploitation = 'exploitation';

	/** Post-exploitation : élévation de privilèges, mouvements latéraux. */
	case PostExploitation = 'post_exploitation';

	/** Nettoyage : retrait des traces et des outils déposés. */
	case Cleanup = 'cleanup';
}
