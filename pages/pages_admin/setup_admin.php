<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/csrf.php';

if (usuario_autenticado()) {
    redirigir('/admin/dashboard');
}

if (hay_admin_configurado()) {
    redirigir('/admin/login');
}

if (!sietelsa_setup_admin_habilitado_en_request()) {
    sietelsa_log('Security setup-admin access blocked', [
        'reason' => 'setup_disabled_or_remote',
        'ip' => sietelsa_remote_ip(),
        'env' => sietelsa_runtime_env(),
        'path' => ruta_request_actual(),
    ]);
    mostrar_pagina_error(403);
}

$error = trim((string) ($_GET['error'] ?? ''));
$ok = trim((string) ($_GET['ok'] ?? ''));
$mensaje = '';
$tipo = 'info';

if ($error === 'campos') {
    $mensaje = 'Completa todos los campos obligatorios.';
    $tipo = 'error';
} elseif ($error === 'metodo') {
    $mensaje = 'Metodo no permitido para esta operacion.';
    $tipo = 'error';
} elseif ($error === 'correo') {
    $mensaje = 'El correo no es valido.';
    $tipo = 'error';
} elseif ($error === 'username') {
    $mensaje = 'El usuario debe tener 3-60 caracteres alfanumericos, guion o guion bajo.';
    $tipo = 'error';
} elseif ($error === 'password_policy') {
    $mensaje = password_fuerte_requisitos();
    $tipo = 'error';
} elseif ($error === 'password_confirm') {
    $mensaje = 'La confirmacion de clave no coincide.';
    $tipo = 'error';
} elseif ($error === 'csrf') {
    $mensaje = 'El token CSRF es invalido o expiro.';
    $tipo = 'error';
} elseif ($error === 'exists') {
    $mensaje = 'Ya existe un administrador activo. Inicia sesion.';
    $tipo = 'error';
} elseif ($error === 'duplicate') {
    $mensaje = 'El usuario o correo ya existe.';
    $tipo = 'error';
} elseif ($error === 'blocked') {
    $mensaje = 'La inicializacion esta bloqueada por politica de entorno.';
    $tipo = 'error';
} elseif ($error === 'db') {
    $mensaje = 'No se pudo crear el administrador por un error de base de datos.';
    $tipo = 'error';
} elseif ($ok === 'created') {
    $mensaje = 'Administrador inicial creado correctamente.';
    $tipo = 'ok';
}

$csrfToken = csrf_token('setup_admin_create');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Inicializar Administrador</title>
                <link rel="icon" type="image/jpeg" href="/assets/img/logos/sietelsaPestana.jpg" />
<style>
            :root {
                color-scheme: light;
                --bg: #0b2947;
                --panel: #ffffff;
                --text: #1a2d3f;
                --muted: #4d6276;
                --error-bg: #ffeef0;
                --error-bd: #ffc7ce;
                --ok-bg: #effaf0;
                --ok-bd: #b8e2bc;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: Arial, sans-serif;
                min-height: 100vh;
                background: linear-gradient(160deg, #0a2745 0%, #0f4a77 100%);
                color: var(--text);
                display: grid;
                place-items: center;
                padding: 1rem;
            }

            .card {
                width: min(680px, 100%);
                background: var(--panel);
                border-radius: 14px;
                padding: 1.5rem;
                box-shadow: 0 20px 45px rgba(0, 0, 0, 0.28);
            }

            h1 {
                margin: 0 0 0.6rem;
                font-size: 1.45rem;
            }

            p {
                margin: 0 0 1rem;
                color: var(--muted);
                line-height: 1.45;
            }

            .alert {
                border: 1px solid transparent;
                border-radius: 10px;
                padding: 0.65rem 0.8rem;
                margin-bottom: 0.9rem;
                font-size: 0.94rem;
            }

            .alert.error {
                background: var(--error-bg);
                border-color: var(--error-bd);
                color: #7a1f2b;
            }

            .alert.ok {
                background: var(--ok-bg);
                border-color: var(--ok-bd);
                color: #1f5e2d;
            }

            form {
                display: grid;
                gap: 0.75rem;
            }

            label {
                display: block;
                font-size: 0.9rem;
                margin-bottom: 0.3rem;
                font-weight: 600;
            }

            input {
                width: 100%;
                border: 1px solid #cad6e2;
                border-radius: 8px;
                padding: 0.65rem 0.7rem;
                font-size: 0.98rem;
            }

            button {
                margin-top: 0.4rem;
                border: 0;
                border-radius: 9px;
                padding: 0.75rem 1rem;
                background: #0f558a;
                color: #fff;
                font-size: 0.98rem;
                font-weight: 700;
                cursor: pointer;
            }

            button:hover {
                background: #0c4773;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <h1>Inicializacion segura de administrador</h1>
            <p>Esta pantalla solo funciona con <code>INSTALL_ENABLED=true</code> y acceso desde localhost. Define una clave fuerte para el admin inicial.</p>

            <?php if ($mensaje !== ''): ?>
                <div class="alert <?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="/admin/auth/setup-admin" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />

                <div>
                    <label for="nombre">Nombre completo</label>
                    <input id="nombre" name="nombre" type="text" maxlength="100" required />
                </div>

                <div>
                    <label for="username">Usuario</label>
                    <input id="username" name="username" type="text" pattern="[A-Za-z0-9_-]{3,60}" maxlength="60" required />
                </div>

                <div>
                    <label for="email">Correo</label>
                    <input id="email" name="email" type="email" maxlength="120" required />
                </div>

                <div>
                    <label for="password">Clave</label>
                    <input id="password" name="password" type="password" minlength="12" maxlength="120" required />
                </div>

                <div>
                    <label for="password_confirm">Confirmar clave</label>
                    <input id="password_confirm" name="password_confirm" type="password" minlength="12" maxlength="120" required />
                </div>

                <button type="submit">Crear administrador</button>
            </form>
        </main>
    </body>
</html>
