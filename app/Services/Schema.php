<?php
// app/Services/SchemeService.php

class SchemeService
{
    private $conn;
    private $dbName;

    /* ======================  Consulta a `information_schema`  ====================== */
    private const COLUMN_QUERY = "
        SELECT 
            c.table_name,
            c.column_name,
            c.ordinal_position,
            c.column_default,
            c.is_nullable,
            c.data_type,
            c.character_maximum_length,
            c.character_set_name,
            c.column_key,
            c.extra,
            cu.position_in_unique_constraint,
            cu.referenced_table_name,
            cu.referenced_column_name
        FROM
            information_schema.columns c
            LEFT JOIN
            information_schema.key_column_usage cu
                ON c.table_schema = cu.table_schema
                AND c.table_name = cu.table_name
                AND c.column_name = cu.column_name
                AND cu.REFERENCED_TABLE_NAME IS NOT NULL
        WHERE
            c.table_schema = ?
            AND c.table_name = ?
        ORDER BY
            c.ordinal_position
    ";

    /* ======================  Cache interno  ====================== */
    private static $cache = [];   // ['usuarios' => [...meta array...]]

    /* ======================  Constructor  ====================== */
    public function __construct(PDO $pdo)
    {
        $this->conn   = $pdo;
        $config = parse_ini_file(__DIR__ . '/../../.env');
        $this->dbName = $config['DB_NAME'];
    }

    /* ======================  Cargar meta y cachear  ====================== */
    private function loadMeta($table)
    {
        if (isset(self::$cache[$table])) {
            return self::$cache[$table];  // ya está cacheado
        }

        $stmt = $this->conn->prepare(self::COLUMN_QUERY);
        $stmt->execute([$this->dbName, $table]);

        $meta = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($meta === false) {
            throw new RuntimeException("Tabla {$table} no encontrada");
        }

        self::$cache[$table] = $meta;
        return $meta;
    }

    /* ======================  Métodos públicos  ====================== */
    public function allColumns($table)
    {
        $meta = $this->loadMeta($table); // metadata de la tabla
        $data = []; // aquí guardaremos los nombres de columnas obligatorias

        foreach ($meta as $c) {
            $data[] = $c['COLUMN_NAME'];
        }

        return $data;
    }

    public function fillableColumns($table)
    {
        $meta = $this->loadMeta($table); // metadata de la tabla
        $data = []; // aquí guardaremos los nombres de columnas obligatorias

        foreach ($meta as $c) {
            // Evitamos errores si alguna clave no existe
            $columnKey    = $c['COLUMN_KEY']    ?? null;
            $columnDefault= $c['COLUMN_DEFAULT']?? null;

            // Reglas para definir si la columna es obligatoria
            if ($columnKey !== 'PRI' && empty($columnDefault)) {
                $data[] = $c['COLUMN_NAME']; // guardamos solo el nombre
            }
        }

        return $data;
    }

    public function updatableColumns($table)
    {
        $meta = $this->loadMeta($table);
        $data = [];

        foreach ($meta as $c) {
            $columnKey   = ($c['COLUMN_KEY'] ?? $c['column_key'] ?? '') ?: '';
            $extra       = ($c['EXTRA'] ?? $c['extra'] ?? '') ?: '';
            $dataType    = strtoupper($c['DATA_TYPE'] ?? $c['data_type'] ?? '');
        
            // Excluir PK, auto_increment y campos de tipo ENUM
            if (strtoupper($columnKey) !== 'PRI' && 
                stripos($extra, 'auto_increment') === false &&
                $dataType !== 'ENUM') {
                $data[] = $c['COLUMN_NAME'];
            }
        }
        
        return $data;
    }

    public function mandatoryColumns($table)
    {
        $meta = $this->loadMeta($table);
        $data = [];

        foreach ($meta as $c) {
            $columnKey = $c['COLUMN_KEY'] ?? null;
            $extra = $c['EXTRA'] ?? null;
            $columnDefault = $c['COLUMN_DEFAULT'] ?? null;
            $isNullable = $c['IS_NULLABLE'] ?? null;

            if ($columnKey !== 'PRI' && $extra !== 'auto_increment'
                && empty($columnDefault) && $isNullable === 'NO') {
                $data[] = $c['COLUMN_NAME'];
            }
        }
        
        return $data;
    }

    public function foreignKeyColumns($table)
    {
        $meta = $this->loadMeta($table);
        $data = [];

        foreach ($meta as $c) {
            $columnName    = $c['COLUMN_NAME'] ?? null;
            $refTableName  = $c['REFERENCED_TABLE_NAME'] ?? null;
            $refColumnName = $c['REFERENCED_COLUMN_NAME'] ?? 'id';

            if ($columnName && !empty($refTableName) && $refTableName !== 'reserva') {
                $data[$columnName] = [
                    'referenced_table'  => $refTableName,
                    'referenced_column' => $refColumnName
                ];
            }
        }

        return $data;
    }

    public function getSwaggerSchema($table) {
        $meta = $this->loadMeta($table);

        $properties = [];
        $required = [];

        foreach ($meta as $col) {
            $dataType   = $col['DATA_TYPE']    ?? ($col['data_type']    ?? null);
            $columnName = $col['COLUMN_NAME']  ?? ($col['column_name']  ?? null);
            $isNullable = $col['IS_NULLABLE']  ?? ($col['is_nullable']  ?? null);
            $columnKey  = $col['COLUMN_KEY']   ?? ($col['column_key']   ?? null);

            if (!$columnName) {
                // No podemos describir sin nombre de columna
                continue;
            }

            $type = match($dataType) {
                'int', 'bigint', 'smallint', 'mediumint' => 'integer',
                'decimal', 'float', 'double' => 'number',
                'tinyint' => 'boolean',
                'datetime', 'timestamp', 'date', 'time' => 'string',
                'enum' => 'string',
                default => 'string'
            };

            $example = match($type) {
                'integer' => 1,
                'number' => 100.5,
                'boolean' => true,
                default => $columnName . "-ejemplo"
            };

            $property = [
                "type" => $type,
                "example" => $example
            ];

            // Para ENUM, agregar valores permitidos
            if ($dataType === 'enum') {
                // Extraer valores del COLUMN_TYPE (ej: "enum('pendiente','procesando','completado')")
                $columnType = $col['COLUMN_TYPE'] ?? $col['column_type'] ?? '';
                if (preg_match("/enum\\(([^)]+)\\)/", $columnType, $matches)) {
                    $enumValues = array_map(function($val) {
                        return trim($val, "'\"");
                    }, explode(',', $matches[1]));
                    $property['enum'] = $enumValues;
                    $property['example'] = $enumValues[0] ?? $example;
                }
            }

            $properties[$columnName] = $property;

            if ($isNullable === 'NO' && $columnKey !== 'PRI') {
                $required[] = $columnName;
            }
        }

        return [
            "type" => "object",
            "properties" => $properties,
            "required" => $required
        ];
    }

    public function getEnumColumns($table) {
        $meta = $this->loadMeta($table);
        $enumColumns = [];

        foreach ($meta as $col) {
            $dataType = $col['DATA_TYPE'] ?? ($col['data_type'] ?? null);
            $columnName = $col['COLUMN_NAME'] ?? ($col['column_name'] ?? null);
            
            if ($dataType === 'enum' && $columnName) {
                $columnType = $col['COLUMN_TYPE'] ?? $col['column_type'] ?? '';
                if (preg_match("/enum\\(([^)]+)\\)/", $columnType, $matches)) {
                    $enumValues = array_map(function($val) {
                        return trim($val, "'\"");
                    }, explode(',', $matches[1]));
                    $enumColumns[$columnName] = $enumValues;
                }
            }
        }

        return $enumColumns;
    }

}
