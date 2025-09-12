<?php

// Define un contrato (interface) que todos los middlewares deben seguir.
// Expone un método como handle($request, $next) o algo equivalente.
// Permite que tu aplicación encadene middlewares de forma estándar.
interface MiddlewareInterface {
    public function handle(array $request): void;
}
