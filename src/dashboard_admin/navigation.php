<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/content.php';
auth_require_admin();
$db = db_connection();
$notice = $error = '';
$defaults = [
    'servicios' => ['SERVICIOS', '#servicios', 10], 'portafolio' => ['PORTAFOLIO', '#portafolio', 20],
    'nosotros' => ['NOSOTROS', '#nosotros', 30], 'proyectos' => ['PROYECTOS', '#proyectos', 40],
    'ubicacion' => ['UBICACIÓN', '#ubicacion', 50], 'contacto' => ['CONTACTO', '#contacto', 60],
    'auth' => ['INICIAR SESIÓN', 'src/login/login.php', 70],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!auth_validate_csrf($_POST['csrf_token'] ?? null)) throw new RuntimeException('La solicitud expiró.');
        if (($_POST['action'] ?? '') === 'create') {
            $key = 'nav-' . bin2hex(random_bytes(5));
            $statement = $db->prepare(
                'INSERT INTO navigation_items
                 (nav_key,draft_label,published_label,draft_url,published_url,draft_visible,published_visible,status,updated_by)
                 VALUES (:key,"NUEVA OPCIÓN","NUEVA OPCIÓN","#","#",1,0,"draft",:user)'
            );
            $statement->execute(['key' => $key, 'user' => (int) auth_current_user()['id']]);
            $id = (int) $db->lastInsertId();
            cms_version($db, 'navigation', $id, $key, 'create', [], ['label' => 'NUEVA OPCIÓN']);
            cms_audit($db, 'create', 'navigation', $id);
            $notice = 'Opción creada como borrador.';
        } else {
          $rows = $db->query('SELECT * FROM navigation_items ORDER BY id')->fetchAll();
          foreach ($rows as $row) {
            $id = (int) $row['id'];
            $key = $row['nav_key'];
            $restore = ($_POST['action'] ?? '') === 'restore';
            $label = $restore ? $defaults[$key][0] : cms_clean_text($_POST['label'][$id] ?? '', 100);
            $url = $restore ? $defaults[$key][1] : cms_clean_url($_POST['url'][$id] ?? '');
            $order = $restore ? $defaults[$key][2] : (int) ($_POST['sort'][$id] ?? 0);
            $target = in_array($_POST['target'][$id] ?? '', ['_self', '_blank'], true) ? $_POST['target'][$id] : '_self';
            $access = in_array($_POST['access'][$id] ?? '', ['public', 'guest', 'authenticated', 'admin'], true) ? $_POST['access'][$id] : 'public';
            if ($url === '') throw new RuntimeException('Existe un enlace no válido.');
            if ($restore) { $target = '_self'; $access = $key === 'auth' ? 'guest' : 'public'; }
            if ((int) $row['is_system'] === 1) { $label = 'INICIAR SESIÓN'; $url = 'src/login/login.php'; $target = '_self'; $access = 'guest'; }
            $visible = isset($_POST['visible'][$id]) || (int) $row['is_system'] === 1;
            $publish = ($_POST['action'] ?? '') !== 'save';
            $sql = 'UPDATE navigation_items SET draft_label=:label,draft_url=:url,draft_target=:target,draft_access=:access,draft_sort_order=:sort,draft_visible=:visible,status=:status,updated_by=:user';
            $params = compact('label', 'url', 'target', 'access'); $params += ['sort' => $order, 'visible' => $visible ? 1 : 0, 'status' => $publish ? 'published' : 'draft', 'user' => (int) auth_current_user()['id'], 'id' => $id];
            if ($publish) { $sql .= ',published_label=:plabel,published_url=:purl,published_target=:ptarget,published_access=:paccess,published_sort_order=:psort,published_visible=:pvisible'; $params += ['plabel' => $label, 'purl' => $url, 'ptarget' => $target, 'paccess' => $access, 'psort' => $order, 'pvisible' => $visible ? 1 : 0]; }
            $sql .= ' WHERE id=:id';
            $db->prepare($sql)->execute($params);
            cms_version($db, 'navigation', $id, $key, $restore ? 'restore' : ($publish ? 'publish' : 'save_draft'), $row, ['label' => $label, 'url' => $url]);
          }
          cms_audit($db, (string) $_POST['action'], 'navigation', 0);
          $notice = 'Navegación actualizada.';
        }
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
}
$rows = $db->query('SELECT * FROM navigation_items ORDER BY draft_sort_order,id')->fetchAll();
$cmsPageTitle = 'Navbar';
require __DIR__ . '/partials/_cms-header.php';
?>
<div class="d-flex justify-content-between"><div><h1 class="h3">Administración de la navbar</h1><p class="text-muted">El acceso al sistema es una opción protegida y no puede eliminarse.</p></div><form method="post"><input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>"><button class="btn btn-outline-primary" name="action" value="create">Crear opción</button></form></div>
<?php if ($notice): ?><div class="alert alert-success"><?= auth_escape($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= auth_escape($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
<div class="card"><div class="card-body table-responsive"><table class="table"><thead><tr><th>Texto</th><th>Enlace</th><th>Destino</th><th>Acceso</th><th>Orden</th><th>Visible</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><input class="form-control" name="label[<?= (int) $row['id'] ?>]" value="<?= auth_escape($row['draft_label']) ?>" <?= $row['is_system'] ? 'readonly' : '' ?>></td><td><input class="form-control" name="url[<?= (int) $row['id'] ?>]" value="<?= auth_escape($row['draft_url']) ?>" <?= $row['is_system'] ? 'readonly' : '' ?>></td><td><select class="form-select" name="target[<?= (int) $row['id'] ?>]" <?= $row['is_system'] ? 'disabled' : '' ?>><option value="_self" <?= $row['draft_target']==='_self'?'selected':'' ?>>Misma ventana</option><option value="_blank" <?= $row['draft_target']==='_blank'?'selected':'' ?>>Nueva ventana</option></select></td><td><select class="form-select" name="access[<?= (int) $row['id'] ?>]" <?= $row['is_system'] ? 'disabled' : '' ?>><?php foreach (['public','guest','authenticated','admin'] as $access): ?><option value="<?= $access ?>" <?= $row['draft_access']===$access?'selected':'' ?>><?= $access ?></option><?php endforeach; ?></select></td><td><input class="form-control" type="number" name="sort[<?= (int) $row['id'] ?>]" value="<?= (int) $row['draft_sort_order'] ?>"></td><td><input type="checkbox" name="visible[<?= (int) $row['id'] ?>]" <?= (int) $row['draft_visible'] ? 'checked' : '' ?> <?= $row['is_system'] ? 'disabled checked' : '' ?>></td></tr><?php endforeach; ?>
</tbody></table><button class="btn btn-outline-primary" name="action" value="save">Guardar borrador</button> <button class="btn btn-primary" name="action" value="publish">Publicar</button> <button class="btn btn-outline-secondary" name="action" value="restore">Restaurar predeterminada</button></div></div></form>
<?php require __DIR__ . '/partials/_cms-footer.php'; ?>
