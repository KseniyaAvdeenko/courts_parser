<?php
require_once 'config.php';
require_once 'parser.php';

function processAndSaveCourts()
{
    try {
        $config = require 'config.php';
        $parser = new JsonCourtParser($config);
        $courts = $parser->getAllCourtsData();
        
        if (empty($courts)) {
            echo "❌ Не удалось получить данные\n";
            return json_encode(['error' => 'No data received'], JSON_UNESCAPED_UNICODE);
        }
        
        // Формируем результат для JSON
        $result = [
            'success' => true,
            'total_courts' => count($courts),
            'courts' => $courts
        ];

        $filename = 'courts_results.json';
        $jsonData = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        // Сохраняем в файл
        file_put_contents($filename, $jsonData);
        echo "\n💾 Данные сохранены в: " . $filename . "\n";
        
        // Возвращаем JSON
        return $jsonData;
        
    } catch (Exception $e) {
        $errorJson = json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        
        echo "❌ Ошибка: " . $e->getMessage() . "\n";
        return $errorJson;
    }
}


$jsonResult = processAndSaveCourts();
echo $jsonResult;