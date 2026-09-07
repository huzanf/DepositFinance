<?php

namespace DepositFinance;

class Database
{
    private static ?\PDO $connection = null;

    public static function connection(): \PDO
    {
        if (self::$connection === null) {
            $config = config('db');

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['name'],
                $config['charset']
            );

            self::$connection = new \PDO($dsn, $config['user'], $config['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$connection;
    }
}
