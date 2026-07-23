<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/csrf.php';

function redirigir_modulo_portafolio(string $estado, int $itemId = 0): void
{
    $vista = 'crear';
    if ($estado === 'actualizado' || ($itemId > 0 && !in_array($estado, ['creado', 'eliminado'], true))) {
        $vista = 'editar';
    }

    $query = in_array($estado, ['creado', 'actualizado', 'eliminado'], true)
        ? 'ok=' . urlencode($estado)
        : 'error=' . urlencode($estado);

    $query .= '&vista=' . urlencode($vista);

    if ($itemId > 0 && $vista === 'editar') {
        $query .= '&id=' . $itemId;
    }

    redirigir('/admin/portafolio?' . $query);
}

function obtener_siguiente_orden_portafolio(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT COALESCE(MAX(orden), 0) FROM portafolio');
    $maxOrden = (int) ($stmt ? $stmt->fetchColumn() : 0);
    return $maxOrden + 1;
}

function reordenar_portafolio_continuo(PDO $pdo): void
{
    $idsStmt = $pdo->query('SELECT id FROM portafolio ORDER BY orden ASC, id ASC');
    $ids = $idsStmt ? $idsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    if (empty($ids)) {
        return;
    }

    $stmtUpdate = $pdo->prepare('UPDATE portafolio SET orden = :orden WHERE id = :id LIMIT 1');
    $orden = 1;
    foreach ($ids as $idRaw) {
        $stmtUpdate->execute([
            ':orden' => $orden,
            ':id' => (int) $idRaw,
        ]);
        $orden++;
    }
}

function normalizar_ruta_imagen_portafolio(string $ruta): string
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

function eliminar_imagen_portafolio_local(string $ruta): void
{
    $ruta = normalizar_ruta_imagen_portafolio($ruta);
    if ($ruta === '') {
        return;
    }

    if (strpos($ruta, 'assets/img/portfolio/upload_portafolio_') !== 0) {
        return;
    }

    if (!preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $ruta)) {
        return;
    }

    $rutaAbsoluta = __DIR__ . '/../' . $ruta;
    if (is_file($rutaAbsoluta)) {
        @unlink($rutaAbsoluta);
    }
}

function subir_imagen_portafolio(array $archivo, bool $obligatoria = true): string
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

    $nombre = 'upload_portafolio_' . date('Ymd_His') . '_' . $sufijo . '.' . $mimeExt[$mime];
    $directorioRelativo = 'assets/img/portfolio';
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

function extraer_archivos_campo_multiples(?array $campo): array
{
    if (!is_array($campo) || !isset($campo['name'])) {
        return [];
    }

    if (!is_array($campo['name'])) {
        return [$campo];
    }

    $archivos = [];
    $total = count($campo['name']);
    for ($i = 0; $i < $total; $i++) {
        $archivos[] = [
            'name' => $campo['name'][$i] ?? '',
            'type' => $campo['type'][$i] ?? '',
            'tmp_name' => $campo['tmp_name'][$i] ?? '',
            'error' => $campo['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $campo['size'][$i] ?? 0,
        ];
    }

    return $archivos;
}

function rutas_imagenes_portafolio(PDO $pdo, int $itemId): array
{
    $stmt = $pdo->prepare(
        'SELECT imagen_path
         FROM portafolio_imagenes
         WHERE portafolio_id = :portafolio_id
         ORDER BY orden ASC, id ASC'
    );
    $stmt->execute([':portafolio_id' => $itemId]);

    $rutas = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $ruta) {
        $rutaLimpia = normalizar_ruta_imagen_portafolio((string) $ruta);
        if ($rutaLimpia !== '') {
            $rutas[] = $rutaLimpia;
        }
    }

    return $rutas;
}

function obtener_siguiente_orden_imagen_portafolio(PDO $pdo, int $itemId): int
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(orden), 0)
         FROM portafolio_imagenes
         WHERE portafolio_id = :portafolio_id'
    );
    $stmt->execute([':portafolio_id' => $itemId]);
    $maxOrden = (int) ($stmt->fetchColumn() ?: 0);
    return $maxOrden + 1;
}

function insertar_imagenes_portafolio(PDO $pdo, int $itemId, array $rutas, int $ordenInicial): void
{
    if (empty($rutas)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO portafolio_imagenes (portafolio_id, imagen_path, orden)
         VALUES (:portafolio_id, :imagen_path, :orden)'
    );

    $orden = $ordenInicial;
    foreach ($rutas as $ruta) {
        $rutaLimpia = normalizar_ruta_imagen_portafolio((string) $ruta);
        if ($rutaLimpia === '') {
            continue;
        }
        $stmt->execute([
            ':portafolio_id' => $itemId,
            ':imagen_path' => $rutaLimpia,
            ':orden' => $orden,
        ]);
        $orden++;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/dashboard');
}

require_modulo('gestionar_portafolio');
global $pdo;
asegurar_tabla_portafolio($pdo);
asegurar_tabla_portafolio_imagenes($pdo);

$accion = trim((string) ($_POST['accion'] ?? ''));
if (!in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    redirigir('/admin/dashboard');
}

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_portafolio')) {
    redirigir_modulo_portafolio('csrf');
}

$itemId = (int) ($_POST['portafolio_id'] ?? 0);

if ($accion === 'eliminar') {
    if ($itemId <= 0) {
        redirigir_modulo_portafolio('id');
    }

    $rutasAEliminar = rutas_imagenes_portafolio($pdo, $itemId);
    $stmtImagen = $pdo->prepare('SELECT imagen_path FROM portafolio WHERE id = :id LIMIT 1');
    $stmtImagen->execute([':id' => $itemId]);
    $imagenPortada = normalizar_ruta_imagen_portafolio((string) ($stmtImagen->fetchColumn() ?: ''));
    if ($imagenPortada !== '') {
        $rutasAEliminar[] = $imagenPortada;
    }
    $rutasAEliminar = array_values(array_unique($rutasAEliminar));

    try {
        $pdo->beginTransaction();
        $stmtDelete = $pdo->prepare('DELETE FROM portafolio WHERE id = :id LIMIT 1');
        $stmtDelete->execute([':id' => $itemId]);
        if ($stmtDelete->rowCount() === 0) {
            $pdo->rollBack();
            redirigir_modulo_portafolio('id');
        }
        reordenar_portafolio_continuo($pdo);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir_modulo_portafolio('db', $itemId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirigir_modulo_portafolio('general', $itemId);
    }

    foreach ($rutasAEliminar as $ruta) {
        eliminar_imagen_portafolio_local((string) $ruta);
    }
    redirigir_modulo_portafolio('eliminado');
}

$titulo = trim((string) ($_POST['titulo'] ?? ''));
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
$activo = isset($_POST['activo']) ? 1 : 0;

if ($titulo === '' || strlen($titulo) > 120) {
    redirigir_modulo_portafolio('titulo', $itemId);
}
if ($descripcion !== '' && strlen($descripcion) > 1200) {
    redirigir_modulo_portafolio('descripcion', $itemId);
}

$campoImagenes = $_FILES['imagenes'] ?? null;
if (!is_array($campoImagenes) && isset($_FILES['imagen']) && is_array($_FILES['imagen'])) {
    $campoImagenes = $_FILES['imagen'];
}
$archivos = extraer_archivos_campo_multiples($campoImagenes);

$rutasNuevas = [];
foreach ($archivos as $archivo) {
    $errorArchivo = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorArchivo === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    try {
        $rutasNuevas[] = subir_imagen_portafolio($archivo, false);
    } catch (RuntimeException $e) {
        foreach ($rutasNuevas as $rutaNueva) {
            eliminar_imagen_portafolio_local($rutaNueva);
        }
        redirigir_modulo_portafolio($e->getMessage(), $itemId);
    }
}

if ($accion === 'crear') {
    if (empty($rutasNuevas)) {
        redirigir_modulo_portafolio('imagen_requerida');
    }

    try {
        $pdo->beginTransaction();
        $orden = obtener_siguiente_orden_portafolio($pdo);
        $portada = normalizar_ruta_imagen_portafolio((string) $rutasNuevas[0]);

        $stmtInsert = $pdo->prepare(
            'INSERT INTO portafolio (titulo, descripcion, imagen_path, orden, activo)
             VALUES (:titulo, :descripcion, :imagen_path, :orden, :activo)'
        );
        $stmtInsert->execute([
            ':titulo' => $titulo,
            ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ':imagen_path' => $portada,
            ':orden' => $orden,
            ':activo' => $activo,
        ]);
        $itemId = (int) $pdo->lastInsertId();

        insertar_imagenes_portafolio($pdo, $itemId, $rutasNuevas, 1);
        reordenar_portafolio_continuo($pdo);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($rutasNuevas as $rutaNueva) {
            eliminar_imagen_portafolio_local($rutaNueva);
        }
        redirigir_modulo_portafolio('db');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($rutasNuevas as $rutaNueva) {
            eliminar_imagen_portafolio_local($rutaNueva);
        }
        redirigir_modulo_portafolio('general');
    }

    redirigir_modulo_portafolio('creado', $itemId);
}

if ($itemId <= 0) {
    foreach ($rutasNuevas as $rutaNueva) {
        eliminar_imagen_portafolio_local($rutaNueva);
    }
    redirigir_modulo_portafolio('id');
}

try {
    $stmtExiste = $pdo->prepare(
        'SELECT id, imagen_path
         FROM portafolio
         WHERE id = :id
         LIMIT 1'
    );
    $stmtExiste->execute([':id' => $itemId]);
    $registroActual = $stmtExiste->fetch();
    if (!$registroActual) {
        foreach ($rutasNuevas as $rutaNueva) {
            eliminar_imagen_portafolio_local($rutaNueva);
        }
        redirigir_modulo_portafolio('id');
    }

    $portadaActual = normalizar_ruta_imagen_portafolio((string) ($registroActual['imagen_path'] ?? ''));

    $pdo->beginTransaction();

    $rutasGaleria = rutas_imagenes_portafolio($pdo, $itemId);
    if (empty($rutasGaleria) && $portadaActual !== '') {
        insertar_imagenes_portafolio($pdo, $itemId, [$portadaActual], 1);
        $rutasGaleria = [$portadaActual];
    }

    if (!empty($rutasNuevas)) {
        $ordenInicial = obtener_siguiente_orden_imagen_portafolio($pdo, $itemId);
        insertar_imagenes_portafolio($pdo, $itemId, $rutasNuevas, $ordenInicial);
    }

    $rutasFinales = rutas_imagenes_portafolio($pdo, $itemId);
    if (empty($rutasFinales)) {
        if (!empty($rutasNuevas)) {
            $rutasFinales = $rutasNuevas;
        } elseif ($portadaActual !== '') {
            $rutasFinales = [$portadaActual];
        }
    }

    if (empty($rutasFinales)) {
        $pdo->rollBack();
        foreach ($rutasNuevas as $rutaNueva) {
            eliminar_imagen_portafolio_local($rutaNueva);
        }
        redirigir_modulo_portafolio('imagen_requerida', $itemId);
    }

    $portadaFinal = normalizar_ruta_imagen_portafolio((string) $rutasFinales[0]);

    $stmtUpdate = $pdo->prepare(
        'UPDATE portafolio
         SET titulo = :titulo,
             descripcion = :descripcion,
             imagen_path = :imagen_path,
             activo = :activo
         WHERE id = :id
         LIMIT 1'
    );
    $stmtUpdate->execute([
        ':id' => $itemId,
        ':titulo' => $titulo,
        ':descripcion' => $descripcion !== '' ? $descripcion : null,
        ':imagen_path' => $portadaFinal,
        ':activo' => $activo,
    ]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($rutasNuevas as $rutaNueva) {
        eliminar_imagen_portafolio_local($rutaNueva);
    }
    redirigir_modulo_portafolio('db', $itemId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($rutasNuevas as $rutaNueva) {
        eliminar_imagen_portafolio_local($rutaNueva);
    }
    redirigir_modulo_portafolio('general', $itemId);
}

redirigir_modulo_portafolio('actualizado', $itemId);
