<?php

namespace App\Enums;

/**
 * Type d'un objet du périmètre (engagement-4, 11.07, 14.01) : chaque type a sa
 * table de détail. Les applications et ressources cloud viendront avec
 * engagement-7, les sous-modules physiques, radio et ingénierie sociale en V2.
 */
enum ScopeObjectType: string
{
	/** Machine : adresse IP ou plage CIDR, nom, système (table scope_hosts). */
	case Host = 'host';

	/** Service d'une machine : protocole, port, nom, version (table scope_services). */
	case Service = 'service';

	/** Compte de test : identifiant et rôle, sans secret (table scope_accounts). */
	case Account = 'account';
}
