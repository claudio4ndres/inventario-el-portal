<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU13: registro de mermas (RF6, K7, OE3).
 *
 * La merma se guarda como un movimiento tipo MERMA. Se agregan dos columnas:
 * - costo_total: costo en CLP de lo perdido (cantidad × costo unitario del lote),
 *   copiado al momento del registro para que el historial no cambie si el lote se corrige.
 * - motivo_merma: causa estructurada (vencimiento, dano, robo u otro) para medir
 *   la merma por vencimiento en el tablero (HU15, indicador M3).
 * Ambas son NULL en los movimientos que no son mermas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->unsignedInteger('costo_total')->nullable()->after('stock_resultante');
            $table->string('motivo_merma', 20)->nullable()->after('costo_total');

            $table->index(['tipo', 'motivo_merma']);
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropIndex(['tipo', 'motivo_merma']);
            $table->dropColumn(['costo_total', 'motivo_merma']);
        });
    }
};
