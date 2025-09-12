<?php

abstract class Base {
    protected $conn;
    protected $table;
    protected $schema;

    public function __construct($conn) {
        $this->conn = $conn;
        // Cargar el esquema para obtener los campos requeridos
        $this->schema = new SchemeService($conn);
    }

    // Este método será sobrescrito en los hijos si es necesario
    public function extraColumns(): array {
        return [];
    }

    // Obtener todos los registros
    public function all() {
        $allColumns = $this->schema->allColumns($this->table) ?? ['*'];
        $fKColumns = $this->schema->foreignKeyColumns($this->table) ?? [];

        $columns = [];
        $joins = [];

        $joinIndex = 1; // contador de alias dinámicos

        foreach ($allColumns as $c) {
            if (isset($fKColumns[$c])) {
                $refTable = $fKColumns[$c]['referenced_table'];
                $refColumn = $fKColumns[$c]['referenced_column'];

                // alias dinámico
                $alias = "r$joinIndex";
                $joinIndex++;

                // Mostrar el campo legible con alias único
                if ($c === "cliente_id") {
                    $columns[] = "$alias.nombre_comercial AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                } elseif ($c === "producto_id") {
                    $columns[] = "$alias.titulo AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                } elseif ($c === "talla_id") {
                    $columns[] = "$alias.talla AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                } else {
                    $columns[] = "$alias.nombre AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                }

                // JOIN con alias distinto
                $joins[]   = "LEFT JOIN $refTable $alias ON t.$c = $alias.$refColumn";
            } else {
                $columns[] = "t.$c";
            }
        }

        $columnsSql = implode(", ", $columns);
        $joinsSql   = implode(" ", $joins);

        $sql = "SELECT $columnsSql
                FROM {$this->table} t
                $joinsSql";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener todos los registros
    public function allBy($column, $value) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener un registro por ID, con campos opcionales
    public function find($id) {
        $fillColumns = $this->schema->fillableColumns($this->table) ?? ['*'];
        $fKColumns = $this->schema->foreignKeyColumns($this->table) ?? [];

        $columns = [];
        $joins = [];

        $joinIndex = 1; // contador de alias dinámicos

        foreach ($fillColumns as $c) {
            if (isset($fKColumns[$c])) {
                $refTable = $fKColumns[$c]['referenced_table'];
                $refColumn = $fKColumns[$c]['referenced_column'];

                // alias dinámico
                $alias = "r$joinIndex";
                $joinIndex++;

                // Mostrar el campo legible con alias único
                if ($c === "cliente_id") {
                    $columns[] = "$alias.nombre_comercial AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                } elseif ($c === "producto_id") {
                    $columns[] = "$alias.titulo AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                } elseif ($c === "talla_id") {
                    $columns[] = "$alias.talla AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                } else {
                    $columns[] = "$alias.nombre AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                }

                // JOIN con alias distinto
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
                WHERE t.id = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener un registro por ID, con campos opcionales
    public function findBy($whereParam, $id) {
        $allColumns = $this->schema->allColumns($this->table) ?? ['*'];
        $fKColumns = $this->schema->foreignKeyColumns($this->table) ?? [];
        
        $columns = [];
        $joins = [];

        $joinIndex = 1; // contador de alias dinámicos

        foreach ($allColumns as $c) {
            if (isset($fKColumns[$c])) {
                $refTable = $fKColumns[$c]['referenced_table'];
                $refColumn = $fKColumns[$c]['referenced_column'];

                // alias dinámico
                $alias = "r$joinIndex";
                $joinIndex++;

                // Mostrar el campo legible con alias único
                if ($c === "cliente_id") {
                    $columns[] = "$alias.nombre_comercial AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                } elseif ($c === "producto_id") {
                    $columns[] = "$alias.titulo AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                } elseif ($c === "talla_id") {
                    $columns[] = "$alias.talla AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                } else {
                    $columns[] = "$alias.nombre AS {$refTable}_nombre";
                    $columns[] = "t.$c";
                }

                // JOIN con alias distinto
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
                WHERE t.$whereParam = ?";

        error_log($sql);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear nuevo usuario
    public function create($data) {
        $fillColumns = $this->schema->fillableColumns($this->table) ?? ['*'];

        // Construir la lista de columnas y placeholders
        $columns = implode(', ', $fillColumns);
        $placeholders = ':' . implode(', :', $fillColumns);
        
        // Crear el array asociativo para execute() y asi evitar errores de campos faltantes y ataques SQLinjection
        $params = [];
        foreach ($fillColumns as $c) {
            $params[$c] = $data[$c] ?? null;
        }

        // Preparar y ejecutar la consulta
        try {
            $this->conn->beginTransaction();
            $sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders)";
            $stmt = $this->conn->prepare($sql);

            // Retornar resultado
            if ($stmt->execute($params)) {
                $id = $this->conn->lastInsertId();
                $this->conn->commit();
                return $id;
            } else {
                return [
                    'success' => false,
                    'error' => $stmt->errorInfo()
                ];
            }
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    // Actualizar usuario
    public function update($id, $data) {
        $updateColumns = $this->schema->updatableColumns($this->table);
        
        // Si el hijo define extraUpdateColumns, se añaden
        if (method_exists($this, 'extraColumns')) {
            $updateColumns = array_merge($updateColumns, $this->extraColumns());
        }

        // error_log(print_r($updateColumns, true));
        
        $params = ['id' => $id];
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

        $sql = "UPDATE $this->table SET ".implode(", ", $setParts)." WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return [
            'success' => $stmt->execute($params),
            'not_updated' => $notUpdated
        ];
    }

    // Actualizar usuario
    public function updateField($id, $data) {
        $params = ['id' => $id];
        $setParts = [];

        // error_log($id);
        // error_log(print_r($data, true));
        // error_log(print_r(array_keys($data), true));
        
        $cols = array_keys($data);
        foreach ($cols as $c) {
            if (isset($data[$c])) {
                $setParts[] = "$c = :$c";
                $params[$c] = $data[$c];
            }
        }
        if (empty($setParts)) return false;

        $sql = "UPDATE $this->table SET ".implode(", ", $setParts)." WHERE id = :id";
        // error_log(print_r($params, true));
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    // Eliminar un registro
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}


// public function find($id, $fields = null) {
//     $fields = $this->requiredFields ?? ['*'];

//     $fields = $this->requiredFields ?? null;
//     if ($fields === null) {
//         $fields = ['*']; // Selecciona todos los campos si no se especifican
//     }
//     $columns = implode(', ', $fields);
    
//     $stmt = $this->conn->prepare("SELECT $columns FROM $this->table WHERE id = ?");
//     $stmt->execute([$id]);
//     return $stmt->fetch(PDO::FETCH_ASSOC);
// }