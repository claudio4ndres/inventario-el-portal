<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use App\Support\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU09: gestión de proveedores con RUT chileno validado (RF3 · K2). */
class ProveedorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrador()->create();
    }

    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'rut' => '76.111.111-6',
            'razon_social' => 'Distribuidora Lácteos del Sur',
            'telefono' => '+56222222222',
            'email' => 'ventas@lacteosdelsur.test',
            'activo' => '1',
        ], $cambios);
    }

    public function test_administrador_ve_el_listado_de_proveedores(): void
    {
        Proveedor::factory()->create(['razon_social' => 'Mayorista Abarrotes Central', 'rut' => '76.222.222-1']);

        $this->actingAs($this->admin())->get('/proveedores')
            ->assertOk()->assertSee('Mayorista Abarrotes Central')->assertSee('76.222.222-1');
    }

    public function test_administrador_crea_un_proveedor_activo(): void
    {
        $this->actingAs($this->admin())
            ->post('/proveedores', $this->datosValidos())
            ->assertRedirect('/proveedores');

        $proveedor = Proveedor::where('rut', '76.111.111-6')->firstOrFail();
        $this->assertSame('Distribuidora Lácteos del Sur', $proveedor->razon_social);
        $this->assertTrue($proveedor->activo);
    }

    public function test_el_rut_se_guarda_normalizado_con_puntos_y_k_mayuscula(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/proveedores', $this->datosValidos(['rut' => '76111111-6']))->assertRedirect('/proveedores');
        $this->actingAs($admin)->post('/proveedores', $this->datosValidos(['rut' => '76000006k', 'razon_social' => 'Proveedor K']))->assertRedirect('/proveedores');

        $this->assertDatabaseHas('proveedores', ['rut' => '76.111.111-6']);
        $this->assertDatabaseHas('proveedores', ['rut' => '76.000.006-K']);
    }

    public function test_el_rut_con_digito_verificador_incorrecto_se_rechaza(): void
    {
        $this->actingAs($this->admin())
            ->post('/proveedores', $this->datosValidos(['rut' => '76.111.111-1']))
            ->assertSessionHasErrors(['rut' => 'El RUT no es válido: revise el formato y el dígito verificador.']);

        $this->assertDatabaseCount('proveedores', 0);
    }

    public function test_el_rut_con_formato_invalido_se_rechaza(): void
    {
        $admin = $this->admin();

        foreach (['', 'abc', '12-3', '76.111.111-X', '761.111.11-6x'] as $rut) {
            $this->actingAs($admin)
                ->post('/proveedores', $this->datosValidos(['rut' => $rut]))
                ->assertSessionHasErrors('rut');
        }

        $this->assertDatabaseCount('proveedores', 0);
    }

    public function test_el_rut_es_unico_aunque_se_escriba_en_otro_formato(): void
    {
        Proveedor::factory()->create(['rut' => '76.111.111-6']);

        $this->actingAs($this->admin())
            ->post('/proveedores', $this->datosValidos(['rut' => '761111116']))
            ->assertSessionHasErrors('rut');

        $this->assertDatabaseCount('proveedores', 1);
    }

    public function test_la_razon_social_es_obligatoria_y_el_telefono_debe_ser_valido(): void
    {
        $this->actingAs($this->admin())
            ->post('/proveedores', $this->datosValidos(['razon_social' => '', 'telefono' => 'llamar al jefe']))
            ->assertSessionHasErrors(['razon_social', 'telefono']);
    }

    public function test_editar_conserva_el_mismo_rut_y_actualiza_los_datos(): void
    {
        $proveedor = Proveedor::factory()->create(['rut' => '76.111.111-6', 'razon_social' => 'Nombre antiguo']);

        $this->actingAs($this->admin())
            ->put("/proveedores/{$proveedor->id}", $this->datosValidos(['razon_social' => 'Nombre nuevo']))
            ->assertRedirect('/proveedores');

        $this->assertSame('Nombre nuevo', $proveedor->fresh()->razon_social);
    }

    public function test_editar_no_permite_usar_el_rut_de_otro_proveedor(): void
    {
        Proveedor::factory()->create(['rut' => '76.222.222-1']);
        $proveedor = Proveedor::factory()->create(['rut' => '76.111.111-6']);

        $this->actingAs($this->admin())
            ->put("/proveedores/{$proveedor->id}", $this->datosValidos(['rut' => '76.222.222-1']))
            ->assertSessionHasErrors('rut');

        $this->assertSame('76.111.111-6', $proveedor->fresh()->rut);
    }

    public function test_administrador_desactiva_y_reactiva_un_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/proveedores/{$proveedor->id}/estado")->assertRedirect();
        $this->assertFalse($proveedor->fresh()->activo);

        $this->actingAs($admin)->patch("/proveedores/{$proveedor->id}/estado")->assertRedirect();
        $this->assertTrue($proveedor->fresh()->activo);
    }

    public function test_un_proveedor_inactivo_no_aparece_en_la_recepcion(): void
    {
        Proveedor::factory()->create(['razon_social' => 'Proveedor Vigente']);
        Proveedor::factory()->inactivo()->create(['razon_social' => 'Proveedor Dado de Baja']);

        $this->actingAs(User::factory()->bodeguero()->create())
            ->get('/recepciones/crear')
            ->assertOk()
            ->assertSee('Proveedor Vigente')
            ->assertDontSee('Proveedor Dado de Baja');
    }

    public function test_no_se_puede_registrar_una_recepcion_con_un_proveedor_inactivo(): void
    {
        $inactivo = Proveedor::factory()->inactivo()->create();

        $this->actingAs(User::factory()->bodeguero()->create())
            ->post('/recepciones', [
                'proveedor_id' => $inactivo->id,
                'numero_documento' => 'F-200',
                'lineas' => [['producto_id' => Producto::factory()->create()->id, 'cantidad' => 3, 'codigo_lote' => 'L1']],
            ])
            ->assertSessionHasErrors('proveedor_id');

        $this->assertDatabaseCount('recepciones', 0);
    }

    public function test_proveedor_sin_recepciones_se_puede_eliminar(): void
    {
        $proveedor = Proveedor::factory()->create();

        $this->actingAs($this->admin())->delete("/proveedores/{$proveedor->id}")->assertRedirect('/proveedores');

        $this->assertDatabaseMissing('proveedores', ['id' => $proveedor->id]);
    }

    public function test_proveedor_con_recepciones_no_se_elimina(): void
    {
        $proveedor = Proveedor::factory()->create();
        app(InventarioService::class)->registrarRecepcion(
            User::factory()->bodeguero()->create(),
            ['proveedor_id' => $proveedor->id, 'numero_documento' => 'F-100'],
            [['producto_id' => Producto::factory()->create()->id, 'cantidad' => 5, 'codigo_lote' => 'L1']],
        );

        $this->actingAs($this->admin())
            ->delete("/proveedores/{$proveedor->id}")
            ->assertSessionHasErrors('proveedor');

        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id]);
    }

    public function test_solo_el_administrador_accede_a_la_gestion_de_proveedores(): void
    {
        $objetivo = Proveedor::factory()->create();

        foreach (['vendedor', 'bodeguero'] as $rol) {
            $usuario = User::factory()->{$rol}()->create();
            $this->actingAs($usuario)->get('/proveedores')->assertForbidden();
            $this->actingAs($usuario)->get('/proveedores/crear')->assertForbidden();
            $this->actingAs($usuario)->post('/proveedores', $this->datosValidos())->assertForbidden();
            $this->actingAs($usuario)->put("/proveedores/{$objetivo->id}", $this->datosValidos())->assertForbidden();
            $this->actingAs($usuario)->patch("/proveedores/{$objetivo->id}/estado")->assertForbidden();
            $this->actingAs($usuario)->delete("/proveedores/{$objetivo->id}")->assertForbidden();
        }

        $this->assertDatabaseMissing('proveedores', ['rut' => '76.111.111-6']);
        $this->assertDatabaseHas('proveedores', ['id' => $objetivo->id, 'activo' => true]);
    }

    public function test_el_menu_muestra_proveedores_solo_al_administrador(): void
    {
        $this->actingAs($this->admin())->get('/')->assertSee(route('proveedores.index'));
        $this->actingAs(User::factory()->bodeguero()->create())->get('/')->assertDontSee(route('proveedores.index'));
    }

    public function test_los_proveedores_de_la_fabrica_tienen_rut_valido(): void
    {
        foreach (Proveedor::factory()->count(20)->create() as $proveedor) {
            $this->assertTrue(Rut::esValido($proveedor->rut), "RUT inválido: {$proveedor->rut}");
        }
    }
}
