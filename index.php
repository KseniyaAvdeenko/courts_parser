<?php
require_once 'config.php';
require_once 'parser.php';

function saveResults($courts)
{
    $result = [
        'total_courts' => count($courts),
        'courts' => $courts
    ];

    $filename = 'courts_results.json';
    file_put_contents(
        $filename,
        json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    return $filename;
}
try {
    $config = require 'config.php';
    $parser = new JsonCourtParser($config);
    $courts = $parser->getAllCourtsData();
    
    if (empty($courts)) {
        echo "❌ Не удалось получить данные\n";
        exit;
    }
    // Сохраняем
    $filename = saveResults($courts);
    echo "\n💾 Данные сохранены в: " . $filename . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
