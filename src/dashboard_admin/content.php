<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/content.php';
auth_require_admin();
$db = db_connection();
$module = preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['module'] ?? '')) ?: '';
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!auth_validate_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('La solicitud expiró.');
        }
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'create') {
            $sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
            $sectionCheck = $db->prepare('SELECT id FROM page_sections WHERE id = :id');
            $sectionCheck->execute(['id' => $sectionId]);
            if (!$sectionCheck->fetchColumn()) {
                throw new RuntimeException('La sección no existe.');
            }
            $key = 'item-' . bin2hex(random_bytes(6));
            $empty = json_encode(['title' => 'Nuevo elemento', 'content' => ''], JSON_UNESCAPED_UNICODE);
            $statement = $db->prepare(
                'INSERT INTO content_items
                 (section_id, item_key, item_type, draft_data, published_data, status, draft_visible, published_visible, updated_by)
                 VALUES (:section_id, :item_key, :item_type, :draft_data, :published_data, "draft", 1, 0, :user_id)'
            );
            $statement->execute([
                'section_id' => $sectionId, 'item_key' => $key,
                'item_type' => cms_clean_text($_POST['item_type'] ?? 'content', 60),
                'draft_data' => $empty, 'published_data' => $empty,
                'user_id' => (int) auth_current_user()['id'],
            ]);
            $newId = (int) $db->lastInsertId();
            cms_version($db, 'item', $newId, $key, 'create', [], ['draft_data' => $empty]);
            cms_audit($db, 'create', 'item', $newId);
            $notice = 'Elemento creado como borrador.';
        } elseif ($action === 'delete') {
            if (($_POST['entity'] ?? '') !== 'item') {
                throw new RuntimeException('Solo los elementos repetibles pueden retirarse.');
            }
            $id = (int) ($_POST['id'] ?? 0);
            $oldStatement = $db->prepare('SELECT * FROM content_items WHERE id=:id');
            $oldStatement->execute(['id' => $id]);
            $old = $oldStatement->fetch();
            if (!$old) throw new RuntimeException('El elemento no existe.');
            $db->prepare('UPDATE content_items SET draft_visible=0,published_visible=0,status="published",updated_by=:user WHERE id=:id')
                ->execute(['user' => (int) auth_current_user()['id'], 'id' => $id]);
            cms_version($db, 'item', $id, $old['item_key'], 'delete', $old, ['visible' => false]);
            cms_audit($db, 'delete', 'item', $id, ['soft_delete' => true]);
            $notice = 'Elemento retirado sin destruir su historial.';
        } else {
            $entity = (string) ($_POST['entity'] ?? '');
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $payload = [];
            foreach (CMS_ALLOWED_FIELDS as $field) {
                if (isset($_POST[$field])) {
                    $payload[$field] = $_POST[$field];
                }
            }
            cms_save_content(
                $entity,
                (int) $id,
                $payload,
                ($_POST['media_id'] ?? '') === '' ? null : (int) $_POST['media_id'],
                (int) ($_POST['sort_order'] ?? 0),
                isset($_POST['visible']),
                $action === 'publish'
            );
            $notice = $action === 'publish' ? 'Contenido publicado.' : 'Borrador guardado.';
        }
    } catch (Throwable $exception) {
        error_log('[SIETELSA] Gestión de contenido: ' . $exception->getMessage());
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible guardar el contenido.';
    }
}

$sql = 'SELECT s.*, p.name AS page_name FROM page_sections s JOIN pages p ON p.id = s.page_id';
$params = [];
if ($module !== '') {
    $sql .= ' WHERE s.section_key = :module';
    $params['module'] = $module;
}
$sql .= ' ORDER BY s.draft_sort_order, s.id';
$statement = $db->prepare($sql);
$statement->execute($params);
$sections = $statement->fetchAll();
$media = $db->query('SELECT id, original_name, alt_text FROM media_library WHERE deleted_at IS NULL ORDER BY created_at DESC')->fetchAll();
$cmsPageTitle = 'Contenido del website';
require __DIR__ . '/partials/_cms-header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
  <div><h1 class="h3 mb-1">Contenido del website</h1><p class="text-muted mb-0">Edición manual, borradores, publicación, visibilidad y orden.</p></div>
  <a class="btn btn-outline-primary" href="<?= auth_escape(app_url('index.php?preview=1')) ?>" target="_blank" rel="noopener">Vista previa</a>
</div>
<?php if ($notice): ?><div class="alert alert-success"><?= auth_escape($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= auth_escape($error) ?></div><?php endif; ?>
<div class="mb-4 d-flex flex-wrap gap-2">
  <?php foreach (['' => 'Todo', 'hero' => 'Inicio', 'servicios' => 'Servicios', 'portafolio' => 'Portafolio', 'nosotros' => 'Nosotros', 'proyectos' => 'Proyectos', 'contacto' => 'Ubicación/Contacto', 'equipo' => 'Equipo', 'preguntas' => 'Preguntas'] as $key => $label): ?>
    <a class="btn btn-sm <?= $module === $key ? 'btn-primary' : 'btn-outline-primary' ?>" href="?module=<?= auth_escape($key) ?>"><?= auth_escape($label) ?></a>
  <?php endforeach; ?>
</div>
<?php foreach ($sections as $section): ?>
  <?php
  $section['data'] = cms_json($section['draft_data']);
  $section['media'] = $section['draft_media_id'];
  $itemsStatement = $db->prepare('SELECT * FROM content_items WHERE section_id = :id ORDER BY draft_sort_order, id');
  $itemsStatement->execute(['id' => $section['id']]);
  $entities = array_merge([['entity' => 'section', 'row' => $section]], array_map(static fn ($item) => ['entity' => 'item', 'row' => $item], $itemsStatement->fetchAll()));
  ?>
  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-3"><?= auth_escape($section['section_key']) ?> <span class="badge badge-<?= $section['status'] === 'published' ? 'success' : 'warning' ?>"><?= auth_escape($section['status']) ?></span></h2>
        <form method="post" class="d-flex gap-2">
          <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
          <input type="hidden" name="action" value="create"><input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>">
          <input type="hidden" name="item_type" value="<?= auth_escape($section['component_type']) ?>">
          <button class="btn btn-sm btn-outline-primary" type="submit">Crear elemento</button>
        </form>
      </div>
      <div class="accordion" id="section-<?= (int) $section['id'] ?>">
        <?php foreach ($entities as $entry): ?>
          <?php $row = $entry['row']; $data = cms_json($row['draft_data']); $entity = $entry['entity']; ?>
          <div class="accordion-item">
            <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $entity ?>-<?= (int) $row['id'] ?>"><?= auth_escape($entity === 'section' ? 'Cabecera de sección' : ($data['title'] ?? $row['item_key'])) ?></button></h3>
            <div id="<?= $entity ?>-<?= (int) $row['id'] ?>" class="accordion-collapse collapse">
              <div class="accordion-body">
                <form method="post" data-sietelsa-loading>
                  <input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>">
                  <input type="hidden" name="entity" value="<?= $entity ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <div class="row g-3">
                    <?php foreach (CMS_ALLOWED_FIELDS as $field): ?>
                      <?php if (!array_key_exists($field, $data) || $field === 'filters') continue; ?>
                      <div class="col-md-<?= in_array($field, ['content', 'lead'], true) ? '12' : '6' ?>">
                        <label class="form-label" for="<?= $entity ?>-<?= (int) $row['id'] ?>-<?= $field ?>"><?= auth_escape(ucfirst(str_replace('_', ' ', $field))) ?></label>
                        <?php if (in_array($field, ['content', 'lead'], true)): ?>
                          <textarea class="form-control" rows="4" id="<?= $entity ?>-<?= (int) $row['id'] ?>-<?= $field ?>" name="<?= $field ?>"><?= auth_escape($data[$field]) ?></textarea>
                        <?php else: ?>
                          <input class="form-control" id="<?= $entity ?>-<?= (int) $row['id'] ?>-<?= $field ?>" name="<?= $field ?>" value="<?= auth_escape($data[$field]) ?>">
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                    <div class="col-md-5"><label class="form-label">Imagen</label><select class="form-select" name="media_id"><option value="">Sin imagen</option><?php foreach ($media as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (int) ($row['draft_media_id'] ?? 0) === (int) $image['id'] ? 'selected' : '' ?>><?= auth_escape($image['alt_text'] ?: $image['original_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Orden</label><input type="number" class="form-control" name="sort_order" value="<?= (int) $row['draft_sort_order'] ?>"></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="visible" id="visible-<?= $entity ?>-<?= (int) $row['id'] ?>" <?= (int) $row['draft_visible'] ? 'checked' : '' ?>><label class="form-check-label" for="visible-<?= $entity ?>-<?= (int) $row['id'] ?>">Visible</label></div></div>
                  </div>
                  <div class="mt-3 d-flex gap-2"><button class="btn btn-outline-primary" name="action" value="save">Guardar borrador</button><button class="btn btn-primary" name="action" value="publish">Publicar</button><?php if ($entity === 'item'): ?><button class="btn btn-outline-danger cms-delete-item" type="button" name="action" value="delete">Retirar</button><?php endif; ?></div>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<script>
document.querySelectorAll('.cms-delete-item').forEach(button => button.addEventListener('click', async () => {
  const result = await window.SietelsaAlert.deletion('El elemento dejará de mostrarse, pero su historial se conservará.');
  if (!result.isConfirmed) return;
  button.type = 'submit';
  button.form.requestSubmit(button);
}));
</script>
<?php require __DIR__ . '/partials/_cms-footer.php'; ?>
