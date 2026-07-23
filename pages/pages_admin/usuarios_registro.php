<?php
declare(strict_types=1);

require_once __DIR__ . '/../../include/auth.php';
require_once __DIR__ . '/../../include/admin_topnav.php';
require_once __DIR__ . '/../../include/admin_sidebar.php';
require_once __DIR__ . '/../../include/csrf.php';
require_once __DIR__ . '/../../include/db_setup.php';

function es_perfil_developer(string $perfil): bool
{
    return strtolower(trim($perfil)) === 'developer';
}

require_modulo('gestionar_usuarios');

global $pdo;
dte_asegurar_rbac_basico($pdo);

$usuario = obtener_usuario_actual() ?? [];
$nombreSesion = trim((string) ($usuario['nombre'] ?? ''));
$usernameSesion = trim((string) ($usuario['username'] ?? ''));
$perfilSesion = trim((string) ($usuario['perfil'] ?? ''));
$sesionEsDeveloper = es_perfil_developer($perfilSesion);
$puedeGestionarServicios = tiene_permiso('gestionar_servicios');
$puedeGestionarPortafolio = tiene_permiso('gestionar_portafolio');
$puedeGestionarProyectos = tiene_permiso('gestionar_proyectos');
$puedeGestionarNosotros = tiene_permiso('gestionar_nosotros');
$puedeGestionarContacto = tiene_permiso('gestionar_contacto');
$puedeGestionarBackup = tiene_permiso('gestionar_backup');
$puedeGestionarPerfiles = tiene_permiso('gestionar_perfiles');
$puedeGestionarContenido = $puedeGestionarServicios
    || $puedeGestionarPortafolio
    || $puedeGestionarProyectos
    || $puedeGestionarNosotros
    || $puedeGestionarContacto;

$etiquetaSesion = $nombreSesion !== '' ? $nombreSesion : 'Usuario';
if ($usernameSesion !== '') {
    $etiquetaSesion .= ' (' . $usernameSesion . ')';
}

$sqlPerfiles = 'SELECT id, nombre FROM perfiles';
if (columna_existe($pdo, 'perfiles', 'activo')) {
    $sqlPerfiles .= ' WHERE activo = 1';
}
$sqlPerfiles .= ' ORDER BY nombre';
$perfiles = $pdo->query($sqlPerfiles)->fetchAll();
$usernamesExistentes = $pdo->query('SELECT username FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);

if (!$sesionEsDeveloper) {
    $perfiles = array_values(array_filter($perfiles, static function (array $perfil): bool {
        return !es_perfil_developer((string) ($perfil['nombre'] ?? ''));
    }));
}

$ok = trim((string) ($_GET['ok'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'success';

if ($ok === 'creado') {
    $mensaje = 'Usuario creado correctamente.';
}

$errores = [
    'campos' => 'Completa los campos obligatorios.',
    'email' => 'El correo no es valido.',
    'username' => 'El usuario debe tener 3 a 60 caracteres y solo letras, numeros, punto, guion o guion bajo.',
    'telefono' => 'Ingresa un telefono internacional valido para el pais seleccionado.',
    'direccion' => 'La direccion excede el limite permitido.',
    'password' => 'La clave debe tener al menos 6 caracteres.',
    'perfil' => 'Selecciona un perfil valido.',
    'foto' => 'No fue posible cargar la foto. Verifica formato y tamano.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'duplicado' => 'El correo o nombre de usuario ya existe.',
    'developer' => 'Solo el developer puede crear o asignar cuentas con perfil developer.',
    'db' => 'No se pudo guardar el usuario por un error de base de datos.',
    'general' => 'No se pudo completar la operacion.',
];

if (isset($errores[$error])) {
    $mensaje = $errores[$error];
    $tipoMensaje = 'danger';
}
$csrfUsuariosGuardar = csrf_token('admin_usuarios_guardar');
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Registro de usuarios - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Registrar Usuario | Sietelsa</title>
        <link rel="icon" type="image/jpeg" href="../../assets/img/logos/sietelsaPestana.jpg" />
        <link rel="stylesheet" href="/assets/vendor/intl-tel-input/intlTelInput.css" />
        <link href="/admin/css/styles.css?v=20260613e" rel="stylesheet" />
        <style>
            .iti { width: 100%; }
            .iti--separate-dial-code .iti__selected-flag {
                background-color: #f8f9fa;
                border-right: 1px solid #ced4da;
                border-top-left-radius: 0.375rem;
                border-bottom-left-radius: 0.375rem;
                padding: 0 10px;
            }
            .iti__country-list {
                z-index: 1080;
            }
        </style>
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
                <?php render_admin_topnav($pdo, $usuario, $etiquetaSesion, $perfilSesion, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, isset($usuario) && is_array($usuario) ? $usuario : (isset($usuarioSesion) && is_array($usuarioSesion) ? $usuarioSesion : []), $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Registrar usuario nuevo</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Registro de usuarios</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-user-plus me-1"></i>
                                Informacion del usuario
                            </div>
                            <div class="card-body">
                                <form action="/admin/acciones/usuarios" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfUsuariosGuardar, ENT_QUOTES, 'UTF-8'); ?>" />
                                    <input type="hidden" name="accion" value="crear" />
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label" for="nombre">Nombre completo</label>
                                            <input class="form-control" id="nombre" name="nombre" type="text" maxlength="100" required />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="username">Usuario</label>
                                            <div class="input-group">
                                                <input class="form-control" id="username" name="username" type="text" minlength="3" maxlength="60" pattern="[A-Za-z0-9._\-]{3,60}" required />
                                                <button class="btn btn-outline-secondary" type="button" id="btnGenerarUsername">Generar</button>
                                            </div>
                                            <div class="form-text">Genera un usuario nuevo y unico. Puedes ajustarlo antes de guardar.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="email">Correo</label>
                                            <input class="form-control" id="email" name="email" type="email" maxlength="120" required />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="password">Clave</label>
                                            <input class="form-control" id="password" name="password" type="password" minlength="6" required />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="telefono">Telefono</label>
                                            <input class="form-control" id="telefono" name="telefono" type="tel" maxlength="30" required />
                                            <div class="invalid-feedback" id="telefonoError">Ingresa un numero valido.</div>
                                            <div class="form-text">Selecciona el codigo de pais y escribe el numero.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="perfil_id">Cargo / Perfil</label>
                                            <select class="form-select" id="perfil_id" name="perfil_id" required>
                                                <option value="">Selecciona un perfil...</option>
                                                <?php foreach ($perfiles as $perfil): ?>
                                                    <option value="<?php echo (int) $perfil['id']; ?>"><?php echo htmlspecialchars((string) $perfil['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="modo_modulos">Acceso rapido a modulos</label>
                                            <select class="form-select" id="modo_modulos" name="modo_modulos">
                                                <option value="perfil" selected>Usar plantilla del perfil (recomendado)</option>
                                                <option value="permitir_todo">Permitir todos los modulos</option>
                                                <option value="denegar_todo">Denegar todos los modulos</option>
                                            </select>
                                            <div class="form-text">Puedes ajustar despues en permisos detallados por usuario.</div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="direccion">Direccion</label>
                                            <input class="form-control" id="direccion" name="direccion" type="text" maxlength="200" required />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label" for="foto">Foto de usuario</label>
                                            <input class="form-control" id="foto" name="foto" type="file" accept="image/png,image/jpeg,.jpg,.jpeg,.png" />
                                            <div class="form-text">Formatos permitidos: JPG, JPEG o PNG.</div>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" id="activo" name="activo" type="checkbox" value="1" checked />
                                                <label class="form-check-label" for="activo">Usuario activo</label>
                                            </div>
                                        </div>
                                        <div class="col-12 d-flex gap-2">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fas fa-save me-1"></i>Guardar usuario
                                            </button>
                                            <a class="btn btn-outline-secondary" href="/admin/usuarios/editar">Ir a editar usuarios</a>
                                            <a class="btn btn-outline-dark" href="/admin/modulos/permisos">Permisos de modulos</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">Copyright &copy; Sietelsa <?php echo date('Y'); ?></div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
        <script src="/assets/vendor/intl-tel-input/intlTelInput.min.js"></script>
        <script src="/admin/js/scripts.js?v=20260613e"></script>
        <script>
            (function () {
                var telefonoInput = document.getElementById('telefono');
                var telefonoError = document.getElementById('telefonoError');
                var formUsuario = document.querySelector('form[action="/admin/acciones/usuarios"]');
                var usernameInput = document.getElementById('username');
                var botonGenerar = document.getElementById('btnGenerarUsername');
                var phoneCountries = ['sv', 'gt', 'hn', 'ni', 'cr', 'pa', 'mx', 'us'];
                var minLengthByCountry = {
                    sv: 8,
                    gt: 8,
                    hn: 8,
                    ni: 8,
                    cr: 8,
                    pa: 8,
                    mx: 10,
                    us: 10
                };
                if (!usernameInput || !botonGenerar) {
                    return;
                }

                var usernamesEnUso = new Set(
                    <?php echo json_encode(
                        array_values(array_map(static function ($username): string {
                            return strtolower(trim((string) $username));
                        }, is_array($usernamesExistentes) ? $usernamesExistentes : [])),
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    ); ?>
                );

                function randomInt(min, max) {
                    return Math.floor(Math.random() * (max - min + 1)) + min;
                }

                function generarUsernameUnico() {
                    var intento = 0;
                    var candidato = '';

                    do {
                        var bloqueA = randomInt(100, 999);
                        var bloqueB = randomInt(100, 999);
                        candidato = 'usr.' + bloqueA + '.' + bloqueB;
                        intento++;
                    } while (usernamesEnUso.has(candidato.toLowerCase()) && intento < 500);

                    if (intento >= 500) {
                        candidato = 'usr.' + Date.now().toString().slice(-8);
                    }

                    usernamesEnUso.add(candidato.toLowerCase());
                    usernameInput.value = candidato;
                }

                botonGenerar.addEventListener('click', function () {
                    generarUsernameUnico();
                });

                if (usernameInput.value.trim() === '') {
                    generarUsernameUnico();
                }

                if (telefonoInput && formUsuario && typeof window.intlTelInput === 'function') {
                    var iti = window.intlTelInput(telefonoInput, {
                        initialCountry: 'sv',
                        onlyCountries: phoneCountries,
                        preferredCountries: ['sv', 'mx', 'us'],
                        nationalMode: true,
                        separateDialCode: true,
                        utilsScript: '/assets/vendor/intl-tel-input/utils.js'
                    });
                    iti.setCountry('sv');

                    function marcarTelefonoInvalido(mensaje) {
                        telefonoInput.classList.remove('is-valid');
                        telefonoInput.classList.add('is-invalid');
                        telefonoInput.setCustomValidity(mensaje);
                        if (telefonoError) {
                            telefonoError.textContent = mensaje;
                        }
                    }

                    function marcarTelefonoValido() {
                        telefonoInput.classList.remove('is-invalid');
                        telefonoInput.classList.add('is-valid');
                        telefonoInput.setCustomValidity('');
                    }

                    function obtenerNumeroE164() {
                        var numero = '';
                        if (window.intlTelInputUtils && window.intlTelInputUtils.numberFormat) {
                            numero = iti.getNumber(window.intlTelInputUtils.numberFormat.E164);
                        } else {
                            var pais = iti.getSelectedCountryData() || {};
                            var dial = pais && pais.dialCode ? ('+' + pais.dialCode) : '';
                            var local = (telefonoInput.value || '').replace(/\D+/g, '');
                            numero = dial + local;
                        }
                        return (numero || '').trim();
                    }

                    function validarTelefono() {
                        var valor = (telefonoInput.value || '').trim();
                        if (valor === '') {
                            marcarTelefonoInvalido('Ingresa el numero telefonico.');
                            return false;
                        }

                        var soloDigitos = valor.replace(/\D+/g, '');
                        if (soloDigitos === '' || !/^\d+$/.test(soloDigitos)) {
                            marcarTelefonoInvalido('El telefono solo debe contener numeros.');
                            return false;
                        }

                        var pais = iti.getSelectedCountryData() || {};
                        var iso2 = (pais.iso2 || '').toLowerCase();
                        var minDigitos = minLengthByCountry[iso2] || 7;
                        if (soloDigitos.length < minDigitos) {
                            marcarTelefonoInvalido('El numero debe tener al menos ' + minDigitos + ' digitos para este pais.');
                            return false;
                        }

                        if (window.intlTelInputUtils && typeof iti.isValidNumber === 'function' && !iti.isValidNumber()) {
                            marcarTelefonoInvalido('El numero no es valido para el pais seleccionado.');
                            return false;
                        }

                        marcarTelefonoValido();
                        return true;
                    }

                    telefonoInput.addEventListener('input', function () {
                        var limpio = (telefonoInput.value || '').replace(/\D+/g, '');
                        if (telefonoInput.value !== limpio) {
                            telefonoInput.value = limpio;
                        }
                        validarTelefono();
                    });
                    telefonoInput.addEventListener('blur', validarTelefono);
                    telefonoInput.addEventListener('countrychange', validarTelefono);

                    formUsuario.addEventListener('submit', function (event) {
                        if (!validarTelefono()) {
                            event.preventDefault();
                            telefonoInput.focus();
                            return;
                        }

                        var numeroE164 = obtenerNumeroE164();
                        if (!/^\+\d{8,15}$/.test(numeroE164)) {
                            event.preventDefault();
                            marcarTelefonoInvalido('No se pudo generar un numero internacional valido.');
                            telefonoInput.focus();
                            return;
                        }

                        telefonoInput.value = numeroE164;
                    }, true);
                }
            })();
        </script>
    </body>
</html>

