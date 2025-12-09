<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;

$bucket = 'cc-lab4-pub-k16';  
$region = 'eu-central-1';

// AWS клиент
$s3 = new S3Client([
    'version' => 'latest',
    'region'  => $region
]);

// Проверяем файл
$file = $_FILES['fileToUpload']['tmp_name'];
$originalName = $_FILES['fileToUpload']['name'];

// Делаем имя уникальным
$newName = time() . "_" . basename($originalName);

// Путь в бакете
$key = 'avatars/' . $newName;

try {
    // Загружаем файл
    $result = $s3->putObject([
        'Bucket' => $bucket,
        'Key'    => $key,
        'SourceFile' => $file,
        'ACL'    => 'public-read'
    ]);

    echo "<h3>Файл успешно загружен!</h3>";
    echo "URL: <a href='{$result['ObjectURL']}' target='_blank'>{$result['ObjectURL']}</a>";

} catch (Exception $e) {
    echo "Ошибка загрузки: " . $e->getMessage();
}
