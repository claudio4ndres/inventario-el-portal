<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Preparación para el despliegue en Railway (proxy HTTPS y datos iniciales sin duplicar). */
class DespliegueTest extends TestCase
{
    use RefreshDatabase;

    public function test_detras_del_proxy_https_los_formularios_se_generan_con_https(): void
    {
        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'inventario.up.railway.app',
            'X-Forwarded-Port' => '443',
        ])->get('/login')
            ->assertOk()
            ->assertSee('action="https://inventario.up.railway.app/login"', false);
    }

    public function test_sembrar_demo_carga_los_datos_solo_si_la_base_esta_vacia(): void
    {
        $this->artisan('inventario:sembrar-demo')->assertSuccessful();

        $usuarios = User::count();
        $productos = Producto::count();
        $this->assertSame(3, $usuarios);
        $this->assertGreaterThan(0, $productos);

        // Un reinicio o un nuevo despliegue no duplica ni borra nada
        $this->artisan('inventario:sembrar-demo')
            ->expectsOutput('La base ya tiene usuarios: no se cargan datos de demostración.')
            ->assertSuccessful();
        $this->assertSame($usuarios, User::count());
        $this->assertSame($productos, Producto::count());
    }

    public function test_sembrar_demo_no_toca_una_base_con_datos_reales(): void
    {
        User::factory()->administrador()->create(['email' => 'dueno@elportal.cl']);

        $this->artisan('inventario:sembrar-demo')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(0, Producto::count());
    }
}
