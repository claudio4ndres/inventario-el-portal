<?php

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

// Se programa cada madrugada, antes de abrir el almacén. En el servidor basta con
// el cron de Laravel: * * * * * php artisan schedule:run
Schedule::command('inventario:recalcular-reorden')->dailyAt('05:00');
