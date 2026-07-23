<?php
declare(strict_types=1);

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db_setup.php';

function sietelsa_profile_safe_asset_url(?string $path, string $fallback = '/assets/img/logos/sietelsaPestana.jpg'): string
{
    $path = trim((string) $path);
    if ($path === '' || preg_match('#^(?:https?:)?//#i', $path) === 1) {
        return $fallback;
    }

    return '/' . ltrim($path, '/');
}

function sietelsa_profile_current_user(PDO $pdo, array $usuarioSesion): array
{
    $usuarioId = (int) ($usuarioSesion['id'] ?? 0);
    if ($usuarioId <= 0) {
        return $usuarioSesion;
    }

    try {
        asegurar_compatibilidad_auth($pdo);
    } catch (Throwable $e) {
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.nombre, u.username, u.email, u.telefono, u.direccion, u.foto_path, u.perfil_id, p.nombre AS perfil
             FROM usuarios u
             LEFT JOIN perfiles p ON p.id = u.perfil_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $usuarioId]);
        $usuario = $stmt->fetch();
        if (is_array($usuario) && !empty($usuario)) {
            return array_merge($usuarioSesion, $usuario);
        }
    } catch (Throwable $e) {
    }

    return $usuarioSesion;
}

function sietelsa_profile_message(): array
{
    $ok = trim((string) ($_GET['perfil_ok'] ?? ''));
    $error = trim((string) ($_GET['perfil_error'] ?? ''));

    $mensajesOk = [
        'foto' => 'Foto de perfil actualizada correctamente.',
        'password' => 'Contrasena actualizada correctamente.',
    ];
    $mensajesError = [
        'csrf' => 'La sesion expiro. Recarga la pagina e intenta de nuevo.',
        'foto' => 'No fue posible cargar la foto. Usa solo imagenes JPG o PNG.',
        'password_actual' => 'La contrasena actual no es correcta.',
        'password_confirmar' => 'La nueva contrasena no coincide.',
        'password_largo' => 'La nueva contrasena debe tener al menos 6 caracteres.',
        'password_igual' => 'La nueva contrasena debe ser diferente a la actual.',
        'db' => 'No se pudo guardar el cambio por un error de base de datos.',
        'general' => 'No se pudo completar la operacion.',
    ];

    if ($ok !== '' && isset($mensajesOk[$ok])) {
        return ['type' => 'success', 'text' => $mensajesOk[$ok]];
    }

    if ($error !== '' && isset($mensajesError[$error])) {
        return ['type' => 'danger', 'text' => $mensajesError[$error]];
    }

    return ['type' => '', 'text' => ''];
}

function render_sietelsa_profile_modal(PDO $pdo, array $usuarioSesion, string $perfilSesion, string $modalId = 'perfilUsuarioModal'): void
{
    $usuario = sietelsa_profile_current_user($pdo, $usuarioSesion);
    $nombre = trim((string) ($usuario['nombre'] ?? ''));
    $username = trim((string) ($usuario['username'] ?? ''));
    $email = trim((string) ($usuario['email'] ?? ''));
    $telefono = trim((string) ($usuario['telefono'] ?? ''));
    $direccion = trim((string) ($usuario['direccion'] ?? ''));
    $perfil = trim((string) ($usuario['perfil'] ?? $perfilSesion));
    $fotoUrl = sietelsa_profile_safe_asset_url($usuario['foto_path'] ?? null);
    $mensaje = sietelsa_profile_message();
    $fotoToken = csrf_token('perfil_foto');
    $passwordToken = csrf_token('perfil_password');
    ?>
        <div class="modal fade" id="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1" aria-labelledby="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>Label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>Label">Mi perfil</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($mensaje['text'] !== ''): ?>
                            <div class="alert alert-<?php echo $mensaje['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert">
                                <?php echo htmlspecialchars($mensaje['text'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-column flex-md-row gap-4 align-items-md-start">
                            <div class="text-center" style="min-width:150px;">
                                <img src="<?php echo htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de perfil" width="112" height="112" loading="lazy" decoding="async" style="width:112px;height:112px;border-radius:50%;object-fit:cover;border:3px solid #eef2ff;" />
                                <div class="fw-semibold mt-3"><?php echo htmlspecialchars($nombre !== '' ? $nombre : 'Usuario', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($username !== '' ? $username : 'sin usuario', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="flex-grow-1">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <div class="small text-muted">Nombre</div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($nombre !== '' ? $nombre : 'No definido', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted">Usuario</div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($username !== '' ? $username : 'No definido', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted">Correo</div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($email !== '' ? $email : 'No definido', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted">Perfil</div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($perfil !== '' ? $perfil : 'No definido', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <?php if ($telefono !== '' || $direccion !== ''): ?>
                                        <div class="col-md-6">
                                            <div class="small text-muted">Telefono</div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($telefono !== '' ? $telefono : 'No definido', ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="small text-muted">Direccion</div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($direccion !== '' ? $direccion : 'No definida', ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <hr />

                                <form class="mb-4" method="post" action="/admin/acciones/perfil" enctype="multipart/form-data">
                                    <input type="hidden" name="accion" value="foto" />
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($fotoToken, ENT_QUOTES, 'UTF-8'); ?>" />
                                    <label class="form-label fw-semibold" for="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>Foto">Cambiar foto de perfil</label>
                                    <input class="form-control" id="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>Foto" name="foto" type="file" accept="image/png,image/jpeg,.jpg,.jpeg,.png" />
                                    <div class="form-text">Formatos permitidos: JPG, JPEG o PNG.</div>
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <button class="btn btn-primary btn-sm" type="submit">Guardar foto</button>
                                        <?php if (trim((string) ($usuario['foto_path'] ?? '')) !== ''): ?>
                                            <button class="btn btn-outline-secondary btn-sm" type="submit" name="eliminar_foto" value="1">Quitar foto</button>
                                        <?php endif; ?>
                                    </div>
                                </form>

                                <form method="post" action="/admin/acciones/perfil">
                                    <input type="hidden" name="accion" value="password" />
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($passwordToken, ENT_QUOTES, 'UTF-8'); ?>" />
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label class="form-label fw-semibold" for="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>PasswordActual">Cambiar contrasena</label>
                                            <input class="form-control" id="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>PasswordActual" name="password_actual" type="password" autocomplete="current-password" placeholder="Contrasena actual" required />
                                        </div>
                                        <div class="col-md-6">
                                            <input class="form-control" name="password_nueva" type="password" minlength="6" autocomplete="new-password" placeholder="Nueva contrasena" required />
                                        </div>
                                        <div class="col-md-6">
                                            <input class="form-control" name="password_confirmar" type="password" minlength="6" autocomplete="new-password" placeholder="Confirmar contrasena" required />
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-sm mt-3" type="submit">Guardar contrasena</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (trim((string) ($_GET['perfil_modal'] ?? '')) === '1'): ?>
            <script>
                window.addEventListener('DOMContentLoaded', function () {
                    var modalElement = document.getElementById('<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>');
                    if (modalElement && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
                    }
                });
            </script>
        <?php endif; ?>
    <?php
}
