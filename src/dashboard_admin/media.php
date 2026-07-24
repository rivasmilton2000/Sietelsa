<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/content.php';
auth_require_admin();
$db = db_connection();
$notice = $error = '';

function media_variant(string $source, string $target, string $mime, int $maxWidth): ?array
{
    if (!extension_loaded('gd')) return null;
    [$width, $height] = getimagesize($source);
    if ($width <= $maxWidth) return null;
    $newWidth = $maxWidth;
    $newHeight = (int) round($height * ($maxWidth / $width));
    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($source),
        'image/png' => imagecreatefrompng($source),
        'image/webp' => imagecreatefromwebp($source),
        default => false,
    };
    if (!$src) return null;
    $dst = imagecreatetruecolor($newWidth, $newHeight);
    if ($mime !== 'image/jpeg') {
        imagealphablending($dst, false); imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($dst, $target, 82),
        'image/png' => imagepng($dst, $target, 7),
        'image/webp' => imagewebp($dst, $target, 82),
        default => false,
    };
    imagedestroy($src); imagedestroy($dst);
    return $saved ? [$newWidth, $newHeight] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!auth_validate_csrf($_POST['csrf_token'] ?? null)) throw new RuntimeException('La solicitud expiró.');
        $action = (string) ($_POST['action'] ?? 'upload');
        if ($action === 'alt') {
            $id = (int) $_POST['id'];
            $alt = cms_clean_text($_POST['alt_text'] ?? '', 255);
            $statement = $db->prepare('UPDATE media_library SET alt_text=:alt WHERE id=:id AND deleted_at IS NULL');
            $statement->execute(compact('alt', 'id'));
            cms_audit($db, 'update_alt', 'media', $id);
            $notice = 'Texto alternativo actualizado.';
        } elseif ($action === 'delete') {
            $id = (int) $_POST['id'];
            $usage = $db->prepare(
                'SELECT (SELECT COUNT(*) FROM page_sections WHERE draft_media_id=:a OR published_media_id=:b)
                      +(SELECT COUNT(*) FROM content_items WHERE draft_media_id=:c OR published_media_id=:d)'
            );
            $usage->execute(['a' => $id, 'b' => $id, 'c' => $id, 'd' => $id]);
            if ((int) $usage->fetchColumn() > 0) throw new RuntimeException('La imagen está en uso y no puede eliminarse.');
            $db->prepare('UPDATE media_library SET deleted_at=NOW() WHERE id=:id')->execute(['id' => $id]);
            cms_audit($db, 'delete', 'media', $id);
            $notice = 'Imagen retirada de la biblioteca; el archivo se conservó de forma segura.';
        } else {
            $file = $_FILES['image'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Seleccione una imagen válida.');
            if ((int) $file['size'] > 8 * 1024 * 1024) throw new RuntimeException('La imagen supera 8 MB.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) throw new RuntimeException('Solo se permiten JPEG, PNG y WebP.');
            $dimensions = getimagesize($file['tmp_name']);
            if (!$dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 12000 || $dimensions[1] > 12000) throw new RuntimeException('Dimensiones no válidas.');
            $name = bin2hex(random_bytes(16));
            $extension = $extensions[$mime];
            $directory = dirname(__DIR__, 2) . '/uploads/media';
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('No se pudo preparar la carpeta.');
            $stored = $name . '.' . $extension;
            $absolute = $directory . '/' . $stored;
            if (!move_uploaded_file($file['tmp_name'], $absolute)) throw new RuntimeException('No se pudo guardar la imagen.');
            $variants = [];
            foreach (['thumbnail' => 320, 'medium' => 960, 'large' => 1600] as $label => $width) {
                $variantName = $name . '-' . $label . '.' . $extension;
                if (media_variant($absolute, $directory . '/' . $variantName, $mime, $width)) $variants[$label] = 'uploads/media/' . $variantName;
            }
            $statement = $db->prepare(
                'INSERT INTO media_library (original_name,stored_name,relative_path,thumbnail_path,medium_path,large_path,mime_type,extension,width,height,file_size,sha256,alt_text,created_by)
                 VALUES (:original,:stored,:path,:thumb,:medium,:large,:mime,:extension,:width,:height,:size,:hash,:alt,:user)'
            );
            $statement->execute([
                'original' => mb_substr(basename($file['name']), 0, 255), 'stored' => $stored, 'path' => 'uploads/media/' . $stored,
                'thumb' => $variants['thumbnail'] ?? null, 'medium' => $variants['medium'] ?? null, 'large' => $variants['large'] ?? null,
                'mime' => $mime, 'extension' => $extension, 'width' => $dimensions[0], 'height' => $dimensions[1],
                'size' => filesize($absolute), 'hash' => hash_file('sha256', $absolute),
                'alt' => cms_clean_text($_POST['alt_text'] ?? '', 255), 'user' => (int) auth_current_user()['id'],
            ]);
            cms_audit($db, 'upload', 'media', (int) $db->lastInsertId(), ['variants' => array_keys($variants)]);
            $notice = extension_loaded('gd') ? 'Imagen subida y variantes optimizadas.' : 'Imagen validada y subida. GD/Imagick no está disponible; se conserva el original optimizado por el cliente.';
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible procesar la imagen.';
        error_log('[SIETELSA] Multimedia: ' . $exception->getMessage());
    }
}
$search = trim((string) ($_GET['q'] ?? ''));
$statement = $db->prepare('SELECT m.*,
 (SELECT COUNT(*) FROM page_sections s WHERE s.draft_media_id=m.id OR s.published_media_id=m.id)
 +(SELECT COUNT(*) FROM content_items i WHERE i.draft_media_id=m.id OR i.published_media_id=m.id) AS uses,
 CONCAT_WS(", ",
   (SELECT GROUP_CONCAT(CONCAT("sección: ",s.section_key) SEPARATOR ", ") FROM page_sections s WHERE s.draft_media_id=m.id OR s.published_media_id=m.id),
   (SELECT GROUP_CONCAT(CONCAT("elemento: ",i.item_key) SEPARATOR ", ") FROM content_items i WHERE i.draft_media_id=m.id OR i.published_media_id=m.id)
 ) AS used_in
 FROM media_library m WHERE m.deleted_at IS NULL AND (:q="" OR m.original_name LIKE :likeq OR m.alt_text LIKE :likeq2) ORDER BY m.created_at DESC');
$statement->execute(['q' => $search, 'likeq' => '%' . $search . '%', 'likeq2' => '%' . $search . '%']);
$images = $statement->fetchAll();
$cmsPageTitle = 'Biblioteca multimedia';
require __DIR__ . '/partials/_cms-header.php';
?>
<h1 class="h3">Biblioteca multimedia</h1>
<?php if ($notice): ?><div class="alert alert-success"><?= auth_escape($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= auth_escape($error) ?></div><?php endif; ?>
<div class="card mb-4"><div class="card-body"><form method="post" enctype="multipart/form-data" data-sietelsa-loading><input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>"><input type="hidden" name="action" value="upload"><div class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label">Imagen JPEG, PNG o WebP (máx. 8 MB)</label><input type="file" class="form-control" id="mediaUpload" name="image" accept="image/jpeg,image/png,image/webp" required></div><div class="col-md-5"><label class="form-label">Texto alternativo</label><input class="form-control" name="alt_text" maxlength="255" required></div><div class="col-md-2"><button class="btn btn-primary w-100">Subir</button></div></div><img id="mediaPreview" class="img-thumbnail mt-3" alt="Vista previa de la imagen" hidden style="max-width:320px;max-height:220px;object-fit:contain"></form></div></div>
<form class="mb-3"><div class="input-group"><input class="form-control" name="q" value="<?= auth_escape($search) ?>" placeholder="Buscar imágenes"><button class="btn btn-outline-primary">Buscar</button></div></form>
<div class="row"><?php foreach ($images as $image): ?><div class="col-xl-3 col-md-4 mb-4"><div class="card h-100"><img src="<?= auth_escape(app_url($image['thumbnail_path'] ?: $image['relative_path'])) ?>" class="card-img-top" alt="<?= auth_escape($image['alt_text']) ?>" loading="lazy" style="height:180px;object-fit:cover"><div class="card-body"><small><?= (int) $image['width'] ?>×<?= (int) $image['height'] ?> · <?= number_format($image['file_size']/1024, 1) ?> KB · usos: <?= (int) $image['uses'] ?></small><?php if ($image['used_in']): ?><small class="d-block text-muted mt-1">Usada en: <?= auth_escape($image['used_in']) ?></small><?php endif; ?><form method="post" class="mt-2"><input type="hidden" name="csrf_token" value="<?= auth_escape(auth_csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int) $image['id'] ?>"><input class="form-control form-control-sm mb-2 media-alt" name="alt_text" value="<?= auth_escape($image['alt_text']) ?>"><button class="btn btn-sm btn-outline-primary" name="action" value="alt">Guardar alt</button> <button class="btn btn-sm btn-outline-secondary copy-alt" type="button">Copiar alt</button> <button class="btn btn-sm btn-outline-danger" name="action" value="delete" <?= (int) $image['uses'] ? 'disabled title="Imagen en uso"' : '' ?>>Eliminar</button></form></div></div></div><?php endforeach; ?></div>
<script>
document.getElementById('mediaUpload').addEventListener('change', event => {
  const file = event.target.files[0], preview = document.getElementById('mediaPreview');
  if (!file || !file.type.startsWith('image/')) { preview.hidden = true; return; }
  preview.src = URL.createObjectURL(file); preview.hidden = false;
  preview.onload = () => URL.revokeObjectURL(preview.src);
});
document.querySelectorAll('.copy-alt').forEach(button => button.addEventListener('click', async () => {
  await navigator.clipboard.writeText(button.form.querySelector('.media-alt').value);
  window.SietelsaAlert.info('Texto alternativo copiado.');
}));
</script>
<?php require __DIR__ . '/partials/_cms-footer.php'; ?>
