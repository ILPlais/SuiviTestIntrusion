<?php

/*
| Routes web : le back-end est une API (routes/api.php). Seule la SPA compilée est
| servie ici, sur la même origine que l'API (socle-2, 13.06) ; en développement,
| elle est servie par Vite (frontend/) et cette route renvoie 404.
*/

use App\Http\Controllers\SpaController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Toute URL hors de /api et de /sanctum (jeton CSRF) reçoit index.html : liens
// profonds de la SPA ; les routes d'API inconnues gardent leur erreur JSON (11.03).
// Sans session ni cookie : servir index.html n'a besoin d'aucun état, et une visite
// anonyme (ou un robot sur un chemin inconnu) ne crée pas de ligne dans « sessions ».
Route::get('/{chemin?}', SpaController::class)
	->where('chemin', '^(?!(api|sanctum)(/|$)).*$')
	->withoutMiddleware([
		EncryptCookies::class,
		AddQueuedCookiesToResponse::class,
		StartSession::class,
		ShareErrorsFromSession::class,
		PreventRequestForgery::class,
	])
	->name('spa');
