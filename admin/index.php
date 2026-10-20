<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin-common.php';
startAdminSession();

$configPath = storagePath('administrator.json');
$configured = is_file($configPath);
$error = '';
$success = '';

if (isset($_GET['configured'])) {
    $success = 'Acceso de administración configurado. Ya puedes iniciar sesión.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión venció. Recarga la página e inténtalo de nuevo.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'login' && $configured) {
            $config = readJsonFile($configPath, null);
            $username = (string) ($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            if (is_array($config)
                && isset($config['username'], $config['password_hash'])
                && hash_equals((string) $config['username'], $username)
                && password_verify($password, (string) $config['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_user'] = $username;
                $_SESSION['last_activity'] = time();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: index.php', true, 303);
                exit;
            }

            $error = 'Usuario o contraseña incorrectos.';
        } elseif ($action === 'logout' && isset($_SESSION['admin_user'])) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $success = 'Sesión cerrada.';
        } elseif ($action === 'publish' && isset($_SESSION['admin_user'])) {
            $title = trim((string) ($_POST['title'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));
            $active = isset($_POST['active']);

            if ($active && ($title === '' || $message === '')) {
                $error = 'Completa el título y el mensaje antes de publicar.';
            } elseif (mb_strlen($title, 'UTF-8') > 100) {
                $error = 'El título no puede superar los 100 caracteres.';
            } elseif (mb_strlen($message, 'UTF-8') > 500) {
                $error = 'El mensaje no puede superar los 500 caracteres.';
            } else {
                try {
                    writeJsonFile(storagePath('announcement.json'), [
                        'title' => $title,
                        'message' => $message,
                        'active' => $active,
                        'updated_at' => gmdate('c'),
                    ]);
                    $success = $active ? 'El anuncio está publicado en la página de inicio.' : 'El anuncio se guardó como desactivado.';
                } catch (JsonException | RuntimeException $exception) {
                    error_log('D&L announcement save failed: ' . $exception->getMessage());
                    $error = 'No se pudo guardar el anuncio. Revisa los permisos de storage y vuelve a intentarlo.';
                }
            }
        }
    }
}

$sessionExpired = isset($_SESSION['admin_user'])
    && isset($_SESSION['last_activity'])
    && time() - (int) $_SESSION['last_activity'] > 7200;
if ($sessionExpired) {
    unset($_SESSION['admin_user'], $_SESSION['last_activity']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $error = 'La sesión expiró. Inicia sesión de nuevo.';
}

$isLoggedIn = isset($_SESSION['admin_user']);
if ($isLoggedIn) {
    $_SESSION['last_activity'] = time();
}

$announcement = ['title' => '', 'message' => '', 'active' => false];
if ($isLoggedIn) {
    try {
        $savedAnnouncement = readJsonFile(storagePath('announcement.json'), null);
        if (is_array($savedAnnouncement)) {
            $announcement = array_merge($announcement, $savedAnnouncement);
        }
    } catch (JsonException | RuntimeException $exception) {
        error_log('D&L announcement read failed: ' . $exception->getMessage());
        $error = 'No se pudo leer el anuncio guardado.';
    }
}

$token = csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anuncios | D&amp;L Pijamas</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <main class="admin-card admin-dashboard">
        <a class="back-link" href="../index.html">← Volver a la tienda</a>
        <p class="eyebrow">D&amp;L PIJAMAS</p>
        <h1>Panel de anuncios</h1>

        <?php if ($error !== ''): ?><p class="form-error" role="alert"><?= escapeHtml($error) ?></p><?php endif; ?>
        <?php if ($success !== ''): ?><p class="form-success" role="status"><?= escapeHtml($success) ?></p><?php endif; ?>

        <?php if (!$configured): ?>
            <p>Aún no se ha configurado el acceso al panel.</p>
            <a class="button-link" href="setup.php">Configurar acceso desde este equipo</a>
        <?php elseif (!$isLoggedIn): ?>
            <p>Inicia sesión para publicar o actualizar un anuncio de colección.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($token) ?>">
                <input type="hidden" name="action" value="login">
                <label for="username">Usuario</label>
                <input id="username" name="username" autocomplete="username" required>
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Iniciar sesión</button>
            </form>
        <?php else: ?>
            <div class="dashboard-heading">
                <p>Sesión iniciada como <strong><?= escapeHtml((string) $_SESSION['admin_user']) ?></strong></p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($token) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="button-secondary">Cerrar sesión</button>
                </form>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($token) ?>">
                <input type="hidden" name="action" value="publish">
                <label for="title">Título del anuncio</label>
                <input id="title" name="title" maxlength="100" value="<?= escapeHtml((string) $announcement['title']) ?>" placeholder="Nueva colección: noches de ensueño">
                <label for="message">Mensaje</label>
                <textarea id="message" name="message" maxlength="500" rows="5" placeholder="Cuéntales a tus clientes qué trae la nueva colección."><?= escapeHtml((string) $announcement['message']) ?></textarea>
                <label class="checkbox-label">
                    <input type="checkbox" name="active" value="1" <?= !empty($announcement['active']) ? 'checked' : '' ?>>
                    Publicar anuncio en la página de inicio
                </label>
                <p class="form-hint">Al desmarcar esta opción, el anuncio se oculta sin borrar el texto. Máximo 100 caracteres para el título y 500 para el mensaje.</p>
                <button type="submit">Guardar anuncio</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
