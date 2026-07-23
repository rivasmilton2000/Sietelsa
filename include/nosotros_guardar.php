<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/csrf.php';

function redirigir_modulo_nosotros(string $estado, int $nosotrosId = 0): void
{
    $vista = 'crear';
    if ($estado === 'actualizado' || ($nosotrosId > 0 && !in_array($estado, ['creado', 'eliminado'], true))) {
        $vista = 'editar';
    }

    $query = in_array($estado, ['creado', 'actualizado', 'eliminado'], true)
        ? 'ok=' . urlencode($estado)
        : 'error=' . urlencode($estado);

    $query .= '&vista=' . urlencode($vista);

    if ($nosotrosId > 0 && $vista === 'editar') {
        $query .= '&id=' . $nosotrosId;
    }

    redirigir('/admin/nosotros?' . $query);
}

function obtener_siguiente_orden_nosotros(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT COALESCE(MAX(orden), 0) FROM nosotros');
    $maxOrden = (int) ($stmt ? $stmt->fetchColumn() : 0);
    return $maxOrden + 1;
}

function reordenar_nosotros_continuo(PDO $pdo): void
{
    $idsStmt = $pdo->query('SELECT id FROM nosotros ORDER BY orden ASC, id ASC');
    $ids = $idsStmt ? $idsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (empty($ids)) {
        return;
    }

    $stmtUpdate = $pdo->prepare('UPDATE nosotros SET orden = :orden WHERE id = :id LIMIT 1');
    $orden = 1;
    foreach ($ids as $idRaw) {
        $stmtUpdate->execute([
            ':orden' => $orden,
            ':id' => (int) $idRaw,
        ]);
        $orden++;
    }
}

function normalizar_ruta_imagen_nosotros(string $ruta): string
{
    $ruta = trim(str_replace('\\', '/', $ruta));

    while (strpos($ruta, './') === 0) {
        $ruta = substr($ruta, 2);
    }

    while (strpos($ruta, '/') === 0) {
        $ruta = substr($ruta, 1);
    }

    return $ruta;
}

function es_imagen_nosotros_subida_local(string $ruta): bool
{
    $ruta = normalizar_ruta_imagen_nosotros($ruta);
    if ($ruta === '') {
        return false;
    }

    if (strpos($ruta, 'assets/img/about/upload_nosotros_') !== 0) {
        return false;
    }

    return preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $ruta) === 1;
}

function eliminar_imagen_nosotros_local(string $ruta): void
{
    if (!es_imagen_nosotros_subida_local($ruta)) {
        return;
    }

    $rutaAbsoluta = __DIR__ . '/../' . normalizar_ruta_imagen_nosotros($ruta);
    if (is_file($rutaAbsoluta)) {
        @unlink($rutaAbsoluta);
    }
}

function subir_imagen_nosotros(array $archivo): string
{
    $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('imagen_subida');
    }

    $tamano = (int) ($archivo['size'] ?? 0);
    if ($tamano <= 0) {
        throw new RuntimeException('imagen_subida');
    }
    if ($tamano > (5 * 1024 * 1024)) {
        throw new RuntimeException('imagen_tamano');
    }

    $tmp = (string) ($archivo['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('imagen_subida');
    }

    $mime = '';
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $mimeDetectado = finfo_file($finfo, $tmp);
        if (is_string($mimeDetectado)) {
            $mime = $mimeDetectado;
        }
        finfo_close($finfo);
    }

    $mimeExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($mimeExt[$mime])) {
        throw new RuntimeException('imagen_tipo');
    }

    try {
        $sufijo = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $sufijo = substr(md5((string) microtime(true) . (string) mt_rand()), 0, 8);
    }

    $nombre = 'upload_nosotros_' . date('Ymd_His') . '_' . $sufijo . '.' . $mimeExt[$mime];
    $directorioRelativo = 'assets/img/about';
    $directorioAbsoluto = __DIR__ . '/../' . $directorioRelativo;

    if (!is_dir($directorioAbsoluto)) {
        if (!mkdir($directorioAbsoluto, 0755, true) && !is_dir($directorioAbsoluto)) {
            throw new RuntimeException('imagen_subida');
        }
    }

    $destinoAbsoluto = $directorioAbsoluto . '/' . $nombre;
    if (!move_uploaded_file($tmp, $destinoAbsoluto)) {
        throw new RuntimeException('imagen_subida');
    }
    sietelsa_optimizar_imagen_subida($destinoAbsoluto, 2200, 2200, 82);

    return $directorioRelativo . '/' . $nombre;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/dashboard');
}

require_modulo('gestionar_nosotros');
global $pdo;
asegurar_tabla_nosotros($pdo);

$accion = trim((string) ($_POST['accion'] ?? ''));
if (!in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    redirigir('/admin/dashboard');
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_nosotros')) {
    redirigir_modulo_nosotros('csrf');
}

$nosotrosId = (int) ($_POST['nosotros_id'] ?? 0);

if ($accion === 'crear') {
    try {
        $stmtTotal = $pdo->query('SELECT COUNT(*) FROM nosotros');
        $totalRegistros = (int) ($stmtTotal ? $stmtTotal->fetchColumn() : 0);
        if ($totalRegistros >= 1) {
            redirigir_modulo_nosotros('solo_uno');
        }
    } catch (Throwable $e) {
        redirigir_modulo_nosotros('db');
    }
}

if ($accion === 'eliminar') {
    if ($nosotrosId <= 0) {
        redirigir_modulo_nosotros('id');
    }

    $stmtImagen = $pdo->prepare('SELECT imagen_path FROM nosotros WHERE id = :id LIMIT 1');
    $stmtImagen->execute([':id' => $nosotrosId]);
    $imagenEliminar = normalizar_ruta_imagen_nosotros((string) ($stmtImagen->fetchColumn() ?: ''));

    try {
        $pdo->beginTransaction();
        $stmtDelete = $pdo->prepare('DELETE FROM nosotros WHERE id = :id LIMIT 1');
        $stmtDelete->execute([':id' => $nosotrosId]);
        if ($stmtDelete->rowCount() === 0) {
            $pdo->rollBack();
            redirigir_modulo_nosotros('id');
        }
        reordenar_nosotros_continuo($pdo);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir_modulo_nosotros('db', $nosotrosId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir_modulo_nosotros('general', $nosotrosId);
    }

    eliminar_imagen_nosotros_local($imagenEliminar);
    redirigir_modulo_nosotros('eliminado');
}

$titulo = trim((string) ($_POST['titulo'] ?? ''));
$descripcion1 = trim((string) ($_POST['descripcion_1'] ?? ''));
$descripcion2 = trim((string) ($_POST['descripcion_2'] ?? ''));
$descripcion3 = trim((string) ($_POST['descripcion_3'] ?? ''));
$activo = isset($_POST['activo']) ? 1 : 0;

if ($titulo === '' || strlen($titulo) > 120) {
    redirigir_modulo_nosotros('titulo', $nosotrosId);
}
if ($descripcion1 === '' || strlen($descripcion1) > 2000) {
    redirigir_modulo_nosotros('descripcion_1', $nosotrosId);
}
if ($descripcion2 !== '' && strlen($descripcion2) > 2000) {
    redirigir_modulo_nosotros('descripcion_2', $nosotrosId);
}
if ($descripcion3 !== '' && strlen($descripcion3) > 2000) {
    redirigir_modulo_nosotros('descripcion_3', $nosotrosId);
}

$imagenNueva = '';
try {
    if (isset($_FILES['imagen']) && is_array($_FILES['imagen'])) {
        $imagenNueva = subir_imagen_nosotros($_FILES['imagen']);
    }
} catch (RuntimeException $e) {
    redirigir_modulo_nosotros($e->getMessage(), $nosotrosId);
}

if ($accion === 'crear') {
    $imagenFinal = $imagenNueva !== '' ? $imagenNueva : 'assets/img/fondoSietelsa.png';

    try {
        $pdo->beginTransaction();
        $orden = obtener_siguiente_orden_nosotros($pdo);
        $stmtInsert = $pdo->prepare(
            'INSERT INTO nosotros (titulo, descripcion_1, descripcion_2, descripcion_3, imagen_path, orden, activo)
             VALUES (:titulo, :descripcion_1, :descripcion_2, :descripcion_3, :imagen_path, :orden, :activo)'
        );
        $stmtInsert->execute([
            ':titulo' => $titulo,
            ':descripcion_1' => $descripcion1,
            ':descripcion_2' => $descripcion2 !== '' ? $descripcion2 : null,
            ':descripcion_3' => $descripcion3 !== '' ? $descripcion3 : null,
            ':imagen_path' => $imagenFinal,
            ':orden' => $orden,
            ':activo' => $activo,
        ]);
        $nosotrosId = (int) $pdo->lastInsertId();
        reordenar_nosotros_continuo($pdo);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        eliminar_imagen_nosotros_local($imagenNueva);
        redirigir_modulo_nosotros('db');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        eliminar_imagen_nosotros_local($imagenNueva);
        redirigir_modulo_nosotros('general');
    }

    redirigir_modulo_nosotros('creado', $nosotrosId);
}

if ($nosotrosId <= 0) {
    eliminar_imagen_nosotros_local($imagenNueva);
    redirigir_modulo_nosotros('id');
}

$stmtExiste = $pdo->prepare('SELECT id, imagen_path FROM nosotros WHERE id = :id LIMIT 1');
$stmtExiste->execute([':id' => $nosotrosId]);
$registro = $stmtExiste->fetch();
if (!$registro) {
    eliminar_imagen_nosotros_local($imagenNueva);
    redirigir_modulo_nosotros('id');
}

$imagenActual = normalizar_ruta_imagen_nosotros((string) ($registro['imagen_path'] ?? ''));
$eliminarImagen = isset($_POST['eliminar_imagen']);
$imagenFinal = $imagenActual;
$imagenEliminarLuego = '';

if ($imagenNueva !== '') {
    $imagenFinal = $imagenNueva;
    $imagenEliminarLuego = $imagenActual;
} elseif ($eliminarImagen) {
    $imagenFinal = '';
    $imagenEliminarLuego = $imagenActual;
}

try {
    $stmtUpdate = $pdo->prepare(
        'UPDATE nosotros
         SET titulo = :titulo,
             descripcion_1 = :descripcion_1,
             descripcion_2 = :descripcion_2,
             descripcion_3 = :descripcion_3,
             imagen_path = :imagen_path,
             activo = :activo
         WHERE id = :id
         LIMIT 1'
    );
    $stmtUpdate->execute([
        ':id' => $nosotrosId,
        ':titulo' => $titulo,
        ':descripcion_1' => $descripcion1,
        ':descripcion_2' => $descripcion2 !== '' ? $descripcion2 : null,
        ':descripcion_3' => $descripcion3 !== '' ? $descripcion3 : null,
        ':imagen_path' => $imagenFinal !== '' ? $imagenFinal : null,
        ':activo' => $activo,
    ]);
} catch (PDOException $e) {
    eliminar_imagen_nosotros_local($imagenNueva);
    redirigir_modulo_nosotros('db', $nosotrosId);
} catch (Throwable $e) {
    eliminar_imagen_nosotros_local($imagenNueva);
    redirigir_modulo_nosotros('general', $nosotrosId);
}

eliminar_imagen_nosotros_local($imagenEliminarLuego);
redirigir_modulo_nosotros('actualizado', $nosotrosId);
