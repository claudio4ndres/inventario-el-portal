# Catálogos de ejemplo para la validación (H5)

Archivos para probar la importación del catálogo desde CSV (HU16) en el ambiente de pruebas (`web-pruebas`), según CC-05 (#48) y la tarea #49.
Los productos son ilustrativos y sus códigos usan el prefijo 20, que el estándar GS1 reserva para uso interno de cada tienda, por lo que no coinciden con productos reales.

| Archivo | Contenido | Resultado esperado en la vista previa |
|---|---|---|
| `catalogo-ejemplo.csv` | 30 productos típicos de almacén | 30 filas «se crea»; al importar, 30 productos nuevos |
| `catalogo-ejemplo-con-errores.csv` | 7 filas de prueba | 2 filas válidas (una «se actualiza» si ya se importó el catálogo anterior) y 5 con error: código repetido en el archivo, precio 0, punto de reorden negativo, clase ABC inválida y código vacío |

Uso: Productos → Importar CSV → elegir el archivo → Ver vista previa → Importar.
