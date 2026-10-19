<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HU01: inicio de sesión por rol (K8). */
class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_usuario_activo_inicia_sesion_con_credenciales_validas(): void
    {
        $usuario = User::factory()->administrador()->create(['password' => 'secreto123']);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'secreto123'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_credenciales_invalidas_no_inician_sesion(): void
    {
        $usuario = User::factory()->create(['password' => 'secreto123']);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_usuario_inactivo_no_puede_ingresar(): void
    {
        $usuario = User::factory()->inactivo()->create(['password' => 'secreto123']);

        $this->post('/login', ['email' => $usuario->email, 'password' => 'secreto123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_la_contrasena_se_guarda_con_hash(): void
    {
        $usuario = User::factory()->create(['password' => 'secreto123']);

        $this->assertNotSame('secreto123', $usuario->getRawOriginal('password'));
    }

    public function test_vendedor_no_accede_a_funciones_de_administrador(): void
    {
        $vendedor = User::factory()->vendedor()->create();

        $this->actingAs($vendedor)->get('/productos/crear')->assertForbidden();
        $this->actingAs($vendedor)->get('/movimientos')->assertForbidden();
        $this->actingAs($vendedor)->get('/recepciones/crear')->assertForbidden();
    }

    public function test_bodeguero_no_puede_registrar_ventas(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();

        $this->actingAs($bodeguero)->get('/ventas/crear')->assertForbidden();
    }

    public function test_cada_rol_ve_su_panel(): void
    {
        foreach (['administrador', 'vendedor', 'bodeguero'] as $rol) {
            $usuario = User::factory()->{$rol}()->create();
            $this->actingAs($usuario)->get('/')->assertOk()->assertSee($usuario->name);
        }
    }
}
