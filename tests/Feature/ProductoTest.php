<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU02: mantenedor de productos (K5). */
class ProductoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrador()->create();
    }

    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'codigo_barras' => '7802900001002',
            'nombre' => 'Leche entera 1 L',
            'precio_venta' => 1190,
            'punto_reorden' => 12,
            'clase_abc' => 'A',
            'activo' => '1',
        ], $cambios);
    }

    public function test_administrador_crea_un_producto(): void
    {
        $this->actingAs($this->admin())
            ->post('/productos', $this->datosValidos())
            ->assertRedirect('/productos');

        $this->assertDatabaseHas('productos', ['codigo_barras' => '7802900001002', 'precio_venta' => 1190, 'punto_reorden' => 12]);
    }

    public function test_codigo_de_barras_duplicado_es_rechazado(): void
    {
        Producto::factory()->create(['codigo_barras' => '7802900001002']);

        $this->actingAs($this->admin())
            ->post('/productos', $this->datosValidos())
            ->assertSessionHasErrors('codigo_barras');

        $this->assertDatabaseCount('productos', 1);
    }

    public function test_precio_debe_ser_mayor_que_cero_y_reorden_no_negativo(): void
    {
        $this->actingAs($this->admin())
            ->post('/productos', $this->datosValidos(['precio_venta' => 0, 'punto_reorden' => -1]))
            ->assertSessionHasErrors(['precio_venta', 'punto_reorden']);
    }

    public function test_editar_conserva_el_mismo_codigo_sin_error_de_unicidad(): void
    {
        $producto = Producto::factory()->create(['codigo_barras' => '7802900001002']);

        $this->actingAs($this->admin())
            ->put("/productos/{$producto->id}", $this->datosValidos(['nombre' => 'Leche descremada 1 L']))
            ->assertRedirect('/productos');

        $this->assertSame('Leche descremada 1 L', $producto->fresh()->nombre);
    }

    public function test_producto_se_desactiva_en_vez_de_eliminarse(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs($this->admin())->patch("/productos/{$producto->id}/estado")->assertRedirect();

        $this->assertFalse($producto->fresh()->activo);
        $this->assertDatabaseHas('productos', ['id' => $producto->id]);
    }

    public function test_busqueda_por_codigo_de_barras_devuelve_el_producto_con_su_stock(): void
    {
        $producto = Producto::factory()->create(['codigo_barras' => '7801234000123', 'nombre' => 'Huevos']);
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($vendedor)
            ->getJson('/productos/buscar?q=7801234000123')
            ->assertOk()
            ->assertJsonPath('0.id', $producto->id)
            ->assertJsonPath('0.stock', 0);
    }
}
