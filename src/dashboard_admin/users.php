<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/content.php';
auth_require_admin();
$db = db_connection();
$notice = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!auth_validate_csrf($_POST['csrf_token'] ?? null)) throw new RuntimeException('La solicitud expiró.');
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) auth_current_user()['id']) throw new RuntimeException('No puedes modificar tu propia cuenta desde esta pantalla.');
        $role = in_array($_POST['role'] ?? '', ['admin', 'usuario'], true) ? $_POST['role'] : 'usuario';
        $active = isset($_POST['active']) ? 1 : 0;
        $statement = $db->prepare('UPDATE usuarios SET rol=:role, activo=:active WHERE id=:id');
        $statement->execute(compact('role', 'active', 'id'));
        cms_audit($db, 'update_permissions', 'user', $id, ['role' => $role, 'active' => $active]);
        $notice = 'Permisos del usuario actualizados.';
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
}
$users = $db->query('SELECT id,nombre,username,email,rol,activo,ultimo_login,creado_en FROM usuarios ORDER BY creado_en DESC')->fetchAll();
$cmsPageTitle = 'Usuarios';
require __DIR__ . '/partials/_cms-header.php';
?>
<h1 class="h3">Usuarios y roles</h1><p class="text-muted">Los roles disponibles son administrador y usuario. La autorización se comprueba siempre en PHP.</p>
<?php if ($notice): ?><div class="alert alert-success"><?= auth_escape($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= auth_escape($error) ?></div><?php endif; ?>
<div class="card"><div class="card-body table-responsive"><table class="table"><thead><tr><th>Usuario</th><th>Correo</th><th>Último acceso</th><th>Permisos</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr><td><?= auth_escape($user['nombre']) ?><br><small><?= auth_escape($user['username']) ?></small></td><td><?= auth_escape($user['email']) ?></td><td><?= auth_escape($user['ultimo_login'] ?? 'Nunca') ?></td><td><form method="post" class="d-flex gap-2 align-items-center"><input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><select class="form-select form-select-sm" name="role" <?= (int) $user['id'] === (int) auth_current_user()['id'] ? 'disabled' : '' ?>><option value="admin" <?= $user['rol']==='admin'?'selected':'' ?>>administrador</option><option value="usuario" <?= $user['rol']==='usuario'?'selected':'' ?>>usuario</option></select><label><input type="checkbox" name="active" <?= (int) $user['activo']?'checked':'' ?> <?= (int) $user['id'] === (int) auth_current_user()['id'] ? 'disabled' : '' ?>> Activo</label><button class="btn btn-sm btn-primary" <?= (int) $user['id'] === (int) auth_current_user()['id'] ? 'disabled' : '' ?>>Guardar</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/partials/_cms-footer.php'; ?>
