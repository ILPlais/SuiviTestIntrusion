<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * État de l'instance : application et base de données.
 *
 * Aucun détail technique n'est renvoyé ; l'erreur est seulement journalisée.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->databaseIsReachable();

        return response()->json([
            'status' => $database ? 'ok' : 'degraded',
            'database' => $database ? 'ok' : 'error',
            'time' => Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ], $database ? 200 : 503);
    }

    private function databaseIsReachable(): bool
    {
        try {
            DB::connection()->select('select 1');

            return true;
        } catch (Throwable $e) {
            Log::error('Contrôle de santé : base de données injoignable.', ['exception' => $e]);

            return false;
        }
    }
}
