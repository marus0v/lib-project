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

// функция записи в таблицу с проверкой наличия
function setID($value, $entName, $tableName, $container) : int
{
    $id = 0;
    $setIdQuery = 'INSERT INTO ' . $tableName . '(' . $entName . ') VALUES (:value)';
    $setIdStmt = $container->get('connection')->prepare($setIdQuery);
    $setIdStmt->execute([':value' => $value]);
    $id = $setIdStmt->fetch();
    $getIdQuery = 'SELECT id FROM ' . $tableName . ' WHERE ' . $entName . ' = :value';
    $getIdStmt = $container->get('connection')->prepare($getIdQuery);
    $getIdStmt->execute([':value' => $value]);
    $id = $getIdStmt->fetchColumn();
    return $id;
}

// функция чтени из таблицы с проверкой наличия
function getID($value, $entName, $tableName, $container)
{
    $id = 0;
    $getIdQuery = 'SELECT id FROM ' . $tableName . ' WHERE ' . $entName . ' = :value';
    $getIdStmt = $container->get('connection')->prepare($getIdQuery);
    $getIdStmt->execute([':value' => $value]);
    $id = $getIdStmt->fetchColumn();
    if ($id == 0) {
        return setID($value, $entName, $tableName, $container);
    }
    return $id;
}

function parse(string $parse_url, $container)
{
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
    // Очищаем базу перед парсингом
    $clearDBQuery = 'DELETE FROM book_author;
        DELETE FROM book_genre;
        DELETE FROM authors;
        DELETE FROM genres;
        DELETE FROM books';
    $clearDBStmt = $container->get('connection')->prepare($clearDBQuery);
    $clearDBStmt->execute();
    $clearDBStmt->closeCursor();

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
        
        // Записываем книгу в БД
        // Внесение данных в таблицу books
        $newBookQuery = 'INSERT INTO books (title, image) VALUES (:title, :image)';
        $newBookStmt = $container->get('connection')->prepare($newBookQuery);
        $newBookStmt->execute([':title' => $title, ':image' => $imageURL]);
        $bookID = getID($title, 'title', 'books', $container);

        // Извлекаем писателя книги
        $author = $bookCard->find('a.author.name', 0)->plaintext;
        // Записываем книгу в БД
        // Внесение данных в таблицу authors
        // проверяем, есть ли такой автор в базе
        $existingAuthorID = getID($author, 'name', 'authors', $container);
            // Если автор не найден, добавляем его и получаем идентификатор
            if ($existingAuthorID == 0) {
                $authorID = setID($author, 'name', 'authors', $container);
            } else {
                // Если автор найден, получаем текущий идентификатор
                $authorID = $existingAuthorID;
            }  
        
        // Записываем книгу в БД
        // Проверка наличия записи в таблице book_author
        $checkBookAuthorQuery = 'SELECT * FROM book_author WHERE book_id = :book_id AND author_id = :author_id';
        $checkBookAuthorStmt = $container->get('connection')->prepare($checkBookAuthorQuery);
        $checkBookAuthorStmt->execute([':book_id' => $bookID, ':author_id' => $authorID]);
        $existingRecord = $checkBookAuthorStmt->fetch();

        if (!$existingRecord) {
        // Записываем книгу в БД
        // Внесение данных в таблицу book_author
            $newBookAuthorQuery = 'INSERT INTO book_author (book_id, author_id) VALUES (:book_id, :author_id)';
            $newBookAuthorStmt = $container->get('connection')->prepare($newBookAuthorQuery);
            $newBookAuthorStmt->execute([':book_id' => $bookID,':author_id' => $authorID]);
        }

        // Находим ссылки на жанры книги
        $genreLinks = $bookCard->find('a[href^="https://litlife.club/genres/"]');

        $genres = [];
        $genresIDs = [];
        foreach ($genreLinks as $genreLink) {
            $genreName = $genreLink->plaintext;
            // Проверка наличия жанра в базе данных
            $existingGenreID = getID($genreName, 'name', 'genres', $container);
            // Если жанр не найден, вставляем его и получаем идентификатор
            if ($existingGenreID == 0) {
                $genreID = setID($genreName, 'name', 'genres', $container);
            } else {
                // Если жанр уже существует, используем его идентификатор
                $genreID = $existingGenreID;
            }

            // Проверка наличия записи в таблице book_genre
            $checkBookGenreQuery = 'SELECT * FROM book_genre WHERE book_id = :book_id AND genre_id = :genre_id';
            $checkBookGenreStmt = $container->get('connection')->prepare($checkBookGenreQuery);
            $checkBookGenreStmt->execute([':book_id' => $bookID, ':genre_id' => $genreID]);
            $existingRecord = $checkBookGenreStmt->fetch();

            if (!$existingRecord) {
            // Записываем книгу в БД
            // Внесение данных в таблицу book_genre
                $newBookGenreQuery = 'INSERT INTO book_genre (book_id, genre_id) VALUES (:book_id, :genre_id)';
                $newBookGenreStmt = $container->get('connection')->prepare($newBookGenreQuery);
                $newBookGenreStmt->execute([':book_id' => $bookID,':genre_id' => $genreID]);
            }
        }
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
$app->post('/books', function ($request, $response) use ($container) {
    $validator = new LibProject\Validator();
    $flash = $this->get('flash');
    $parse_url = $request->getParsedBodyParam('parse_url');
    $errors = $validator->validate($parse_url);
    if (is_array($errors) && count($errors) === 0) {
        parse($parse_url, $container);
        return $response->withRedirect('/books', 302);
    }
    $params = [
        'errors' => $errors,
        'flash' => $flash
    ];
    $this->get('renderer')
                ->render($response->withStatus(422), 'layout.phtml', $params);
    });

// ОТОБРАЖАЕМ И ФИЛЬТРУЕМ
$app->get('/books', function ($request, $response) use ($container) {
    $validator = new LibProject\Validator();
    $flash = $this->get('flash');
    $term = '%' . $request->getQueryParam('term') . '%';
    $filterBooksQuery = 'SELECT DISTINCT
    b.title AS title,
    b.image AS image,
    a.name AS author,
    GROUP_CONCAT(g.name) AS genres
    FROM books AS b
    LEFT JOIN book_genre AS bg ON b.id = bg.book_id
    LEFT JOIN genres AS g ON bg.genre_id = g.id
    LEFT JOIN book_author AS ba ON b.id = ba.book_id
    LEFT JOIN authors AS a ON a.id = ba.author_id
    WHERE title LIKE :term
    OR a.name LIKE :term
    GROUP BY b.id, a.name';
    $filterBooksStmt = $this->get('connection')->prepare($filterBooksQuery);
    $filterBooksStmt->execute([':term' => $term]);
    $filteredBooks = $filterBooksStmt->fetchAll();
    $page = $request->getQueryParam('page', 1);
    $per = $request->getQueryParam('per', 10);
    $books = array_slice($filteredBooks, ($page - 1) * $per, $per);
    $params = [
    'books' => $books,
    'page' => $page
    ];
    return $this->get('renderer')->render($response, 'main.phtml', $params);
    })->setName('books');

$app->run();
