<?php

/*
|--------------------------------------------------------------------------
| Timezone
|--------------------------------------------------------------------------
|
| The SQL Server runs on local machine time (Asia/Karachi / UTC+5).
| PHP defaults to Europe/Berlin, which breaks date comparisons
| (e.g. sales window validation). Keep PHP in sync with the DB.
|
*/

date_default_timezone_set('Asia/Karachi');

$serverName = "localhost";
$databaseName = "SchoolFunFair";
$username = "sa";
$password = "12345678";

try {

    $dsn = "sqlsrv:Server=$serverName;Database=$databaseName";

    $pdo = new PDO($dsn, $username, $password);

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {

    die("Database connection failed: " . $e->getMessage());

}