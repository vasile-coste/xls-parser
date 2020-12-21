<?php

class Connect
{
    public static function PDO()
    {
        $host = 'localhost';
        $dbname = 'parse_xls';
        $username = 'root';
        $password = '';

        try {
            $mysql = sprintf(
                "mysql:host=%s;dbname=%s",
                $host,
                $dbname
            );

            return new PDO($mysql, $username, $password);
        } catch (PDOException $pe) {
            die("Could not connect to the database $dbname:" . $pe->getMessage());
        }
    }
}
