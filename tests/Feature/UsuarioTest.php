<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** HU08: gestión de usuarios y roles (RF9 · K8). */
class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrador()->create();
    }

    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'name' => 'Javiera Rojas',
            'email' => 'javiera@elportal.test',
            'password' => 'Bodega2026',
            'password_confirmation' => 'Bodega2026',
            'rol' => User::BODEGUERO,
            'activo' => '1',
        ], $cambios);
    }

    public function test_administrador_ve_el_listado_de_usuarios(): void
    {
        $admin = $this->admin();
        User::factory()->vendedor()->create(['name' => 'Pedro Caja']);

        $this->actingAs($admin)->get('/usuarios')->assertOk()->assertSee('Pedro Caja')->assertSee($admin->email);
    }

    public function test_administrador_crea_un_usuario_con_rol_y_contrasena_con_hash(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datosValidos())
            ->assertRedirect('/usuarios');

        $usuario = User::where('email', 'javiera@elportal.test')->firstOrFail();
        $this->assertSame(User::BODEGUERO, $usuario->rol);
        $this->assertTrue($usuario->activo);
        $this->assertTrue(Hash::check('Bodega2026', $usuario->password));
    }

    public function test_el_usuario_creado_puede_iniciar_sesion(): void
    {
        $this->actingAs($this->admin())->post('/usuarios', $this->datosValidos());
        auth()->logout();

        $this->post('/login', ['email' => 'javiera@elportal.test', 'password' => 'Bodega2026'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_el_correo_debe_ser_unico(): void
    {
        User::factory()->create(['email' => 'javiera@elportal.test']);

        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datosValidos())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'javiera@elportal.test')->count());
    }

    public function test_el_correo_es_unico_sin_importar_mayusculas(): void
    {
        User::factory()->create(['email' => 'javiera@elportal.test']);

        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datosValidos(['email' => 'Javiera@ElPortal.test']))
            ->assertSessionHasErrors('email');
    }

    public function test_la_contrasena_debe_tener_al_menos_8_caracteres(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datosValidos(['password' => 'corta12', 'password_confirmation' => 'corta12']))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'javiera@elportal.test']);
    }

    public function test_la_confirmacion_de_contrasena_debe_coincidir(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datosValidos(['password_confirmation' => 'OtraClave2026']))
            ->assertSessionHasErrors('password');
    }

    public function test_el_rol_debe_ser_valido(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datosValidos(['rol' => 'gerente']))
            ->assertSessionHasErrors('rol');
    }

    public function test_editar_sin_contrasena_conserva_la_actual_y_cambia_el_rol(): void
    {
        $usuario = User::factory()->vendedor()->create(['email' => 'javiera@elportal.test', 'password' => 'Original2026']);

        $this->actingAs($this->admin())
            ->put("/usuarios/{$usuario->id}", $this->datosValidos(['password' => '', 'password_confirmation' => '', 'rol' => User::ADMINISTRADOR]))
            ->assertRedirect('/usuarios');

        $usuario->refresh();
        $this->assertSame(User::ADMINISTRADOR, $usuario->rol);
        $this->assertTrue(Hash::check('Original2026', $usuario->password));
    }

    public function test_administrador_desactiva_y_reactiva_a_otro_usuario(): void
    {
        $usuario = User::factory()->vendedor()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/usuarios/{$usuario->id}/estado")->assertRedirect();
        $this->assertFalse($usuario->fresh()->activo);

        $this->actingAs($admin)->patch("/usuarios/{$usuario->id}/estado")->assertRedirect();
        $this->assertTrue($usuario->fresh()->activo);
    }

    public function test_administrador_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/usuarios/{$admin->id}/estado")->assertSessionHasErrors('usuario');
        $this->assertTrue($admin->fresh()->activo);

        $this->actingAs($admin)
            ->put("/usuarios/{$admin->id}", $this->datosValidos(['email' => $admin->email, 'rol' => User::ADMINISTRADOR, 'activo' => '0']))
            ->assertSessionHasErrors('activo');
        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_usuario_sin_movimientos_se_puede_eliminar(): void
    {
        $usuario = User::factory()->vendedor()->create();

        $this->actingAs($this->admin())->delete("/usuarios/{$usuario->id}")->assertRedirect('/usuarios');

        $this->assertDatabaseMissing('users', ['id' => $usuario->id]);
    }

    public function test_usuario_con_movimientos_no_se_elimina(): void
    {
        $bodeguero = User::factory()->bodeguero()->create();
        app(InventarioService::class)->registrarRecepcion(
            $bodeguero,
            ['proveedor_id' => Proveedor::factory()->create()->id, 'numero_documento' => 'F-100'],
            [['producto_id' => Producto::factory()->create()->id, 'cantidad' => 5, 'codigo_lote' => 'L1']],
        );

        $this->actingAs($this->admin())
            ->delete("/usuarios/{$bodeguero->id}")
            ->assertSessionHasErrors('usuario');

        $this->assertDatabaseHas('users', ['id' => $bodeguero->id]);
    }

    public function test_administrador_no_puede_eliminarse_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/usuarios/{$admin->id}")->assertSessionHasErrors('usuario');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_solo_el_administrador_accede_a_la_gestion_de_usuarios(): void
    {
        $objetivo = User::factory()->vendedor()->create();

        foreach (['vendedor', 'bodeguero'] as $rol) {
            $usuario = User::factory()->{$rol}()->create();
            $this->actingAs($usuario)->get('/usuarios')->assertForbidden();
            $this->actingAs($usuario)->get('/usuarios/crear')->assertForbidden();
            $this->actingAs($usuario)->post('/usuarios', $this->datosValidos())->assertForbidden();
            $this->actingAs($usuario)->patch("/usuarios/{$objetivo->id}/estado")->assertForbidden();
            $this->actingAs($usuario)->delete("/usuarios/{$objetivo->id}")->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'javiera@elportal.test']);
        $this->assertDatabaseHas('users', ['id' => $objetivo->id, 'activo' => true]);
    }

    public function test_el_menu_muestra_usuarios_solo_al_administrador(): void
    {
        $this->actingAs($this->admin())->get('/')->assertSee(route('usuarios.index'));
        $this->actingAs(User::factory()->vendedor()->create())->get('/')->assertDontSee(route('usuarios.index'));
    }
}
