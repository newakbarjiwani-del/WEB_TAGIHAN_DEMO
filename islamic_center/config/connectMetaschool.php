<?php
$metaschoolDbConfig = [
    'host'    => '157.66.35.103',
    'dbname'  => 'metaschool_switching',
    'user'    => 'root',
    'pass'    => 'Bismillah100%',
    'charset' => 'utf8mb4',
];

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $metaschoolDbConfig['host'],
        $metaschoolDbConfig['dbname'],
        $metaschoolDbConfig['charset']
    );

    $pdo = new PDO(
        $dsn,
        $metaschoolDbConfig['user'],
        $metaschoolDbConfig['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}
