<?php
declare(strict_types=1);

function storagePath(string $file = ''): string
{
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
    return $file === '' ? $directory : $directory . DIRECTORY_SEPARATOR . $file;
}

function readJsonFile(string $path, mixed $default): mixed
{
    if (!is_file($path)) {
        return $default;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('No se pudo leer el archivo de datos.');
    }

    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
}

function writeJsonFile(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporaryPath = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';

    if (file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
        throw new RuntimeException('No se pudieron guardar los datos.');
    }

    if (!rename($temporaryPath, $path)) {
        unlink($temporaryPath);
        throw new RuntimeException('No se pudo finalizar el guardado de los datos.');
    }
}

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/';
    $basePath = dirname(dirname($scriptPath));
    if ($basePath === '.' || $basePath === DIRECTORY_SEPARATOR) {
        $basePath = '/';
    }

    session_name('DLPijamasAdmin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $basePath,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(mixed $submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function requireAdmin(): void
{
    startAdminSession();
    $sessionExpired = isset($_SESSION['admin_user'], $_SESSION['last_activity'])
        && time() - (int) $_SESSION['last_activity'] > 7200;

    if ($sessionExpired) {
        unset($_SESSION['admin_user'], $_SESSION['last_activity']);
    }

    if (!isset($_SESSION['admin_user'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Debes iniciar sesión para administrar el catálogo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function productCategories(): array
{
    return [
        'ninos' => 'Niños',
        'ninas' => 'Niñas',
        'adulto-femenino' => 'Adultos femenino',
        'talla-plus' => 'Talla plus',
    ];
}

function readProducts(): array
{
    $products = readJsonFile(storagePath('products.json'), []);
    if (!is_array($products) || !array_is_list($products)) {
        throw new RuntimeException('El catálogo guardado tiene un formato inválido.');
    }

    return $products;
}

function saveUploadedImage(array $upload): string
{
    if (!isset($upload['error'], $upload['tmp_name'], $upload['size'])
        || is_array($upload['error'])
        || (int) $upload['error'] !== UPLOAD_ERR_OK
        || !is_uploaded_file((string) $upload['tmp_name'])) {
        throw new RuntimeException('No se pudo recibir la imagen. Intenta subirla otra vez.');
    }

    if ((int) $upload['size'] < 1 || (int) $upload['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen debe pesar menos de 5 MB.');
    }

    $imageInfo = getimagesize((string) $upload['tmp_name']);
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $detectedMimeType = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);

    if ($imageInfo === false
        || !in_array($detectedMimeType, $allowedMimeTypes, true)
        || ($imageInfo['mime'] ?? '') !== $detectedMimeType) {
        throw new RuntimeException('Formato de imagen no permitido. Usa JPG, PNG o WebP.');
    }

    [$width, $height] = $imageInfo;
    if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || $width * $height > 24000000) {
        throw new RuntimeException('Las dimensiones máximas son 6000 × 6000 píxeles.');
    }

    $contents = file_get_contents((string) $upload['tmp_name']);
    $image = $contents === false ? false : imagecreatefromstring($contents);
    if ($image === false) {
        throw new RuntimeException('No se pudo procesar la imagen subida.');
    }

    $uploadDirectory = storagePath('uploads');
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        imagedestroy($image);
        throw new RuntimeException('No se pudo crear el directorio de imágenes.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.webp';
    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
    $saved = imagewebp($image, $destination, 86);
    imagedestroy($image);

    if (!$saved) {
        throw new RuntimeException('No se pudo guardar la imagen procesada.');
    }

    chmod($destination, 0640);
    return $fileName;
}

function removeUploadedImage(mixed $fileName): void
{
    if (is_string($fileName) && preg_match('/^[a-f0-9]{32}\.webp$/', $fileName)) {
        $path = storagePath('uploads') . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('No se pudo eliminar una imagen reemplazada.');
        }
    }
}
