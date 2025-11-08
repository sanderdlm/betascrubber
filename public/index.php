<?php

declare(strict_types=1);

use App\Service\LocalVideoStorageManager;
use App\Service\S3VideoStorageManager;
use App\Service\StorageService;
use App\Service\VideoStorageManagerInterface;
use Dotenv\Dotenv;
use App\Controller\AboutController;
use App\Controller\EditBetaController;
use App\Controller\EventStreamController;
use App\Controller\PricingController;
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
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

use function FastRoute\simpleDispatcher;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Initialize PSR-11 container
$containerBuilder = new ContainerBuilder();
$containerBuilder->useAutowiring(true);

// Initialize Twig
$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, ['debug' => true]);
$twig->addExtension(new DebugExtension());

// Determine environment
$appEnv = $_ENV['APP_ENV'] ?? 'production';
$tmpDir = $_ENV['TMP_DIR'] ?? __DIR__ . '/tmp';

// Initialize Logger
$logger = new Logger('app');
if (strtolower($appEnv) === 'dev') {
    $logger->pushHandler(new RotatingFileHandler(__DIR__ . '/../logs/app.log'));
} else {
    $logger->pushHandler(new StreamHandler('php://stdout'));
}

// Set container definitions
$containerBuilder->addDefinitions([
    Environment::class => $twig,
    LoggerInterface::class => $logger,
    'tmpDir' => $tmpDir,
    VideoStorageManagerInterface::class => function ($container) use ($appEnv, $tmpDir) {
        if (strtolower($appEnv) === 'dev') {
            return new LocalVideoStorageManager($tmpDir);
        }

        return new S3VideoStorageManager(
            $container->get(StorageService::class),
            $tmpDir
        );
    },
]);

$container = $containerBuilder->build();

// Define the routes
$routes = simpleDispatcher(function (RouteCollector $r) {
    $r->get('/', EditBetaController::class);
    $r->post('/', EditBetaController::class);
    $r->get('/about', AboutController::class);
    $r->get('/pricing', PricingController::class);
    $r->get('/events', EventStreamController::class);
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
