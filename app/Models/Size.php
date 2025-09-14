<?php
/**
 * Modelo para la tabla 'talla'
 *
 * Esta clase representa la tabla de tallas en la base de datos.
 *
 * Al igual que otros modelos simples en este proyecto, no contiene lógica de negocio
 * propia y hereda toda su funcionalidad CRUD directamente del modelo 'Base'.
 * Su única responsabilidad es indicar el nombre de la tabla que maneja.
 */
class Size extends Base {
    /**
     * @var string El nombre de la tabla en la base de datos.
     */
    protected $table = "talla";

    /**
     * Constructor del modelo Size.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    public function __construct($conn) {
        // Llama al constructor del padre (Base) para configurar la conexión.
        parent::__construct($conn);
    }
}