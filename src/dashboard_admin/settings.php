<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/content.php';
auth_require_admin();
$db = db_connection();
$notice = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!auth_validate_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('La solicitud expiró.');
        }
        $publish = ($_POST['action'] ?? '') === 'publish';
        if (($_POST['entity'] ?? '') === 'page') {
            $id = (int) $_POST['id'];
            $oldStatement = $db->prepare('SELECT * FROM pages WHERE id = :id');
            $oldStatement->execute(['id' => $id]);
            $old = $oldStatement->fetch();
            if (!$old) throw new RuntimeException('Página no encontrada.');
            $seo = [
                'title' => cms_clean_text($_POST['title'] ?? '', 180),
                'description' => cms_clean_text($_POST['description'] ?? '', 320),
                'keywords' => cms_clean_text($_POST['keywords'] ?? '', 500),
            ];
            $json = json_encode($seo, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $sql = 'UPDATE pages SET draft_seo=:draft, status=:status, updated_by=:user';
            $params = ['draft' => $json, 'status' => $publish ? 'published' : 'draft', 'user' => (int) auth_current_user()['id'], 'id' => $id];
            if ($publish) { $sql .= ', published_seo=:published'; $params['published'] = $json; }
            $sql .= ' WHERE id=:id';
            $db->prepare($sql)->execute($params);
            cms_version($db, 'page', $id, $old['slug'], $publish ? 'publish' : 'save_draft', $old, ['seo' => $seo]);
        } else {
            $id = (int) $_POST['id'];
            $oldStatement = $db->prepare('SELECT * FROM site_settings WHERE id=:id');
            $oldStatement->execute(['id' => $id]);
            $old = $oldStatement->fetch();
            if (!$old) throw new RuntimeException('Configuración no encontrada.');
            $value = cms_clean_text($_POST['value'] ?? '', 5000);
            if ($old['value_type'] === 'url') $value = cms_clean_url($value);
            if ($old['value_type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Correo no válido.');
            $json = json_encode(['value' => $value], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $sql = 'UPDATE site_settings SET draft_value=:draft,status=:status,updated_by=:user';
            $params = ['draft' => $json, 'status' => $publish ? 'published' : 'draft', 'user' => (int) auth_current_user()['id'], 'id' => $id];
            if ($publish) { $sql .= ',published_value=:published'; $params['published'] = $json; }
            $sql .= ' WHERE id=:id';
            $db->prepare($sql)->execute($params);
            cms_version($db, 'setting', $id, $old['setting_key'], $publish ? 'publish' : 'save_draft', $old, ['value' => $value]);
        }
        cms_audit($db, $publish ? 'publish' : 'save_draft', (string) ($_POST['entity'] ?? 'setting'), (int) $_POST['id']);
        $notice = $publish ? 'Cambios publicados.' : 'Borrador guardado.';
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible guardar.';
        error_log('[SIETELSA] Configuración: ' . $exception->getMessage());
    }
}
$settings = $db->query('SELECT * FROM site_settings ORDER BY setting_group, setting_key')->fetchAll();
$pages = $db->query('SELECT * FROM pages ORDER BY name')->fetchAll();
$cmsPageTitle = 'Configuración y SEO';
require __DIR__ . '/partials/_cms-header.php';
?>
<h1 class="h3 mb-4">Configuración general, footer, redes sociales y SEO</h1>
<?php if ($notice): ?><div class="alert alert-success"><?= auth_escape($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= auth_escape($error) ?></div><?php endif; ?>
<?php foreach ($pages as $page): $seo = cms_json($page['draft_seo']); ?>
<div class="card mb-4"><div class="card-body"><h2 class="h5">SEO: <?= auth_escape($page['name']) ?></h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>"><input type="hidden" name="entity" value="page"><input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
<div class="row g-3"><div class="col-12"><label class="form-label">Título</label><input class="form-control" name="title" value="<?= auth_escape($seo['title'] ?? '') ?>"></div><div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="description"><?= auth_escape($seo['description'] ?? '') ?></textarea></div><div class="col-12"><label class="form-label">Palabras clave</label><input class="form-control" name="keywords" value="<?= auth_escape($seo['keywords'] ?? '') ?>"></div></div>
<div class="mt-3"><button class="btn btn-outline-primary" name="action" value="save">Guardar borrador</button> <button class="btn btn-primary" name="action" value="publish">Publicar</button></div></form></div></div>
<?php endforeach; ?>
<div class="row">
<?php foreach ($settings as $setting): $value = cms_json($setting['draft_value'])['value'] ?? ''; ?>
<div class="col-lg-6 mb-3"><div class="card h-100"><div class="card-body"><form method="post">
<input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>"><input type="hidden" name="entity" value="setting"><input type="hidden" name="id" value="<?= (int) $setting['id'] ?>">
<label class="form-label"><?= auth_escape($setting['setting_key']) ?> <span class="badge badge-secondary"><?= auth_escape($setting['setting_group']) ?></span></label>
<?php if ($setting['value_type'] === 'textarea'): ?><textarea class="form-control" rows="3" name="value"><?= auth_escape($value) ?></textarea><?php else: ?><input class="form-control" name="value" value="<?= auth_escape($value) ?>"><?php endif; ?>
<div class="mt-3"><button class="btn btn-sm btn-outline-primary" name="action" value="save">Borrador</button> <button class="btn btn-sm btn-primary" name="action" value="publish">Publicar</button></div>
</form></div></div></div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/partials/_cms-footer.php'; ?>
