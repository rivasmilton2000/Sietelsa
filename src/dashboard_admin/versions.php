<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/content.php';
auth_require_admin();
$versions = db_connection()->query(
    'SELECT v.*, u.nombre FROM content_versions v LEFT JOIN usuarios u ON u.id=v.created_by
     ORDER BY v.created_at DESC LIMIT 200'
)->fetchAll();
$cmsPageTitle = 'Historial de versiones';
require __DIR__ . '/partials/_cms-header.php';
?>
<h1 class="h3">Historial de versiones</h1><p class="text-muted">Cada guardado o publicación registra quién, cuándo y qué entidad cambió.</p>
<div id="versionMessage"></div>
<div class="card"><div class="card-body table-responsive"><table class="table table-hover"><thead><tr><th>Fecha</th><th>Usuario</th><th>Entidad</th><th>Clave</th><th>Acción</th><th></th></tr></thead><tbody>
<?php foreach ($versions as $version): ?><tr><td><?= auth_escape($version['created_at']) ?></td><td><?= auth_escape($version['nombre'] ?? 'Sistema') ?></td><td><?= auth_escape($version['entity_type']) ?> #<?= (int) $version['entity_id'] ?></td><td><?= auth_escape($version['entity_key']) ?></td><td><?= auth_escape($version['action']) ?></td><td><?php if (!empty($version['old_data'])): ?><button class="btn btn-sm btn-outline-primary restore-version" data-id="<?= (int) $version['id'] ?>">Restaurar</button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<script>
document.querySelectorAll('.restore-version').forEach(button => button.addEventListener('click', async () => {
  const confirmation = await window.SietelsaAlert.confirm('Se restaurarán los datos anteriores y se conservará un nuevo registro en el historial.', 'Restaurar versión');
  if (!confirmation.isConfirmed) return;
  const response = await fetch(<?= json_encode(app_url('src/dashboard_admin/api/content.php')) ?>, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'restore',version_id:Number(button.dataset.id),csrf_token:<?= json_encode(auth_csrf_token()) ?>})});
  const result = await response.json();
  document.getElementById('versionMessage').innerHTML = `<div class="alert alert-${result.ok?'success':'danger'}">${String(result.message).replace(/[<>&"']/g,'')}</div>`;
  if (result.ok) button.disabled = true;
}));
</script>
<?php require __DIR__ . '/partials/_cms-footer.php'; ?>
