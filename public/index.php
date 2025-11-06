<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use App\Controller\AboutController;
use App\Controller\CheckStatusController;
use App\Controller\EditBetaController;
use App\Controller\PricingController;
use App\Controller\ProcessingController;
use App\Controller\ViewFinalController;
use App\Controller\ViewFramesController;
use DI\ContainerBuilder;
use FastRoute\RouteCollector;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Middlewares\ErrorFormatter\HtmlFormatter;
use Middlewares\ErrorHandler;
use Middlewares\FastRoute;
use Middlewares\RequestHandler;
use Middlewares\Utils\Dispatcher;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

use function FastRoute\simpleDispatcher;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Initialize PSR-11 container
$containerBuilder = new ContainerBuilder();
$containerBuilder->useAutowiring(true);

// Initialize Twig
$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, ['debug' => true]);
$twig->addExtension(new DebugExtension());

// Set container definitions
$containerBuilder->addDefinitions([
    Environment::class => $twig,
    \App\Service\StorageService::class => \DI\autowire(),
]);

$container = $containerBuilder->build();

// Define the routes
$routes = simpleDispatcher(function (RouteCollector $r) {
    $r->get('/', EditBetaController::class);
    $r->post('/', EditBetaController::class);
    $r->get('/about', AboutController::class);
    $r->get('/pricing', PricingController::class);
    $r->get('/processing/{hash}', ProcessingController::class);
    $r->get('/check-status/{hash}', CheckStatusController::class);
    $r->get('/view-frames/{id}', ViewFramesController::class);
    $r->post('/view-frames/{id}', ViewFramesController::class);
    $r->get('/view-final/{id}', ViewFinalController::class);
});

// Build the middleware queue
$queue[] = new ErrorHandler([new HtmlFormatter()]);
$queue[] = new FastRoute($routes);
$queue[] = new RequestHandler($container);

// Handle the request
$dispatcher = new Dispatcher($queue);
$response = $dispatcher->dispatch(ServerRequestFactory::fromGlobals());

// 💨
(new SapiEmitter())->emit($response);
