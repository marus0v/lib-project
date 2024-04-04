<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Подключение сторонних библиотек
use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;
use App\Connection;
use DiDom\Document;


// СТАРТ СЕССИИ
session_start();

// ПОДКЛЮЧЕНИЕ КОНТЕЙНЕРОВ
$container = new Container();

$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

// Подключение базы
$container->set('connection', function () {
    $conn = new LibProject\Connection();
    return $conn->connect();
});

AppFactory::setContainer($container);

$app = AppFactory::createFromContainer($container);

$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

function parse(string $parse_url, $container)
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
            // Записываем жанры в БД
            $newGenreQuery = 'INSERT INTO genres (name) VALUES (:genre)';
            $newGenreStmt = $container->get('connection')->prepare($newGenreQuery);
            $newGenreStmt->execute([':genre' => $genreLink->plaintext]);
        }

        // Выводим информацию о книге
        echo '<div>';
        echo '<h3>' . $title . '</h3>';
        echo '<p>Писатель: ' . $author . '</p>';
        echo '<p>Жанры: ' . implode(', ', $genres) . '</p>';
        echo '<img src="' . $imageURL . '" alt="' . $title . '">';
        echo '</div>';

        // Записываем книгу в БД
        $newBookQuery = 'INSERT INTO books (title, image) VALUES (:title, :image)';
        $newBookStmt = $container->get('connection')->prepare($newBookQuery);
        $newBookStmt->execute([':title' => $title, ':image' => $imageURL]);

        // Внесение данных в таблицу authors
        $newAuthorQuery = 'INSERT INTO authors (name) VALUES (:author)';
        $newAuthorStmt = $container->get('connection')->prepare($newAuthorQuery);
        $newAuthorStmt->execute([':author' => $author]);
        
        // Внесение данных в таблицу genres

        // Внесение данных в таблицу books

        // Внесение данных в таблицу book_author
        
        // Внесение данных в таблицу book_genre

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

// ПАРСИМ
$app->post('/parse', function ($request, $response) use ($container) {
    $validator = new LibProject\Validator();
    $flash = $this->get('flash');
    $parse_url = $request->getParsedBodyParam('parse_url');
    $errors = $validator->validate($parse_url);
    if (is_array($errors) && count($errors) === 0) {
        parse($parse_url, $container);
        return $response->withRedirect('/', 302);

    }
    $params = [
        'errors' => $errors,
        'flash' => $flash
    ];
    $this->get('renderer')
                ->render($response->withStatus(422), 'layout.phtml', $params);
    });

$app->run();
