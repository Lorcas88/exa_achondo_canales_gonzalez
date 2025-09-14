<?php
/**
 * Modelo Base Abstracto (Nota: El nombre de la clase es 'Base')
 *
 * Esta clase abstracta proporciona la funcionalidad principal para interactuar con la base de datos.
 * Está diseñada para ser extendida por modelos específicos (como User, Product, etc.).
 *
 * Características principales:
 * - Proporciona métodos CRUD básicos: all, find, create, update, delete.
 * - Utiliza un 'SchemeService' para leer la estructura de la base de datos y construir consultas dinámicamente.
 * - Realiza JOINs automáticamente para las claves foráneas (foreign keys) para enriquecer los datos devueltos.
 * - Utiliza sentencias preparadas de PDO para prevenir inyecciones SQL.
 */
abstract class Base {
    /**
     * @var PDO Conexión a la base de datos.
     */
    protected $conn;
    /**
     * @var string Nombre de la tabla de la base de datos asociada a este modelo.
     *             Debe ser definido en la clase hija.
     */
    protected $table;
    /**
     * @var SchemeService Servicio para obtener información del esquema de la base de datos.
     */
    protected $schema;

    /**
     * Constructor del modelo base.
     *
     * @param PDO $conn Una instancia de la conexión a la base de datos.
     */
    public function __construct($conn) {
        $this->conn = $conn;
        // Instancia el servicio de esquema para poder consultar la estructura de la BD.
        $this->schema = new SchemeService($conn);
    }

    /**
     * Permite a los modelos hijos añadir columnas extra en ciertas operaciones.
     * Puede ser sobrescrito.
     *
     * @return array
     */
    public function extraColumns(): array {
        return [];
    }

    /**
     * Obtiene todos los registros de la tabla.
     *
     * Este método construye dinámicamente una consulta SQL que:
     * 1. Selecciona todas las columnas de la tabla principal.
     * 2. Detecta claves foráneas y realiza LEFT JOINs para obtener datos legibles
     *    (ej. en lugar de 'rol_id' = 1, obtiene 'rol_nombre' = 'Admin').
     *
     * @return array Un array de registros.
     */
    public function all() {
        // Obtiene todas las columnas y las claves foráneas de la tabla.
        $allColumns = $this->schema->allColumns($this->table) ?? ['*'];
        $fKColumns = $this->schema->foreignKeyColumns($this->table) ?? [];

        $columns = [];
        $joins = [];
        $joinIndex = 1; // Contador para crear alias de tabla únicos en los JOINs.

        // Itera sobre cada columna para construir la consulta.
        foreach ($allColumns as $c) {
            if (isset($fKColumns[$c])) { // Si la columna es una clave foránea...
                $refTable = $fKColumns[$c]['referenced_table'];
                $refColumn = $fKColumns[$c]['referenced_column'];

                $alias = "r$joinIndex"; // Crea un alias único, ej. "r1", "r2".
                $joinIndex++;

                // Añade la columna legible de la tabla referenciada con un alias.
                // Ej: 'cliente.nombre_comercial' se convierte en 'cliente_nombre'.
                if ($c === "cliente_id") {
                    $columns[] = "$alias.nombre_comercial AS {$refTable}_nombre";
                } elseif ($c === "producto_id") {
                    $columns[] = "$alias.titulo AS {$refTable}_nombre";
                } elseif ($c === "talla_id") {
                    $columns[] = "$alias.talla AS {$refTable}_nombre";
                } else {
                    $columns[] = "$alias.nombre AS {$refTable}_nombre";
                }
                $columns[] = "t.$c"; // También incluye el ID de la clave foránea.

                // Construye la sentencia LEFT JOIN.
                $joins[]   = "LEFT JOIN $refTable $alias ON t.$c = $alias.$refColumn";
            } else {
                // Si no es una clave foránea, solo selecciona la columna.
                $columns[] = "t.$c";
            }
        }

        // Une las partes de la consulta.
        $columnsSql = implode(", ", $columns);
        $joinsSql   = implode(" ", $joins);

        // Construye la consulta final.
        $sql = "SELECT $columnsSql FROM {$this->table} t $joinsSql";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los registros que coinciden con un valor en una columna específica.
     *
     * @param string $column La columna por la que filtrar.
     * @param mixed $value El valor a buscar.
     * @return array
     */
    public function allBy($column, $value) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un único registro por su ID.
     * Similar a `all()`, pero para un solo registro y con un WHERE por ID.
     *
     * @param int $id El ID del registro a buscar.
     * @return mixed El registro como un array asociativo, o false si no se encuentra.
     */
    public function find($id) {
        // Usa 'fillableColumns' para obtener solo las columnas que se deben mostrar.
        $fillColumns = $this->schema->fillableColumns($this->table) ?? ['*'];
        $fKColumns = $this->schema->foreignKeyColumns($this->table) ?? [];

        $columns = [];
        $joins = [];
        $joinIndex = 1;

        // La lógica de construcción de JOINs es idéntica a la del método `all()`.
        foreach ($fillColumns as $c) {
            if (isset($fKColumns[$c])) {
                $refTable = $fKColumns[$c]['referenced_table'];
                $refColumn = $fKColumns[$c]['referenced_column'];
                $alias = "r$joinIndex";
                $joinIndex++;

                if ($c === "cliente_id") {
                    $columns[] = "$alias.nombre_comercial AS {$refTable}_nombre";
                } elseif ($c === "producto_id") {
                    $columns[] = "$alias.titulo AS {$refTable}_nombre";
                } elseif ($c === "talla_id") {
                    $columns[] = "$alias.talla AS {$refTable}_nombre";
                } else {
                    $columns[] = "$alias.nombre AS {$refTable}_nombre";
                }
                $columns[] = "t.$c";
                $joins[]   = "LEFT JOIN $refTable $alias ON t.$c = $alias.$refColumn";
            } else {
                $columns[] = "t.$c";
            }
        }

        $columnsSql = implode(", ", $columns);
        $joinsSql   = implode(" ", $joins);

        // Añade la cláusula WHERE para buscar por ID.
        $sql = "SELECT $columnsSql FROM {$this->table} t $joinsSql WHERE t.id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un registro basado en una condición WHERE personalizada.
     *
     * @param string $whereParam La columna para la cláusula WHERE.
     * @param mixed $id El valor a buscar.
     * @return mixed
     */
    public function findBy($whereParam, $id) {
        // Lógica de construcción de JOINs idéntica a `all()` y `find()`.
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
                if ($c === "cliente_id") {
                    $columns[] = "$alias.nombre_comercial AS {$refTable}_nombre";
                } elseif ($c === "producto_id") {
                    $columns[] = "$alias.titulo AS {$refTable}_nombre";
                } elseif ($c === "talla_id") {
                    $columns[] = "$alias.talla AS {$refTable}_nombre";
                } else {
                    $columns[] = "$alias.nombre AS {$refTable}_nombre";
                }
                $columns[] = "t.$c";
                $joins[]   = "LEFT JOIN $refTable $alias ON t.$c = $alias.$refColumn";
            } else {
                $columns[] = "t.$c";
            }
        }

        $columnsSql = implode(", ", $columns);
        $joinsSql   = implode(" ", $joins);

        // Construye la consulta con una cláusula WHERE dinámica.
        $sql = "SELECT $columnsSql FROM {$this->table} t $joinsSql WHERE t.$whereParam = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crea un nuevo registro en la base de datos.
     *
     * @param array $data Un array asociativo con los datos a insertar (columna => valor).
     * @return mixed El ID del nuevo registro si tiene éxito, o false si falla.
     */
    public function create($data) {
        // Obtiene solo las columnas que se pueden rellenar para evitar inserciones maliciosas.
        $fillColumns = $this->schema->fillableColumns($this->table) ?? ['*'];
        
        // Construye la lista de columnas y los placeholders para la sentencia preparada.
        $columns = implode(', ', $fillColumns);
        $placeholders = ':' . implode(', :', $fillColumns);
        
        // Prepara los parámetros para la ejecución, asegurando que solo se usen los datos esperados.
        $params = [];
        foreach ($fillColumns as $c) {
            $params[$c] = $data[$c] ?? null;
        }

        try {
            // Inicia una transacción para asegurar la integridad de los datos.
            $this->conn->beginTransaction();
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
            $stmt = $this->conn->prepare($sql);

            if ($stmt->execute($params)) {
                // Si la inserción es exitosa, obtiene el último ID insertado y confirma la transacción.
                $id = $this->conn->lastInsertId();
                $this->conn->commit();
                return $id;
            } else {
                // Si falla, devuelve información del error.
                return ['success' => false, 'error' => $stmt->errorInfo()];
            }
        } catch (Exception $e) {
            // Si ocurre una excepción, revierte la transacción.
            $this->conn->rollBack();
            return false;
        }
    }

    /**
     * Actualiza un registro existente.
     *
     * @param int $id El ID del registro a actualizar.
     * @param array $data Los nuevos datos (columna => valor).
     * @return array Un array indicando el éxito y los campos que no se pudieron actualizar.
     */
    public function update($id, $data) {
        // Obtiene solo las columnas que son actualizables.
        $updateColumns = $this->schema->updatableColumns($this->table);
        
        if (method_exists($this, 'extraColumns')) {
            $updateColumns = array_merge($updateColumns, $this->extraColumns());
        }

        $params = ['id' => $id];
        $setParts = [];
        $notUpdated = [];
        
        // Construye la parte SET de la consulta dinámicamente para evitar inyección SQL.
        foreach ($updateColumns as $c) {
            if (isset($data[$c])) {
                $setParts[] = "$c = :$c";
                $params[$c] = $data[$c];
            }
        }
        if (empty($setParts)) return false; // No hay nada que actualizar.

        // Registra los campos enviados que no son actualizables.
        foreach ($data as $key => $value) {
            if (!in_array($key, $updateColumns)) {
                $notUpdated[] = $key;
            }
        }

        $sql = "UPDATE {$this->table} SET " . implode(", ", $setParts) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return [
            'success' => $stmt->execute($params),
            'not_updated' => $notUpdated
        ];
    }

    /**
     * Actualiza un campo específico de un registro.
     *
     * @param int $id El ID del registro.
     * @param array $data El campo a actualizar (columna => valor).
     * @return bool
     */
    public function updateField($id, $data) {
        $params = ['id' => $id];
        $setParts = [];
        
        $cols = array_keys($data);
        foreach ($cols as $c) {
            if (isset($data[$c])) {
                $setParts[] = "$c = :$c";
                $params[$c] = $data[$c];
            }
        }
        if (empty($setParts)) return false;

        $sql = "UPDATE {$this->table} SET " . implode(", ", $setParts) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina un registro de la base de datos.
     *
     * @param int $id El ID del registro a eliminar.
     * @return bool True si tiene éxito, false si falla.
     */
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
