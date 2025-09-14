<?php
/**
 * Modelo para la tabla 'producto_talla_stock'
 *
 * Esta clase maneja la lógica de la base de datos para la tabla pivote que conecta
 * productos, tallas y su stock.
 *
 * Es una clase especial porque no extiende 'Base', ya que la tabla 'producto_talla_stock'
 * tiene una clave primaria compuesta (producto_id, talla_id) y no una simple columna 'id'.
 * Esto requiere una implementación a medida para los métodos CRUD.
 */
class Stock {
    protected $conn;
    protected $table = "producto_talla_stock";
    protected $schema;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->schema = new SchemeService($conn);
    }

    /**
     * Obtiene todas las entradas de stock para un producto específico.
     *
     * @param int $id El ID del producto.
     * @return array Una lista de las tallas y stocks para ese producto.
     */
    public function all($id) {
        // La lógica para construir la consulta con JOINs es similar a la de BaseModel,
        // pero se añade una cláusula WHERE para filtrar por producto_id.
        $allColumns = $this->schema->allColumns($this->table) ?? ['*'];
        $fKColumns = $this->schema->foreignKeyColumns($this->table) ?? [];
        $columns = [];
        $joins = [];
        $joinIndex = 1;

        foreach ($allColumns as $c) {
            if (isset($fKColumns[$c])) {
                $refTable = $fKColumns[$c]['referenced_table'];
                $refColumn = $fKColumns[$c]['referenced_column'];
                $alias = "r$joinIndex";
                $joinIndex++;

                if ($c === "producto_id") {
                    $columns[] = "$alias.titulo AS {$refTable}_nombre";
                } elseif ($c === "talla_id") {
                    $columns[] = "$alias.talla AS {$refTable}_nombre";
                }
                $columns[] = "t.$c";
                $joins[]   = "LEFT JOIN $refTable $alias ON t.$c = $alias.$refColumn";
            } else {
                $columns[] = "t.$c";
            }
        }

        $columnsSql = implode(", ", $columns);
        $joinsSql   = implode(" ", $joins);

        $sql = "SELECT $columnsSql
                FROM {$this->table} t
                $joinsSql
                WHERE t.producto_id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca una entrada de stock específica usando la clave primaria compuesta.
     *
     * @param array $ids Un array que contiene [producto_id, talla_id].
     * @return mixed El registro de stock o false si no se encuentra.
     */
    public function find($ids) {
        // Lógica de construcción de JOINs similar a `all()`.
        $fillColumns = $this->schema->fillableColumns($this->table) ?? ['*'];
        $fKColumns = $this->schema->foreignKeyColumns($this->table) ?? [];
        $columns = [];
        $joins = [];
        $joinIndex = 1;

        foreach ($fillColumns as $c) {
            if (isset($fKColumns[$c])) {
                $refTable = $fKColumns[$c]['referenced_table'];
                $refColumn = $fKColumns[$c]['referenced_column'];
                $alias = "r$joinIndex";
                $joinIndex++;

                if ($c === "producto_id") {
                    $columns[] = "$alias.titulo AS {$refTable}_nombre";
                } elseif ($c === "talla_id") {
                    $columns[] = "$alias.talla AS {$refTable}_nombre";
                }
                $columns[] = "t.$c";
                $joins[]   = "LEFT JOIN $refTable $alias ON t.$c = $alias.$refColumn";
            } else {
                $columns[] = "t.$c";
            }
        }

        $columnsSql = implode(", ", $columns);
        $joinsSql   = implode(" ", $joins);

        // La cláusula WHERE busca la combinación exacta de producto_id y talla_id.
        $sql = "SELECT $columnsSql 
                FROM {$this->table} t
                $joinsSql
                WHERE t.producto_id = ? AND t.talla_id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([...$ids]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crea una nueva entrada de stock.
     *
     * @param array $data Los datos a insertar, debe incluir 'producto_id', 'talla_id' y 'stock'.
     * @return mixed Un array con la clave primaria compuesta si tiene éxito, o false si falla.
     * @throws Exception Si la combinación de producto y talla ya existe.
     */
    public function create($data) {
        // Primero, verifica si ya existe una entrada para este producto y talla para evitar duplicados.
        $result = $this->find([$data['producto_id'], $data['talla_id']]);
        if ($result) {
            throw new Exception("Combinación de producto y talla ya tiene stock registrado");
        }

        // Lógica de inserción similar a la de BaseModel.
        $fillColumns = $this->schema->fillableColumns($this->table) ?? ['*'];
        $columns = implode(', ', $fillColumns);
        $placeholders = ':' . implode(', :', $fillColumns);
        $params = [];
        foreach ($fillColumns as $c) {
            $params[$c] = $data[$c] ?? null;
        }

        try {
            $this->conn->beginTransaction();
            $sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders)";
            $stmt = $this->conn->prepare($sql);

            if ($stmt->execute($params)) {
                $this->conn->commit();
                // Devuelve la clave primaria compuesta del nuevo registro.
                return [$data['producto_id'], $data['talla_id']];
            } else {
                return ['success' => false, 'error' => $stmt->errorInfo()];
            }
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    /**
     * Actualiza una entrada de stock.
     *
     * @param array $ids La clave primaria compuesta [producto_id, talla_id].
     * @param array $data Los nuevos datos a actualizar.
     * @return array Un array indicando el éxito y los campos no actualizados.
     */
    public function update(array $ids, array $data) {
        $updateColumns = $this->schema->updatableColumns($this->table);
        $params = ['producto_id' => $ids[0], 'talla_id' => $ids[1]];
        $setParts = [];
        $notUpdated = [];
        
        foreach ($updateColumns as $c) {
            if (isset($data[$c])) {
                $setParts[] = "$c = :$c";
                $params[$c] = $data[$c];
            }
        }
        if (empty($setParts)) return false;

        foreach ($data as $key => $value) {
            if (!in_array($key, $updateColumns)) {
                $notUpdated[] = $key;
            }
        }

        // La cláusula WHERE se ajusta para la clave primaria compuesta.
        $sql = "UPDATE $this->table SET ".implode(", ", $setParts)." WHERE producto_id = :producto_id AND talla_id = :talla_id";
        $stmt = $this->conn->prepare($sql);
        return [
            'success' => $stmt->execute($params),
            'not_updated' => $notUpdated
        ];
    }

    /**
     * Elimina una entrada de stock.
     *
     * @param array $ids La clave primaria compuesta [producto_id, talla_id].
     * @return bool True si tiene éxito, false si no.
     */
    public function delete(array $ids) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE producto_id = :producto_id AND talla_id = :talla_id");
        return $stmt->execute(['producto_id' => $ids[0], 'talla_id' => $ids[1]]);
    }
}