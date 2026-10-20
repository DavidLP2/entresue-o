<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin-common.php';

$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
    http_response_code(404);
    exit;
}

try {
    $products = readProducts();
    $product = null;
    foreach ($products as $entry) {
        if (is_array($entry) && isset($entry['id']) && hash_equals((string) $entry['id'], $id)) {
            $product = $entry;
            break;
        }
    }

    if ($product === null || !isset($product['image']) || !is_string($product['image'])) {
        http_response_code(404);
        exit;
    }

    $fileName = $product['image'];
    if (!preg_match('/^[a-f0-9]{32}\.webp$/', $fileName)) {
        http_response_code(404);
        exit;
    }

    $path = storagePath('uploads') . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: image/webp');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . filesize($path));
    readfile($path);
} catch (JsonException | RuntimeException $exception) {
    error_log('Entresueño image API failed: ' . $exception->getMessage());
    http_response_code(500);
    exit;
}
