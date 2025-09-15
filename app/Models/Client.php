<?php
/**
 * Modelo para la tabla 'cliente'
 *
 * Esta clase representa la tabla de clientes (empresas B2B) en la base de datos.
 *
 * Actualmente, esta clase no tiene lógica de negocio propia, por lo que hereda
 * toda su funcionalidad directamente del modelo 'Base'. Simplemente define
 * el nombre de la tabla con la que debe interactuar.
 */
class Client extends Base {
    /**
     * @var string El nombre de la tabla en la base de datos.
     */
    protected $table = "cliente";

    /**
     * Constructor del modelo Client.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    /**
     * Obtiene todos los clientes que tienen un descuento aplicado.
     *
     * @return array Un array de clientes con descuento.
     */
    public function findAllWithDiscount() {
        $sql = "SELECT * FROM {$this->table} WHERE porcentaje_descuento > 0";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}