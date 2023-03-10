<?php

class Router {
    
    protected $routes = [];

    public function add($uri, $controller, $requestMethod)
    {
        // Check if the URI is valid
        if (empty($uri) || substr($uri, 0, 1) !== '/') {
            throw new Exception("Invalid route URI: '$uri'.");
        }
        
        // Remove trailing slash from the URI
        $uri = rtrim($uri, '/');
        
        // Check if the controller and method names are valid
        if (strpos($controller, '@') === false) {
            throw new Exception("Invalid controller and method name: '$controller'.");
        }
        
        // Split the controller and method names
        list($controllerName, $methodName) = explode('@', $controller);

        // Check if the method name is valid
        if (empty($methodName)) {
            throw new Exception("Method name not provided for controller: '$controllerName'.");
        }
        
        // Check if a route with the same URI and request method already exists
        if (isset($this->routes[$uri]['methods'][$requestMethod])) {
            throw new Exception("Route with URI '$uri' and request method '$requestMethod' already exists.");
        }
        
        $newSegments = explode('/', $uri);

        
        // Check if the endpoint matches any existing routes
        foreach ($this->routes as $existingUri => $existingRoute) {
            if ($existingUri !== $uri && count($existingRoute['params']) === count($newSegments)) {
                $existingSegments = explode('/', $existingUri);
                
                $same = true;
                $params = array();
                for ($i = 0; $i < count($existingSegments); $i++) {
                    if ($existingSegments[$i] !== $newSegments[$i] && substr($existingSegments[$i], 0, 1) !== ':') {
                        $same = false;
                        break;
                    } elseif (substr($existingSegments[$i], 0, 1) === ':') {
                        $params[] = substr($existingSegments[$i], 1);
                    }
                }
                
                if ($same && !empty($params)) {
                    throw new Exception("Route with URI '$uri' conflicts with existing route '$existingUri'.");
                }
            }
        }
        
        // Check if the controller file exists
        $controllerDir = '../private/controllers/';
        $controllerPath = $controllerDir . $controllerName . '.php';
        
        if (!file_exists($controllerPath)) {
            throw new Exception("Controller file '$controllerName.php' not found.");
        }

        // Check if the method exists in the controller file
        $controllerContents = file_get_contents($controllerPath);

        if (strpos($controllerContents, "function $methodName") === false) {
            throw new Exception("Method '$methodName' does not exist in controller '$controllerName'.");
        }

        // Check if the request method is valid
        if (!in_array($requestMethod, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
            throw new Exception("Invalid request method: '$requestMethod'.");
        }
        
        // Extract parameters from the URI
        $params = array_filter(explode('/', $uri), function($segment) {
            return substr($segment, 0, 1) == ':';
        });
        
        // Add the new route
        $this->routes[$uri]['params'] = $params;
        $this->routes[$uri]['methods'][$requestMethod]['controller'] = $controller;
    }


    public function dispatch($url, $dbConnection)
    {
        $url = implode('/', $url);

        // Loop through the routes to find a match
        foreach ($this->routes as $route => $config) {
            // Replace parameter values in URL with parameter names
            $pattern = preg_replace('/:[^\/]+/', '([^\/]+)', $route);

            // Check if URL matches route pattern
            if (preg_match('#^' . $pattern . '$#', $url, $matches)) {

                // Extract parameter names and values
                $routeParams = array_combine($config['params'], array_slice($matches, 1));

                // Replace parameter values in URL with parameter names
                $routeUrl = str_replace(array_values($routeParams), array_keys($routeParams), $route);

                // Check if request method is allowed for this route
                if (!isset($config['methods'][$_SERVER['REQUEST_METHOD']])) {
                    $this->handleError("Route with URI '$url' and request method '{$_SERVER['REQUEST_METHOD']}' not found.");
                    return;
                }

                // Extract controller and method from route
                list($controller, $method) = explode('@', $config['methods'][$_SERVER['REQUEST_METHOD']]['controller']);
                
                // Include the controller class
                require_once $GLOBALS['config']['private_folder'] . "/controllers/{$controller}.php";

                // Check if controller and method exist
                if (!class_exists($controller) || !method_exists($controller, $method)) {
                    $this->handleError("Controller or method not found for route with URI '$url'.");
                    return;
                }

                // Combine parameter names and values into associative array, ignoring optional parameters with no value
                $params = array_intersect_key($routeParams, array_flip(array_filter($config['params'], function($param) use ($routeParams) {
                    return substr($param, -1) !== '?' || array_key_exists(rtrim($param, '?'), $routeParams);
                })));
                

                // Validate the parameters
                $validatedParams = $this->validateParams($controller, $method, $params);
                if ($validatedParams === false) {
                    $this->handleError("Invalid parameters for route with URI '$url'.");
                    return;
                }

                // Call the controller method with the parameters
                $controllerInstance = new $controller($dbConnection);
                $controllerInstance->{$method}(...$validatedParams);
                return;
            }
        }
        
        // If no route is found, handle the error
        $this->handleError("Route with URI '$url' not found.");
    }

    // Handle errors in development mode by displaying a message and error code
    private function handleError($message) {
        http_response_code(ERROR_NOT_FOUND);
        if ($GLOBALS['config']['devmode']) {
            echo "Error: $message";
        } else {
            echo "Error: Route not found.";
        }
    }

    private function validateParams($controller, $method, $params) {
        $methodParams = new ReflectionMethod($controller, $method);
        $paramTypes = array_map(function($param) {
            return [
                'param' => $param,
                'type' => $param->getType(),
            ];
        }, $methodParams->getParameters());
    
        // Validate the number and types of parameters
        if (count($params) != count($paramTypes)) {
            return false;
        }
    
        $validatedParams = array();
    
        
        foreach ($paramTypes as $param) {
            $paramName = ':'.$param['param']->getName();
            $paramType = $param['type'] ? $param['type']->getName() : 'string';

            //checks to see if the parameter within the function matches the one in route
            if (!array_key_exists($paramName, $params)) {
                return false;
            }

            $value = $params[$paramName];
            
            echo '<pre>' , $paramType , '</pre>';
    
            switch ($paramType) {
                case 'int':
                    $validatedValue = filter_var($value, FILTER_VALIDATE_INT);
                    break;
                case 'float':
                    $validatedValue = filter_var($value, FILTER_VALIDATE_FLOAT);
                    break;
                case 'bool':
                    $validatedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                default:
                    $validatedValue = $value;
            }
    
            if ($validatedValue === false) {
                return false;
            }
    
            $validatedParams[] = $validatedValue;
        }
    
        return $validatedParams;
    }
    

}


