<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/content.php';
auth_require_admin();
$media = db_connection()->query('SELECT id,original_name,alt_text FROM media_library WHERE deleted_at IS NULL ORDER BY created_at DESC')->fetchAll();
$cmsPageTitle = 'Editor visual del website';
require __DIR__ . '/partials/_cms-header.php';
?>
<style>
.cms-editor-grid{display:grid;grid-template-columns:minmax(320px,380px) 1fr;gap:1rem}.cms-preview-shell{background:#e8eaf0;padding:1rem;overflow:auto}.cms-preview-shell iframe{display:block;width:100%;height:76vh;border:0;background:#fff;margin:auto;transition:width .2s}.cms-preview-shell[data-device=tablet] iframe{width:768px}.cms-preview-shell[data-device=mobile] iframe{width:390px}@media(max-width:992px){.cms-editor-grid{grid-template-columns:1fr}.cms-preview-shell iframe{height:60vh}}
</style>
<div class="d-flex flex-wrap justify-content-between mb-3"><div><h1 class="h3">Editor visual del website</h1><p class="text-muted">Selecciona un texto dentro de la vista real. Los cambios se previsualizan sin guardar por cada tecla.</p></div><div class="btn-group"><button class="btn btn-outline-primary cms-device" data-device="desktop">Escritorio</button><button class="btn btn-outline-primary cms-device" data-device="tablet">Tablet</button><button class="btn btn-outline-primary cms-device" data-device="mobile">Celular</button></div></div>
<div class="cms-editor-grid">
  <aside class="card"><div class="card-body">
    <div id="editorEmpty" class="text-muted">Haz clic sobre un elemento editable del website.</div>
    <form id="visualForm" hidden>
      <input type="hidden" id="entity"><input type="hidden" id="entityId">
      <div class="d-flex justify-content-between"><strong id="selectionName">Contenido</strong><span id="contentStatus" class="badge badge-warning">Borrador</span></div>
      <div id="fieldList" class="mt-3"></div>
      <label class="form-label mt-3">Imagen</label><select class="form-select" id="mediaId"><option value="">Sin imagen</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" data-url="<?= auth_escape(cms_media_url(cms_media_row((int) $image['id']), 'large')) ?>"><?= auth_escape($image['alt_text'] ?: $image['original_name']) ?></option><?php endforeach; ?></select>
      <div class="row mt-3"><div class="col-6"><label class="form-label">Orden</label><input class="form-control" type="number" id="sortOrder"></div><div class="col-6 d-flex align-items-end"><label><input type="checkbox" id="visible"> Visible</label></div></div>
      <div id="unsaved" class="alert alert-warning py-2 mt-3" hidden>Hay cambios sin guardar.</div>
      <small class="text-muted d-block" id="updatedAt"></small>
      <div class="d-flex flex-wrap gap-2 mt-3"><button type="button" class="btn btn-outline-secondary" id="undo">Deshacer</button><button type="button" class="btn btn-outline-primary" data-action="save">Guardar borrador</button><button type="button" class="btn btn-primary" data-action="publish">Publicar</button></div>
    </form>
  </div></aside>
  <div class="cms-preview-shell" id="previewShell" data-device="desktop"><iframe id="websitePreview" title="Vista previa del website" src="<?= auth_escape(app_url('index.php?preview=1')) ?>"></iframe></div>
</div>
<script>
window.SIETELSA_EDITOR = <?= json_encode(['origin' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 'api' => app_url('src/dashboard_admin/api/content.php'), 'csrf' => auth_csrf_token()], JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= auth_escape(app_url('src/dashboard_admin/assets/js/cms-editor.js')) ?>"></script>
<?php require __DIR__ . '/partials/_cms-footer.php'; ?>
