<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/content.php';
auth_require_admin();
$db = db_connection();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && auth_validate_csrf($_POST['csrf_token'] ?? null)) {
    $id = (int) ($_POST['id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['new','read','archived'], true) ? $_POST['status'] : 'read';
    $db->prepare('UPDATE contact_messages SET status=:status WHERE id=:id')->execute(compact('status','id'));
    cms_audit($db, 'message_status', 'contact_message', $id, ['status' => $status]);
}
$messages = $db->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 250')->fetchAll();
$cmsPageTitle = 'Mensajes';
require __DIR__ . '/partials/_cms-header.php';
?>
<h1 class="h3">Mensajes recibidos</h1>
<div class="card"><div class="card-body table-responsive"><table class="table table-hover"><thead><tr><th>Fecha</th><th>Tipo</th><th>Remitente</th><th>Asunto / mensaje</th><th>Estado</th></tr></thead><tbody>
<?php foreach ($messages as $message): ?><tr><td><?= auth_escape($message['created_at']) ?></td><td><?= auth_escape($message['message_type']) ?></td><td><?= auth_escape($message['name']) ?><br><a href="mailto:<?= auth_escape($message['email']) ?>"><?= auth_escape($message['email']) ?></a></td><td><strong><?= auth_escape($message['subject']) ?></strong><br><?= nl2br(auth_escape($message['message'])) ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $message['id'] ?>"><select class="form-select form-select-sm" name="status" onchange="this.form.submit()"><option value="new" <?= $message['status']==='new'?'selected':'' ?>>Nuevo</option><option value="read" <?= $message['status']==='read'?'selected':'' ?>>Leído</option><option value="archived" <?= $message['status']==='archived'?'selected':'' ?>>Archivado</option></select></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/partials/_cms-footer.php'; ?>
