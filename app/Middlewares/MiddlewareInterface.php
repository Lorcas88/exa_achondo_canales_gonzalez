<?php
/**
 * Interfaz para Middlewares
 *
 * Una interfaz en PHP define un "contrato" que una clase debe seguir.
 * Cualquier clase que `implemente` esta interfaz está obligada a tener
 * un método `handle` público con la misma firma (parámetros y tipo de retorno).
 *
 * Esto asegura que todos los middlewares en la aplicación tengan una estructura
 * consistente, lo que permite al enrutador tratarlos de la misma manera y
 * ejecutarlos en una cadena (pipeline).
 */
interface MiddlewareInterface {
    /**
     * Maneja la lógica principal del middleware.
     *
     * @param array $request Un array que puede contener datos de la petición.
     * @return void El método no debe retornar nada. Si un middleware necesita
     *              detener la ejecución (por un error de autenticación, por ejemplo),
     *              debe hacerlo llamando a una función de respuesta como `jsonResponse`
     *              que termina el script.
     */
    public function handle(array $request): void;
}