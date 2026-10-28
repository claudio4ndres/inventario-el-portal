<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use App\Services\CatalogoCsvService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** HU16: importar el catálogo inicial desde CSV (OE5, M5.1). */
class ImportacionCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private const ENCABEZADO = "codigo_barras;nombre;precio_venta;punto_reorden;clase_abc\n";

    private function admin(): User
    {
        return User::factory()->administrador()->create();
    }

    private function csv(string $filas, string $encabezado = self::ENCABEZADO): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('catalogo.csv', $encabezado.$filas);
    }

    public function test_plantilla_csv_se_descarga_con_el_encabezado_y_ejemplos(): void
    {
        $respuesta = $this->actingAs($this->admin())->get('/productos/importar/plantilla');

        $respuesta->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="plantilla-catalogo.csv"');
        $this->assertStringContainsString('text/csv', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('codigo_barras;nombre;precio_venta;punto_reorden;clase_abc', $respuesta->getContent());

        // La propia plantilla es un archivo válido para importar
        $filas = app(CatalogoCsvService::class)->analizar($respuesta->getContent());
        $this->assertCount(2, $filas);
        $this->assertSame([[], []], array_column($filas, 'errores'));
    }

    public function test_vista_previa_muestra_los_errores_fila_por_fila_sin_guardar_nada(): void
    {
        $archivo = $this->csv(
            "7802900001002;Leche entera 1 L;1190;12;A\n".
            "7801234000017;Arroz 1 kg;0;8;B\n".        // línea 3: precio inválido
            "7801234000024;Fideos 400 g;890;5;Z\n"     // línea 4: clase inválida
        );

        $this->actingAs($this->admin())
            ->post('/productos/importar/vista-previa', ['archivo' => $archivo])
            ->assertOk()
            ->assertViewHas('totalValidas', 1)
            ->assertViewHas('totalErrores', 2)
            ->assertViewHas('filas', function (array $filas) {
                return $filas[0]['linea'] === 2 && $filas[0]['errores'] === [] && $filas[0]['accion'] === 'crear'
                    && $filas[1]['linea'] === 3 && $filas[1]['errores'] === ['El campo precio de venta debe ser al menos 1.']
                    && $filas[2]['linea'] === 4 && $filas[2]['errores'] === ['El valor seleccionado en clase ABC no es válido.'];
            })
            ->assertSee('El campo precio de venta debe ser al menos 1.')
            ->assertSee('Importar 1 fila válida');

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_al_confirmar_se_importan_solo_las_filas_validas(): void
    {
        $admin = $this->admin();
        $archivo = $this->csv(
            "7802900001002;Leche entera 1 L;1190;12;A\n".
            "7801234000017;Arroz 1 kg;1490;8;b\n".
            "SIN CODIGO;Pan;100;1;C\n"                 // código con espacio: inválido
        );

        $this->actingAs($admin)->post('/productos/importar/vista-previa', ['archivo' => $archivo])->assertOk();
        $this->actingAs($admin)->post('/productos/importar')
            ->assertRedirect('/productos')
            ->assertSessionHas('ok', 'Catálogo importado desde «catalogo.csv»: 2 productos creados y 0 actualizados.');

        $this->assertDatabaseCount('productos', 2);
        $this->assertDatabaseHas('productos', ['codigo_barras' => '7802900001002', 'precio_venta' => 1190, 'punto_reorden' => 12, 'clase_abc' => 'A', 'activo' => true]);
        $this->assertDatabaseHas('productos', ['codigo_barras' => '7801234000017', 'clase_abc' => 'B']);
        $this->assertNull(session('importacion_catalogo'));   // no se puede importar dos veces la misma vista previa
    }

    public function test_si_el_codigo_ya_existe_se_actualiza_el_producto(): void
    {
        $admin = $this->admin();
        $existente = Producto::factory()->create([
            'codigo_barras' => '7802900001002', 'nombre' => 'Leche', 'precio_venta' => 990,
            'punto_reorden' => 3, 'clase_abc' => 'C', 'dias_reposicion' => 5,
        ]);

        $this->actingAs($admin)
            ->post('/productos/importar/vista-previa', ['archivo' => $this->csv("7802900001002;Leche entera 1 L;1190;12;A\n7801234000017;Arroz 1 kg;1490;8;B\n")])
            ->assertViewHas('totalActualizar', 1)
            ->assertViewHas('totalCrear', 1);
        $this->actingAs($admin)->post('/productos/importar')
            ->assertSessionHas('ok', 'Catálogo importado desde «catalogo.csv»: 1 producto creado y 1 actualizado.');

        $existente->refresh();
        $this->assertSame('Leche entera 1 L', $existente->nombre);
        $this->assertSame(1190, $existente->precio_venta);
        $this->assertSame(12, $existente->punto_reorden);
        $this->assertSame('A', $existente->clase_abc);
        $this->assertSame(5, $existente->dias_reposicion);   // lo que no viene en el CSV se conserva
        $this->assertDatabaseCount('productos', 2);
    }

    public function test_acepta_coma_como_separador_bom_y_precio_con_separador_de_miles(): void
    {
        $archivo = $this->csv(
            "7802900001002,\"Leche entera, 1 L\",\$1.190,12,a\n",
            "\xEF\xBB\xBFcodigo_barras,nombre,precio_venta,punto_reorden,clase_abc\n"
        );

        $this->actingAs($this->admin())
            ->post('/productos/importar/vista-previa', ['archivo' => $archivo])
            ->assertViewHas('totalValidas', 1);
        $this->post('/productos/importar')->assertRedirect('/productos');

        $this->assertDatabaseHas('productos', ['nombre' => 'Leche entera, 1 L', 'precio_venta' => 1190, 'clase_abc' => 'A']);
    }

    public function test_codigo_repetido_dentro_del_archivo_se_marca_como_error(): void
    {
        $archivo = $this->csv("7802900001002;Leche entera 1 L;1190;12;A\n7802900001002;Leche descremada 1 L;1190;12;A\n");

        $this->actingAs($this->admin())
            ->post('/productos/importar/vista-previa', ['archivo' => $archivo])
            ->assertViewHas('totalValidas', 1)
            ->assertSee('El código de barras 7802900001002 está repetido en el archivo (línea 2).');
    }

    public function test_archivo_sin_las_columnas_obligatorias_es_rechazado(): void
    {
        $archivo = $this->csv("7802900001002;Leche;1190\n", "codigo_barras;nombre;precio_venta\n");

        $this->actingAs($this->admin())
            ->from('/productos/importar')
            ->post('/productos/importar/vista-previa', ['archivo' => $archivo])
            ->assertRedirect('/productos/importar')
            ->assertSessionHasErrors(['archivo' => 'Faltan columnas en el encabezado del archivo: punto_reorden, clase_abc. Descargue la plantilla para ver el formato.']);

        $this->actingAs($this->admin())
            ->post('/productos/importar/vista-previa', [])
            ->assertSessionHasErrors(['archivo' => 'El campo archivo CSV es obligatorio.']);
    }

    public function test_las_filas_validas_se_importan_en_una_transaccion(): void
    {
        $filas = [
            ['codigo_barras' => '7802900001002', 'nombre' => 'Leche', 'precio_venta' => '1190', 'punto_reorden' => '12', 'clase_abc' => 'A'],
            ['codigo_barras' => '7801234000017', 'nombre' => 'Arroz', 'precio_venta' => '-5', 'punto_reorden' => '8', 'clase_abc' => 'B'],
        ];

        try {
            app(CatalogoCsvService::class)->importar($filas);
            $this->fail('Se esperaba una ValidationException.');
        } catch (ValidationException) {
            // la segunda fila falla: la primera tampoco debe quedar guardada
        }

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_confirmar_sin_vista_previa_no_importa_nada(): void
    {
        $this->actingAs($this->admin())
            ->post('/productos/importar')
            ->assertRedirect('/productos/importar')
            ->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('productos', 0);
    }

    public function test_solo_el_administrador_puede_importar(): void
    {
        $this->actingAs($this->admin())->get('/productos/importar')->assertOk()->assertSee('Descargar plantilla');

        foreach ([User::factory()->bodeguero()->create(), User::factory()->vendedor()->create()] as $usuario) {
            $this->actingAs($usuario)->get('/productos/importar')->assertForbidden();
            $this->actingAs($usuario)->get('/productos/importar/plantilla')->assertForbidden();
            $this->actingAs($usuario)
                ->post('/productos/importar/vista-previa', ['archivo' => $this->csv("7802900001002;Leche;1190;12;A\n")])
                ->assertForbidden();
            $this->actingAs($usuario)->post('/productos/importar')->assertForbidden();
        }

        $this->assertDatabaseCount('productos', 0);
    }
}
