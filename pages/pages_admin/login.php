<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/recaptcha.php';
require_once __DIR__ . '/../../include/csrf.php';
iniciar_sesion_segura();

if (usuario_autenticado()) {
    redirigir(ruta_inicio_usuario_actual());
}

$sinAdminConfigurado = !hay_admin_configurado();
if ($sinAdminConfigurado) {
    if (sietelsa_setup_admin_habilitado_en_request()) {
        redirigir('/admin/setup-admin');
    }
}

$error = $_GET['error'] ?? '';
$ok = $_GET['ok'] ?? '';

$mensaje = '';
$tipo_mensaje = 'info';
if ($error === 'campos') {
    $mensaje = 'Completa usuario/correo y clave.';
    $tipo_mensaje = 'error';
} elseif ($error === 'captcha') {
    $mensaje = 'Completa la verificacion reCAPTCHA.';
    $tipo_mensaje = 'error';
} elseif ($error === 'captcha_config') {
    $mensaje = 'Google reCAPTCHA no esta configurado en el servidor.';
    $tipo_mensaje = 'error';
} elseif ($error === 'formato') {
    $mensaje = 'El identificador no tiene un formato valido.';
    $tipo_mensaje = 'error';
} elseif ($error === 'credenciales') {
    $mensaje = 'Credenciales invalidas.';
    $tipo_mensaje = 'error';
} elseif ($error === 'auth') {
    $mensaje = 'Debes iniciar sesion primero.';
    $tipo_mensaje = 'error';
} elseif ($error === 'timeout') {
    $mensaje = 'Tu sesion expiro por inactividad. Inicia sesion de nuevo.';
    $tipo_mensaje = 'error';
} elseif ($error === 'estructura') {
    $mensaje = 'La base de datos no tiene la estructura requerida. Ejecuta migracion de mantenimiento por CLI.';
    $tipo_mensaje = 'error';
} elseif ($error === 'perfil') {
    $mensaje = 'Tu usuario no tiene un perfil valido asignado. Contacta al administrador.';
    $tipo_mensaje = 'error';
} elseif ($error === 'setup_required') {
    $mensaje = 'No hay ningun administrador activo configurado. Completa la inicializacion de admin.';
    $tipo_mensaje = 'error';
} elseif ($error === 'setup_blocked') {
    $mensaje = 'La inicializacion de admin esta bloqueada por politica de entorno.';
    $tipo_mensaje = 'error';
} elseif ($error === 'csrf') {
    $mensaje = 'La sesion del formulario expiro. Recarga e intenta de nuevo.';
    $tipo_mensaje = 'error';
} elseif ($error === 'rate_limit') {
    $mensaje = 'Demasiados intentos fallidos. Espera unos minutos antes de intentar nuevamente.';
    $tipo_mensaje = 'error';
} elseif ($error === 'google_config') {
    $mensaje = 'El acceso con Google no esta configurado. Define GOOGLE_OAUTH_CLIENT_ID, GOOGLE_OAUTH_CLIENT_SECRET y GOOGLE_OAUTH_REDIRECT_URI (https://sietelsaonline.com/admin/auth/google/callback).';
    $tipo_mensaje = 'error';
} elseif ($error === 'google_state') {
    $mensaje = 'La validacion de seguridad de Google expiro. Intenta iniciar de nuevo.';
    $tipo_mensaje = 'error';
} elseif ($error === 'google_email') {
    $mensaje = 'No se recibio un correo verificado desde Google.';
    $tipo_mensaje = 'error';
} elseif ($error === 'google_no_user') {
    $mensaje = 'Tu correo de Google no tiene una cuenta activa en este sistema.';
    $tipo_mensaje = 'error';
} elseif ($error === 'google_profile') {
    $mensaje = 'Tu cuenta existe, pero no tiene un perfil valido asignado.';
    $tipo_mensaje = 'error';
} elseif ($error === 'google_auth') {
    $mensaje = 'No se pudo completar el inicio de sesion con Google.';
    $tipo_mensaje = 'error';
} elseif ($ok === 'logout') {
    $mensaje = 'Sesion cerrada correctamente.';
    $tipo_mensaje = 'ok';
} elseif ($ok === 'instalado') {
    $mensaje = 'Mantenimiento de base de datos aplicado correctamente.';
    $tipo_mensaje = 'ok';
} elseif ($ok === 'admin_created') {
    $mensaje = 'Administrador inicial creado. Inicia sesion para continuar.';
    $tipo_mensaje = 'ok';
}

if ($sinAdminConfigurado && $mensaje === '') {
    $mensaje = 'No hay administradores activos. Habilita INSTALL_ENABLED=true y usa localhost para inicializar.';
    $tipo_mensaje = 'error';
}

$recaptchaSiteKey = recaptcha_site_key();
$csrfLogin = csrf_token('admin_login');
$googleLoginEnabled = trim(sietelsa_env('GOOGLE_OAUTH_CLIENT_ID', '')) !== ''
    && trim(sietelsa_env('GOOGLE_OAUTH_CLIENT_SECRET', '')) !== '';
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="description" content="Acceso administrativo Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Iniciar Sesion | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=IBM+Plex+Sans:wght@400;500;600&display=swap" />
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'" />
        <noscript><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet" /></noscript>
        <style>
            :root {
                --brand-navy: #07233f;
                --brand-blue: #0d4f8b;
                --brand-cyan: #1aa0d8;
                --brand-text: #163048;
                --brand-warm: #f4ad42;
                --surface: rgba(255, 255, 255, 0.9);
                --shadow: 0 30px 60px -26px rgba(4, 24, 43, 0.45);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: "IBM Plex Sans", sans-serif;
                color: var(--brand-text);
                background: radial-gradient(circle at 20% 20%, #2d8fcb 0%, #0c4375 30%, #08284a 70%, #061a30 100%);
                position: relative;
                overflow-x: hidden;
            }

            body::before,
            body::after {
                content: "";
                position: fixed;
                border-radius: 50%;
                pointer-events: none;
                z-index: 0;
                filter: blur(2px);
            }

            body::before {
                width: 340px;
                height: 340px;
                top: -90px;
                right: -80px;
                background: rgba(244, 173, 66, 0.2);
            }

            body::after {
                width: 280px;
                height: 280px;
                bottom: -110px;
                left: -70px;
                background: rgba(26, 160, 216, 0.22);
            }

            .page {
                width: min(1080px, 92vw);
                margin: 0 auto;
                min-height: 100vh;
                display: flex;
                align-items: center;
                padding: 2rem 0;
                position: relative;
                z-index: 1;
            }

            .login-shell {
                display: grid;
                grid-template-columns: 1.1fr 1fr;
                width: 100%;
                border-radius: 28px;
                overflow: hidden;
                background: var(--surface);
                box-shadow: var(--shadow);
                border: 1px solid rgba(255, 255, 255, 0.35);
                backdrop-filter: blur(9px);
            }

            .panel-brand {
                background:
                    linear-gradient(140deg, rgba(6, 30, 55, 0.95), rgba(10, 70, 120, 0.9)),
                    url("../../assets/img/header-bg.jpg") center/cover no-repeat;
                color: #eaf6ff;
                padding: 2.5rem;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                gap: 2rem;
            }

            .logo-wrap {
                display: inline-flex;
                align-items: center;
                gap: 0.9rem;
                text-decoration: none;
                color: inherit;
                width: fit-content;
            }

            .logo-wrap img {
                width: 54px;
                height: 54px;
                border-radius: 50%;
                background: #fff;
                padding: 0.25rem;
                object-fit: contain;
            }

            .brand-copy h1 {
                margin: 0;
                font-family: "Manrope", sans-serif;
                font-size: 1.45rem;
                letter-spacing: 0.02em;
            }

            .brand-copy p {
                margin: 0.2rem 0 0;
                color: rgba(234, 246, 255, 0.82);
                font-size: 0.95rem;
            }

            .headline h2 {
                margin: 0;
                font-family: "Manrope", sans-serif;
                font-size: clamp(1.65rem, 3vw, 2.35rem);
                line-height: 1.18;
                max-width: 18ch;
            }

            .headline p {
                margin: 0.9rem 0 0;
                color: rgba(234, 246, 255, 0.83);
                max-width: 36ch;
                line-height: 1.5;
            }

            .feature-list {
                margin: 0;
                padding: 0;
                list-style: none;
                display: grid;
                gap: 0.7rem;
            }

            .feature-list li {
                display: flex;
                align-items: center;
                gap: 0.6rem;
                color: rgba(234, 246, 255, 0.9);
                font-size: 0.95rem;
            }

            .feature-list li::before {
                content: "";
                width: 0.5rem;
                height: 0.5rem;
                border-radius: 50%;
                background: var(--brand-warm);
                box-shadow: 0 0 0 0.2rem rgba(244, 173, 66, 0.2);
                flex-shrink: 0;
            }

            .panel-form {
                padding: 2rem;
                display: flex;
                flex-direction: column;
                justify-content: center;
                background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(247, 252, 255, 0.93));
            }

            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                width: fit-content;
                color: #215886;
                text-decoration: none;
                font-weight: 600;
                margin-bottom: 1.2rem;
                transition: transform 0.2s ease, color 0.2s ease;
            }

            .back-link:hover {
                color: #113b60;
                transform: translateX(-3px);
            }

            .panel-form h3 {
                margin: 0;
                font-family: "Manrope", sans-serif;
                font-size: clamp(1.4rem, 3vw, 2rem);
                color: #0a2b49;
                letter-spacing: 0.01em;
            }

            .panel-form p {
                margin: 0.35rem 0 1.35rem;
                color: #4a6780;
            }

            .alert-box {
                border-radius: 14px;
                padding: 0.85rem 0.95rem;
                margin-bottom: 1rem;
                font-size: 0.95rem;
                border: 1px solid transparent;
            }

            .alert-box.error {
                background: #fff0f0;
                border-color: #ffc6c6;
                color: #842029;
            }

            .alert-box.ok {
                background: #effcf5;
                border-color: #badfcc;
                color: #195f35;
            }

            .alert-box.info {
                background: #eef7ff;
                border-color: #c6e2fd;
                color: #0f4f84;
            }

            form {
                display: grid;
                gap: 0.95rem;
            }

            label {
                font-size: 0.9rem;
                font-weight: 600;
                color: #234b6b;
                display: block;
                margin-bottom: 0.35rem;
            }

            .field input {
                width: 100%;
                border: 1px solid #c6d8e7;
                border-radius: 12px;
                padding: 0.78rem 0.85rem;
                font-family: inherit;
                font-size: 1rem;
                color: #0f314d;
                background: #fff;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .field input:focus {
                outline: none;
                border-color: var(--brand-cyan);
                box-shadow: 0 0 0 0.2rem rgba(26, 160, 216, 0.2);
            }

            .submit-btn {
                margin-top: 0.25rem;
                border: 0;
                border-radius: 12px;
                padding: 0.88rem 1rem;
                font-family: "Manrope", sans-serif;
                font-size: 1rem;
                font-weight: 800;
                color: #fff;
                cursor: pointer;
                background: linear-gradient(135deg, #0b4d86, #1a8eca);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                box-shadow: 0 12px 24px -15px rgba(7, 70, 119, 0.8);
            }

            .submit-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 18px 28px -16px rgba(7, 70, 119, 0.9);
            }

            .submit-btn:active {
                transform: translateY(0);
            }

            .divider {
                display: flex;
                align-items: center;
                gap: 0.8rem;
                margin: 0.2rem 0;
                color: #5a738a;
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }

            .divider::before,
            .divider::after {
                content: "";
                height: 1px;
                background: #d8e6f2;
                flex: 1;
            }

            .google-btn {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                border: 1px solid #c9d9ea;
                border-radius: 12px;
                padding: 0.68rem 0.8rem 0.68rem 0.74rem;
                background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
                color: #18344f;
                text-decoration: none;
                font-weight: 800;
                letter-spacing: 0.01em;
                box-shadow: 0 10px 18px -16px rgba(7, 53, 89, 0.95);
                transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, background 0.2s ease;
            }

            .google-btn-logo {
                width: 34px;
                height: 34px;
                border-radius: 10px;
                border: 1px solid #e4ecf5;
                background: #fff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .google-btn-label {
                flex: 1;
                text-align: left;
                font-family: "Manrope", sans-serif;
                font-size: 0.96rem;
            }

            .google-btn img {
                width: 18px;
                height: 18px;
                display: block;
            }

            .google-btn-arrow {
                color: #2f587e;
                font-size: 1rem;
                line-height: 1;
            }

            .google-btn:hover {
                border-color: #97b4d2;
                background: linear-gradient(180deg, #ffffff 0%, #f1f8ff 100%);
                box-shadow: 0 16px 24px -17px rgba(6, 55, 93, 0.95);
                transform: translateY(-1px);
            }

            .google-btn:focus-visible {
                outline: none;
                border-color: #6ca1d1;
                box-shadow: 0 0 0 0.22rem rgba(26, 140, 202, 0.2);
            }

            .google-hint {
                margin: 0;
                color: #5f768d;
                font-size: 0.82rem;
                text-align: center;
            }

            @media (max-width: 960px) {
                .login-shell {
                    grid-template-columns: 1fr;
                }

                .panel-brand {
                    padding: 1.9rem 1.6rem 1.6rem;
                }

                .panel-form {
                    padding: 1.8rem 1.25rem 1.5rem;
                }
            }

            @media (max-width: 480px) {
                .page {
                    width: 100%;
                    padding: 1rem 0.75rem;
                }

                .login-shell {
                    border-radius: 20px;
                }

                .panel-brand,
                .panel-form {
                    padding-left: 1rem;
                    padding-right: 1rem;
                }

                .headline h2 {
                    font-size: 1.5rem;
                }
            }
        </style>
        <link href="../../css/login-admin.css" rel="stylesheet" />
    </head>
    <body>
        <main class="page">
            <section class="login-shell" aria-label="Acceso administrativo">
                <aside class="panel-brand">
                    <a class="logo-wrap" href="/">
                        <img src="../../assets/img/logos/logoSietelsa.png" alt="Logo Sietelsa" width="54" height="54" decoding="async" />
                        <div class="brand-copy">
                            <h1>Sietelsa</h1>
                            <p>SIETELSA, S.A. DE C.V</p>
                        </div>
                    </a>
                    <div class="headline">
                        <h2>Bienvenido a Sietelsa</h2>
                        <p>Aqui puedes iniciar sesion en la plataforma</p>
                    </div>
                    <ul class="feature-list">
                        <li>Acceso centralizado para administracion interna</li>
                        <li>Sesion segura con validaciones de autenticacion</li>
                        <li>Interfaz clara para trabajo diario</li>
                    </ul>
                </aside>
                <section class="panel-form">
                    <a class="back-link" href="/" aria-label="Regresar al inicio">
                        <span aria-hidden="true">&larr;</span>
                        Volver al inicio
                    </a>
                    <h3>Bienvenido</h3>
                    <p>Ingresa tus credenciales para continuar.</p>

                    <?php if ($mensaje !== ''): ?>
                    <div class="alert-box <?php echo htmlspecialchars($tipo_mensaje, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                        <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php endif; ?>

                    <form action="/admin/auth/login" method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfLogin, ENT_QUOTES, 'UTF-8'); ?>" />
                        <div class="field">
                            <label for="inputIdentificador">Correo o nombre de usuario</label>
                            <input id="inputIdentificador" name="identificador" type="text" placeholder="usuario o email" autocomplete="username" maxlength="120" required />
                        </div>
                        <div class="field">
                            <label for="inputPassword">Clave de acceso</label>
                            <input id="inputPassword" name="password" type="password" placeholder="********" autocomplete="current-password" maxlength="120" required />
                        </div>
                        <div class="field">
                            <label>Verificacion de seguridad</label>
                            <?php if ($recaptchaSiteKey !== ''): ?>
                            <div class="recaptcha-wrap">
                                <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptchaSiteKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
                            </div>
                            <?php else: ?>
                            <div class="alert-box error alert-inline">
                                Configura `RECAPTCHA_SITE_KEY` y `RECAPTCHA_SECRET_KEY` en el servidor.
                            </div>
                            <?php endif; ?>
                        </div>
                        <button class="submit-btn" type="submit">Iniciar sesion</button>
                    </form>
                    <div class="divider" aria-hidden="true">o</div>
                    <a class="google-btn" href="/admin/auth/google">
                        <span class="google-btn-logo" aria-hidden="true">
                            <img src="../../assets/img/logos/google.svg" alt="" />
                        </span>
                        <span class="google-btn-label">Iniciar sesion con Google</span>
                        <span class="google-btn-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                    <?php if (!$googleLoginEnabled): ?>
                    <!-- <p class="google-hint">Configuracion pendiente del acceso con Google en servidor.</p> -->
                    <?php endif; ?>

                </section>
            </section>
        </main>
        <footer class="login-footer">
            Sietelsa &copy; <?php echo date('Y'); ?>
        </footer>
        <?php if ($recaptchaSiteKey !== ''): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <?php endif; ?>
        <script src="/admin/js/scripts.js?v=20260613e"></script>
    </body>
</html>
