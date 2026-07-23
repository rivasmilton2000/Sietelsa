<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/csrf.php';

function redirigir_modulo_proyectos(string $estado, int $proyectoId = 0): void
{
    $vista = 'crear';
    if ($estado === 'actualizado' || ($proyectoId > 0 && !in_array($estado, ['creado', 'eliminado'], true))) {
        $vista = 'editar';
    }

    $query = in_array($estado, ['creado', 'actualizado', 'eliminado'], true)
        ? 'ok=' . urlencode($estado)
        : 'error=' . urlencode($estado);

    $query .= '&vista=' . urlencode($vista);

    if ($proyectoId > 0 && $vista === 'editar') {
        $query .= '&id=' . $proyectoId;
    }

    redirigir('/admin/proyectos?' . $query);
}

function obtener_siguiente_orden_proyecto(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT COALESCE(MAX(orden), 0) FROM proyectos');
    $maxOrden = (int) ($stmt ? $stmt->fetchColumn() : 0);
    return $maxOrden + 1;
}

function reordenar_proyectos_continuo(PDO $pdo): void
{
    $idsStmt = $pdo->query('SELECT id FROM proyectos ORDER BY orden ASC, id ASC');
    $ids = $idsStmt ? $idsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (empty($ids)) {
        return;
    }

    $stmtUpdate = $pdo->prepare('UPDATE proyectos SET orden = :orden WHERE id = :id LIMIT 1');
    $orden = 1;
    foreach ($ids as $idRaw) {
        $stmtUpdate->execute([
            ':orden' => $orden,
            ':id' => (int) $idRaw,
        ]);
        $orden++;
    }
}

function portafolio_existe(PDO $pdo, int $portafolioId): bool
{
    if ($portafolioId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id FROM portafolio WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $portafolioId]);
    return (bool) $stmt->fetchColumn();
}

function normalizar_ruta_imagen_proyecto(string $ruta): string
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

function es_imagen_proyecto_subida_local(string $ruta): bool
{
    $ruta = normalizar_ruta_imagen_proyecto($ruta);
    if ($ruta === '') {
        return false;
    }

    if (strpos($ruta, 'assets/img/proyectos/upload_proyecto_') !== 0) {
        return false;
    }

    return preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $ruta) === 1;
}

function eliminar_imagen_proyecto_local(string $ruta): void
{
    if (!es_imagen_proyecto_subida_local($ruta)) {
        return;
    }

    $rutaAbsoluta = __DIR__ . '/../' . normalizar_ruta_imagen_proyecto($ruta);
    if (is_file($rutaAbsoluta)) {
        @unlink($rutaAbsoluta);
    }
}

function subir_imagen_proyecto(array $archivo, bool $obligatoria = true): string
{
    $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        if ($obligatoria) {
            throw new RuntimeException('imagen_requerida');
        }
        return '';
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('imagen_subida');
    }

    $tamano = (int) ($archivo['size'] ?? 0);
    if ($tamano <= 0 || $tamano > (5 * 1024 * 1024)) {
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

    $nombre = 'upload_proyecto_' . date('Ymd_His') . '_' . $sufijo . '.' . $mimeExt[$mime];
    $directorioRelativo = 'assets/img/proyectos';
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

require_modulo('gestionar_proyectos');
global $pdo;
asegurar_tabla_proyectos($pdo);

$accion = trim((string) ($_POST['accion'] ?? ''));
if (!in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    redirigir('/admin/dashboard');
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_proyectos')) {
    redirigir_modulo_proyectos('csrf');
}

$proyectoId = (int) ($_POST['proyecto_id'] ?? 0);

if ($accion === 'eliminar') {
    if ($proyectoId <= 0) {
        redirigir_modulo_proyectos('id');
    }

    $stmtImagen = $pdo->prepare('SELECT imagen_path FROM proyectos WHERE id = :id LIMIT 1');
    $stmtImagen->execute([':id' => $proyectoId]);
    $imagenEliminar = normalizar_ruta_imagen_proyecto((string) ($stmtImagen->fetchColumn() ?: ''));

    try {
        $pdo->beginTransaction();
        $stmtDelete = $pdo->prepare('DELETE FROM proyectos WHERE id = :id LIMIT 1');
        $stmtDelete->execute([':id' => $proyectoId]);
        if ($stmtDelete->rowCount() === 0) {
            $pdo->rollBack();
            redirigir_modulo_proyectos('id');
        }
        reordenar_proyectos_continuo($pdo);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir_modulo_proyectos('db', $proyectoId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir_modulo_proyectos('general', $proyectoId);
    }

    eliminar_imagen_proyecto_local($imagenEliminar);
    redirigir_modulo_proyectos('eliminado');
}

$titulo = trim((string) ($_POST['titulo'] ?? ''));
$portafolioId = (int) ($_POST['portafolio_id'] ?? 0);
$activo = isset($_POST['activo']) ? 1 : 0;

if ($titulo === '' || strlen($titulo) > 120) {
    redirigir_modulo_proyectos('titulo', $proyectoId);
}
if (!portafolio_existe($pdo, $portafolioId)) {
    redirigir_modulo_proyectos('categoria', $proyectoId);
}

if ($accion === 'crear') {
    $stmtTotal = $pdo->query('SELECT COUNT(*) FROM proyectos');
    $totalProyectos = (int) ($stmtTotal ? $stmtTotal->fetchColumn() : 0);
    if ($totalProyectos >= 6) {
        redirigir_modulo_proyectos('limite');
    }
}

$imagenNueva = '';
try {
    if (isset($_FILES['imagen']) && is_array($_FILES['imagen'])) {
        $imagenNueva = subir_imagen_proyecto($_FILES['imagen'], $accion === 'crear');
    } elseif ($accion === 'crear') {
        redirigir_modulo_proyectos('imagen_requerida');
    }
} catch (RuntimeException $e) {
    redirigir_modulo_proyectos($e->getMessage(), $proyectoId);
}

if ($accion === 'crear') {
    try {
        $pdo->beginTransaction();
        $orden = obtener_siguiente_orden_proyecto($pdo);
        $stmtInsert = $pdo->prepare(
            'INSERT INTO proyectos (titulo, portafolio_id, imagen_path, orden, activo)
             VALUES (:titulo, :portafolio_id, :imagen_path, :orden, :activo)'
        );
        $stmtInsert->execute([
            ':titulo' => $titulo,
            ':portafolio_id' => $portafolioId,
            ':imagen_path' => $imagenNueva,
            ':orden' => $orden,
            ':activo' => $activo,
        ]);
        $proyectoId = (int) $pdo->lastInsertId();
        reordenar_proyectos_continuo($pdo);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        eliminar_imagen_proyecto_local($imagenNueva);
        redirigir_modulo_proyectos('db');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        eliminar_imagen_proyecto_local($imagenNueva);
        redirigir_modulo_proyectos('general');
    }

    redirigir_modulo_proyectos('creado', $proyectoId);
}

if ($proyectoId <= 0) {
    eliminar_imagen_proyecto_local($imagenNueva);
    redirigir_modulo_proyectos('id');
}

$stmtExiste = $pdo->prepare('SELECT id, imagen_path FROM proyectos WHERE id = :id LIMIT 1');
$stmtExiste->execute([':id' => $proyectoId]);
$registro = $stmtExiste->fetch();
if (!$registro) {
    eliminar_imagen_proyecto_local($imagenNueva);
    redirigir_modulo_proyectos('id');
}

$imagenActual = normalizar_ruta_imagen_proyecto((string) ($registro['imagen_path'] ?? ''));
$imagenFinal = $imagenActual;
$imagenEliminarLuego = '';

if ($imagenNueva !== '') {
    $imagenFinal = $imagenNueva;
    $imagenEliminarLuego = $imagenActual;
}

if ($imagenFinal === '') {
    eliminar_imagen_proyecto_local($imagenNueva);
    redirigir_modulo_proyectos('imagen_requerida', $proyectoId);
}

try {
    $stmtUpdate = $pdo->prepare(
        'UPDATE proyectos
         SET titulo = :titulo,
             portafolio_id = :portafolio_id,
             imagen_path = :imagen_path,
             activo = :activo
         WHERE id = :id
         LIMIT 1'
    );
    $stmtUpdate->execute([
        ':id' => $proyectoId,
        ':titulo' => $titulo,
        ':portafolio_id' => $portafolioId,
        ':imagen_path' => $imagenFinal,
        ':activo' => $activo,
    ]);
} catch (PDOException $e) {
    eliminar_imagen_proyecto_local($imagenNueva);
    redirigir_modulo_proyectos('db', $proyectoId);
} catch (Throwable $e) {
    eliminar_imagen_proyecto_local($imagenNueva);
    redirigir_modulo_proyectos('general', $proyectoId);
}

eliminar_imagen_proyecto_local($imagenEliminarLuego);
redirigir_modulo_proyectos('actualizado', $proyectoId);
