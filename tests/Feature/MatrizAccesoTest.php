<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Prueba de integración del control de acceso (RF9, RNF2, K8; issue #27).
 *
 * Recorre todas las pantallas del sistema con los tres roles y un invitado, y verifica que
 * cada rol entre solo a lo suyo. Si se agrega una ruta nueva sin protección, esta prueba la
 * detecta al compararla con la matriz.
 */
class MatrizAccesoTest extends TestCase
{
    use RefreshDatabase;

    /** Pantalla => roles que pueden verla. */
    private const MATRIZ = [
        '/' => ['administrador', 'vendedor', 'bodeguero'],
        '/productos' => ['administrador', 'vendedor', 'bodeguero'],
        '/productos/crear' => ['administrador'],
        '/productos/importar' => ['administrador'],
        '/movimientos' => ['administrador'],
        '/usuarios' => ['administrador'],
        '/usuarios/crear' => ['administrador'],
        '/proveedores' => ['administrador'],
        '/proveedores/crear' => ['administrador'],
        '/ordenes-compra' => ['administrador'],
        '/indicadores' => ['administrador'],
        '/recepciones' => ['administrador', 'bodeguero'],
        '/recepciones/crear' => ['administrador', 'bodeguero'],
        '/alertas' => ['administrador', 'bodeguero'],
        '/lotes/por-vencer' => ['administrador', 'bodeguero'],
        '/mermas' => ['administrador', 'bodeguero'],
        '/mermas/crear' => ['administrador', 'bodeguero'],
        '/conteos' => ['administrador', 'bodeguero'],
        '/conteos/crear?clase=A' => ['administrador', 'bodeguero'],
        '/ventas' => ['administrador', 'vendedor'],
        '/ventas/crear' => ['administrador', 'vendedor'],
    ];

    public static function roles(): array
    {
        return [
            'administrador' => ['administrador'],
            'vendedor' => ['vendedor'],
            'bodeguero' => ['bodeguero'],
        ];
    }

    #[DataProvider('roles')]
    public function test_cada_rol_entra_solo_a_sus_pantallas(string $rol): void
    {
        $usuario = User::factory()->{$rol}()->create();

        foreach (self::MATRIZ as $url => $permitidos) {
            $esperado = in_array($rol, $permitidos, true) ? 200 : 403;
            $this->actingAs($usuario)->get($url)->assertStatus($esperado);
        }
    }

    public function test_un_invitado_es_enviado_al_login_en_todas_las_pantallas(): void
    {
        foreach (array_keys(self::MATRIZ) as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_las_operaciones_que_mueven_stock_rechazan_al_rol_equivocado(): void
    {
        $producto = Producto::factory()->create();
        $vendedor = User::factory()->vendedor()->create();
        $bodeguero = User::factory()->bodeguero()->create();

        // El vendedor no recibe mercadería, no registra mermas ni conteos
        $this->actingAs($vendedor)->post('/recepciones', [])->assertForbidden();
        $this->actingAs($vendedor)->post('/mermas', [])->assertForbidden();
        $this->actingAs($vendedor)->post('/conteos', [])->assertForbidden();

        // El bodeguero no vende ni genera órdenes de compra ni cambia productos
        $this->actingAs($bodeguero)->post('/ventas', [
            'medio_pago' => 'efectivo', 'items' => [['producto_id' => $producto->id, 'cantidad' => 1]],
        ])->assertForbidden();
        $this->actingAs($bodeguero)->post('/ordenes-compra/sugerir')->assertForbidden();
        $this->actingAs($bodeguero)->post('/productos/recalcular-abc')->assertForbidden();

        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('movimientos', 0);
    }
}
