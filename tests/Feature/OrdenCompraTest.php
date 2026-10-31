<?php

namespace Tests\Feature;

use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Recepcion;
use App\Models\User;
use App\Services\InventarioService;
use App\Services\OrdenCompraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/** HU11: orden de compra sugerida y recepción contra OC (RF4/RF3, K3/K2, OE2). */
class OrdenCompraTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrador()->create();
    }

    /** Recepción real (HU03): deja stock y fija el último proveedor del producto. */
    private function recibir(Producto $producto, int $cantidad, Proveedor $proveedor): void
    {
        app(InventarioService::class)->registrarRecepcion(
            User::factory()->bodeguero()->create(),
            ['proveedor_id' => $proveedor->id, 'numero_documento' => 'F-'.$producto->id.'-'.$cantidad],
            [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'codigo_lote' => 'L-'.$producto->id]],
        );
    }

    /** Crea una OC enviada al proveedor con las cantidades dadas (producto_id => cantidad). */
    private function ordenEnviada(Proveedor $proveedor, array $cantidades): OrdenCompra
    {
        $orden = OrdenCompra::create([
            'proveedor_id' => $proveedor->id, 'user_id' => $this->admin()->id,
            'estado' => OrdenCompra::ENVIADA, 'fecha_envio' => now(),
        ]);
        foreach ($cantidades as $productoId => $cantidad) {
            $orden->detalles()->create(['producto_id' => $productoId, 'cantidad_sugerida' => $cantidad, 'cantidad' => $cantidad]);
        }

        return $orden;
    }

    public function test_la_cantidad_sugerida_es_punto_de_reorden_por_dos_menos_stock(): void
    {
        $this->assertSame(7, OrdenCompraService::cantidadSugerida(6, 5));
        $this->assertSame(8, OrdenCompraService::cantidadSugerida(4, 0));
        $this->assertSame(1, OrdenCompraService::cantidadSugerida(1, 1));
    }

    public function test_las_alertas_muestran_cantidad_sugerida_proveedor_y_boton_para_el_administrador(): void
    {
        $mayorista = Proveedor::factory()->create(['razon_social' => 'Mayorista Abarrotes Central']);
        $huevos = Producto::factory()->create(['nombre' => 'Huevos blancos 12 un.', 'punto_reorden' => 6]);
        $this->recibir($huevos, 5, $mayorista);

        $this->actingAs($this->admin())->get('/alertas')
            ->assertOk()
            ->assertSeeInOrder(['Huevos blancos 12 un.', '<td class="text-end">7</td>', 'Mayorista Abarrotes Central'], false)
            ->assertSee('Generar OC sugeridas');

        // El bodeguero ve la sugerencia, pero no genera órdenes de compra
        $this->actingAs(User::factory()->bodeguero()->create())->get('/alertas')
            ->assertOk()
            ->assertSee('Mayorista Abarrotes Central')
            ->assertDontSee('Generar OC sugeridas');
    }

    public function test_desde_las_alertas_se_genera_una_oc_en_borrador_por_proveedor(): void
    {
        $admin = $this->admin();
        $mayorista = Proveedor::factory()->create();
        $lacteos = Proveedor::factory()->create();
        $huevos = Producto::factory()->create(['punto_reorden' => 6]);
        $fideos = Producto::factory()->create(['punto_reorden' => 8]);
        $yogur = Producto::factory()->create(['punto_reorden' => 15]);
        $arroz = Producto::factory()->create(['punto_reorden' => 8]);
        $this->recibir($huevos, 5, $mayorista);
        $this->recibir($fideos, 6, $mayorista);
        $this->recibir($yogur, 15, $lacteos);
        $this->recibir($arroz, 20, $mayorista);   // sobre su punto de reorden: no se sugiere

        $this->actingAs($admin)->from('/alertas')->post('/ordenes-compra/sugerir')
            ->assertRedirect('/ordenes-compra')
            ->assertSessionHas('ok');

        $this->assertSame(2, OrdenCompra::count());
        $ocMayorista = OrdenCompra::where('proveedor_id', $mayorista->id)->sole();
        $this->assertSame(OrdenCompra::BORRADOR, $ocMayorista->estado);
        $this->assertSame($admin->id, $ocMayorista->user_id);
        $this->assertDatabaseHas('detalle_ordenes_compra', ['orden_compra_id' => $ocMayorista->id, 'producto_id' => $huevos->id, 'cantidad_sugerida' => 7, 'cantidad' => 7]);
        $this->assertDatabaseHas('detalle_ordenes_compra', ['orden_compra_id' => $ocMayorista->id, 'producto_id' => $fideos->id, 'cantidad_sugerida' => 10, 'cantidad' => 10]);
        $this->assertDatabaseHas('detalle_ordenes_compra', ['producto_id' => $yogur->id, 'cantidad' => 15]);
        $this->assertDatabaseMissing('detalle_ordenes_compra', ['producto_id' => $arroz->id]);
    }

    public function test_el_proveedor_sugerido_es_el_de_la_ultima_recepcion_y_debe_estar_activo(): void
    {
        $antiguo = Proveedor::factory()->create();
        $reciente = Proveedor::factory()->create();
        $inactivo = Proveedor::factory()->create();
        $leche = Producto::factory()->create(['punto_reorden' => 10]);
        $cecina = Producto::factory()->create(['nombre' => 'Cecina jamón 250 g', 'punto_reorden' => 4]);   // nunca recibida
        $azucar = Producto::factory()->create(['punto_reorden' => 6]);
        $this->recibir($leche, 2, $antiguo);
        $this->recibir($leche, 3, $reciente);
        $this->recibir($azucar, 1, $inactivo);
        $inactivo->update(['activo' => false]);

        $ordenes = app(OrdenCompraService::class)->generarSugeridas($this->admin());

        $this->assertCount(1, $ordenes);
        $this->assertSame($reciente->id, $ordenes->first()->proveedor_id);
        $this->assertDatabaseMissing('detalle_ordenes_compra', ['producto_id' => $cecina->id]);
        $this->assertDatabaseMissing('detalle_ordenes_compra', ['producto_id' => $azucar->id]);

        $this->actingAs($this->admin())->get('/ordenes-compra')
            ->assertOk()
            ->assertSee('Cecina jamón 250 g')
            ->assertSee('Sin proveedor activo: no se incluye');
    }

    public function test_un_producto_en_una_oc_abierta_no_se_vuelve_a_sugerir(): void
    {
        $admin = $this->admin();
        $proveedor = Proveedor::factory()->create();
        $producto = Producto::factory()->create(['punto_reorden' => 5]);
        $this->recibir($producto, 2, $proveedor);

        $this->actingAs($admin)->post('/ordenes-compra/sugerir')->assertRedirect('/ordenes-compra');
        $this->actingAs($admin)->from('/ordenes-compra')->post('/ordenes-compra/sugerir')
            ->assertRedirect('/ordenes-compra')
            ->assertSessionHasErrors('orden');

        $this->assertSame(1, OrdenCompra::count());

        // Una vez recibida la OC, si el producto sigue en alerta vuelve a sugerirse
        OrdenCompra::first()->update(['estado' => OrdenCompra::RECIBIDA]);
        $this->actingAs($admin)->post('/ordenes-compra/sugerir')->assertSessionHasNoErrors();
        $this->assertSame(2, OrdenCompra::count());
    }

    public function test_las_cantidades_se_editan_en_borrador_y_no_despues_de_enviar(): void
    {
        $admin = $this->admin();
        $proveedor = Proveedor::factory()->create();
        $producto = Producto::factory()->create(['nombre' => 'Fideos spaghetti 400 g', 'punto_reorden' => 8]);
        $this->recibir($producto, 6, $proveedor);
        $orden = app(OrdenCompraService::class)->generarSugeridas($admin)->first();
        $detalle = $orden->detalles()->first();

        $this->actingAs($admin)->get("/ordenes-compra/{$orden->id}")
            ->assertOk()
            ->assertSee('Fideos spaghetti 400 g')
            ->assertSee('name="detalles['.$detalle->id.'][cantidad]"', false);

        $this->actingAs($admin)->put("/ordenes-compra/{$orden->id}", ['detalles' => [$detalle->id => ['cantidad' => 0]]])
            ->assertSessionHasErrors('detalles.'.$detalle->id.'.cantidad');

        $this->actingAs($admin)->put("/ordenes-compra/{$orden->id}", ['detalles' => [$detalle->id => ['cantidad' => 24]]])
            ->assertRedirect("/ordenes-compra/{$orden->id}");
        $this->assertSame(24, $detalle->fresh()->cantidad);
        $this->assertSame(10, $detalle->fresh()->cantidad_sugerida);   // la sugerencia original se conserva

        $orden->update(['estado' => OrdenCompra::ENVIADA]);
        $this->actingAs($admin)->put("/ordenes-compra/{$orden->id}", ['detalles' => [$detalle->id => ['cantidad' => 50]]])
            ->assertSessionHasErrors('orden');
        $this->assertSame(24, $detalle->fresh()->cantidad);
    }

    public function test_la_oc_pasa_de_borrador_a_enviada_una_sola_vez(): void
    {
        $admin = $this->admin();
        $proveedor = Proveedor::factory()->create(['razon_social' => 'Panificadora Valle Grande']);
        $producto = Producto::factory()->create(['punto_reorden' => 5]);
        $this->recibir($producto, 4, $proveedor);
        $orden = app(OrdenCompraService::class)->generarSugeridas($admin)->first();

        $this->actingAs($admin)->patch("/ordenes-compra/{$orden->id}/enviar")
            ->assertRedirect("/ordenes-compra/{$orden->id}")
            ->assertSessionHas('ok');

        $orden->refresh();
        $this->assertSame(OrdenCompra::ENVIADA, $orden->estado);
        $this->assertNotNull($orden->fecha_envio);

        $this->actingAs($admin)->patch("/ordenes-compra/{$orden->id}/enviar")->assertSessionHasErrors('orden');

        $this->actingAs($admin)->get('/ordenes-compra')
            ->assertOk()
            ->assertSee('Panificadora Valle Grande')
            ->assertSee('Enviada');
    }

    public function test_la_recepcion_contra_una_oc_la_marca_recibida_y_muestra_las_diferencias(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $proveedor = Proveedor::factory()->create();
        $huevos = Producto::factory()->create(['nombre' => 'Huevos blancos 12 un.']);
        $fideos = Producto::factory()->create(['nombre' => 'Fideos spaghetti 400 g']);
        $azucar = Producto::factory()->create(['nombre' => 'Azúcar 1 kg']);
        $orden = $this->ordenEnviada($proveedor, [$huevos->id => 12, $fideos->id => 10]);

        // El formulario precarga el proveedor y los productos de la OC
        $this->actingAs($bodeguero)->get('/recepciones/crear?orden_compra_id='.$orden->id)
            ->assertOk()
            ->assertSee('OC N.º '.$orden->id)
            ->assertViewHas('lineasIniciales', [
                ['producto_id' => $huevos->id, 'cantidad' => 12],
                ['producto_id' => $fideos->id, 'cantidad' => 10],
            ]);

        $respuesta = $this->actingAs($bodeguero)->post('/recepciones', [
            'proveedor_id' => $proveedor->id,
            'orden_compra_id' => $orden->id,
            'numero_documento' => 'F-55900',
            'lineas' => [
                ['producto_id' => $huevos->id, 'cantidad' => 12, 'codigo_lote' => 'HU-1101'],
                ['producto_id' => $fideos->id, 'cantidad' => 7, 'codigo_lote' => 'FI-1101'],   // faltan 3
                ['producto_id' => $azucar->id, 'cantidad' => 2, 'codigo_lote' => 'AZ-1101'],  // no estaba en la OC
            ],
        ]);

        $recepcion = Recepcion::sole();
        $respuesta->assertRedirect("/recepciones/{$recepcion->id}")
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '2 producto(s) con diferencias de cantidad'));

        $this->assertSame($orden->id, $recepcion->orden_compra_id);
        $orden->refresh();
        $this->assertSame(OrdenCompra::RECIBIDA, $orden->estado);
        $this->assertNotNull($orden->fecha_recepcion);
        $this->assertSame(7, $fideos->stockActual());

        $diferencias = $orden->diferencias()->keyBy(fn ($f) => $f->producto->id);
        $this->assertSame(0, $diferencias[$huevos->id]->diferencia);
        $this->assertSame([10, 7, -3], [$diferencias[$fideos->id]->pedida, $diferencias[$fideos->id]->recibida, $diferencias[$fideos->id]->diferencia]);
        $this->assertSame([0, 2, 2], [$diferencias[$azucar->id]->pedida, $diferencias[$azucar->id]->recibida, $diferencias[$azucar->id]->diferencia]);

        $this->actingAs($bodeguero)->get("/recepciones/{$recepcion->id}")
            ->assertOk()
            ->assertSee('Diferencias con la orden de compra N.º '.$orden->id)
            ->assertSeeInOrder(['Fideos spaghetti 400 g', '10', '7', '-3', 'Faltante'])
            ->assertSee('No estaba en la OC')
            ->assertSee('Completo');

        $this->actingAs($this->admin())->get("/ordenes-compra/{$orden->id}")
            ->assertOk()
            ->assertSee('Recibida')
            ->assertSee('Diferencias entre lo pedido y lo recibido')
            ->assertSee('Faltante');
    }

    public function test_solo_se_recibe_contra_una_oc_enviada_del_mismo_proveedor(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        $proveedor = Proveedor::factory()->create();
        $otro = Proveedor::factory()->create();
        $producto = Producto::factory()->create();
        $enviada = $this->ordenEnviada($proveedor, [$producto->id => 5]);
        $borrador = $this->ordenEnviada($proveedor, [$producto->id => 5]);
        $borrador->update(['estado' => OrdenCompra::BORRADOR]);

        $datos = fn (int $proveedorId, int $ordenId) => [
            'proveedor_id' => $proveedorId, 'orden_compra_id' => $ordenId, 'numero_documento' => 'F-1',
            'lineas' => [['producto_id' => $producto->id, 'cantidad' => 5, 'codigo_lote' => 'L1']],
        ];

        $this->actingAs($bodeguero)->post('/recepciones', $datos($otro->id, $enviada->id))
            ->assertSessionHasErrors(['orden_compra_id' => 'La orden de compra debe estar enviada y ser del proveedor seleccionado.']);
        $this->actingAs($bodeguero)->post('/recepciones', $datos($proveedor->id, $borrador->id))
            ->assertSessionHasErrors('orden_compra_id');

        $this->assertDatabaseCount('recepciones', 0);
        $this->assertSame(0, $producto->stockActual());

        // El servicio también lo impide y no registra nada (por ejemplo, si otra recepción cerró la OC antes)
        $enviada->update(['estado' => OrdenCompra::RECIBIDA]);
        $this->expectException(InvalidArgumentException::class);
        try {
            app(InventarioService::class)->registrarRecepcion($bodeguero, ['proveedor_id' => $proveedor->id, 'numero_documento' => 'F-2', 'orden_compra_id' => $enviada->id], [
                ['producto_id' => $producto->id, 'cantidad' => 5, 'codigo_lote' => 'L2'],
            ]);
        } finally {
            $this->assertDatabaseCount('recepciones', 0);
            $this->assertDatabaseCount('lotes', 0);
        }
    }

    public function test_la_recepcion_sin_oc_sigue_funcionando_como_antes(): void
    {
        $proveedor = Proveedor::factory()->create();
        $producto = Producto::factory()->create();

        $this->actingAs(User::factory()->bodeguero()->create())->post('/recepciones', [
            'proveedor_id' => $proveedor->id,
            'numero_documento' => 'F-300',
            'lineas' => [['producto_id' => $producto->id, 'cantidad' => 4, 'codigo_lote' => 'L-300']],
        ])->assertRedirect('/recepciones');

        $this->assertDatabaseHas('recepciones', ['numero_documento' => 'F-300', 'orden_compra_id' => null]);
        $this->assertSame(4, $producto->stockActual());
    }

    public function test_un_proveedor_con_ordenes_de_compra_no_se_elimina(): void
    {
        $proveedor = Proveedor::factory()->create();
        $this->ordenEnviada($proveedor, [Producto::factory()->create()->id => 3]);

        $this->actingAs($this->admin())->delete("/proveedores/{$proveedor->id}")
            ->assertSessionHasErrors('proveedor');

        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id]);
    }

    public function test_solo_el_administrador_gestiona_las_ordenes_de_compra(): void
    {
        $proveedor = Proveedor::factory()->create();
        $orden = $this->ordenEnviada($proveedor, [Producto::factory()->create()->id => 3]);
        $orden->update(['estado' => OrdenCompra::BORRADOR]);

        $this->get('/ordenes-compra')->assertRedirect('/login');
        $this->actingAs($this->admin())->get('/ordenes-compra')->assertOk()->assertSee('Compras');

        foreach (['vendedor', 'bodeguero'] as $rol) {
            $usuario = User::factory()->{$rol}()->create();
            $this->actingAs($usuario)->get('/ordenes-compra')->assertForbidden();
            $this->actingAs($usuario)->get("/ordenes-compra/{$orden->id}")->assertForbidden();
            $this->actingAs($usuario)->post('/ordenes-compra/sugerir')->assertForbidden();
            $this->actingAs($usuario)->patch("/ordenes-compra/{$orden->id}/enviar")->assertForbidden();
        }

        $this->assertSame(OrdenCompra::BORRADOR, $orden->fresh()->estado);
    }
}
