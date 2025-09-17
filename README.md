# TodoCamisetas API Server

## Descripción

Este proyecto corresponde al backend de la aplicación "TodoCamisetas". Provee una API RESTful para gestionar los recursos de la aplicación, tales como productos, clientes, usuarios, y stock.

## Requisitos Previos

Para poder ejecutar este proyecto, es necesario tener instalado el siguiente software:

- **XAMPP**: Se recomienda la versión que incluya PHP 8.0 o superior, Apache y MySQL.

## Instalación

Siga los siguientes pasos para la instalación y configuración del entorno de desarrollo:

1.  **Clonar el Repositorio**: Clone este repositorio dentro del directorio `htdocs` de su instalación de XAMPP. Es crucial que la carpeta del proyecto se llame `todocamisetas_server`.

    ```bash
    cd c:\xampp\htdocs\
    git clone <URL_DEL_REPOSITORIO> todocamisetas_server
    ```

    Si descarga el proyecto manualmente, asegúrese de que la ruta final sea `c:\xampp\htdocs\todocamisetas_server`.

2.  **Configurar Variables de Entorno**: El proyecto requiere un archivo `.env` en la raíz para la configuración de la base de datos. Aunque el sistema puede funcionar sin él (usando configuraciones por defecto), se recomienda crearlo para personalizar la conexión.

    Cree un archivo llamado `.env` en la raíz del proyecto y agregue las siguientes variables:

    ```ini
    # Configuración de la Base de Datos
    DB_HOST=localhost
    DB_USER=tu_usuario_mysql
    DB_PASS=tu_contraseña_mysql
    DB_NAME=todo_camisetas_db
    DB_PORT=3306
    ```

    **Nota**: El archivo `.htaccess` principal está configurado para denegar el acceso a los archivos `.env`, protegiendo sus credenciales.

## Configuración de la Base de Datos

1.  **Crear la Base de Datos**: Utilice su cliente de base de datos para crear una nueva base de datos. El nombre por defecto es `todo_camisetas_db`, pero puede ser cualquier otro si lo especifica en su archivo `.env`.

2.  **Importar Datos de Prueba**: Importe el archivo `mock_database.sql` en la base de datos recién creada. Este archivo contiene la estructura de las tablas y datos de ejemplo para empezar a operar con la API.

## Documentación de la API

La API está documentada utilizando Swagger UI. Para acceder a la documentación interactiva:

1.  Asegúrese de que su servidor Apache de XAMPP esté en ejecución.
2.  Abra su navegador y diríjase a:
    [http://localhost/todocamisetas_server/public/swagger-ui/](http://localhost/todocamisetas_server/public/swagger-ui/)

Desde esta interfaz, podrá visualizar todos los endpoints disponibles, sus parámetros, y probarlos directamente.

## Ejecución

Una vez que XAMPP (Apache y MySQL) esté en funcionamiento y la base de datos haya sido configurada, la API estará activa y lista para recibir peticiones en `http://localhost/todocamisetas_server/`.
