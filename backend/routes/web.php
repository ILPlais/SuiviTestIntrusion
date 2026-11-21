<?php

/*
| Routes web : le back-end est une API (routes/api.php). Seule la SPA compilée est
| servie ici, sur la même origine que l'API (socle-2, 13.06) ; en développement,
| elle est servie par Vite (frontend/) et cette route renvoie 404.
*/

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// Toute URL hors de /api (liens profonds de la SPA) reçoit index.html ; les routes
// d'API inconnues gardent leur erreur JSON (11.03).
Route::get('/{chemin?}', SpaController::class)
	->where('chemin', '^(?!api(/|$)).*$')
	->name('spa');
