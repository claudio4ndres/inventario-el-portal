<?php

use App\Services\ClasificacionAbcService;
use App\Services\PuntoReordenService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// HU10 / K3: recalcula el punto de reorden con las ventas de los últimos 28 días.
Artisan::command('inventario:recalcular-reorden', function (PuntoReordenService $servicio) {
    $recalculados = $servicio->recalcular();

    $this->info("Punto de reorden recalculado en {$recalculados} producto(s) con ventas en los últimos ".
        PuntoReordenService::DIAS_PERIODO.' días.');
})->purpose('Recalcula el punto de reorden según la demanda de los últimos 28 días (HU10)');

// HU17 / K6-K7: clasificación ABC por valor vendido en los últimos 90 días (A 80 %, B 95 %, C el resto).
Artisan::command('inventario:recalcular-abc', function (ClasificacionAbcService $servicio) {
    $resumen = $servicio->recalcular();

    if ($resumen === null) {
        $this->warn('No hay ventas en los últimos '.ClasificacionAbcService::DIAS_PERIODO.' días: no se modificó la clasificación ABC.');

        return;
    }

    $this->info("Clasificación ABC recalculada: {$resumen['A']} producto(s) en A, {$resumen['B']} en B y {$resumen['C']} en C.");
})->purpose('Recalcula la clase ABC según el valor vendido en los últimos 90 días (HU17)');

// Se programa cada madrugada, antes de abrir el almacén. En el servidor basta con
// el cron de Laravel: * * * * * php artisan schedule:run
Schedule::command('inventario:recalcular-reorden')->dailyAt('05:00');
