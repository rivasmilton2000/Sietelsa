<?php
declare(strict_types=1);

function bitacora_asegurar_tabla(PDO $pdo): void
{
    static $asegurada = false;
    if ($asegurada) {
        return;
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS actividad_bitacora (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT UNSIGNED NOT NULL,
        usuario_nombre VARCHAR(120) NULL,
        usuario_username VARCHAR(60) NULL,
        perfil_nombre VARCHAR(50) NULL,
        modulo_slug VARCHAR(120) NULL,
        ruta VARCHAR(190) NOT NULL,
        metodo VARCHAR(10) NOT NULL,
        ip_origen VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_bitacora_usuario (usuario_id),
        INDEX idx_bitacora_modulo (modulo_slug),
        INDEX idx_bitacora_creado (creado_en)
    ) ENGINE=InnoDB');

    if (!indice_existe($pdo, 'actividad_bitacora', 'idx_bitacora_usuario')) {
        $pdo->exec('CREATE INDEX idx_bitacora_usuario ON actividad_bitacora (usuario_id)');
    }
    if (!indice_existe($pdo, 'actividad_bitacora', 'idx_bitacora_modulo')) {
        $pdo->exec('CREATE INDEX idx_bitacora_modulo ON actividad_bitacora (modulo_slug)');
    }
    if (!indice_existe($pdo, 'actividad_bitacora', 'idx_bitacora_creado')) {
        $pdo->exec('CREATE INDEX idx_bitacora_creado ON actividad_bitacora (creado_en)');
    }

    if (tabla_existe($pdo, 'usuarios') && !restriccion_existe($pdo, 'actividad_bitacora', 'fk_actividad_bitacora_usuario')) {
        $pdo->exec('ALTER TABLE actividad_bitacora
                    ADD CONSTRAINT fk_actividad_bitacora_usuario
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                    ON DELETE CASCADE ON UPDATE CASCADE');
    }

    $asegurada = true;
}

function bitacora_ruta_excluida(string $ruta): bool
{
    $ruta = trim($ruta);
    if ($ruta === '') {
        return true;
    }

    $excluidas = [
        '/admin/login',
        '/admin/auth/login',
        '/admin/auth/google',
        '/admin/auth/google/callback',
        '/admin/error/401',
        '/admin/error/403',
        '/admin/error/404',
        '/admin/error/500',
    ];

    if (in_array($ruta, $excluidas, true)) {
        return true;
    }

    if (str_starts_with($ruta, '/admin/dte/api/')) {
        return true;
    }
    if (str_starts_with($ruta, '/admin/dte/src/api/')) {
        return true;
    }

    return false;
}

function bitacora_registrar_evento(PDO $pdo, array $usuario, string $ruta, string $metodo = 'GET', string $moduloSlug = ''): void
{
    $usuarioId = (int) ($usuario['id'] ?? 0);
    if ($usuarioId <= 0) {
        return;
    }

    $ruta = '/' . ltrim((string) (parse_url($ruta, PHP_URL_PATH) ?: $ruta), '/');
    if ($ruta !== '/') {
        $ruta = rtrim($ruta, '/');
    }
    $ruta = substr($ruta, 0, 190);
    if ($ruta === '' || bitacora_ruta_excluida($ruta)) {
        return;
    }

    $metodo = strtoupper(trim($metodo));
    if ($metodo === '') {
        $metodo = 'GET';
    }
    $metodo = substr($metodo, 0, 10);

    if ($moduloSlug === '' && function_exists('modulos_publicacion_slug_desde_ruta')) {
        $resuelto = modulos_publicacion_slug_desde_ruta($ruta);
        $moduloSlug = is_string($resuelto) ? $resuelto : '';
    }
    $moduloSlug = substr(trim($moduloSlug), 0, 120);

    $stmt = $pdo->prepare(
        'INSERT INTO actividad_bitacora (
            usuario_id,
            usuario_nombre,
            usuario_username,
            perfil_nombre,
            modulo_slug,
            ruta,
            metodo,
            ip_origen,
            user_agent
        ) VALUES (
            :usuario_id,
            :usuario_nombre,
            :usuario_username,
            :perfil_nombre,
            :modulo_slug,
            :ruta,
            :metodo,
            :ip_origen,
            :user_agent
        )'
    );

    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':usuario_nombre' => substr(trim((string) ($usuario['nombre'] ?? '')), 0, 120),
        ':usuario_username' => substr(trim((string) ($usuario['username'] ?? '')), 0, 60),
        ':perfil_nombre' => substr(trim((string) ($usuario['perfil'] ?? '')), 0, 50),
        ':modulo_slug' => $moduloSlug !== '' ? $moduloSlug : null,
        ':ruta' => $ruta,
        ':metodo' => $metodo,
        ':ip_origen' => substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45),
        ':user_agent' => substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
    ]);
}

function bitacora_registrar_request_si_aplica(PDO $pdo, array $usuario, string $ruta = '', string $metodo = ''): void
{
    static $registradaEnRequest = false;
    if ($registradaEnRequest) {
        return;
    }

    $usuarioId = (int) ($usuario['id'] ?? 0);
    if ($usuarioId <= 0) {
        return;
    }

    $ruta = $ruta !== '' ? $ruta : (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $metodo = $metodo !== '' ? $metodo : (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

    iniciar_sesion_segura();
    $fingerprint = $usuarioId . '|' . strtoupper(trim($metodo)) . '|' . (string) (parse_url($ruta, PHP_URL_PATH) ?: $ruta);
    $lastFp = (string) ($_SESSION['bitacora_last_fp'] ?? '');
    $lastTs = (int) ($_SESSION['bitacora_last_ts'] ?? 0);
    if ($lastFp === $fingerprint && (time() - $lastTs) <= 2) {
        return;
    }

    try {
        bitacora_asegurar_tabla($pdo);
        bitacora_registrar_evento($pdo, $usuario, $ruta, $metodo);
        $_SESSION['bitacora_last_fp'] = $fingerprint;
        $_SESSION['bitacora_last_ts'] = time();
        $registradaEnRequest = true;
    } catch (Throwable $e) {
        sietelsa_log('Bitacora logging failed', ['message' => $e->getMessage()]);
    }
}
