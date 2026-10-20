<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin-common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

try {
    $products = array_values(array_filter(readProducts(), static function ($product): bool {
        return is_array($product) && !empty($product['active']);
    }));

    $publicProducts = array_map(static function (array $product): array {
        return [
            'id' => (string) $product['id'],
            'name' => (string) $product['name'],
            'price' => (int) $product['price'],
            'category' => (string) $product['category'],
            'stock' => (int) $product['stock'],
            'image' => 'api/imagen-producto.php?id=' . rawurlencode((string) $product['id']),
        ];
    }, $products);

    echo json_encode(['products' => $publicProducts], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (JsonException | RuntimeException | TypeError | Error $exception) {
    error_log('Entresueño product API failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo cargar el catálogo.'], JSON_UNESCAPED_UNICODE);
}
