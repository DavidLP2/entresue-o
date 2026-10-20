<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin-common.php';
startAdminSession();

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('La configuración inicial solo está disponible desde este equipo.');
}

$configPath = storagePath('administrator.json');
$alreadyConfigured = is_file($configPath);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyConfigured) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión de configuración venció. Recarga la página e inténtalo de nuevo.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $username)) {
            $error = 'El usuario debe tener entre 3 y 40 caracteres: letras, números, punto, guion o guion bajo.';
        } elseif (strlen($password) < 14) {
            $error = 'La contraseña debe tener al menos 14 caracteres.';
        } elseif (!hash_equals($password, $passwordConfirmation)) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (!is_dir(storagePath()) || !is_writable(storagePath())) {
            $error = 'La carpeta storage no existe o no permite escritura para Apache.';
        } else {
            $handle = @fopen($configPath, 'x');
            if ($handle === false) {
                $alreadyConfigured = is_file($configPath);
                $error = $alreadyConfigured
                    ? 'La configuración ya se completó. Inicia sesión en el panel.'
                    : 'No se pudo crear la configuración. Revisa los permisos de la carpeta storage.';
            } else {
                $config = json_encode([
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $written = fwrite($handle, $config);
                fclose($handle);

                if ($written === false || $written !== strlen($config)) {
                    unlink($configPath);
                    $error = 'No se pudo guardar la configuración completa.';
                } else {
                    chmod($configPath, 0600);
                    header('Location: index.php?configured=1', true, 303);
                    exit;
                }
            }
        }
    }
}

$token = csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar acceso | D&amp;L Pijamas</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <main class="admin-card">
        <a class="back-link" href="../index.html">← Volver a la tienda</a>
        <p class="eyebrow">D&amp;L PIJAMAS</p>
        <h1>Configurar el panel</h1>
        <?php if ($alreadyConfigured): ?>
            <p>El acceso de administración ya está configurado.</p>
            <a class="button-link" href="index.php">Ir al inicio de sesión</a>
        <?php else: ?>
            <p>Esta configuración inicial solo se puede realizar desde este equipo. Elige un usuario y una contraseña segura de al menos 14 caracteres.</p>
            <?php if ($error !== ''): ?><p class="form-error" role="alert"><?= escapeHtml($error) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($token) ?>">
                <label for="username">Usuario</label>
                <input id="username" name="username" autocomplete="username" minlength="3" maxlength="40" required>
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="14" required>
                <label for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="14" required>
                <button type="submit">Crear acceso</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
