<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo de datos del MPV (HU01–HU07).
 *
 * Reglas de integridad referencial: todas las claves foráneas usan
 * RESTRICT al borrar, porque un producto, proveedor o usuario con historia
 * (lotes, ventas, movimientos) no debe eliminarse: se desactiva (campo "activo").
 * Así la bitácora nunca queda con referencias huérfanas (K8, Ley 21.459).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_barras', 32)->unique();          // K5: lectura por escáner, sin duplicados
            $table->string('nombre', 120);
            $table->unsignedInteger('precio_venta');                 // CLP con IVA incluido (Ley 19.496, art. 30)
            $table->unsignedInteger('punto_reorden')->default(0);    // K3: umbral de alerta
            $table->char('clase_abc', 1)->default('C');              // A, B o C (cálculo automático en HU17)
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 12)->unique();
            $table->string('razon_social', 120);
            $table->string('telefono', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Cabecera de la recepción de mercadería (K2)
        Schema::create('recepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('numero_documento', 30);                  // N.º de factura o guía del proveedor
            $table->timestamp('fecha_recepcion')->useCurrent();
            $table->timestamps();
        });

        // Cada recepción de un producto crea un lote (K2, K4)
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('recepcion_id')->constrained('recepciones')->restrictOnDelete();
            $table->string('codigo_lote', 40);
            $table->date('fecha_vencimiento')->nullable();           // null = producto no perecible
            $table->unsignedInteger('cantidad_inicial');
            $table->unsignedInteger('cantidad_disponible');          // nunca negativa (unsigned + validación)
            $table->unsignedInteger('costo_unitario')->default(0);   // base para valorizar mermas (HU13)
            $table->timestamps();

            // Índice para la consulta FIFO: lotes con saldo de un producto, ordenados por vencimiento
            $table->index(['producto_id', 'fecha_vencimiento']);
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('total');
            $table->string('medio_pago', 20)->default('efectivo');
            $table->timestamp('fecha_venta')->useCurrent();
            $table->timestamps();
        });

        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('precio_unitario');              // precio vigente al momento de la venta
            $table->unsignedInteger('subtotal');
            $table->timestamps();
        });

        // Bitácora de movimientos: resuelve la relación M:N entre DETALLE_VENTA y LOTE
        // (una línea de venta puede consumir varios lotes y un lote abastece varias ventas).
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('lote_id')->constrained('lotes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 10);                               // ENTRADA, SALIDA, AJUSTE, MERMA
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('stock_resultante');              // stock total del producto tras el movimiento
            $table->foreignId('detalle_venta_id')->nullable()->constrained('detalle_ventas')->restrictOnDelete();
            $table->foreignId('recepcion_id')->nullable()->constrained('recepciones')->restrictOnDelete();
            $table->string('motivo', 120)->nullable();
            $table->timestamps();

            $table->index(['producto_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
        Schema::dropIfExists('detalle_ventas');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('lotes');
        Schema::dropIfExists('recepciones');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('productos');
    }
};
