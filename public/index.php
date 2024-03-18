<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Подключение сторонних библиотек
use Slim\Factory\AppFactory;
use Slim\Routing\RouteContext;
use DI\Container;
// use Valitron\Validator;
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
    $renderer->setLayout('layout.phtml');
    return $renderer;
});

// Парсинг данных
/* $document = new Document('https://litlife.club/', true);

$books = $document->find('.book');

foreach($books as $book) {
    echo $book->text(), "\n";
} */

ini_set("display_errors", 1);
error_reporting(E_ALL);

// Подключаем библиотеку Simple HTML DOM Parser
require '../src/simple_html_dom.php';

// URL страницы для парсинга
$url = 'https://litlife.club/popular_books/month';

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

    // Извлекаем жанры книги
    $genres = [];
    foreach($bookCard->find('div > div:nth-child(4) > a') as $genreElement) {
        $genres[] = $genreElement->plaintext;
        var_dump($genres);
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

// ДОМАШНЯЯ СТРАНИЦА
$app->get('/', function ($request, $response) {
    //    return $this->get('renderer')->render($response, 'home.phtml');
    // })->setName('home');
        return $response->write('Welcome to Slim!');
    });

$app->run();