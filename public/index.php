<?php
declare(strict_types=1);

use App\Core\Env;
use App\Core\Router;

define('BASE_PATH', dirname(__DIR__));

// Autoload: App\Core\Router -> app/Core/Router.php
spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Env::load(BASE_PATH . '/.env');

if (Env::get('APP_ENV') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

$router = new Router();

// Every file in config/routes/ registers its own routes (one file per person)
foreach (glob(BASE_PATH . '/config/routes/*.php') as $file) {
    (require $file)($router);
}

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
