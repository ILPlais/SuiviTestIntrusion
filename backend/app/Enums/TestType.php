<?php

namespace App\Enums;

/**
 * Type d'un test d'intrusion (engagement-2), d'après le questionnaire
 * d'engagement de 11.05 : exercice d'équipe rouge, ou niveau d'information donné
 * aux testeurs.
 */
enum TestType: string
{
	/** Exercice d'équipe rouge : scénario d'attaque réaliste, de bout en bout. */
	case RedTeam = 'red_team';

	/** Boîte noire : aucune information préalable sur la cible. */
	case BlackBox = 'black_box';

	/** Boîte grise : informations partielles (comptes, documentation). */
	case GreyBox = 'grey_box';

	/** Boîte blanche : accès complet (code source, architecture). */
	case WhiteBox = 'white_box';
}
