<?php
/**
 * Router Class
 * 
 * @version 4.0
 */

defined('API_ACCESS') or die('Direct access not permitted');

class Router {
    private $routes = [];
    
    /**
     * Registrar ruta GET
     */
    public function get($path, $callback) {
        $this->addRoute('GET', $path, $callback);
    }
    
    /**
     * Registrar ruta POST
     */
    public function post($path, $callback) {
        $this->addRoute('POST', $path, $callback);
    }
    
    /**
     * Registrar ruta PUT
     */
    public function put($path, $callback) {
        $this->addRoute('PUT', $path, $callback);
    }
    
    /**
     * Registrar ruta DELETE
     */
    public function delete($path, $callback) {
        $this->addRoute('DELETE', $callback);
    }
    
    /**
     * Añadir ruta al registro
     */
    private function addRoute($method, $path, $callback) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'callback' => $callback
        ];
    }
    
    /**
     * Ejecutar router
     */
    public function run($requestPath) {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Normalizar path
        $requestPath = trim($requestPath, '/');
        
        Logger::api('debug', 'Router matching', [
            'method' => $method,
            'path' => $requestPath,
            'routes_count' => count($this->routes)
        ]);
        
        // Buscar ruta coincidente
        foreach ($this->routes as $route) {
            if ($this->matchRoute($route, $method, $requestPath)) {
                Logger::api('debug', 'Route matched', [
                    'route' => $route['path'],
                    'method' => $route['method']
                ]);
                
                call_user_func($route['callback']);
                return;
            }
        }
        
        // No se encontró ruta
        Logger::api('error', 'Route not found', [
            'method' => $method,
            'path' => $requestPath
        ]);
        
        Response::error('Endpoint not found: ' . $requestPath, 404);
    }
    
    /**
     * Comprobar si ruta coincide
     */
    private function matchRoute($route, $method, $path) {
        if ($route['method'] !== $method) {
            return false;
        }
        
        $routePath = trim($route['path'], '/');
        
        // Coincidencia exacta
        if ($routePath === $path) {
            return true;
        }
        
        // Coincidencia con parámetros
        $routeParts = explode('/', $routePath);
        $pathParts = explode('/', $path);
        
        if (count($routeParts) !== count($pathParts)) {
            return false;
        }
        
        for ($i = 0; $i < count($routeParts); $i++) {
            // Parámetro dinámico
            if (strpos($routeParts[$i], '{') === 0) {
                continue;
            }
            
            // Parte fija debe coincidir
            if ($routeParts[$i] !== $pathParts[$i]) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Extraer parámetros de la URL
     */
    public static function getParams($routePath, $requestPath) {
        $params = [];
        $routeParts = explode('/', trim($routePath, '/'));
        $pathParts = explode('/', trim($requestPath, '/'));
        
        for ($i = 0; $i < count($routeParts); $i++) {
            if (strpos($routeParts[$i], '{') === 0) {
                $paramName = trim($routeParts[$i], '{}');
                $params[$paramName] = $pathParts[$i];
            }
        }
        
        return $params;
    }
}
