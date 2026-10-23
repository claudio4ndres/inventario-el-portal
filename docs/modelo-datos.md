# Modelo de datos del MPV

Este modelo se construyó siguiendo las pautas del apunte de modelado de datos (Pinto, 2022):

- **Entidades** con un identificador único (clave primaria).
- **Asociaciones** 1:N.
- Las relaciones **M:N se resuelven con una entidad de intersección**.
- La **integridad referencial** se aplica mediante claves foráneas.

## Diagrama entidad-relación

```mermaid
erDiagram
    USERS ||--o{ RECEPCIONES : registra
    USERS ||--o{ VENTAS : registra
    USERS ||--o{ MOVIMIENTOS : ejecuta
    PROVEEDORES ||--o{ RECEPCIONES : entrega
    RECEPCIONES ||--|{ LOTES : origina
    PRODUCTOS ||--o{ LOTES : "se almacena en"
    VENTAS ||--|{ DETALLE_VENTAS : contiene
    PRODUCTOS ||--o{ DETALLE_VENTAS : "se vende en"
    LOTES ||--o{ MOVIMIENTOS : afecta
    PRODUCTOS ||--o{ MOVIMIENTOS : registra
    DETALLE_VENTAS |o--o{ MOVIMIENTOS : "genera (SALIDA)"
    RECEPCIONES |o--o{ MOVIMIENTOS : "genera (ENTRADA)"

    USERS {
        bigint id PK
        string name
        string email UK
        string password "hash bcrypt"
        string rol "administrador|vendedor|bodeguero"
        bool activo
    }
    PRODUCTOS {
        bigint id PK
        string codigo_barras UK
        string nombre
        int precio_venta "CLP con IVA"
        int punto_reorden
        char clase_abc "A|B|C"
        bool activo
    }
    PROVEEDORES {
        bigint id PK
        string rut UK
        string razon_social
        string telefono
        string email
        bool activo
    }
    RECEPCIONES {
        bigint id PK
        bigint proveedor_id FK
        bigint user_id FK
        string numero_documento
        timestamp fecha_recepcion
    }
    LOTES {
        bigint id PK
        bigint producto_id FK
        bigint recepcion_id FK
        string codigo_lote
        date fecha_vencimiento "null = no perecible"
        int cantidad_inicial
        int cantidad_disponible
        int costo_unitario
    }
    VENTAS {
        bigint id PK
        bigint user_id FK
        int total
        string medio_pago
        timestamp fecha_venta
    }
    DETALLE_VENTAS {
        bigint id PK
        bigint venta_id FK
        bigint producto_id FK
        int cantidad
        int precio_unitario
        int subtotal
    }
    MOVIMIENTOS {
        bigint id PK
        bigint producto_id FK
        bigint lote_id FK
        bigint user_id FK
        string tipo "ENTRADA|SALIDA|AJUSTE|MERMA"
        int cantidad
        int stock_resultante
        int costo_total "nullable, solo MERMA (HU13)"
        string motivo_merma "vencimiento|dano|robo|otro (HU13)"
        bigint detalle_venta_id FK "nullable"
        bigint recepcion_id FK "nullable"
        string motivo
    }
```

## Decisiones de diseño

1. **El stock no se guarda en `productos`.** Se calcula como la suma de `lotes.cantidad_disponible`. Con una sola fuente de verdad, el stock teórico no puede desalinearse de los lotes (causa C5, control K1).
2. **`MOVIMIENTOS` es la entidad de intersección entre `DETALLE_VENTAS` y `LOTES`.** Una línea de venta puede consumir varios lotes (por ejemplo, 6 unidades: 4 del lote que vence antes y 2 del siguiente), y un lote abastece muchas ventas. Es la misma técnica del apunte para transformar una relación M:N en dos 1:N. Además, cada fila de la tabla sirve como registro de auditoría (K8).
3. **Integridad referencial con `RESTRICT`.** No se puede borrar un producto, proveedor o usuario que tenga historia; en su lugar se desactiva (`activo = false`). Así la bitácora nunca queda con referencias huérfanas.
4. **El precio se copia a `detalle_ventas.precio_unitario`.** Si el precio del producto cambia después, el historial de ventas no se altera.
5. **Los montos se guardan como enteros en CLP**, porque el peso chileno no usa decimales. Esto evita errores de redondeo.
6. **El orden de salida de los lotes es FEFO** (*first expired, first out*), una variante del criterio FIFO: sale primero el lote que vence antes y, a igual vencimiento, el que ingresó primero. Es el orden recomendado para perecibles (Decreto 977, art. 106).

## Diccionario de datos (resumen)

| Tabla | Campo | Tipo | Restricción | Descripción |
|-------|-------|------|-------------|-------------|
| productos | codigo_barras | varchar(32) | UNIQUE, NOT NULL | Código EAN leído por el escáner |
| productos | precio_venta | int unsigned | > 0 | Precio con IVA incluido (Ley 19.496) |
| productos | punto_reorden | int unsigned | ≥ 0 | Umbral de la alerta K3 |
| productos | clase_abc | char(1) | A, B o C | Prioridad de control |
| lotes | fecha_vencimiento | date | NULL permitido | NULL indica un producto no perecible |
| lotes | cantidad_disponible | int unsigned | ≥ 0 | Saldo del lote; la suma de todos los lotes es el stock |
| movimientos | tipo | varchar(10) | ENTRADA, SALIDA, AJUSTE o MERMA | Naturaleza del movimiento |
| movimientos | stock_resultante | int unsigned | NOT NULL | Stock total del producto después del movimiento |
| movimientos | costo_total | int unsigned | NULL permitido | Solo en MERMA: cantidad × costo unitario del lote, copiado al registrar (HU13) |
| movimientos | motivo_merma | varchar(20) | vencimiento, dano, robo u otro; NULL si no es merma | Causa de la merma, base del indicador M3 (HU13, HU15) |
| proveedores | rut | varchar(12) | UNIQUE, NOT NULL | RUT con dígito verificador válido (módulo 11), guardado normalizado como `76.111.111-6` (HU09) |
| users | rol | varchar(20) | administrador, vendedor o bodeguero | Permisos (K8) |

## Extensiones previstas después del MPV

- **HU11:** `ordenes_compra` y `detalle_ordenes_compra`.
- **HU14:** `conteos` y `detalle_conteos`.

El modelo del MPV no requiere cambios para soportarlas; solo se agregan tablas.

**Implementado (HU13):** las mermas se registran en `movimientos` con `tipo = MERMA`. La migración `2026_10_23_000001` agrega `costo_total` (se copia al registrar, igual que el precio en `detalle_ventas`, para que el historial no cambie) y `motivo_merma`, con un índice `(tipo, motivo_merma)` para el indicador de merma por vencimiento.

## Imágenes

- [Diagrama ER compacto](img/modelo_er.png)
- [Diagrama ER detallado](img/modelo_er_detallado.png)
- [Diagrama de casos de uso](img/casos_uso.png)
- [Mockups](img/mockups/): 14 pantallas, 9 del MPV y 5 de los incrementos siguientes
