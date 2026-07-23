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

$usuarioSesion = obtener_usuario_actual() ?? [];
$nombreSesion = trim((string) ($usuarioSesion['nombre'] ?? ''));
$usernameSesion = trim((string) ($usuarioSesion['username'] ?? ''));
$perfilSesion = trim((string) ($usuarioSesion['perfil'] ?? ''));
$sesionEsDeveloper = es_perfil_developer($perfilSesion);
$usuarioSesionId = (int) ($usuarioSesion['id'] ?? 0);
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

$usuarios = $pdo->query(
    'SELECT u.id, u.nombre, u.username, u.email, u.telefono, u.direccion, u.foto_path, u.activo, u.perfil_id, p.nombre AS perfil, LOWER(TRIM(p.nombre)) AS perfil_normalizado
     FROM usuarios u
     INNER JOIN perfiles p ON p.id = u.perfil_id
     ORDER BY u.nombre ASC'
)->fetchAll();

$perfiles = $pdo->query('SELECT id, nombre FROM perfiles WHERE activo = 1 ORDER BY nombre')->fetchAll();

if (!$sesionEsDeveloper) {
    $perfiles = array_values(array_filter($perfiles, static function (array $perfil): bool {
        return !es_perfil_developer((string) ($perfil['nombre'] ?? ''));
    }));
}

foreach ($usuarios as &$filaUsuario) {
    $idFila = (int) ($filaUsuario['id'] ?? 0);
    $esDeveloperObjetivo = es_perfil_developer((string) ($filaUsuario['perfil_normalizado'] ?? ''));
    $bloqueado = (!$sesionEsDeveloper && $esDeveloperObjetivo);

    $filaUsuario['bloqueado_edicion'] = $bloqueado;
    if (!$sesionEsDeveloper && $esDeveloperObjetivo) {
        $filaUsuario['motivo_bloqueo'] = 'Solo el developer puede editar cuentas developer.';
    } else {
        $filaUsuario['motivo_bloqueo'] = '';
    }
}
unset($filaUsuario);

$usuarioId = (int) ($_GET['id'] ?? 0);
$usuarioSolicitadoBloqueado = false;
$errorUsuarioSolicitado = '';
if ($usuarioId > 0) {
    foreach ($usuarios as $itemUsuario) {
        if ((int) ($itemUsuario['id'] ?? 0) !== $usuarioId) {
            continue;
        }
        $usuarioSolicitadoBloqueado = (bool) ($itemUsuario['bloqueado_edicion'] ?? false);
        if ($usuarioSolicitadoBloqueado) {
            $errorUsuarioSolicitado = 'developer';
        }
        break;
    }
}

$usuarioEdicion = null;
foreach ($usuarios as $itemUsuario) {
    if ((bool) ($itemUsuario['bloqueado_edicion'] ?? false)) {
        continue;
    }
    if ($usuarioId <= 0 || (int) $itemUsuario['id'] === $usuarioId) {
        $usuarioEdicion = $itemUsuario;
        $usuarioId = (int) $itemUsuario['id'];
        break;
    }
}
$esEdicionPropia = $usuarioEdicion !== null
    && $usuarioSesionId > 0
    && (int) ($usuarioEdicion['id'] ?? 0) === $usuarioSesionId;

$ok = trim((string) ($_GET['ok'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$mensaje = '';
$tipoMensaje = 'success';

if ($ok === 'actualizado') {
    $mensaje = 'Usuario actualizado correctamente.';
}

$errores = [
    'campos' => 'Completa los campos obligatorios.',
    'email' => 'El correo no es valido.',
    'username' => 'El usuario debe tener 3 a 60 caracteres y solo letras, numeros, punto, guion o guion bajo.',
    'telefono' => 'Ingresa un telefono internacional valido para el pais seleccionado.',
    'direccion' => 'La direccion excede el limite permitido.',
    'password' => 'La clave debe tener al menos 6 caracteres.',
    'id' => 'No se encontro el usuario seleccionado.',
    'perfil' => 'Selecciona un perfil valido.',
    'foto' => 'No fue posible cargar la foto. Verifica formato y tamano.',
    'csrf' => 'Token CSRF invalido o expirado. Recarga la pagina e intenta de nuevo.',
    'duplicado' => 'El correo o nombre de usuario ya existe.',
    'developer' => 'Solo el developer puede crear, editar o reasignar cuentas developer.',
    'db' => 'No se pudo actualizar por un error de base de datos.',
    'general' => 'No se pudo completar la operacion.',
];

if ($error === '' && $usuarioSolicitadoBloqueado && $errorUsuarioSolicitado !== '') {
    $error = $errorUsuarioSolicitado;
}

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
        <meta name="description" content="Edicion de usuarios - Sietelsa" />
        <meta name="author" content="Sietelsa" />
        <title>Editar Usuarios | Sietelsa</title>
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
                <?php render_admin_topnav($pdo, $usuarioSesion, $etiquetaSesion, $perfilSesion, $puedeGestionarContacto); ?>
        <div id="layoutSidenav">
            <?php render_admin_sidebar($pdo, isset($usuario) && is_array($usuario) ? $usuario : (isset($usuarioSesion) && is_array($usuarioSesion) ? $usuarioSesion : []), $etiquetaSesion); ?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <h1>Editar usuarios</h1>
                            <a class="btn btn-primary btn-sm" href="/admin/usuarios/registro">
                                <i class="fas fa-user-plus me-1"></i>Nuevo usuario
                            </a>
                        </div>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Edicion de usuarios</li>
                        </ol>

                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-<?php echo $tipoMensaje === 'danger' ? 'danger' : 'success'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-users me-1"></i>
                                Usuarios registrados
                            </div>
                            <div class="card-body">
                                <?php if (empty($usuarios)): ?>
                                    <p class="mb-0 text-muted">No hay usuarios para editar.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Nombre</th>
                                                    <th>Usuario</th>
                                                    <th>Perfil</th>
                                                    <th>Estado</th>
                                                    <th>Accion</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($usuarios as $filaUsuario): ?>
                                                    <?php
                                                    $filaBloqueada = (bool) ($filaUsuario['bloqueado_edicion'] ?? false);
                                                    $motivoBloqueo = (string) ($filaUsuario['motivo_bloqueo'] ?? '');
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars((string) $filaUsuario['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string) $filaUsuario['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td><?php echo htmlspecialchars((string) $filaUsuario['perfil'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td>
                                                            <?php if ((int) $filaUsuario['activo'] === 1): ?>
                                                                <span class="badge bg-success">Activo</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Inactivo</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($filaBloqueada): ?>
                                                                <button class="btn btn-outline-secondary btn-sm" type="button" disabled title="<?php echo htmlspecialchars($motivoBloqueo, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    Bloqueado
                                                                </button>
                                                            <?php else: ?>
                                                                <a class="btn btn-outline-primary btn-sm" href="/admin/usuarios/editar?id=<?php echo (int) $filaUsuario['id']; ?>">Editar</a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($usuarioEdicion !== null): ?>
                            <?php
                            $fotoPath = trim((string) ($usuarioEdicion['foto_path'] ?? ''));
                            $fotoExiste = ($fotoPath !== '') && is_file(__DIR__ . '/../../' . $fotoPath);
                            ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-user-pen me-1"></i>
                                    Editando: <?php echo htmlspecialchars((string) $usuarioEdicion['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="card-body">
                                    <form action="/admin/acciones/usuarios" method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfUsuariosGuardar, ENT_QUOTES, 'UTF-8'); ?>" />
                                        <input type="hidden" name="accion" value="editar" />
                                        <input type="hidden" name="usuario_id" value="<?php echo (int) $usuarioEdicion['id']; ?>" />

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="nombre">Nombre completo</label>
                                                <input
                                                    class="form-control"
                                                    id="nombre"
                                                    name="nombre"
                                                    type="text"
                                                    maxlength="100"
                                                    value="<?php echo htmlspecialchars((string) $usuarioEdicion['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    required
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="username">Usuario</label>
                                                <input
                                                    class="form-control"
                                                    id="username"
                                                    name="username"
                                                    type="text"
                                                    minlength="3"
                                                    maxlength="60"
                                                    pattern="[A-Za-z0-9._\-]{3,60}"
                                                    value="<?php echo htmlspecialchars((string) $usuarioEdicion['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    required
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="email">Correo</label>
                                                <input
                                                    class="form-control"
                                                    id="email"
                                                    name="email"
                                                    type="email"
                                                    maxlength="120"
                                                    value="<?php echo htmlspecialchars((string) $usuarioEdicion['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    required
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="password">Nueva clave (opcional)</label>
                                                <input class="form-control" id="password" name="password" type="password" minlength="6" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="telefono">Telefono</label>
                                                <input
                                                    class="form-control"
                                                    id="telefono"
                                                    name="telefono"
                                                    type="tel"
                                                    maxlength="30"
                                                    value="<?php echo htmlspecialchars((string) ($usuarioEdicion['telefono'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    required
                                                />
                                                <div class="invalid-feedback" id="telefonoError">Ingresa un numero valido.</div>
                                                <div class="form-text">Selecciona el codigo de pais y escribe el numero.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="perfil_id">Cargo / Perfil</label>
                                                <select
                                                    class="form-select"
                                                    id="perfil_id"
                                                    name="perfil_id"
                                                    required
                                                    <?php echo $esEdicionPropia ? 'disabled' : ''; ?>
                                                >
                                                    <?php foreach ($perfiles as $perfil): ?>
                                                        <?php $seleccionadoPerfil = ((int) $usuarioEdicion['perfil_id'] === (int) $perfil['id']); ?>
                                                        <option value="<?php echo (int) $perfil['id']; ?>" <?php echo $seleccionadoPerfil ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars((string) $perfil['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if ($esEdicionPropia): ?>
                                                    <input type="hidden" name="perfil_id" value="<?php echo (int) $usuarioEdicion['perfil_id']; ?>" />
                                                    <div class="form-text">Tu tipo de perfil no puede ser cambiado desde tu propia cuenta.</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="modo_modulos">Acceso rapido a modulos</label>
                                                <select class="form-select" id="modo_modulos" name="modo_modulos">
                                                    <option value="no_cambiar" selected>No cambiar accesos actuales</option>
                                                    <option value="perfil">Usar plantilla del perfil</option>
                                                    <option value="permitir_todo">Permitir todos los modulos</option>
                                                    <option value="denegar_todo">Denegar todos los modulos</option>
                                                </select>
                                                <div class="form-text">Ajuste fino disponible en el modulo de permisos.</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label" for="direccion">Direccion</label>
                                                <input
                                                    class="form-control"
                                                    id="direccion"
                                                    name="direccion"
                                                    type="text"
                                                    maxlength="200"
                                                    value="<?php echo htmlspecialchars((string) ($usuarioEdicion['direccion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    required
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="foto">Actualizar foto</label>
                                                <input class="form-control" id="foto" name="foto" type="file" accept="image/png,image/jpeg,.jpg,.jpeg,.png" />
                                                <div class="form-text">Formatos permitidos: JPG, JPEG o PNG.</div>
                                            </div>
                                            <div class="col-md-6 d-flex flex-column justify-content-end">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" id="activo" name="activo" type="checkbox" value="1" <?php echo ((int) $usuarioEdicion['activo'] === 1) ? 'checked' : ''; ?> />
                                                    <label class="form-check-label" for="activo">Usuario activo</label>
                                                </div>
                                                <?php if ($fotoExiste): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" id="eliminar_foto" name="eliminar_foto" type="checkbox" value="1" />
                                                        <label class="form-check-label" for="eliminar_foto">Eliminar foto actual</label>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($fotoExiste): ?>
                                                <div class="col-12">
                                                    <label class="form-label">Foto actual</label><br />
                                                    <img
                                                        src="../../<?php echo htmlspecialchars($fotoPath, ENT_QUOTES, 'UTF-8'); ?>"
                                                        alt="Foto de usuario"
                                                        style="max-height: 120px; border-radius: 8px; border: 1px solid #ced4da;"
                                                    />
                                                </div>
                                            <?php endif; ?>
                                            <div class="col-12 d-flex gap-2">
                                                <button class="btn btn-primary" type="submit">
                                                    <i class="fas fa-save me-1"></i>Actualizar usuario
                                                </button>
                                                <a class="btn btn-outline-dark" href="/admin/modulos/permisos?user_id=<?php echo (int) $usuarioEdicion['id']; ?>">Permisos de modulos</a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning" role="alert">
                                No hay cuentas disponibles para edicion con tu perfil actual.
                            </div>
                        <?php endif; ?>
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
                if (!telefonoInput || !formUsuario || typeof window.intlTelInput !== 'function') {
                    return;
                }

                var iti = window.intlTelInput(telefonoInput, {
                    initialCountry: 'sv',
                    onlyCountries: phoneCountries,
                    preferredCountries: ['sv', 'mx', 'us'],
                    nationalMode: true,
                    separateDialCode: true,
                    utilsScript: '/assets/vendor/intl-tel-input/utils.js'
                });
                iti.setCountry('sv');

                if ((telefonoInput.value || '').trim() !== '') {
                    iti.setNumber(telefonoInput.value);
                }

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
            })();
        </script>
    </body>
</html>

