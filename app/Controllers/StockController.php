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
            if (empty($data['nombre'])) $errores['nombre'] = 'El nombre es obligatorio.';
            if (empty($data['precio'])) $errores['precio'] = 'El precio es obligatorio.';
            if (empty($data['categoria_id'])) $errores['categoria_id'] = 'El categoria_id es obligatorio.';
        }
        if (isset($data['nombre']) && strlen($data['nombre']) > 200) $errores['nombre'] = 'Máximo 150 caracteres.';
        if (isset($data['descripcion']) && strlen($data['descripcion']) > 200) $errores['descripcion'] = 'Máximo 500 caracteres.';
        if (isset($data['precio']) && !preg_match('/^\d+(\.\d{1,2})?$/', $data['precio'])) {
            $errores['precio'] = 'El precio debe ser un número válido con hasta 2 decimales.';
        }
        
        return $errores;
    }
}
