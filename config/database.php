<?php
/**
 * Clase Database para la Conexión a la Base de Datos
 *
 * Esta clase se encarga de gestionar la conexión con la base de datos MySQL utilizando PDO.
 * Lee las credenciales y la configuración de la base de datos desde un archivo .env
 * para mantener la seguridad y facilitar la configuración en diferentes entornos.
 */
class Database {
    // --- Propiedades de la Conexión ---
    private $host;      // Dirección del servidor de la base de datos (ej. localhost)
    private $db_name;   // Nombre de la base de datos
    private $username;  // Nombre de usuario para la conexión
    private $password;  // Contraseña del usuario
    private $port;      // Puerto de conexión a la base de datos (ej. 3306)
    public $conn;       // Propiedad pública para almacenar el objeto de conexión PDO

    /**
     * Constructor de la clase.
     *
     * Lee la configuración de la base de datos desde el archivo .env
     * y la asigna a las propiedades de la clase.
     */
    public function __construct() {
        // Carga las variables de entorno desde el archivo .env ubicado en la raíz del proyecto.
        // parse_ini_file es una forma sencilla de leer archivos de configuración estilo .ini.
        $config = parse_ini_file(__DIR__ . '/../.env');

        // Asigna los valores del archivo .env a las propiedades de la clase.
        $this->host = $config['DB_HOST'];
        $this->db_name = $config['DB_NAME'];
        $this->username = $config['DB_USER'];
        $this->password = $config['DB_PASS'];
        $this->port = $config['DB_PORT'];
    }

    /**
     * Obtiene la conexión a la base de datos.
     *
     * Establece una conexión PDO si no existe una. Si la conexión falla,
     * detiene la aplicación y muestra un mensaje de error en formato JSON.
     *
     * @return PDO|null El objeto de conexión PDO o null si falla.
     */
    public function getConnection() {
        $this->conn = null; // Asegura que no haya una conexión antigua.

        try {
            // Crea una nueva instancia de PDO para conectarse a la base de datos.
            // El DSN (Data Source Name) contiene la información necesaria para la conexión.
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );

            // Configura el modo de error de PDO a 'ERRMODE_EXCEPTION'.
            // Esto hace que PDO lance excepciones en caso de error, lo que permite
            // manejarlos de forma más clara en el bloque catch.
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch(PDOException $exception) {
            // Si la conexión falla, se captura la excepción.
            // Se devuelve una respuesta JSON con el error y se detiene la ejecución del script.
            // Es importante hacer esto para no exponer información sensible en caso de un error de producción.
            echo json_encode([
                'error' => 'Error de Conexión: ' . $exception->getMessage()
            ]);
            exit;
        }

        // Devuelve el objeto de conexión si todo fue exitoso.
        return $this->conn;
    }
}