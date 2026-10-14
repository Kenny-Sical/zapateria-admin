<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Spanish)
    |--------------------------------------------------------------------------
    |
    | Covers the rules used by the admin forms. Missing keys fall back to the
    | framework's English lines (fallback_locale).
    |
    */

    'array' => 'El campo :attribute debe ser una lista.',
    'confirmed' => 'La confirmación del campo :attribute no coincide.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'exists' => 'El valor seleccionado en :attribute no es válido.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor seleccionado en :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'file' => 'El archivo :attribute no debe pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
    ],
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El archivo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser un texto.',
    'unique' => 'Ya existe un registro con este :attribute.',
    'uploaded' => 'No se pudo subir el archivo :attribute.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'audience' => 'público',
        'category_id' => 'categoría',
        'email' => 'correo electrónico',
        'image' => 'imagen',
        'inventory' => 'inventario',
        'name' => 'nombre',
        'password' => 'contraseña',
        'price' => 'precio',
        'role' => 'rol',
        'sku' => 'SKU',
        'status' => 'estado',
    ],

];
