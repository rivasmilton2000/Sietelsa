<?php
declare(strict_types=1);

require_once __DIR__ . '/db_setup.php';

function dte_tabla(string $base): string
{
    $base = trim(strtolower($base));
    if ($base === '') {
        throw new InvalidArgumentException('Nombre de tabla DTE invalido.');
    }

    return 'dte_' . $base;
}

function dte_tablas_requeridas(): array
{
    return [
        dte_tabla('usuarios'),
        dte_tabla('user_bridge'),
        dte_tabla('empresas'),
        dte_tabla('libros'),
        dte_tabla('facturas'),
        dte_tabla('facturas_disponibles'),
    ];
}

function dte_esquema_disponible(PDO $pdo): bool
{
    foreach (dte_tablas_requeridas() as $tabla) {
        if (!tabla_existe($pdo, $tabla)) {
            return false;
        }
    }

    return true;
}

function dte_buscar_perfil(PDO $pdo, string $perfilNombre = 'dte', bool $soloActivo = true): ?array
{
    if (!tabla_existe($pdo, 'perfiles')) {
        return null;
    }

    $perfilNombre = strtolower(trim($perfilNombre));
    if ($perfilNombre === '') {
        return null;
    }

    $sql = 'SELECT id, nombre FROM perfiles WHERE LOWER(TRIM(nombre)) = :nombre';
    if ($soloActivo && columna_existe($pdo, 'perfiles', 'activo')) {
        $sql .= ' AND activo = 1';
    }
    $sql .= ' ORDER BY id ASC LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':nombre' => $perfilNombre]);
    $perfil = $stmt->fetch();

    if (!is_array($perfil) || (int) ($perfil['id'] ?? 0) <= 0) {
        return null;
    }

    return [
        'id' => (int) ($perfil['id'] ?? 0),
        'nombre' => trim((string) ($perfil['nombre'] ?? '')),
    ];
}

function dte_asegurar_rbac_basico(PDO $pdo): void
{
    static $ejecutado = false;
    if ($ejecutado) {
        return;
    }
    $ejecutado = true;

    if (!tabla_existe($pdo, 'perfiles')) {
        return;
    }

    try {
        $perfilTieneDescripcion = columna_existe($pdo, 'perfiles', 'descripcion');
        $perfilTieneActivo = columna_existe($pdo, 'perfiles', 'activo');
        $perfilDte = dte_buscar_perfil($pdo, 'dte', false);

        if ($perfilDte === null) {
            if ($perfilTieneDescripcion && $perfilTieneActivo) {
                $stmtInsertPerfil = $pdo->prepare(
                    "INSERT INTO perfiles (nombre, descripcion, activo)
                     VALUES ('dte', 'Acceso operativo a modulos DTE unificados', 1)"
                );
            } elseif ($perfilTieneDescripcion) {
                $stmtInsertPerfil = $pdo->prepare(
                    "INSERT INTO perfiles (nombre, descripcion)
                     VALUES ('dte', 'Acceso operativo a modulos DTE unificados')"
                );
            } elseif ($perfilTieneActivo) {
                $stmtInsertPerfil = $pdo->prepare(
                    "INSERT INTO perfiles (nombre, activo)
                     VALUES ('dte', 1)"
                );
            } else {
                $stmtInsertPerfil = $pdo->prepare(
                    "INSERT INTO perfiles (nombre)
                     VALUES ('dte')"
                );
            }

            $stmtInsertPerfil->execute();
            $perfilDte = dte_buscar_perfil($pdo, 'dte', false);
        }

        if (is_array($perfilDte) && (int) ($perfilDte['id'] ?? 0) > 0) {
            $paramsPerfil = [':id' => (int) $perfilDte['id']];

            if ($perfilTieneDescripcion) {
                $stmtUpdatePerfil = $pdo->prepare(
                    'UPDATE perfiles
                     SET descripcion = :descripcion
                     WHERE id = :id
                     LIMIT 1'
                );
                $stmtUpdatePerfil->execute($paramsPerfil + [
                    ':descripcion' => 'Acceso operativo a modulos DTE unificados',
                ]);
            }

            if ($perfilTieneActivo) {
                $stmtUpdateActivo = $pdo->prepare(
                    'UPDATE perfiles
                     SET activo = 1
                     WHERE id = :id
                     LIMIT 1'
                );
                $stmtUpdateActivo->execute($paramsPerfil);
            }
        }

        $perfilContadro = dte_buscar_perfil($pdo, 'contadro', false);
        if ($perfilContadro === null) {
            if ($perfilTieneDescripcion && $perfilTieneActivo) {
                $stmtInsertContadro = $pdo->prepare(
                    "INSERT INTO perfiles (nombre, descripcion, activo)
                     VALUES ('contadro', 'Perfil contable con acceso operativo DTE', 1)"
                );
            } elseif ($perfilTieneDescripcion) {
                $stmtInsertContadro = $pdo->prepare(
                    "INSERT INTO perfiles (nombre, descripcion)
                     VALUES ('contadro', 'Perfil contable con acceso operativo DTE')"
                );
            } elseif ($perfilTieneActivo) {
                $stmtInsertContadro = $pdo->prepare(
                    "INSERT INTO perfiles (nombre, activo)
                     VALUES ('contadro', 1)"
                );
            } else {
                $stmtInsertContadro = $pdo->prepare(
                    "INSERT INTO perfiles (nombre)
                     VALUES ('contadro')"
                );
            }

            $stmtInsertContadro->execute();
            $perfilContadro = dte_buscar_perfil($pdo, 'contadro', false);
        }

        if (is_array($perfilContadro) && (int) ($perfilContadro['id'] ?? 0) > 0) {
            $paramsPerfilContadro = [':id' => (int) $perfilContadro['id']];

            if ($perfilTieneDescripcion) {
                $stmtUpdateContadro = $pdo->prepare(
                    'UPDATE perfiles
                     SET descripcion = :descripcion
                     WHERE id = :id
                     LIMIT 1'
                );
                $stmtUpdateContadro->execute($paramsPerfilContadro + [
                    ':descripcion' => 'Perfil contable con acceso operativo DTE',
                ]);
            }

            if ($perfilTieneActivo) {
                $stmtUpdateActivoContadro = $pdo->prepare(
                    'UPDATE perfiles
                     SET activo = 1
                     WHERE id = :id
                     LIMIT 1'
                );
                $stmtUpdateActivoContadro->execute($paramsPerfilContadro);
            }
        }

        if (!tabla_existe($pdo, 'permisos')) {
            return;
        }

        $pdo->exec(
            "INSERT INTO permisos (codigo, descripcion) VALUES
                ('acceso_dte', 'Acceso general al panel DTE unificado'),
                ('dte_ver', 'Consultar informacion DTE'),
                ('dte_crear', 'Crear registros DTE'),
                ('dte_editar', 'Editar registros DTE'),
                ('dte_eliminar', 'Eliminar registros DTE'),
                ('dte_emitir', 'Emitir o importar documentos DTE'),
                ('dte_anular', 'Anular documentos DTE'),
                ('dte_reportes', 'Consultar reportes DTE'),
                ('dte_configurar', 'Configurar parametros DTE')
             ON DUPLICATE KEY UPDATE
                descripcion = VALUES(descripcion)"
        );

        if (tabla_existe($pdo, 'perfil_permiso')) {
            $pdo->exec(
                "INSERT INTO perfil_permiso (perfil_id, permiso_id)
                 SELECT p.id, pr.id
                 FROM perfiles p
                 INNER JOIN permisos pr ON pr.codigo IN (
                    'ver_dashboard',
                    'ver_bitacora',
                    'acceso_dte',
                    'dte_ver',
                    'dte_emitir',
                    'dte_reportes'
                 )
                 WHERE p.nombre IN ('dte', 'contadro')
                 ON DUPLICATE KEY UPDATE perfil_id = VALUES(perfil_id)"
            );
        }

        if (tabla_existe($pdo, 'modulos')) {
            $pdo->exec(
                "INSERT INTO modulos (nombre_modulo, descripcion, estado)
                 SELECT pr.codigo, pr.descripcion, 'activo'
                 FROM permisos pr
                 WHERE pr.codigo IN (
                    'ver_dashboard',
                    'ver_bitacora',
                    'acceso_dte',
                    'dte_ver',
                    'dte_crear',
                    'dte_editar',
                    'dte_eliminar',
                    'dte_emitir',
                    'dte_anular',
                    'dte_reportes',
                    'dte_configurar'
                 )
                 ON DUPLICATE KEY UPDATE
                    descripcion = VALUES(descripcion),
                    estado = VALUES(estado)"
            );
        }

        if (tabla_existe($pdo, 'perfil_modulo') && tabla_existe($pdo, 'modulos') && tabla_existe($pdo, 'perfil_permiso')) {
            $pdo->exec(
                "INSERT INTO perfil_modulo (perfil_id, modulo_id, aprobado)
                 SELECT pp.perfil_id, m.id, 1
                 FROM perfil_permiso pp
                 INNER JOIN permisos pr ON pr.id = pp.permiso_id
                 INNER JOIN modulos m ON BINARY m.nombre_modulo = BINARY pr.codigo
                 WHERE pr.codigo IN (
                    'acceso_dte',
                    'dte_ver',
                    'dte_crear',
                    'dte_editar',
                    'dte_eliminar',
                    'dte_emitir',
                    'dte_anular',
                    'dte_reportes',
                    'dte_configurar'
                 )
                 ON DUPLICATE KEY UPDATE aprobado = VALUES(aprobado)"
            );
        }

        if (tabla_existe($pdo, 'profile_module_access') && tabla_existe($pdo, 'system_modules')) {
            $pdo->exec(
                "INSERT INTO profile_module_access (profile_id, module_id, allowed)
                 SELECT p.id, sm.id, 1
                 FROM perfiles p
                 INNER JOIN system_modules sm ON sm.slug IN (
                    'bitacora',
                    'dte_dashboard',
                    'dte_compras',
                    'dte_ventas_consumidor',
                    'dte_ventas_contribuyente',
                    'dte_retencion_iva'
                 )
                 WHERE p.nombre IN ('dte', 'contadro')
                 ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)"
            );
        }
    } catch (Throwable $exception) {
        if (function_exists('sietelsa_log')) {
            sietelsa_log('Fallo asegurando RBAC DTE basico', ['message' => $exception->getMessage()]);
        }
    }
}

function dte_username_normalizado(array $usuario): string
{
    $base = trim((string) ($usuario['username'] ?? ''));
    if ($base === '') {
        $email = trim((string) ($usuario['email'] ?? ''));
        if ($email !== '') {
            $partes = explode('@', $email);
            $base = trim((string) ($partes[0] ?? ''));
        }
    }

    if ($base === '') {
        $base = 'usuario_' . (int) ($usuario['id'] ?? 0);
    }

    $base = strtolower($base);
    $base = preg_replace('/[^a-z0-9._-]+/', '_', $base) ?? '';
    $base = trim($base, '._-');
    if ($base === '') {
        $base = 'usuario_' . (int) ($usuario['id'] ?? 0);
    }

    if (strlen($base) < 3) {
        $base = str_pad($base, 3, 'x');
    }

    return substr($base, 0, 60);
}

function dte_rol_desde_usuario_principal(array $usuario): string
{
    $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
    if (in_array($perfil, ['admin', 'administrador', 'developer'], true)) {
        return 'admin';
    }

    return 'user';
}

function dte_id_usuario_por_username(PDO $pdo, string $username): ?int
{
    $tablaUsuarios = dte_tabla('usuarios');
    $stmt = $pdo->prepare(
        "SELECT id
         FROM {$tablaUsuarios}
         WHERE LOWER(username) = LOWER(:username)
         LIMIT 1"
    );
    $stmt->execute([':username' => $username]);

    $id = $stmt->fetchColumn();
    if ($id === false) {
        return null;
    }

    return (int) $id;
}

function dte_generar_username_disponible(PDO $pdo, string $base): string
{
    $base = dte_username_normalizado(['username' => $base]);
    $candidato = $base;
    $intento = 1;

    while (dte_id_usuario_por_username($pdo, $candidato) !== null) {
        $sufijo = '_' . $intento;
        $maxBase = max(1, 60 - strlen($sufijo));
        $candidato = substr($base, 0, $maxBase) . $sufijo;
        $intento++;

        if ($intento > 9999) {
            $candidato = 'usuario_' . bin2hex(random_bytes(3));
            break;
        }
    }

    return $candidato;
}

function dte_crear_usuario(PDO $pdo, array $usuarioPrincipal): int
{
    $tablaUsuarios = dte_tabla('usuarios');
    $usernameBase = dte_username_normalizado($usuarioPrincipal);
    $username = dte_generar_username_disponible($pdo, $usernameBase);
    $rol = dte_rol_desde_usuario_principal($usuarioPrincipal);

    $passwordRandom = bin2hex(random_bytes(16));
    $passwordHash = password_hash($passwordRandom, PASSWORD_DEFAULT);

    $nombreCompleto = trim((string) ($usuarioPrincipal['nombre'] ?? ''));
    if ($nombreCompleto === '') {
        $nombreCompleto = $username;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO {$tablaUsuarios} (username, nombre_completo, password, rol, estado)
         VALUES (:username, :nombre_completo, :password, :rol, 1)"
    );
    $stmt->execute([
        ':username' => $username,
        ':nombre_completo' => $nombreCompleto,
        ':password' => $passwordHash,
        ':rol' => $rol,
    ]);

    return (int) $pdo->lastInsertId();
}

function dte_crear_bridge(PDO $pdo, int $mainUserId, int $dteUserId): void
{
    $tablaBridge = dte_tabla('user_bridge');
    $stmt = $pdo->prepare(
        "INSERT INTO {$tablaBridge} (main_user_id, dte_user_id)
         VALUES (:main_user_id, :dte_user_id)
         ON DUPLICATE KEY UPDATE dte_user_id = VALUES(dte_user_id), updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([
        ':main_user_id' => $mainUserId,
        ':dte_user_id' => $dteUserId,
    ]);
}

function dte_contexto_usuario(PDO $pdo, array $usuarioPrincipal): ?array
{
    if (!dte_esquema_disponible($pdo)) {
        return null;
    }

    $mainUserId = (int) ($usuarioPrincipal['id'] ?? 0);
    if ($mainUserId <= 0) {
        return null;
    }

    $tablaBridge = dte_tabla('user_bridge');
    $tablaUsuarios = dte_tabla('usuarios');

    $stmtBridge = $pdo->prepare(
        "SELECT b.dte_user_id,
                u.username,
                u.rol,
                u.estado
         FROM {$tablaBridge} b
         INNER JOIN {$tablaUsuarios} u ON u.id = b.dte_user_id
         WHERE b.main_user_id = :main_user_id
         LIMIT 1"
    );
    $stmtBridge->execute([':main_user_id' => $mainUserId]);
    $fila = $stmtBridge->fetch();

    if (is_array($fila) && (int) ($fila['dte_user_id'] ?? 0) > 0 && (int) ($fila['estado'] ?? 0) === 1) {
        return [
            'id_usuario' => (int) $fila['dte_user_id'],
            'username' => (string) ($fila['username'] ?? ''),
            'rol' => (string) ($fila['rol'] ?? 'user'),
            'main_user_id' => $mainUserId,
        ];
    }

    $usernamePrincipal = dte_username_normalizado($usuarioPrincipal);
    $dteUserId = dte_id_usuario_por_username($pdo, $usernamePrincipal);
    if ($dteUserId === null) {
        $dteUserId = dte_crear_usuario($pdo, $usuarioPrincipal);
    }

    dte_crear_bridge($pdo, $mainUserId, $dteUserId);

    $stmtUsuario = $pdo->prepare(
        "SELECT id, username, rol, estado
         FROM {$tablaUsuarios}
         WHERE id = :id
         LIMIT 1"
    );
    $stmtUsuario->execute([':id' => $dteUserId]);
    $usuarioDte = $stmtUsuario->fetch();

    if (!is_array($usuarioDte) || (int) ($usuarioDte['estado'] ?? 0) !== 1) {
        return null;
    }

    return [
        'id_usuario' => (int) ($usuarioDte['id'] ?? 0),
        'username' => (string) ($usuarioDte['username'] ?? ''),
        'rol' => (string) ($usuarioDte['rol'] ?? 'user'),
        'main_user_id' => $mainUserId,
    ];
}
