<?php

class ProductController extends BaseController {
    protected $hiddenFields = ['categoria_id'];

    public function __construct($conn) {
        $model = new Product($conn);
        parent::__construct($model);
    }

    protected function validate($data, $isUpdate = false) {
        $errores = [];
        if (!$isUpdate) {
            $mandatoryFields = ['titulo', 'precio', 'sku'];

            foreach ($mandatoryFields as $field) {
                if (empty($data[$field])) $errores[$field] = 'El campo ' . $field . ' es obligatorio.';
            }
        }

        $maxLengthFields = [
            'titulo' => 200,
            'club' => 150,
            'pais' => 80,
            'tipo' => 80,
            'color' => 120,
            'sku' => 80,
        ];
        foreach ($maxLengthFields as $field => $maxLength) {
            if (isset($data[$field]) && strlen($data[$field]) > $maxLength) $errores[$field] = 'Máximo ' . $maxLength . ' caracteres.';
        }

        $decFields = [
            'precio' => 2,
            'precio_oferta' => 2,
        ];
        foreach ($decFields as $field => $dec) {
            if (isset($data[$field]) && !is_numeric($data[$field])) $errores[$field] = 'El campo ' . $field . ' debe ser un número válido.';
            if (isset($data[$field]) && $data[$field] < 0) $errores[$field] = 'El campo ' . $field . ' no puede ser negativo.';
            if (isset($data[$field]) && !preg_match('/^\d+(\.\d{1,'.$dec.'})?$/', $data[$field])) $errores[$field] = 'El campo ' . $field . ' debe ser un número válido con hasta 2 decimales.';
        }

        return $errores;
    }
}
