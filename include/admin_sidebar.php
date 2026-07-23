<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function admin_sidebar_catalogo(): array
{
    return modulos_publicacion_catalogo();
}

function admin_sidebar_badge_estado(string $estado): ?array
{
    $estado = modulos_publicacion_normalizar_estado($estado);
    if ($estado === 'APROBADO') {
        return null;
    }

    if ($estado === 'PENDIENTE') {
        return ['class' => 'bg-warning text-dark', 'label' => 'pendiente'];
    }

    if ($estado === 'RECHAZADO') {
        return ['class' => 'bg-danger', 'label' => 'rechazado'];
    }

    return ['class' => 'bg-secondary', 'label' => 'borrador'];
}

function render_admin_sidebar(PDO $pdo, array $usuarioActual, string $etiquetaSesion): void
{
    sync_modules($pdo);
    sync_module_access_matrix($pdo);

    $catalogo = admin_sidebar_catalogo();
    $sesionEsAdmin = usuario_actual_es_admin();
    $modulosSistema = modulos_publicacion_mapa($pdo);
    $slugActual = modulos_publicacion_slug_actual();
    $usuarioId = (int) ($usuarioActual['id'] ?? ($_SESSION['usuario']['id'] ?? 0));

    $principal = [];
    $administracion = [];

    foreach ($catalogo as $item) {
        $slug = modulos_publicacion_normalizar_slug((string) ($item['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        if ($usuarioId <= 0) {
            continue;
        }

        if (!usuario_puede_acceder_modulo($usuarioId, $slug)) {
            continue;
        }

        $estado = modulos_publicacion_normalizar_estado((string) ($modulosSistema[$slug]['approval_status'] ?? 'BORRADOR'));
        if (!$sesionEsAdmin && $estado !== 'APROBADO') {
            continue;
        }

        $item['_slug'] = $slug;
        $item['_estado_publicacion'] = $estado;

        if (($item['seccion'] ?? '') === 'principal') {
            $principal[] = $item;
            continue;
        }

        $administracion[] = $item;
    }
    ?>
    <div id="layoutSidenav_nav">
        <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
            <div class="sb-sidenav-menu">
                <div class="nav">
                    <div class="sb-sidenav-menu-heading">Principal</div>
                    <?php foreach ($principal as $item): ?>
                        <?php
                        $estadoItem = (string) ($item['_estado_publicacion'] ?? 'APROBADO');
                        $badgeItem = $sesionEsAdmin ? admin_sidebar_badge_estado($estadoItem) : null;
                        $activeClass = (($item['_slug'] ?? null) === $slugActual) ? 'active' : '';
                        ?>
                        <a class="nav-link <?php echo $activeClass; ?>" href="<?php echo htmlspecialchars((string) $item['ruta'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="sb-nav-link-icon"><i class="fas <?php echo htmlspecialchars((string) $item['icono'], ENT_QUOTES, 'UTF-8'); ?>"></i></div>
                            <?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (is_array($badgeItem)): ?>
                                <span class="badge <?php echo htmlspecialchars((string) $badgeItem['class'], ENT_QUOTES, 'UTF-8'); ?> ms-2">
                                    <?php echo htmlspecialchars((string) $badgeItem['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>

                    <?php if (!empty($administracion)): ?>
                        <div class="sb-sidenav-menu-heading">Administracion</div>
                        <?php foreach ($administracion as $item): ?>
                            <?php
                            $estadoItem = (string) ($item['_estado_publicacion'] ?? 'APROBADO');
                            $badgeItem = $sesionEsAdmin ? admin_sidebar_badge_estado($estadoItem) : null;
                            $activeClass = (($item['_slug'] ?? null) === $slugActual) ? 'active' : '';
                            ?>
                            <a class="nav-link <?php echo $activeClass; ?>" href="<?php echo htmlspecialchars((string) $item['ruta'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="sb-nav-link-icon"><i class="fas <?php echo htmlspecialchars((string) $item['icono'], ENT_QUOTES, 'UTF-8'); ?>"></i></div>
                                <?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (is_array($badgeItem)): ?>
                                    <span class="badge <?php echo htmlspecialchars((string) $badgeItem['class'], ENT_QUOTES, 'UTF-8'); ?> ms-2">
                                        <?php echo htmlspecialchars((string) $badgeItem['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sb-sidenav-footer">
                <div class="small">Sesion iniciada:</div>
                <?php echo htmlspecialchars($etiquetaSesion, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </nav>
    </div>
    <?php
}
