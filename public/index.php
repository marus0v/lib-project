<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Подключение сторонних библиотек
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;
use DI\Container;
use Valitron\Validator;
use App\Connection;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use DiDom\Document;


// СТАРТ СЕССИИ
session_start();

// ПОДКЛЮЧЕНИЕ КОНТЕЙНЕРОВ
$container = new Container();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(function (Request $request, RequestHandler $handler) use ($container) {
    $routeContext = RouteContext::fromRequest($request);
    $route = $routeContext->getRoute();
    $routeName = !empty($route) ? $route->getName() : '';
    $container->set('routeName', $routeName);
    return $handler->handle($request);
});

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$container->set('router', $app->getRouteCollector()->getRouteParser());

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

// Подключение базы
$container->set('connection', function () {
    $conn = new App\Connection();
    return $conn->connect();
});

$container->set('renderer', function () use ($container) {
    $templateVars = [
        'routeName' => $container->get('routeName'),
        'router' => $container->get('router'),
        'flash' => $container->get('flash')->getMessages()
    ];
    $renderer = new \Slim\Views\PhpRenderer(__DIR__ . '/../templates', $templateVars);
    $renderer->setLayout('index.phtml');
    return $renderer;
});

function parse(string $parse_url)
{
    ini_set("display_errors", 1);
    error_reporting(E_ALL);

    // Подключаем библиотеку Simple HTML DOM Parser
    require '../src/simple_html_dom.php';

    // URL страницы для парсинга
    $url = $parse_url;

    // Получаем содержимое страницы
    $html = file_get_html($url);

    // Находим все карточки книг на странице
    foreach($html->find('div.book.card') as $bookCard) {
        // Извлекаем название книги
        $title = $bookCard->find('h3 a', 0)->plaintext;

        // Извлекаем URL обложки книги
        $imageURL = $bookCard->find('img', 0)->getAttribute('data-src');

        // Извлекаем писателя книги
        $author = $bookCard->find('a.author.name', 0)->plaintext;

        // Находим ссылки на жанры книги
        $genreLinks = $bookCard->find('a[href^="https://litlife.club/genres/"]');

        // Извлекаем текст жанров
        $genres = [];
        foreach ($genreLinks as $genreLink) {
            $genres[] = $genreLink->plaintext;
        }

    // Выводим информацию о книге
    echo '<div>';
    echo '<h3>' . $title . '</h3>';
    echo '<p>Писатель: ' . $author . '</p>';
    echo '<p>Жанры: ' . implode(', ', $genres) . '</p>';
    echo '<img src="' . $imageURL . '" alt="' . $title . '">';
    echo '</div>';


    }

    // Освобождаем ресурсы
    $html->clear();
    unset($html);
    return true;
}

// ДОМАШНЯЯ СТРАНИЦА
$app->get('/', function ($request, $response) {
        return $this->get('renderer')->render($response, 'index.phtml');
    })->setName('home');
    //    return $response->write('Welcome to Slim!');
    // });

// ПАРСИМ
$app->get('/parse', function ($request, $response) {
    $validator = new Validator();
    $parse_url = $request->getParsedBodyParam('parse_url');
    $errors = $validator->validate($parse_url);
    if (count($errors) === 0) {
        parse($parse_url);
        return $response->withRedirect('/', 302)
                        ->get('flash')->addMessage('success', 'URL has been parsed successfully');
    }
    $params = [
        'errors' => $errors,
        'flash' => $flash
    ];
    $this->get('renderer')
                ->render($response->withStatus(422), 'index.phtml', $params);
    });

$app->run();
