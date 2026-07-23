<?php
declare(strict_types=1);

require_once __DIR__ . '/db_setup.php';

const MANTENIMIENTO_CLAVE_ESTADO = 'mantenimiento_activo';
const MANTENIMIENTO_CLAVE_PASSWORD_HASH = 'mantenimiento_password_hash';

function usuario_puede_gestionar_mantenimiento(?array $usuario): bool
{
    if (!is_array($usuario)) {
        return false;
    }

    $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
    return in_array($perfil, ['admin', 'developer'], true);
}

function usuario_puede_bypass_mantenimiento(?array $usuario): bool
{
    return usuario_puede_gestionar_mantenimiento($usuario);
}

function usuario_puede_configurar_password_mantenimiento(?array $usuario): bool
{
    if (!is_array($usuario)) {
        return false;
    }

    $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
    return in_array($perfil, ['admin', 'administrador'], true);
}

function mantenimiento_cache_configuracion(?string $clave = null, ?string $valor = null, bool $borrar = false): ?string
{
    static $cache = [];

    if ($borrar) {
        if ($clave === null) {
            $cache = [];
            return null;
        }

        unset($cache[$clave]);
        return null;
    }

    if ($valor !== null && $clave !== null) {
        $cache[$clave] = $valor;
        return $valor;
    }

    if ($clave === null || !array_key_exists($clave, $cache)) {
        return null;
    }

    return (string) $cache[$clave];
}

function mantenimiento_obtener_configuracion(PDO $pdo, string $clave, string $default = ''): string
{
    $clave = trim($clave);
    if ($clave === '') {
        return $default;
    }

    $cache = mantenimiento_cache_configuracion($clave);
    if ($cache !== null) {
        return $cache;
    }

    asegurar_tabla_configuracion_sistema($pdo);

    $stmt = $pdo->prepare('SELECT valor FROM configuracion_sistema WHERE clave = :clave LIMIT 1');
    $stmt->execute([':clave' => $clave]);
    $valor = trim((string) ($stmt->fetchColumn() ?: $default));
    mantenimiento_cache_configuracion($clave, $valor);

    return $valor;
}

function mantenimiento_guardar_configuracion(PDO $pdo, string $clave, string $valor): void
{
    $clave = trim($clave);
    if ($clave === '') {
        throw new InvalidArgumentException('La clave de configuracion de mantenimiento es obligatoria.');
    }

    asegurar_tabla_configuracion_sistema($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO configuracion_sistema (clave, valor)
         VALUES (:clave, :valor)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_en = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':clave' => $clave,
        ':valor' => $valor,
    ]);

    mantenimiento_cache_configuracion($clave, trim($valor));
}

function mantenimiento_esta_activo(PDO $pdo): bool
{
    $valor = mantenimiento_obtener_configuracion($pdo, MANTENIMIENTO_CLAVE_ESTADO, '0');
    return $valor === '1';
}

function mantenimiento_actualizar_estado(PDO $pdo, bool $activo): void
{
    mantenimiento_guardar_configuracion($pdo, MANTENIMIENTO_CLAVE_ESTADO, $activo ? '1' : '0');
}

function mantenimiento_password_configurada(PDO $pdo): bool
{
    return mantenimiento_obtener_password_hash($pdo) !== '';
}

function mantenimiento_obtener_password_hash(PDO $pdo): string
{
    return mantenimiento_obtener_configuracion($pdo, MANTENIMIENTO_CLAVE_PASSWORD_HASH, '');
}

function mantenimiento_verificar_password(PDO $pdo, string $passwordPlano): bool
{
    $passwordPlano = trim($passwordPlano);
    if ($passwordPlano === '') {
        return false;
    }

    $hash = mantenimiento_obtener_password_hash($pdo);
    if ($hash === '') {
        return false;
    }

    $esValida = password_verify($passwordPlano, $hash);
    if (!$esValida) {
        return false;
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        mantenimiento_actualizar_password($pdo, $passwordPlano);
    }

    return true;
}

function mantenimiento_actualizar_password(PDO $pdo, string $passwordPlano): void
{
    $passwordPlano = trim($passwordPlano);
    if ($passwordPlano === '') {
        throw new InvalidArgumentException('La clave de mantenimiento es obligatoria.');
    }

    $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('No se pudo generar hash para la clave de mantenimiento.');
    }

    mantenimiento_guardar_configuracion($pdo, MANTENIMIENTO_CLAVE_PASSWORD_HASH, $hash);
}

function mantenimiento_normalizar_accion(string $accion): string
{
    $accion = strtolower(trim($accion));
    if ($accion === '') {
        return 'evento';
    }

    return preg_replace('/[^a-z0-9_]/', '_', $accion) ?: 'evento';
}

function mantenimiento_label_accion(string $accion): string
{
    $accion = mantenimiento_normalizar_accion($accion);
    $mapa = [
        'activar_mantenimiento' => 'Activar mantenimiento',
        'desactivar_mantenimiento' => 'Desactivar mantenimiento',
        'acceso_modulo_mantenimiento' => 'Ingreso al modulo',
        'actualizar_clave_mantenimiento' => 'Actualizar clave',
        'primera_configuracion_clave_mantenimiento' => 'Primera configuracion de clave',
        'exportar_reporte_mantenimiento_excel' => 'Exportar reporte Excel',
        'exportar_reporte_mantenimiento_pdf' => 'Exportar reporte PDF',
    ];

    return $mapa[$accion] ?? ucfirst(str_replace('_', ' ', $accion));
}

function mantenimiento_registrar_evento(PDO $pdo, ?array $usuario, string $accion, string $detalle = ''): void
{
    asegurar_tabla_mantenimiento_eventos($pdo);

    $usuarioId = (int) ($usuario['id'] ?? 0);
    $usuarioNombre = trim((string) ($usuario['nombre'] ?? ''));
    $usuarioUsername = trim((string) ($usuario['username'] ?? ''));
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $accion = mantenimiento_normalizar_accion($accion);
    $detalle = trim($detalle);

    $stmt = $pdo->prepare(
        'INSERT INTO mantenimiento_eventos (
            usuario_id,
            usuario_nombre,
            usuario_username,
            accion,
            detalle,
            ip_origen
         ) VALUES (
            :usuario_id,
            :usuario_nombre,
            :usuario_username,
            :accion,
            :detalle,
            :ip_origen
         )'
    );
    $stmt->execute([
        ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
        ':usuario_nombre' => $usuarioNombre !== '' ? $usuarioNombre : null,
        ':usuario_username' => $usuarioUsername !== '' ? $usuarioUsername : null,
        ':accion' => $accion,
        ':detalle' => $detalle !== '' ? $detalle : null,
        ':ip_origen' => $ip !== '' ? $ip : null,
    ]);
}

function mantenimiento_obtener_eventos(PDO $pdo, int $limite = 200): array
{
    asegurar_tabla_mantenimiento_eventos($pdo);
    $limite = max(1, min(5000, $limite));

    $sql = 'SELECT me.id,
                   me.usuario_id,
                   me.usuario_nombre,
                   me.usuario_username,
                   me.accion,
                   me.detalle,
                   me.ip_origen,
                   me.creado_en,
                   u.nombre AS usuario_nombre_actual,
                   u.username AS usuario_username_actual
            FROM mantenimiento_eventos me
            LEFT JOIN usuarios u ON u.id = me.usuario_id
            ORDER BY me.id DESC
            LIMIT ' . $limite;

    $stmt = $pdo->query($sql);
    if (!$stmt) {
        return [];
    }

    return $stmt->fetchAll() ?: [];
}
