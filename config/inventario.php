<?php

// Parámetros del negocio del almacén (configurables por .env sin tocar el código).
return [
    // HU12 / K4: ventana por defecto, en días, para la alerta de lotes por vencer.
    'dias_por_vencer' => (int) env('INVENTARIO_DIAS_POR_VENCER', 7),
];
