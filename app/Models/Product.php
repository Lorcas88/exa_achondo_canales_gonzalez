<?php
/**
 * Modelo para la tabla 'producto'
 *
 * Esta clase representa la tabla de productos en la base de datos.
 * Extiende la clase 'Base' para heredar la funcionalidad CRUD básica, pero añade
 * métodos específicos para manejar la lógica de negocio de los productos, como
 * el cálculo de precios dinámicos y el filtrado.
 */
class Product extends Base {
    /**
     * @var string El nombre de la tabla en la base de datos.
     */
    protected $table = "producto";

    /**
     * Constructor del modelo Product.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    public function __construct($conn) {
        parent::__construct($conn);
    }

    /**
     * Obtiene el precio final para un único producto y un cliente específico.
     * Nota: Este método puede ser redundante ya que `findWithFinalPrice` ofrece una funcionalidad similar
     * junto con el resto de los datos del producto.
     *
     * @param int $productoId El ID del producto.
     * @param int $clienteId El ID del cliente.
     * @return array Un array con los detalles del precio.
     */
    public function getPrecioFinal($productoId, $clienteId) {
        $sql = "SELECT
            p.precio AS precio_base,
            p.precio_oferta,
            c.porcentaje_descuento AS descuento_cliente,
            ROUND(
                CASE
                    WHEN p.precio_oferta IS NOT NULL THEN p.precio_oferta
                    ELSE p.precio * (1 - COALESCE(c.porcentaje_descuento, 0)/100)
                END
            , 0) AS precio_final
        FROM producto p, cliente c
        WHERE p.id = :producto_id
        AND c.id = :cliente_id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':producto_id' => $productoId, ':cliente_id' => $clienteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los productos con su precio final calculado y aplicando filtros.
     *
     * @param int|null $clienteId El ID del cliente para calcular el descuento. Puede ser null.
     * @param array $filters Un array asociativo con los filtros a aplicar (ej. ['pais' => 'Chile']).
     * @return array Una lista de todos los productos que coinciden con los filtros.
     */
    public function allWithFinalPrice($clienteId, $filters = []) {
        $descuento = 0;
        // 1. Obtener el porcentaje de descuento del cliente.
        if ($clienteId) {
            $clientSql = "SELECT porcentaje_descuento FROM cliente WHERE id = :cliente_id";
            $stmt = $this->conn->prepare($clientSql);
            $stmt->execute([':cliente_id' => $clienteId]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            $descuento = $cliente ? $cliente['porcentaje_descuento'] : 0;
        }

        // 2. Construir la consulta base para los productos.
        // La cláusula CASE maneja la lógica del precio final:
        // - Si hay precio_oferta, se usa ese.
        // - Si no, se aplica el descuento al precio base.
        $productSql = "SELECT *,
            ROUND(
                CASE
                    WHEN precio_oferta IS NOT NULL THEN precio_oferta
                    ELSE precio * (1 - :descuento/100)
                END
            , 0) AS precio_final
            FROM producto";

        // 3. Añadir dinámicamente las cláusulas WHERE para los filtros.
        $whereClauses = [];
        $params = [':descuento' => $descuento];

        if (!empty($filters['pais'])) {
            $whereClauses[] = "pais = :pais";
            $params[':pais'] = $filters['pais'];
        }
        if (!empty($filters['tipo'])) {
            $whereClauses[] = "tipo = :tipo";
            $params[':tipo'] = $filters['tipo'];
        }
        if (!empty($filters['color'])) {
            $whereClauses[] = "color = :color";
            $params[':color'] = $filters['color'];
        }

        // Si hay filtros, se añaden a la consulta SQL.
        if (!empty($whereClauses)) {
            $productSql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        // 4. Ejecutar la consulta y devolver los resultados.
        $stmt = $this->conn->prepare($productSql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un producto específico por su ID con el precio final calculado.
     *
     * @param int $id El ID del producto.
     * @param int|null $clienteId El ID del cliente para calcular el descuento.
     * @return mixed El registro del producto como un array asociativo, o false si no se encuentra.
     */
    public function findWithFinalPrice($id, $clienteId) {
        $descuento = 0;
        // 1. Obtener el descuento del cliente (lógica idéntica a allWithFinalPrice).
        if ($clienteId) {
            $clientSql = "SELECT porcentaje_descuento FROM cliente WHERE id = :cliente_id";
            $stmt = $this->conn->prepare($clientSql);
            $stmt->execute([':cliente_id' => $clienteId]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            $descuento = $cliente ? $cliente['porcentaje_descuento'] : 0;
        }

        // 2. Construir la consulta para un producto específico, incluyendo el cálculo del precio final.
        $productSql = "SELECT *,
            ROUND(
                CASE
                    WHEN precio_oferta IS NOT NULL THEN precio_oferta
                    ELSE precio * (1 - :descuento/100)
                END
            , 0) AS precio_final
            FROM producto WHERE id = :id";
            
        // 3. Ejecutar la consulta con los parámetros de descuento y ID del producto.
        $stmt = $this->conn->prepare($productSql);
        $stmt->execute([':descuento' => $descuento, ':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}