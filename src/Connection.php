<?php

namespace LibProject;

use Illuminate\Support\Arr;

final class Connection
{
    public function connect()
    {
        if (getenv('DATABASE_URL')) {
            $databaseUrl = parse_url(getenv('DATABASE_URL'));
        }

        if (isset($databaseUrl['host'])) {
            $params['host'] = Arr::get($databaseUrl, 'host');
            $params['port'] = Arr::get($databaseUrl, 'port', 3306);
            $params['database'] = ltrim(Arr::get($databaseUrl, 'path', null), '/');
            $params['user'] = Arr::get($databaseUrl, 'user', null);
            $params['password'] = Arr::get($databaseUrl, 'pass', null);
        } else {
            $params = parse_ini_file('database.ini');
        }

        if ($params === false) {
            throw new \Exception("Error reading database configuration file");
        }

        $conStr = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
            $params['host'],
            $params['port'],
            $params['database'],
            $params['user'],
            $params['password']
        );

        var_dump($params);

        $pdo = new \PDO($conStr);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}