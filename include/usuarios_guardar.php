<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/image_optimizer.php';
require_once __DIR__ . '/csrf.php';

function redirigir_modulo_usuarios(string $accion, string $estado, int $usuarioId = 0): void
{
    if ($accion === 'editar') {
        $query = 'error=' . urlencode($estado);
        if ($estado === 'actualizado') {
            $query = 'ok=actualizado';
        }
        if ($usuarioId > 0) {
            $query .= '&id=' . $usuarioId;
        }
        redirigir('/admin/usuarios/editar?' . $query);
    }

    $query = $estado === 'creado' ? 'ok=creado' : ('error=' . urlencode($estado));
    redirigir('/admin/usuarios/registro?' . $query);
}

function es_perfil_developer(string $perfil): bool
{
    return strtolower(trim($perfil)) === 'developer';
}

function normalizar_username_base(string $nombre): string
{
    $nombre = trim($nombre);
    if ($nombre === '') {
        return 'usuario';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
    if (is_string($ascii) && $ascii !== '') {
        $nombre = $ascii;
    }

    $nombre = strtolower($nombre);
    $nombre = preg_replace('/[^a-z0-9]+/', '.', $nombre) ?? '';
    $nombre = trim($nombre, '.-_');
    $nombre = preg_replace('/[._-]{2,}/', '.', $nombre) ?? '';

    if ($nombre === '') {
        return 'usuario';
    }

    if (strlen($nombre) < 3) {
        $nombre = str_pad($nombre, 3, 'x');
    }

    if (strlen($nombre) > 50) {
        $nombre = substr($nombre, 0, 50);
    }

    return trim($nombre, '.-_') ?: 'usuario';
}

function username_existe(PDO $pdo, string $username, int $ignorarUsuarioId = 0): bool
{
    $sql = 'SELECT 1 FROM usuarios WHERE username = :username';
    $params = [':username' => $username];

    if ($ignorarUsuarioId > 0) {
        $sql .= ' AND id <> :ignorar_id';
        $params[':ignorar_id'] = $ignorarUsuarioId;
    }

    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

function generar_username_disponible(PDO $pdo, string $nombre, int $ignorarUsuarioId = 0): string
{
    $base = normalizar_username_base($nombre);
    $candidato = $base;
    $contador = 2;

    while (username_existe($pdo, $candidato, $ignorarUsuarioId)) {
        $sufijo = '.' . $contador;
        $limiteBase = 60 - strlen($sufijo);
        $baseRecortada = substr($base, 0, max(3, $limiteBase));
        $candidato = $baseRecortada . $sufijo;
        $contador++;
    }

    return $candidato;
}

function generar_username_aleatorio_disponible(PDO $pdo, int $ignorarUsuarioId = 0): string
{
    $intento = 0;
    do {
        $intento++;
        $candidato = 'usr.' . random_int(100, 999) . '.' . random_int(100, 999);
        if (!username_existe($pdo, $candidato, $ignorarUsuarioId)) {
            return $candidato;
        }
    } while ($intento < 500);

    return generar_username_disponible($pdo, 'usuario', $ignorarUsuarioId);
}

function limpiar_texto_usuario(string $valor, int $maximo): string
{
    $valor = strip_tags(trim($valor));
    $valor = preg_replace('/[\x00-\x1F\x7F]/u', '', $valor) ?? '';
    if ($maximo > 0 && strlen($valor) > $maximo) {
        $valor = substr($valor, 0, $maximo);
    }
    return trim($valor);
}

function reglas_telefono_internacional_usuario(): array
{
    return [
        ['code' => '503', 'min' => 8, 'max' => 8],
        ['code' => '502', 'min' => 8, 'max' => 8],
        ['code' => '504', 'min' => 8, 'max' => 8],
        ['code' => '505', 'min' => 8, 'max' => 8],
        ['code' => '506', 'min' => 8, 'max' => 8],
        ['code' => '507', 'min' => 8, 'max' => 8],
        ['code' => '52', 'min' => 10, 'max' => 10],
        ['code' => '1', 'min' => 10, 'max' => 10],
    ];
}

function normalizar_telefono_internacional_usuario(string $telefono): ?string
{
    $telefono = strip_tags(trim($telefono));
    $telefono = preg_replace('/[\x00-\x1F\x7F]/u', '', $telefono) ?? '';
    if ($telefono === '') {
        return null;
    }

    $telefono = str_replace([' ', "\t", "\n", "\r", '-', '(', ')', '.'], '', $telefono);
    if (str_starts_with($telefono, '00')) {
        $telefono = '+' . substr($telefono, 2);
    }
    if (preg_match('/^\+\d+$/', $telefono) !== 1) {
        return null;
    }

    $soloDigitos = preg_replace('/\D+/', '', $telefono) ?? '';
    if ($soloDigitos === '' || strlen($soloDigitos) < 8 || strlen($soloDigitos) > 15) {
        return null;
    }

    $reglas = reglas_telefono_internacional_usuario();
    usort($reglas, static function (array $a, array $b): int {
        return strlen((string) ($b['code'] ?? '')) <=> strlen((string) ($a['code'] ?? ''));
    });

    foreach ($reglas as $regla) {
        $codigoPais = (string) ($regla['code'] ?? '');
        if ($codigoPais === '' || !str_starts_with($soloDigitos, $codigoPais)) {
            continue;
        }

        $numeroNacional = substr($soloDigitos, strlen($codigoPais));
        $longitudNacional = strlen((string) $numeroNacional);
        $minima = (int) ($regla['min'] ?? 0);
        $maxima = (int) ($regla['max'] ?? 0);
        if ($longitudNacional < $minima || $longitudNacional > $maxima) {
            return null;
        }

        return '+' . $soloDigitos;
    }

    return null;
}

function procesar_subida_foto(?array $archivo): array
{
    if ($archivo === null || !isset($archivo['error'])) {
        return ['subido' => false, 'ruta_relativa' => null, 'ruta_absoluta' => null];
    }

    $error = (int) $archivo['error'];
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['subido' => false, 'ruta_relativa' => null, 'ruta_absoluta' => null];
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo cargar la foto del usuario.');
    }

    $size = (int) ($archivo['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('La foto no tiene contenido.');
    }

    $tmpPath = (string) ($archivo['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('La foto recibida no es valida.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpPath);
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    if (!isset($mimeToExt[$mime])) {
        throw new RuntimeException('Formato de foto no permitido. Usa JPG, JPEG o PNG.');
    }

    $uploadDir = __DIR__ . '/../assets/uploads/usuarios';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('No se pudo crear el directorio para fotos.');
    }

    $filename = 'user_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $mimeToExt[$mime];
    $targetPath = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('No se pudo guardar la foto en el servidor.');
    }
    sietelsa_optimizar_imagen_subida($targetPath, 1200, 1200, 82);

    return [
        'subido' => true,
        'ruta_relativa' => 'assets/uploads/usuarios/' . $filename,
        'ruta_absoluta' => $targetPath,
    ];
}

function eliminar_foto_si_existe(?string $rutaRelativa): void
{
    if ($rutaRelativa === null || trim($rutaRelativa) === '') {
        return;
    }

    $filename = basename($rutaRelativa);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $path = __DIR__ . '/../assets/uploads/usuarios/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

function aplicar_modo_modulos_usuario(PDO $pdo, int $usuarioId, string $modoModulos): void
{
    $usuarioId = (int) $usuarioId;
    if ($usuarioId <= 0) {
        return;
    }

    $modoModulos = strtolower(trim($modoModulos));
    if (!in_array($modoModulos, ['perfil', 'permitir_todo', 'denegar_todo'], true)) {
        return;
    }

    if (!tabla_existe($pdo, 'system_modules') || !tabla_existe($pdo, 'user_module_override')) {
        return;
    }

    if ($modoModulos === 'perfil') {
        $stmtDelete = $pdo->prepare('DELETE FROM user_module_override WHERE user_id = :user_id');
        $stmtDelete->execute([':user_id' => $usuarioId]);
        return;
    }

    $effect = $modoModulos === 'permitir_todo' ? 'allow' : 'deny';
    $stmtModulos = $pdo->query('SELECT id FROM system_modules');
    $modulosIds = $stmtModulos ? array_map('intval', $stmtModulos->fetchAll(PDO::FETCH_COLUMN)) : [];
    if (empty($modulosIds)) {
        return;
    }

    $stmtUpsert = $pdo->prepare(
        'INSERT INTO user_module_override (user_id, module_id, effect)
         VALUES (:user_id, :module_id, :effect)
         ON DUPLICATE KEY UPDATE effect = VALUES(effect), updated_at = CURRENT_TIMESTAMP'
    );

    foreach ($modulosIds as $moduleId) {
        if ($moduleId <= 0) {
            continue;
        }

        $stmtUpsert->execute([
            ':user_id' => $usuarioId,
            ':module_id' => (int) $moduleId,
            ':effect' => $effect,
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('/admin/dashboard');
}

require_modulo('gestionar_usuarios');

global $pdo;
dte_asegurar_rbac_basico($pdo);
sync_modules($pdo);
sync_module_access_matrix($pdo);

$accion = trim((string) ($_POST['accion'] ?? ''));
if ($accion !== 'crear' && $accion !== 'editar') {
    redirigir('/admin/dashboard');
}

$sesion = obtener_usuario_actual() ?? [];
$usuarioSesionId = (int) ($sesion['id'] ?? 0);
$perfilSesion = strtolower(trim((string) ($sesion['perfil'] ?? '')));
$sesionEsDeveloper = es_perfil_developer($perfilSesion);

$token = trim((string) ($_POST['csrf_token'] ?? ''));
if (!csrf_token_valido($token, 'admin_usuarios_guardar')) {
    redirigir_modulo_usuarios($accion === 'editar' ? 'editar' : 'crear', 'csrf', (int) ($_POST['usuario_id'] ?? 0));
}

$usuarioId = (int) ($_POST['usuario_id'] ?? 0);
$nombre = limpiar_texto_usuario((string) ($_POST['nombre'] ?? ''), 100);
$username = limpiar_texto_usuario((string) ($_POST['username'] ?? ''), 60);
$email = trim((string) filter_var((string) ($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL));
$telefono = normalizar_telefono_internacional_usuario((string) ($_POST['telefono'] ?? '')) ?? '';
$direccion = limpiar_texto_usuario((string) ($_POST['direccion'] ?? ''), 200);
$perfilId = (int) ($_POST['perfil_id'] ?? 0);
$password = (string) ($_POST['password'] ?? '');
$activo = isset($_POST['activo']) ? 1 : 0;
$modoModulosRaw = strtolower(trim((string) ($_POST['modo_modulos'] ?? '')));
$aplicarModoModulos = false;
$modoModulos = 'perfil';

if ($accion === 'crear') {
    if (in_array($modoModulosRaw, ['perfil', 'permitir_todo', 'denegar_todo'], true)) {
        $modoModulos = $modoModulosRaw;
    }
    $aplicarModoModulos = true;
} elseif (in_array($modoModulosRaw, ['perfil', 'permitir_todo', 'denegar_todo'], true)) {
    $modoModulos = $modoModulosRaw;
    $aplicarModoModulos = true;
}

if ($accion === 'editar' && $usuarioId <= 0) {
    redirigir_modulo_usuarios($accion, 'id', $usuarioId);
}

$usuarioExistente = null;
$esEdicionPropia = false;
if ($accion === 'editar') {
    $stmtUsuario = $pdo->prepare(
        'SELECT u.id, u.foto_path, u.perfil_id, LOWER(TRIM(p.nombre)) AS perfil_normalizado
         FROM usuarios u
         INNER JOIN perfiles p ON p.id = u.perfil_id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmtUsuario->execute([':id' => $usuarioId]);
    $usuarioExistente = $stmtUsuario->fetch();
    if (!$usuarioExistente) {
        redirigir_modulo_usuarios($accion, 'id', $usuarioId);
    }

    $esEdicionPropia = $usuarioSesionId > 0 && $usuarioSesionId === $usuarioId;
    if ($esEdicionPropia) {
        $perfilId = (int) ($usuarioExistente['perfil_id'] ?? 0);
    }

    $perfilActualObjetivo = strtolower(trim((string) ($usuarioExistente['perfil_normalizado'] ?? '')));
    if (!$sesionEsDeveloper && es_perfil_developer($perfilActualObjetivo)) {
        redirigir_modulo_usuarios($accion, 'developer', $usuarioId);
    }
}

if ($accion === 'crear') {
    if ($nombre === '' || $email === '' || $telefono === '' || $direccion === '' || $perfilId <= 0) {
        redirigir_modulo_usuarios($accion, 'campos', $usuarioId);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirigir_modulo_usuarios($accion, 'email', $usuarioId);
    }

    if ($username === '') {
        $username = generar_username_aleatorio_disponible($pdo);
    } else {
        if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
            redirigir_modulo_usuarios($accion, 'username', $usuarioId);
        }
        if (username_existe($pdo, $username)) {
            $username = generar_username_disponible($pdo, $username);
        }
    }
} elseif ($username === '') {
    if ($nombre === '' || $email === '' || $telefono === '' || $direccion === '' || $perfilId <= 0) {
        redirigir_modulo_usuarios($accion, 'campos', $usuarioId);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirigir_modulo_usuarios($accion, 'email', $usuarioId);
    }

    $username = generar_username_disponible($pdo, $nombre, $usuarioId);
} else {
    if ($nombre === '' || $email === '' || $telefono === '' || $direccion === '' || $perfilId <= 0) {
        redirigir_modulo_usuarios($accion, 'campos', $usuarioId);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirigir_modulo_usuarios($accion, 'email', $usuarioId);
    }
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
    redirigir_modulo_usuarios($accion, 'username', $usuarioId);
}

if ($telefono === '') {
    redirigir_modulo_usuarios($accion, 'telefono', $usuarioId);
}

if (strlen($direccion) > 200) {
    redirigir_modulo_usuarios($accion, 'direccion', $usuarioId);
}

if ($accion === 'crear' && strlen($password) < 6) {
    redirigir_modulo_usuarios($accion, 'password', $usuarioId);
}

$sqlPerfil = 'SELECT id, LOWER(TRIM(nombre)) AS perfil_normalizado FROM perfiles WHERE id = :id';
if (columna_existe($pdo, 'perfiles', 'activo')) {
    $sqlPerfil .= ' AND activo = 1';
}
$sqlPerfil .= ' LIMIT 1';

$stmtPerfil = $pdo->prepare($sqlPerfil);
$stmtPerfil->execute([':id' => $perfilId]);
$perfilDestino = $stmtPerfil->fetch();
if (!$perfilDestino) {
    redirigir_modulo_usuarios($accion, 'perfil', $usuarioId);
}
$perfilDestinoNormalizado = strtolower(trim((string) ($perfilDestino['perfil_normalizado'] ?? '')));

if (!$sesionEsDeveloper && es_perfil_developer($perfilDestinoNormalizado)) {
    redirigir_modulo_usuarios($accion, 'developer', $usuarioId);
}

$fotoSubida = ['subido' => false, 'ruta_relativa' => null, 'ruta_absoluta' => null];
try {
    $fotoSubida = procesar_subida_foto($_FILES['foto'] ?? null);
} catch (Throwable $e) {
    redirigir_modulo_usuarios($accion, 'foto', $usuarioId);
}

$eliminarFoto = $accion === 'editar' && isset($_POST['eliminar_foto']);
$fotoAnterior = (string) ($usuarioExistente['foto_path'] ?? '');
$fotoFinal = $fotoAnterior;
if ($fotoSubida['subido']) {
    $fotoFinal = (string) $fotoSubida['ruta_relativa'];
} elseif ($eliminarFoto) {
    $fotoFinal = null;
}

$fotoAEliminarDespues = null;

try {
    $pdo->beginTransaction();

    if ($accion === 'crear') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmtInsert = $pdo->prepare(
            'INSERT INTO usuarios (nombre, username, email, telefono, direccion, foto_path, password_hash, perfil_id, activo)
             VALUES (:nombre, :username, :email, :telefono, :direccion, :foto_path, :password_hash, :perfil_id, :activo)'
        );
        $stmtInsert->execute([
            ':nombre' => $nombre,
            ':username' => $username,
            ':email' => $email,
            ':telefono' => ($telefono !== '' ? $telefono : null),
            ':direccion' => ($direccion !== '' ? $direccion : null),
            ':foto_path' => $fotoFinal,
            ':password_hash' => $passwordHash,
            ':perfil_id' => $perfilId,
            ':activo' => $activo,
        ]);
        $usuarioId = (int) $pdo->lastInsertId();
    } else {
        $params = [
            ':id' => $usuarioId,
            ':nombre' => $nombre,
            ':username' => $username,
            ':email' => $email,
            ':telefono' => ($telefono !== '' ? $telefono : null),
            ':direccion' => ($direccion !== '' ? $direccion : null),
            ':foto_path' => $fotoFinal,
            ':perfil_id' => $perfilId,
            ':activo' => $activo,
        ];

        $sql = 'UPDATE usuarios
                SET nombre = :nombre,
                    username = :username,
                    email = :email,
                    telefono = :telefono,
                    direccion = :direccion,
                    foto_path = :foto_path,
                    perfil_id = :perfil_id,
                    activo = :activo';

        if (trim($password) !== '') {
            if (strlen($password) < 6) {
                throw new RuntimeException('La clave debe tener minimo 6 caracteres.');
            }
            $sql .= ', password_hash = :password_hash';
            $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= ' WHERE id = :id LIMIT 1';
        $stmtUpdate = $pdo->prepare($sql);
        $stmtUpdate->execute($params);
    }

    if ($aplicarModoModulos) {
        aplicar_modo_modulos_usuario($pdo, $usuarioId, $modoModulos);
    }

    $pdo->commit();

    if ($accion === 'editar' && trim($fotoAnterior) !== '' && ($fotoFinal !== $fotoAnterior)) {
        $fotoAEliminarDespues = $fotoAnterior;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($fotoSubida['subido']) {
        eliminar_foto_si_existe((string) $fotoSubida['ruta_relativa']);
    }

    $errorCode = (string) ($e->errorInfo[1] ?? '');
    if ($errorCode === '1062') {
        redirigir_modulo_usuarios($accion, 'duplicado', $usuarioId);
    }

    redirigir_modulo_usuarios($accion, 'db', $usuarioId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($fotoSubida['subido']) {
        eliminar_foto_si_existe((string) $fotoSubida['ruta_relativa']);
    }

    redirigir_modulo_usuarios($accion, 'general', $usuarioId);
}

if ($fotoAEliminarDespues !== null) {
    eliminar_foto_si_existe($fotoAEliminarDespues);
}

$estadoFinal = 'actualizado';
if ($accion === 'crear') {
    $estadoFinal = 'creado';
}

redirigir_modulo_usuarios($accion, $estadoFinal, $usuarioId);
