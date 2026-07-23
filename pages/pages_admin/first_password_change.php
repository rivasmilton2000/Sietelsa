<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/csrf.php';

require_login();

if (!usuario_actual_debe_cambiar_password()) {
    redirigir('/admin/dashboard');
}

$usuario = obtener_usuario_actual() ?? [];
$nombre = trim((string) ($usuario['nombre'] ?? ''));

$error = trim((string) ($_GET['error'] ?? ''));
$ok = trim((string) ($_GET['ok'] ?? ''));
$mensaje = '';
$tipo = 'info';

if ($error === 'campos') {
    $mensaje = 'Completa todos los campos.';
    $tipo = 'error';
} elseif ($error === 'metodo') {
    $mensaje = 'Metodo no permitido.';
    $tipo = 'error';
} elseif ($error === 'actual') {
    $mensaje = 'La clave actual no es valida.';
    $tipo = 'error';
} elseif ($error === 'policy') {
    $mensaje = password_fuerte_requisitos();
    $tipo = 'error';
} elseif ($error === 'confirm') {
    $mensaje = 'La nueva clave y su confirmacion no coinciden.';
    $tipo = 'error';
} elseif ($error === 'csrf') {
    $mensaje = 'El token CSRF es invalido o expiro.';
    $tipo = 'error';
} elseif ($error === 'db') {
    $mensaje = 'No se pudo actualizar la clave por un error temporal.';
    $tipo = 'error';
} elseif ($ok === 'updated') {
    $mensaje = 'Clave actualizada correctamente.';
    $tipo = 'ok';
}

$csrfToken = csrf_token('first_password_change');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Cambio Obligatorio de Clave</title>
                <link rel="icon" type="image/jpeg" href="/assets/img/logos/sietelsaPestana.jpg" />
<style>
            :root {
                color-scheme: light;
                --bg: #0c2239;
                --panel: #ffffff;
                --text: #1a2d3f;
                --muted: #4f6277;
                --error-bg: #fff0f1;
                --error-bd: #ffc7cd;
                --ok-bg: #effaf1;
                --ok-bd: #bae4c0;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                min-height: 100vh;
                background: radial-gradient(circle at 15% 20%, #16598a 0%, #0e3659 35%, #081d31 100%);
                display: grid;
                place-items: center;
                padding: 1rem;
                color: var(--text);
            }

            .panel {
                width: min(560px, 100%);
                background: var(--panel);
                border-radius: 12px;
                padding: 1.35rem;
                box-shadow: 0 18px 42px rgba(0, 0, 0, 0.3);
            }

            h1 {
                margin: 0 0 0.65rem;
                font-size: 1.35rem;
            }

            p {
                margin: 0 0 0.9rem;
                color: var(--muted);
                line-height: 1.45;
            }

            .alert {
                border: 1px solid transparent;
                border-radius: 10px;
                padding: 0.65rem 0.8rem;
                margin-bottom: 0.85rem;
                font-size: 0.93rem;
            }

            .alert.error {
                background: var(--error-bg);
                border-color: var(--error-bd);
                color: #7a2030;
            }

            .alert.ok {
                background: var(--ok-bg);
                border-color: var(--ok-bd);
                color: #1d5f31;
            }

            form {
                display: grid;
                gap: 0.75rem;
            }

            label {
                display: block;
                margin-bottom: 0.3rem;
                font-size: 0.9rem;
                font-weight: 600;
            }

            input {
                width: 100%;
                border: 1px solid #ccd7e1;
                border-radius: 8px;
                padding: 0.65rem 0.7rem;
                font-size: 0.96rem;
            }

            button {
                margin-top: 0.35rem;
                border: 0;
                border-radius: 9px;
                padding: 0.74rem 1rem;
                background: #0f5689;
                color: #fff;
                font-weight: 700;
                cursor: pointer;
                font-size: 0.97rem;
            }

            button:hover {
                background: #0d4973;
            }
        </style>
    </head>
    <body>
        <main class="panel">
            <h1>Cambio obligatorio de clave</h1>
            <p>
                <?php if ($nombre !== ''): ?>
                    Hola, <?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>.
                <?php endif; ?>
                Debes actualizar tu clave antes de acceder al panel.
            </p>
            <p>Requisito: <?php echo htmlspecialchars(password_fuerte_requisitos(), ENT_QUOTES, 'UTF-8'); ?></p>

            <?php if ($mensaje !== ''): ?>
                <div class="alert <?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="/admin/auth/primer-acceso/cambiar-clave" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />

                <div>
                    <label for="password_actual">Clave actual</label>
                    <input id="password_actual" name="password_actual" type="password" maxlength="120" autocomplete="current-password" required />
                </div>

                <div>
                    <label for="password_nueva">Nueva clave</label>
                    <input id="password_nueva" name="password_nueva" type="password" maxlength="120" minlength="12" autocomplete="new-password" required />
                </div>

                <div>
                    <label for="password_confirmar">Confirmar nueva clave</label>
                    <input id="password_confirmar" name="password_confirmar" type="password" maxlength="120" minlength="12" autocomplete="new-password" required />
                </div>

                <button type="submit">Actualizar clave</button>
            </form>
        </main>
    </body>
</html>
