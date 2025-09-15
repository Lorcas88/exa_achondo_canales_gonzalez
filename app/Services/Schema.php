<?php
/**
 * Servicio para introspección de esquemas de bases de datos.
 *
 * Esta clase provee métodos para obtener metadatos de tablas y columnas,
 * como por ejemplo, qué columnas son rellenables, actualizables, u obligatorias.
 * Utiliza la `information_schema` de MySQL y cachea los resultados para mejorar el rendimiento.
 */
class SchemeService
{
    /**
     * @var PDO Conexión a la base de datos.
     */
    private $conn;

    /**
     * @var string Nombre de la base de datos.
     */
    private $dbName;

    /**
     * @var string Consulta SQL para obtener metadatos de las columnas desde `information_schema`.
     */
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
            CASE 
                WHEN c.column_key = 'PRI' THEN 
                    CASE 
                        WHEN pk.pk_column_count = 1 THEN 'Simple PK'
                        ELSE 'Composite PK'
                    END
                ELSE NULL
            END AS pk_type,
            c.extra,
            cu.position_in_unique_constraint,
            cu.referenced_table_name,
            cu.referenced_column_name
        FROM
            information_schema.columns c
            LEFT JOIN information_schema.key_column_usage cu
                ON c.table_schema = cu.table_schema
                AND c.table_name = cu.table_name
                AND c.column_name = cu.column_name
                AND cu.REFERENCED_TABLE_NAME IS NOT NULL
            LEFT JOIN (
                SELECT table_schema, table_name, COUNT(*) AS pk_column_count
                FROM information_schema.columns
                WHERE column_key = 'PRI'
                GROUP BY table_schema, table_name
            ) AS pk
                ON c.table_schema = pk.table_schema
                AND c.table_name = pk.table_name
        WHERE
            c.table_schema = ?
            AND c.table_name = ?
        ORDER BY
            c.ordinal_position
    ";

    /**
     * @var array Cache para almacenar los metadatos de las tablas y evitar consultas repetidas.
     */
    private static $cache = [];

    /**
     * Constructor de SchemeService.
     *
     * @param PDO $pdo La conexión a la base de datos.
     */
    public function __construct(PDO $pdo)
    {
        $this->conn   = $pdo;
        $config = parse_ini_file(__DIR__ . '/../../.env');
        $this->dbName = $config['DB_NAME'];
    }

    /**
     * Carga los metadatos de una tabla.
     *
     * Si los metadatos ya están en caché, los devuelve. Si no, realiza la consulta
     * a la base de datos y guarda el resultado en la caché antes de devolverlo.
     *
     * @param string $table El nombre de la tabla.
     * @return array Los metadatos de la tabla.
     * @throws RuntimeException Si la tabla no se encuentra.
     */
    private function loadMeta($table)
    {
        if (isset(self::$cache[$table])) {
            return self::$cache[$table];
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

    /**
     * Obtiene todas las columnas de una tabla.
     *
     * @param string $table El nombre de la tabla.
     * @return array Una lista con los nombres de todas las columnas.
     */
    public function allColumns($table)
    {
        $meta = $this->loadMeta($table);
        $data = [];

        foreach ($meta as $c) {
            $data[] = $c['column_name'];
        }

        return $data;
    }

    /**
     * Determina qué columnas de una tabla son "rellenables".
     *
     * Una columna es rellenable si no es una clave primaria simple o si es parte de una clave primaria compuesta.
     *
     * @param string $table El nombre de la tabla.
     * @return array Una lista de nombres de columnas rellenables.
     */
    public function fillableColumns($table)
    {
        $meta = $this->loadMeta($table);
        $data = [];
        
        foreach ($meta as $c) {
            $columnKey    = $c['column_key'];
            $columnDefault= $c['column_default'];
            $pKType       = $c['pk_type'];

            // Las columnas son rellenables si no son PK, o siéndolo, son parte de una PK compuesta.
            if (($columnKey !== 'PRI' && 
                (empty($columnDefault) || $columnDefault === "NULL")) || 
                $pKType === 'Composite PK') {
                
                $data[] = $c['column_name'];
            }
        }

        return $data;
    }

    /**
     * Determina qué columnas son actualizables.
     *
     * Excluye claves primarias, columnas autoincrementables, timestamps automáticos, ENUMs y el campo 'rut'.
     *
     * @param string $table El nombre de la tabla.
     * @return array Una lista de nombres de columnas actualizables.
     */
    public function updatableColumns($table)
    {
        $meta = $this->loadMeta($table);
        $data = [];

        foreach ($meta as $c) {
            $columnKey   = $c['column_key'];
            $columnDefault= $c['column_default'];
            $extra       = $c['extra'];
            $dataType    = $c['data_type'];

            if (strtoupper($columnKey) !== 'PRI' && 
                stripos($extra, 'auto_increment') === false &&
                stripos($columnDefault, 'current_timestamp()') === false &&
                $dataType !== 'ENUM' &&
                stripos($c['column_name'], 'rut') === false) {
                $data[] = $c['column_name'];
            }
        }
        
        return $data;
    }

    /**
     * Obtiene las columnas que son obligatorias.
     *
     * Una columna es obligatoria si no es clave primaria, no es autoincremental,
     * no tiene un valor por defecto y no permite valores nulos.
     *
     * @param string $table El nombre de la tabla.
     * @return array Una lista de nombres de columnas obligatorias.
     */
    public function mandatoryColumns($table)
    {
        $meta = $this->loadMeta($table);
        $data = [];

        foreach ($meta as $c) {
            $columnKey = $c['column_key'];
            $extra = $c['extra'];
            $columnDefault = $c['column_default'];
            $isNullable = $c['is_nullable'];

            if ($columnKey !== 'PRI' && $extra !== 'auto_increment'
                && (empty($columnDefault) || $columnDefault === "NULL") 
                && $isNullable === 'NO') {
                $data[] = $c['column_name'];
            }
        }
        
        return $data;
    }

    /**
     * Obtiene las columnas que son claves foráneas.
     *
     * @param string $table El nombre de la tabla.
     * @return array Un array asociativo donde las claves son los nombres de las columnas FK
     *               y los valores son información sobre la tabla y columna referenciada.
     */
    public function foreignKeyColumns($table)
    {
        $meta = $this->loadMeta($table);
        $data = [];

        foreach ($meta as $c) {
            $columnName    = $c['column_name'];
            $refTableName  = $c['referenced_table_name'];
            $refColumnName = $c['referenced_column_name'];

            if ($columnName && !empty($refTableName) && $refTableName !== 'reserva') {
                $data[$columnName] = [
                    'referenced_table'  => $refTableName,
                    'referenced_column' => $refColumnName
                ];
            }
        }

        return $data;
    }

    /**
     * Genera un esquema de la tabla compatible con la especificación Swagger/OpenAPI.
     *
     * @param string $table El nombre de la tabla.
     * @return array Un array que representa el esquema del objeto para Swagger.
     */
    public function getSwaggerSchema($table) {
        $meta = $this->loadMeta($table);

        $properties = [];
        $required = [];

        foreach ($meta as $col) {
            $dataType   = $col['data_type'];
            $columnName = $col['column_name'];
            $isNullable = $col['is_nullable'];
            $columnKey  = $col['column_key'];

            if (!$columnName) {
                continue;
            }

            // Mapea tipos de datos de la DB a tipos de Swagger.
            $type = match($dataType) {
                'int', 'bigint', 'smallint', 'mediumint' => 'integer',
                'decimal', 'float', 'double' => 'number',
                'tinyint' => 'boolean',
                'datetime', 'timestamp', 'date', 'time' => 'string',
                'enum' => 'string',
                default => 'string'
            };

            // Genera un ejemplo basado en el tipo de dato.
            $example = match(true) {
                $type === 'integer' => 1,
                $type === 'number' => 12.5,
                $type === 'boolean' => true,
                $dataType === 'date' => '2024-01-01',
                $dataType === 'datetime' => '2024-01-01 12:00:00',
                $dataType === 'timestamp' => '2024-01-01 12:00:00',
                default => $columnName . "-ejemplo"
            };

            $property = [
                "type" => $type,
                "example" => $example
            ];

            // Si es un ENUM, extrae y añade los posibles valores al esquema.
            if ($dataType === 'enum') {
                $columnType = $col['COLUMN_TYPE'] ?? $col['column_type'] ?? '';
                if (preg_match("/enum\(([^)]+)\)/", $columnType, $matches)) {
                    $enumValues = array_map(function($val) {
                        return trim($val, "'\"");
                    }, explode(',', $matches[1]));
                    $property['enum'] = $enumValues;
                    $property['example'] = $enumValues[0] ?? $example;
                }
            }

            $properties[$columnName] = $property;

            // Si la columna no es nullable y no es PK, se añade a la lista de requeridos.
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

    /**
     * Obtiene las columnas de tipo ENUM y sus posibles valores.
     *
     * @param string $table El nombre de la tabla.
     * @return array Un array asociativo con los nombres de las columnas ENUM y sus valores.
     */
    public function getEnumColumns($table) {
        $meta = $this->loadMeta($table);
        $enumColumns = [];

        foreach ($meta as $col) {
            $dataType = $col['DATA_TYPE'] ?? ($col['data_type'] ?? null);
            $columnName = $col['COLUMN_NAME'] ?? ($col['column_name'] ?? null);
            
            if ($dataType === 'enum' && $columnName) {
                $columnType = $col['COLUMN_TYPE'] ?? $col['column_type'] ?? '';
                if (preg_match("/enum\(([^)]+)\)/", $columnType, $matches)) {
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