<?php
declare(strict_types=1);

require_once __DIR__ . '/include/conexion.php';
require_once __DIR__ . '/include/cache.php';
require_once __DIR__ . '/include/perf.php';
require_once __DIR__ . '/include/image_optimizer.php';
require_once __DIR__ . '/include/asset.php';
require_once __DIR__ . '/include/db_setup.php';
require_once __DIR__ . '/include/visitas_analytics.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/maintenance.php';
require_once __DIR__ . '/include/csrf.php';

sietelsa_perf_start('index');

try {
    if (mantenimiento_esta_activo($pdo)) {
        $usuarioActual = obtener_usuario_actual();
        if (!usuario_puede_bypass_mantenimiento($usuarioActual)) {
            http_response_code(503);
            header('Retry-After: 600');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            require __DIR__ . '/maintenance.php';
            exit;
        }
    }
} catch (Throwable $e) {
}

$__sietelsaPageCacheEnabled = false;
$__sietelsaPageCacheKey = '';

if (
    sietelsa_cache_enabled()
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && !isset($_COOKIE[session_name()])
    && !isset($_GET['contacto'])
) {
    $__sietelsaPageCacheEnabled = true;
    $__sietelsaPageCacheKey = 'public_index_v2|' . (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $htmlCache = sietelsa_cache_get('page', $__sietelsaPageCacheKey);
    if (is_string($htmlCache) && $htmlCache !== '') {
        header('X-Sietelsa-Page-Cache: HIT');
        echo $htmlCache;
        sietelsa_perf_finish([
            'page_cache' => 'hit',
            'query_cache_enabled' => sietelsa_cache_enabled(),
        ]);
        exit;
    }
    header('X-Sietelsa-Page-Cache: MISS');
    ob_start();
}

try {
    asegurar_esquema_publico_cacheado($pdo);
} catch (Throwable $e) {
}

try {
    registrar_visita_sitio_controlado($pdo, 'inicio', 900);
} catch (Throwable $e) {
}

$servicios = sietelsa_cache_remember('query', 'index_servicios_v1', 120, static function () use ($pdo): array {
    $inicio = microtime(true);
    $stmtServicios = $pdo->query(
        'SELECT titulo, descripcion, icono
         FROM servicios
         WHERE activo = 1
         ORDER BY orden ASC, id ASC'
    );
    $data = $stmtServicios ? $stmtServicios->fetchAll() : [];
    sietelsa_perf_query('SELECT servicios activos', (microtime(true) - $inicio) * 1000, ['rows' => count($data)]);
    return $data;
});

$portafolio = sietelsa_cache_remember('query', 'index_portafolio_v1', 120, static function () use ($pdo): array {
    $inicio = microtime(true);
    $portafolio = [];
    $portafolioMap = [];
    $stmtPortafolio = $pdo->query(
        'SELECT p.id,
                p.titulo,
                p.descripcion,
                p.imagen_path AS portada_path,
                pi.imagen_path AS imagen_carrusel_path,
                pi.orden AS imagen_orden,
                pi.id AS imagen_id
         FROM portafolio p
         LEFT JOIN portafolio_imagenes pi ON pi.portafolio_id = p.id
         WHERE p.activo = 1
         ORDER BY p.orden ASC, p.id ASC, pi.orden ASC, pi.id ASC'
    );
    if ($stmtPortafolio) {
        foreach ($stmtPortafolio->fetchAll() as $filaPortafolio) {
            $id = (int) ($filaPortafolio['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            if (!isset($portafolioMap[$id])) {
                $portafolioMap[$id] = [
                    'id' => $id,
                    'titulo' => (string) ($filaPortafolio['titulo'] ?? ''),
                    'descripcion' => (string) ($filaPortafolio['descripcion'] ?? ''),
                    'imagen_path' => (string) ($filaPortafolio['portada_path'] ?? ''),
                    'imagenes' => [],
                ];
            }

            $imagenCarrusel = trim((string) ($filaPortafolio['imagen_carrusel_path'] ?? ''));
            if ($imagenCarrusel !== '' && !in_array($imagenCarrusel, $portafolioMap[$id]['imagenes'], true)) {
                $portafolioMap[$id]['imagenes'][] = $imagenCarrusel;
            }
        }
    }

    foreach ($portafolioMap as $itemPortafolio) {
        if (empty($itemPortafolio['imagenes'])) {
            $portada = trim((string) ($itemPortafolio['imagen_path'] ?? ''));
            if ($portada !== '') {
                $itemPortafolio['imagenes'][] = $portada;
            }
        }
        $portafolio[] = $itemPortafolio;
    }
    sietelsa_perf_query('SELECT portafolio publico', (microtime(true) - $inicio) * 1000, ['rows' => count($portafolio)]);
    return $portafolio;
});

$proyectos = sietelsa_cache_remember('query', 'index_proyectos_v1', 120, static function () use ($pdo): array {
    $inicio = microtime(true);
    $stmtProyectos = $pdo->query(
        'SELECT pr.id,
                pr.titulo,
                pr.imagen_path,
                pf.titulo AS categoria
         FROM proyectos pr
         LEFT JOIN portafolio pf ON pf.id = pr.portafolio_id
         WHERE pr.activo = 1
         ORDER BY pr.orden ASC, pr.id ASC'
    );
    $data = $stmtProyectos ? $stmtProyectos->fetchAll() : [];
    sietelsa_perf_query('SELECT proyectos publicos', (microtime(true) - $inicio) * 1000, ['rows' => count($data)]);
    return $data;
});

$nosotros = [
    'titulo' => 'NOSOTROS',
    'descripcion_1' => 'SIETELSA, S.A. DE C.V. nace en 2005 con el objetivo de otorgar soluciones de alto nivel a empresas nacionales y multinacionales, para todo tipo de proyectos de telecomunicaciones y electricidad.',
    'descripcion_2' => 'A traves de los anos, la empresa ha desempenado trabajos con eficacia y eficiencia, impulsando siempre calidad de servicio dentro de los plazos solicitados por nuestros clientes.',
    'descripcion_3' => 'Presencia en Centro America: El Salvador, Guatemala, Honduras y Nicaragua.',
    'imagen_path' => 'assets/img/fondoSietelsa.png',
];

$nosotrosCache = sietelsa_cache_remember('query', 'index_nosotros_v1', 300, static function () use ($pdo): array {
    $inicio = microtime(true);
    $stmtNosotros = $pdo->query(
        'SELECT titulo, descripcion_1, descripcion_2, descripcion_3, imagen_path
         FROM nosotros
         WHERE activo = 1
         ORDER BY orden ASC, id ASC
         LIMIT 1'
    );
    $data = $stmtNosotros ? ($stmtNosotros->fetch() ?: []) : [];
    sietelsa_perf_query('SELECT nosotros publico', (microtime(true) - $inicio) * 1000, ['rows' => is_array($data) ? 1 : 0]);
    return is_array($data) ? $data : [];
});
if (!empty($nosotrosCache)) {
    $nosotros = [
        'titulo' => trim((string) ($nosotrosCache['titulo'] ?? '')) !== '' ? (string) $nosotrosCache['titulo'] : $nosotros['titulo'],
        'descripcion_1' => (string) ($nosotrosCache['descripcion_1'] ?? $nosotros['descripcion_1']),
        'descripcion_2' => (string) ($nosotrosCache['descripcion_2'] ?? ''),
        'descripcion_3' => (string) ($nosotrosCache['descripcion_3'] ?? ''),
        'imagen_path' => (string) ($nosotrosCache['imagen_path'] ?? $nosotros['imagen_path']),
    ];
}

$contactoEstado = trim((string) ($_GET['contacto'] ?? ''));
$contactoMensaje = '';
$contactoTipo = 'success';
if ($contactoEstado === 'ok') {
    $contactoMensaje = 'Tu mensaje fue enviado correctamente. Te responderemos pronto.';
} elseif ($contactoEstado === 'ok_pendiente') {
    $contactoMensaje = 'Tu mensaje se guardo, pero no se pudo notificar por correo en este momento.';
    $contactoTipo = 'warning';
}

$erroresContacto = [
    'campos' => 'Completa los campos obligatorios del formulario.',
    'nombre' => 'El nombre excede el limite permitido.',
    'email' => 'El correo ingresado no es valido.',
    'telefono' => 'El telefono es obligatorio, maximo 15 caracteres y solo admite numeros, espacios, + y -.',
    'asunto' => 'El asunto es obligatorio y debe tener maximo 120 caracteres.',
    'mensaje' => 'El mensaje contiene caracteres no permitidos o no cumple el formato esperado.',
    'metodo' => 'Metodo no permitido para el formulario.',
    'rate_limit' => 'Demasiados envios en poco tiempo. Espera unos minutos antes de intentar nuevamente.',
    'csrf' => 'La sesion del formulario expiro. Recarga la pagina e intenta nuevamente.',
    'db' => 'No se pudo guardar tu mensaje por un error temporal.',
    'general' => 'No se pudo procesar tu solicitud.',
];
if (isset($erroresContacto[$contactoEstado])) {
    $contactoMensaje = $erroresContacto[$contactoEstado];
    $contactoTipo = 'danger';
}

$seccionesPermitidas = [
    'servicios' => 'services',
    'portafolio' => 'portfolio',
    'nosotros' => 'about',
    'proyectos' => 'proyectos',
    'ubicacion' => 'ubicacion',
    'contacto' => 'contact',
];
$seccionSolicitada = trim((string) ($_GET['section'] ?? ''));
$seccionObjetivo = $seccionesPermitidas[$seccionSolicitada] ?? '';
$servicioSolicitado = trim((string) ($_GET['service'] ?? ''));

$ubicacionNombre = 'SIETELSA SA DE CV';
$ubicacionDireccion = 'Final 5 avenida norte col Alfaro 506, Pje. F, Mejicanos';
$ubicacionTelefono = '2226 1027';
$ubicacionMapaEmbed = 'https://www.google.com/maps?q=13.7217104,-89.1920827&z=17&output=embed';
$ubicacionMapaLink = 'https://www.google.com/maps/place/SIETELSA+SA+DE+CV/@13.7217104,-89.1920827,17z';

$serviciosLanding = [
    'fibra-optica' => [
        'title' => 'Fibra Optica | Sietelsa El Salvador',
        'description' => 'Diseno, despliegue y mantenimiento de soluciones de fibra optica para conectividad empresarial en El Salvador y Centroamerica.',
        'h1' => 'Fibra Optica para Telecomunicaciones',
        'intro' => 'Implementamos redes de fibra optica para operadores y empresas, desde la ingenieria de campo hasta la activacion y soporte tecnico.',
        'beneficios' => [
            'Tendido, fusion y certificacion de enlaces de fibra.',
            'Mantenimiento preventivo y correctivo de infraestructura optica.',
            'Escalabilidad para proyectos corporativos y de operador.',
        ],
    ],
    'radiobases' => [
        'title' => 'Radiobases | Sietelsa El Salvador',
        'description' => 'Construccion, modernizacion y soporte de radiobases para redes moviles y proyectos de telecomunicaciones.',
        'h1' => 'Radiobases y Despliegue de Red Movil',
        'intro' => 'Desarrollamos proyectos de radiobases con enfoque en continuidad operativa, seguridad tecnica y cumplimiento de tiempos.',
        'beneficios' => [
            'Instalacion y adecuacion de sitios de radiobase.',
            'Integracion electromecanica y soporte en campo.',
            'Optimizacion para cobertura y capacidad de red.',
        ],
    ],
    'enlaces-microondas' => [
        'title' => 'Enlaces Microondas | Sietelsa El Salvador',
        'description' => 'Soluciones de enlaces de microondas para transporte de datos y conectividad troncal empresarial.',
        'h1' => 'Enlaces de Microondas para Transporte de Datos',
        'intro' => 'Disenamos e implementamos enlaces de microondas para conectar puntos criticos con alta disponibilidad y rendimiento.',
        'beneficios' => [
            'Planificacion y alineacion de enlaces punto a punto.',
            'Instalacion de equipos y pruebas de rendimiento.',
            'Soporte tecnico para continuidad del servicio.',
        ],
    ],
    'energia' => [
        'title' => 'Energia e Infraestructura Electrica | Sietelsa El Salvador',
        'description' => 'Servicios de energia e infraestructura electrica para telecomunicaciones, industria y operaciones empresariales.',
        'h1' => 'Energia e Infraestructura Electrica',
        'intro' => 'Ejecutamos proyectos electricos para garantizar disponibilidad energetica en operaciones de telecomunicaciones e infraestructura tecnica.',
        'beneficios' => [
            'Montaje y mantenimiento de sistemas electricos.',
            'Soluciones para respaldo energetico e infraestructura critica.',
            'Ejecucion tecnica con enfoque en seguridad y confiabilidad.',
        ],
    ],
    'torres-y-monopolos' => [
        'title' => 'Torres y Monopolos | Sietelsa El Salvador',
        'description' => 'Diseno, montaje y mantenimiento de torres y monopolos para proyectos de telecomunicaciones.',
        'h1' => 'Torres y Monopolos para Telecomunicaciones',
        'intro' => 'Desarrollamos infraestructura vertical para soportar redes de telecomunicaciones con criterios de calidad y seguridad.',
        'beneficios' => [
            'Montaje y adecuacion de torres y monopolos.',
            'Mantenimiento estructural y revisiones tecnicas.',
            'Soporte para expansion de cobertura y capacidad.',
        ],
    ],
];

$servicioLanding = $serviciosLanding[$servicioSolicitado] ?? null;
$esPaginaServicio = is_array($servicioLanding);

$usuarioNavbar = obtener_usuario_actual();
$usuarioAutenticadoNavbar = usuario_autenticado() && is_array($usuarioNavbar);
$nombreUsuarioNavbar = '';
$mostrarPanelControlNavbar = false;
$rutaPanelControlNavbar = '/admin/dashboard';
if ($usuarioAutenticadoNavbar) {
    $nombreUsuarioNavbar = trim((string) ($usuarioNavbar['nombre'] ?? ''));
    if ($nombreUsuarioNavbar === '') {
        $nombreUsuarioNavbar = trim((string) ($usuarioNavbar['username'] ?? ''));
    }
    if ($nombreUsuarioNavbar === '') {
        $nombreUsuarioNavbar = trim((string) ($usuarioNavbar['email'] ?? ''));
    }
    $mostrarPanelControlNavbar = usuario_tiene_acceso_panel_control($usuarioNavbar);
    if ($mostrarPanelControlNavbar) {
        $rutaPanelControlNavbar = ruta_inicio_usuario($usuarioNavbar);
    }
}

$sectionPathMap = [
    '' => '/',
    'servicios' => '/servicios',
    'portafolio' => '/portafolio',
    'nosotros' => '/nosotros',
    'proyectos' => '/proyectos',
    'contacto' => '/contacto',
    'ubicacion' => '/contacto',
];

$seoMap = [
    '' => [
        'title' => 'Sietelsa SA de CV | Telecomunicaciones y Electricidad en El Salvador',
        'description' => 'SIETELSA S.A. de C.V. brinda soluciones de telecomunicaciones y electricidad: radiobases, microondas, fibra optica, energia e infraestructura tecnica para empresas en El Salvador y Centroamerica.',
    ],
    'servicios' => [
        'title' => 'Servicios | Sietelsa',
        'description' => 'Conoce los servicios profesionales de SIETELSA en telecomunicaciones, energia e infraestructura.',
    ],
    'portafolio' => [
        'title' => 'Portafolio | Sietelsa',
        'description' => 'Explora el portafolio de proyectos y ejecuciones tecnicas desarrolladas por SIETELSA.',
    ],
    'nosotros' => [
        'title' => 'Nosotros | Sietelsa',
        'description' => 'Conoce la trayectoria de SIETELSA y su experiencia en proyectos de telecomunicaciones y electricidad.',
    ],
    'proyectos' => [
        'title' => 'Proyectos | Sietelsa',
        'description' => 'Revisa los proyectos destacados de SIETELSA en infraestructura, telecomunicaciones y energia.',
    ],
    'contacto' => [
        'title' => 'Contacto | Sietelsa',
        'description' => 'Contacta a SIETELSA para cotizaciones y consultas sobre servicios de telecomunicaciones y electricidad.',
    ],
    'ubicacion' => [
        'title' => 'Ubicacion y Contacto | Sietelsa',
        'description' => 'Encuentra la ubicacion de SIETELSA en Mejicanos, San Salvador y envia tu consulta desde el sitio.',
    ],
];

$baseUrl = rtrim((string) (getenv('APP_URL') ?: 'https://sietelsaonline.com'), '/');
$seoKey = array_key_exists($seccionSolicitada, $seoMap) ? $seccionSolicitada : '';
$seoData = $esPaginaServicio ? $servicioLanding : $seoMap[$seoKey];
$canonicalPath = $esPaginaServicio ? '/' . $servicioSolicitado : ($sectionPathMap[$seoKey] ?? '/');
$canonicalUrl = $baseUrl . $canonicalPath;
$metaOgImagePath = '/assets/img/logos/logoSietelsa.png';
$metaOgImageUrl = $baseUrl . $metaOgImagePath;
$schemaLogoPath = '/favicon-512x512.png';
$schemaLogoUrl = $baseUrl . $schemaLogoPath;
$telefonoJsonLd = '+503' . preg_replace('/\D+/', '', $ubicacionTelefono);
$contactoCsrfToken = csrf_cookie_token('contacto_publico');
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $ubicacionNombre,
    'url' => $baseUrl . '/',
    'telephone' => $telefonoJsonLd,
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $ubicacionDireccion,
        'addressLocality' => 'Mejicanos',
        'addressRegion' => 'San Salvador',
        'addressCountry' => 'SV',
    ],
    'openingHoursSpecification' => [
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'opens' => '08:00',
            'closes' => '17:00',
        ],
    ],
    'sameAs' => [
        'https://www.facebook.com/profile.php?id=100054891634300',
    ],
    'image' => $metaOgImageUrl,
    'logo' => $schemaLogoUrl,
];

function normalizar_icono_servicio_index(string $icono): string
{
    $icono = strtolower(trim($icono));
    if ($icono === '') {
        return 'fa-circle-info';
    }

    $tokens = preg_split('/\s+/', $icono) ?: [];
    foreach ($tokens as $token) {
        if (preg_match('/^fa-[a-z0-9-]+$/', $token) === 1) {
            return $token;
        }
    }

    return 'fa-circle-info';
}

function normalizar_ruta_imagen_portafolio_index(string $ruta): string
{
    $ruta = trim($ruta);
    if ($ruta === '') {
        return 'assets/img/portfolio/1.jpg';
    }

    if (preg_match('/^https?:\/\//i', $ruta) === 1) {
        return $ruta;
    }

    return ltrim(str_replace('\\', '/', $ruta), '/');
}

function desglosar_descripcion_portafolio_index(string $descripcion): array
{
    $descripcion = trim($descripcion);
    if ($descripcion === '') {
        return [];
    }

    if (strpos($descripcion, "\n") !== false) {
        $lineas = array_values(array_filter(array_map(static fn(string $linea): string => trim($linea), preg_split('/\r\n|\r|\n/', $descripcion) ?: [])));
        if (!empty($lineas)) {
            return $lineas;
        }
    }

    $descripcionNormalizada = preg_replace('/\s+/', ' ', $descripcion) ?: $descripcion;

    $partesPorPunto = array_values(array_filter(array_map('trim', preg_split('/\.\s+/', $descripcionNormalizada) ?: [])));
    if (count($partesPorPunto) > 1) {
        return $partesPorPunto;
    }

    $partesPorComa = array_values(array_filter(array_map('trim', preg_split('/,\s*/', $descripcionNormalizada) ?: [])));
    if (count($partesPorComa) >= 3) {
        return $partesPorComa;
    }

    return [$descripcionNormalizada];
}

function normalizar_ruta_imagen_nosotros_index(string $ruta): string
{
    $ruta = trim($ruta);
    if ($ruta === '') {
        return 'assets/img/fondoSietelsa.png';
    }

    if (preg_match('/^https?:\/\//i', $ruta) === 1) {
        return $ruta;
    }

    return ltrim(str_replace('\\', '/', $ruta), '/');
}

function normalizar_ruta_imagen_proyecto_index(string $ruta): string
{
    $ruta = trim($ruta);
    if ($ruta === '') {
        return 'assets/img/fondoSietelsa.png';
    }

    if (preg_match('/^https?:\/\//i', $ruta) === 1) {
        return $ruta;
    }

    return ltrim(str_replace('\\', '/', $ruta), '/');
}

function construir_fuentes_picture_index(string $ruta): array
{
    $ruta = trim($ruta);
    if ($ruta === '') {
        return [
            'fallback' => '',
            'webp' => null,
            'avif' => null,
        ];
    }

    if (preg_match('/^https?:\/\//i', $ruta) === 1) {
        return [
            'fallback' => $ruta,
            'webp' => null,
            'avif' => null,
        ];
    }

    $rutaNormalizada = ltrim(str_replace('\\', '/', $ruta), '/');
    $rutaLocal = __DIR__ . '/' . $rutaNormalizada;
    $ext = strtolower((string) pathinfo($rutaNormalizada, PATHINFO_EXTENSION));
    $base = preg_replace('/\.[a-z0-9]+$/i', '', $rutaNormalizada);
    if (!is_string($base) || $base === '') {
        return [
            'fallback' => $rutaNormalizada,
            'webp' => null,
            'avif' => null,
        ];
    }

    $webp = null;
    $avif = null;

    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true)) {
        $rutaWebp = $base . '.webp';
        if (is_file(__DIR__ . '/' . $rutaWebp)) {
            $webp = $rutaWebp;
        }

        $rutaAvif = $base . '.avif';
        if (is_file(__DIR__ . '/' . $rutaAvif)) {
            $avif = $rutaAvif;
        }
    }

    if (!is_file($rutaLocal) && $webp !== null) {
        $rutaNormalizada = $webp;
    }

    return [
        'fallback' => $rutaNormalizada,
        'webp' => $webp,
        'avif' => $avif,
    ];
}

function obtener_dimensiones_imagen_index(string $ruta, int $fallbackAncho, int $fallbackAlto): array
{
    static $cache = [];

    $ruta = trim($ruta);
    if ($ruta === '' || preg_match('/^https?:\/\//i', $ruta) === 1) {
        return ['width' => $fallbackAncho, 'height' => $fallbackAlto];
    }

    $rutaNormalizada = ltrim(str_replace('\\', '/', $ruta), '/');
    if (isset($cache[$rutaNormalizada])) {
        return $cache[$rutaNormalizada];
    }

    $rutaLocal = __DIR__ . '/' . $rutaNormalizada;
    if (!is_file($rutaLocal)) {
        $cache[$rutaNormalizada] = ['width' => $fallbackAncho, 'height' => $fallbackAlto];
        return $cache[$rutaNormalizada];
    }

    $datos = @getimagesize($rutaLocal);
    if (!is_array($datos) || !isset($datos[0], $datos[1])) {
        $cache[$rutaNormalizada] = ['width' => $fallbackAncho, 'height' => $fallbackAlto];
        return $cache[$rutaNormalizada];
    }

    $cache[$rutaNormalizada] = ['width' => (int) $datos[0], 'height' => (int) $datos[1]];
    return $cache[$rutaNormalizada];
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="<?php echo htmlspecialchars((string) $seoData['description'], ENT_QUOTES, 'UTF-8'); ?>" />
        <meta name="robots" content="index,follow,max-image-preview:large" />
        <meta name="author" content="Sietelsa" />
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="SIETELSA" />
        <meta property="og:title" content="<?php echo htmlspecialchars((string) $seoData['title'], ENT_QUOTES, 'UTF-8'); ?>" />
        <meta property="og:description" content="<?php echo htmlspecialchars((string) $seoData['description'], ENT_QUOTES, 'UTF-8'); ?>" />
        <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>" />
        <meta property="og:image" content="<?php echo htmlspecialchars($metaOgImageUrl, ENT_QUOTES, 'UTF-8'); ?>" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="<?php echo htmlspecialchars((string) $seoData['title'], ENT_QUOTES, 'UTF-8'); ?>" />
        <meta name="twitter:description" content="<?php echo htmlspecialchars((string) $seoData['description'], ENT_QUOTES, 'UTF-8'); ?>" />
        <meta name="twitter:image" content="<?php echo htmlspecialchars($metaOgImageUrl, ENT_QUOTES, 'UTF-8'); ?>" />
        <title><?php echo htmlspecialchars((string) $seoData['title'], ENT_QUOTES, 'UTF-8'); ?></title>
        <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>" />
        <!-- Favicon-->
        <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48x48.png" />
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
        <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192x192.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
        <link rel="shortcut icon" href="/favicon.ico" />
        <link rel="manifest" href="/site.webmanifest" />
        <!-- Font Awesome icons (free version)-->
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link rel="preconnect" href="https://use.fontawesome.com" crossorigin />
        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin />
        <link rel="preload" as="image" href="assets/img/fondoSietelsa.webp" type="image/webp" fetchpriority="high" />
        <script defer src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <!-- Google fonts-->
        <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Roboto+Slab:wght@400;700&display=swap" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Roboto+Slab:wght@400;700&display=swap" media="print" onload="this.media='all'" />
        <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Roboto+Slab:wght@400;700&display=swap" /></noscript>
        <!-- Core theme CSS (includes Bootstrap)-->
        <link href="<?php echo htmlspecialchars(sietelsa_asset_url('css/styles.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
        <link href="<?php echo htmlspecialchars(sietelsa_asset_url('css/index-redesign.min.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" />
        <style>
            .navbar-user-zone {
                display: flex;
                align-items: center;
                gap: 0.7rem;
                margin-top: 0.5rem;
            }
            .navbar-user-label {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                margin: 0;
                font-size: 0.72rem;
                font-weight: 500;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: rgba(226, 234, 255, 0.8);
                white-space: nowrap;
            }
            .navbar-user-name {
                color: #ffffff;
                font-weight: 600;
                letter-spacing: 0.02em;
            }
            .navbar-user-actions {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
            }
            .navbar-action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 36px;
                padding: 0.5rem 0.9rem;
                border-radius: 10px;
                border: 1px solid transparent;
                font-size: 0.72rem;
                font-weight: 600;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                line-height: 1;
                text-decoration: none;
                transition: background-color 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease, color 0.22s ease, transform 0.22s ease;
            }
            .navbar-action-btn:focus-visible {
                outline: none;
                box-shadow: 0 0 0 3px rgba(116, 165, 255, 0.35);
            }
            .navbar-action-btn-primary {
                background: #3f6de0;
                border-color: #4f7ef1;
                color: #f8fbff;
            }
            .navbar-action-btn-primary:hover {
                background: #4b79ea;
                border-color: #5a88f6;
                color: #ffffff;
                box-shadow: 0 8px 20px rgba(42, 92, 210, 0.24);
                transform: translateY(-1px);
            }
            .navbar-action-btn-secondary {
                background: transparent;
                border-color: rgba(221, 232, 255, 0.34);
                color: rgba(233, 241, 255, 0.92);
            }
            .navbar-action-btn-secondary:hover {
                background: rgba(255, 255, 255, 0.08);
                border-color: rgba(232, 240, 255, 0.56);
                color: #ffffff;
                box-shadow: 0 6px 16px rgba(8, 20, 52, 0.24);
                transform: translateY(-1px);
            }
            @media (min-width: 992px) {
                .navbar-user-zone {
                    margin-top: 0;
                    margin-left: 0.65rem;
                }
            }
            @media (max-width: 991.98px) {
                .navbar-user-zone {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.55rem;
                }
                .navbar-user-actions {
                    width: 100%;
                    justify-content: flex-start;
                    flex-wrap: wrap;
                }
            }
        </style>
        <script type="application/ld+json"><?php echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    </head>
    <body id="page-top">
        <!-- Navigation-->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
            <div class="container">
                <?php
                $fuentesLogo = construir_fuentes_picture_index('assets/img/logos/logoSietelsa.png');
                $dimensionesLogo = obtener_dimensiones_imagen_index($fuentesLogo['fallback'], 450, 250);
                ?>
                <a class="navbar-brand" href="#page-top">
                    <picture>
                        <?php if ($fuentesLogo['avif'] !== null): ?>
                            <source type="image/avif" srcset="<?php echo htmlspecialchars($fuentesLogo['avif'], ENT_QUOTES, 'UTF-8'); ?>" />
                        <?php endif; ?>
                        <?php if ($fuentesLogo['webp'] !== null): ?>
                            <source type="image/webp" srcset="<?php echo htmlspecialchars($fuentesLogo['webp'], ENT_QUOTES, 'UTF-8'); ?>" />
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars($fuentesLogo['fallback'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo Sietelsa" width="<?php echo $dimensionesLogo['width']; ?>" height="<?php echo $dimensionesLogo['height']; ?>" decoding="async" fetchpriority="high" />
                    </picture>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
                    Menu
                    <i class="fas fa-bars ms-1"></i>
                </button>
                <div class="collapse navbar-collapse" id="navbarResponsive">
                    <ul class="navbar-nav text-uppercase ms-auto py-4 py-lg-0">
                        <li class="nav-item"><a class="nav-link" href="#services">Servicios</a></li>
                        <li class="nav-item"><a class="nav-link" href="#portfolio">Portafolio</a></li>
                        <li class="nav-item"><a class="nav-link" href="#about">Nosotros</a></li>
                        <li class="nav-item"><a class="nav-link" href="#proyectos">Proyectos</a></li>
                        <li class="nav-item"><a class="nav-link" href="#ubicacion">Ubicacion</a></li>
                        <li class="nav-item"><a class="nav-link" href="#contact">Contacto</a></li>
                        <?php if ($usuarioAutenticadoNavbar): ?>
                            <li class="nav-item navbar-user-zone">
                                <span class="navbar-user-label">
                                    Usuario:
                                    <strong class="navbar-user-name"><?php echo htmlspecialchars($nombreUsuarioNavbar !== '' ? $nombreUsuarioNavbar : 'Usuario', ENT_QUOTES, 'UTF-8'); ?></strong>
                                </span>
                                <div class="navbar-user-actions">
                                    <?php if ($mostrarPanelControlNavbar): ?>
                                        <a class="navbar-action-btn navbar-action-btn-primary" href="<?php echo htmlspecialchars($rutaPanelControlNavbar, ENT_QUOTES, 'UTF-8'); ?>">
                                            Panel de control
                                        </a>
                                    <?php endif; ?>
                                    <a class="navbar-action-btn navbar-action-btn-secondary" href="/admin/auth/logout">
                                        Cerrar sesion
                                    </a>
                                </div>
                            </li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="/admin/login">Iniciar Sesion</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
        <!-- Tarjeta de bienvenida  index-->
        <header class="masthead">
            <div class="container">
                <div class="masthead-card">
                    <div class="masthead-subheading">Confianza y profesionalismo</div>
                    <div class="masthead-heading text-uppercase">Seguridad y alta competencia tecnica</div>
                    <a class="btn btn-primary btn-xl text-uppercase" href="#services">Conoce nuestros servicios</a>
                </div>
            </div>
        </header>
        <?php if ($esPaginaServicio): ?>
            <section class="page-section bg-light">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-lg-10">
                            <h1 class="text-uppercase mb-4"><?php echo htmlspecialchars((string) $servicioLanding['h1'], ENT_QUOTES, 'UTF-8'); ?></h1>
                            <p class="lead mb-4"><?php echo htmlspecialchars((string) $servicioLanding['intro'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <ul class="text-muted mb-4">
                                <?php foreach (($servicioLanding['beneficios'] ?? []) as $beneficio): ?>
                                    <li><?php echo htmlspecialchars((string) $beneficio, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="mb-0">
                                <a class="btn btn-primary text-uppercase" href="/contacto">Solicitar cotizacion</a>
                            </p>
                            <p class="mt-4 mb-0 text-muted">
                                Otros servicios:
                                <a href="/fibra-optica">Fibra optica</a>,
                                <a href="/radiobases">Radiobases</a>,
                                <a href="/enlaces-microondas">Enlaces microondas</a>,
                                <a href="/energia">Energia</a>,
                                <a href="/torres-y-monopolos">Torres y monopolos</a>.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
        <!-- Services-->
        <section class="page-section" id="services">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Servicios</h2>
                    <h3 class="section-subheading text-muted">Conoce nuestras soluciones profesionales.</h3>
                </div>
                <div class="row text-center">
                    <?php if (empty($servicios)): ?>
                        <div class="col-12">
                            <p class="text-muted mb-0">No hay servicios publicados por el momento.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($servicios as $servicio): ?>
                            <div class="col-md-4">
                                <span class="fa-stack fa-4x">
                                    <i class="fas fa-circle fa-stack-2x text-primary"></i>
                                    <i class="fas <?php echo htmlspecialchars(normalizar_icono_servicio_index((string) ($servicio['icono'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> fa-stack-1x fa-inverse"></i>
                                </span>
                                <h4 class="my-3"><?php echo htmlspecialchars((string) ($servicio['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h4>
                                <?php $descripcionServicio = trim((string) ($servicio['descripcion'] ?? '')); ?>
                                <?php if ($descripcionServicio !== ''): ?>
                                    <p class="text-muted"><?php echo htmlspecialchars($descripcionServicio, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <!-- Portfolio Grid-->
        <section class="page-section bg-light" id="portfolio">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Portafolio</h2>
                    <h3 class="section-subheading text-muted">Proyectos y trabajos destacados.</h3>
                </div>
                <div class="row portfolio-grid">
                    <?php if (empty($portafolio)): ?>
                        <div class="col-12">
                            <p class="text-muted mb-0">No hay proyectos de portafolio publicados por el momento.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($portafolio as $proyecto): ?>
                            <?php
                            $proyectoId = (int) ($proyecto['id'] ?? 0);
                            $modalId = 'portfolioModal' . $proyectoId;
                            $tituloProyecto = trim((string) ($proyecto['titulo'] ?? ''));
                            $imagenesProyecto = is_array($proyecto['imagenes'] ?? null) ? $proyecto['imagenes'] : [];
                            $imagenPortadaProyecto = (string) ($proyecto['imagen_path'] ?? '');
                            $imagenProyecto = normalizar_ruta_imagen_portafolio_index((string) ($imagenesProyecto[0] ?? $imagenPortadaProyecto));
                            $variantesImagenProyecto = sietelsa_generar_variantes_imagen($imagenProyecto, 640, 420, 'cover', 82);
                            ?>
                            <div class="col-lg-4 col-sm-6 mb-4 portfolio-card-col">
                                <div class="portfolio-item">
                                    <a class="portfolio-link" data-bs-toggle="modal" data-bs-target="#<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>" href="#!">
                                        <div class="portfolio-hover">
                                            <div class="portfolio-hover-content"><i class="fas fa-plus fa-3x"></i></div>
                                        </div>
                                        <picture>
                                            <?php if ($variantesImagenProyecto['webp'] !== null): ?>
                                                <source type="image/webp" srcset="<?php echo htmlspecialchars((string) $variantesImagenProyecto['webp'], ENT_QUOTES, 'UTF-8'); ?>" />
                                            <?php endif; ?>
                                            <img class="img-fluid" loading="lazy" decoding="async" src="<?php echo htmlspecialchars((string) $variantesImagenProyecto['fallback'], ENT_QUOTES, 'UTF-8'); ?>" width="<?php echo (int) $variantesImagenProyecto['width']; ?>" height="<?php echo (int) $variantesImagenProyecto['height']; ?>" alt="<?php echo htmlspecialchars($tituloProyecto !== '' ? $tituloProyecto : 'Proyecto del portafolio', ENT_QUOTES, 'UTF-8'); ?>" />
                                        </picture>
                                    </a>
                                    <div class="portfolio-caption">
                                        <div class="portfolio-caption-heading"><?php echo htmlspecialchars($tituloProyecto !== '' ? $tituloProyecto : 'Proyecto', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="portfolio-caption-subheading text-muted">Ver galeria</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <!-- About-->
        <section class="page-section about-redesign" id="about">
            <div class="about-layout">
                <div class="about-copy">
                    <h2 class="about-title"><?php echo htmlspecialchars((string) ($nosotros['titulo'] ?? 'NOSOTROS'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <span class="about-accent"></span>
                    <p><?php echo nl2br(htmlspecialchars((string) ($nosotros['descripcion_1'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php $parrafo2Nosotros = trim((string) ($nosotros['descripcion_2'] ?? '')); ?>
                    <?php if ($parrafo2Nosotros !== ''): ?>
                        <p><?php echo nl2br(htmlspecialchars($parrafo2Nosotros, ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php endif; ?>
                    <?php $parrafo3Nosotros = trim((string) ($nosotros['descripcion_3'] ?? '')); ?>
                    <?php if ($parrafo3Nosotros !== ''): ?>
                        <p><?php echo nl2br(htmlspecialchars($parrafo3Nosotros, ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php endif; ?>
                </div>
                <div class="about-media">
                    <?php
                    $rutaImagenNosotros = normalizar_ruta_imagen_nosotros_index((string) ($nosotros['imagen_path'] ?? ''));
                    $variantesImagenNosotros = sietelsa_generar_variantes_imagen($rutaImagenNosotros, 960, 640, 'cover', 82);
                    ?>
                    <picture>
                        <?php if ($variantesImagenNosotros['webp'] !== null): ?>
                            <source type="image/webp" srcset="<?php echo htmlspecialchars((string) $variantesImagenNosotros['webp'], ENT_QUOTES, 'UTF-8'); ?>" />
                        <?php endif; ?>
                    <img
                        class="about-image"
                        loading="lazy"
                        decoding="async"
                        src="<?php echo htmlspecialchars((string) $variantesImagenNosotros['fallback'], ENT_QUOTES, 'UTF-8'); ?>"
                        width="<?php echo (int) $variantesImagenNosotros['width']; ?>"
                        height="<?php echo (int) $variantesImagenNosotros['height']; ?>"
                        alt="<?php echo htmlspecialchars((string) ($nosotros['titulo'] ?? 'Nosotros'), ENT_QUOTES, 'UTF-8'); ?>"
                    />
                    </picture>
                </div>
            </div>
        </section>
        <!-- Proyectos-->
        <section class="page-section projects-showcase" id="proyectos">
            <div class="container">
                <div class="text-center projects-heading-wrap">
                    <h2 class="section-heading text-uppercase projects-heading">Proyectos</h2>
                    <span class="projects-accent"></span>
                    <h3 class="section-subheading projects-subheading">Ejecuciones destacadas en telecomunicaciones, energia e infraestructura tecnica.</h3>
                </div>
                <?php if (empty($proyectos)): ?>
                    <div class="text-center">
                        <p class="text-muted mb-0">No hay proyectos publicados por el momento.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($proyectos as $proyectoItem): ?>
                            <?php
                            $tituloProyecto = trim((string) ($proyectoItem['titulo'] ?? ''));
                            $categoriaProyecto = trim((string) ($proyectoItem['categoria'] ?? ''));
                            $rutaProyecto = normalizar_ruta_imagen_proyecto_index((string) ($proyectoItem['imagen_path'] ?? ''));
                            $variantesProyecto = sietelsa_generar_variantes_imagen($rutaProyecto, 640, 420, 'cover', 82);
                            ?>
                            <div class="col-md-6 col-xl-4">
                                <article class="project-card-redesign">
                                    <div class="project-media-wrap">
                                        <picture>
                                            <?php if ($variantesProyecto['webp'] !== null): ?>
                                                <source type="image/webp" srcset="<?php echo htmlspecialchars((string) $variantesProyecto['webp'], ENT_QUOTES, 'UTF-8'); ?>" />
                                            <?php endif; ?>
                                            <img
                                                class="project-media"
                                                loading="lazy"
                                                decoding="async"
                                                src="<?php echo htmlspecialchars((string) $variantesProyecto['fallback'], ENT_QUOTES, 'UTF-8'); ?>"
                                                width="<?php echo (int) $variantesProyecto['width']; ?>"
                                                height="<?php echo (int) $variantesProyecto['height']; ?>"
                                                alt="<?php echo htmlspecialchars($tituloProyecto !== '' ? $tituloProyecto : 'Proyecto Sietelsa', ENT_QUOTES, 'UTF-8'); ?>"
                                            />
                                        </picture>
                                    </div>
                                    <div class="project-card-content">
                                        <h4 class="project-card-title"><?php echo htmlspecialchars($tituloProyecto !== '' ? $tituloProyecto : 'Proyecto', ENT_QUOTES, 'UTF-8'); ?></h4>
                                        <?php if ($categoriaProyecto !== ''): ?>
                                            <p class="project-card-category"><?php echo htmlspecialchars($categoriaProyecto, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <!--
        Clients
        <div class="py-5">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-4 col-sm-6 my-3">
                        <a href="#!"><img class="img-fluid img-brand d-block mx-auto" src="assets/img/logos/Claro-Logo.png" alt="Logo Claro" aria-label="Claro Logo" /></a>
                    </div>
                    <div class="col-md-4 col-sm-6 my-3">
                        <a href="#!"><img class="img-fluid img-brand d-block mx-auto" src="assets/img/logos/Tigo.png" alt="Logo Tigo" aria-label="Tigo Logo" /></a>
                    </div>
                    <div class="col-md-4 col-sm-6 my-3">
                        <a href="#!"><img class="img-fluid img-brand d-block mx-auto" src="assets/img/logos/telefonica-logo-0-1.png" alt="Logo Telefonica" aria-label="Telefonica Logo" /></a>
                    </div>
                </div>
            </div>
        </div>
        -->
        <!-- Ubicacion-->
        <section class="page-section bg-light" id="ubicacion">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Ubicacion</h2>
                    <h3 class="section-subheading text-muted">Encuentranos en Mejicanos, San Salvador.</h3>
                </div>
                <div class="row g-4 align-items-stretch">
                    <div class="col-lg-7">
                        <div class="ratio ratio-16x9 shadow-sm rounded-3 overflow-hidden bg-white">
                            <iframe
                                title="Mapa de ubicacion de SIETELSA"
                                src="<?php echo htmlspecialchars($ubicacionMapaEmbed, ENT_QUOTES, 'UTF-8'); ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen
                            ></iframe>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-body p-4">
                                <h4 class="mb-3"><?php echo htmlspecialchars($ubicacionNombre, ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p class="mb-2"><strong>Direccion:</strong> <?php echo htmlspecialchars($ubicacionDireccion, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="mb-4"><strong>Telefono:</strong> <?php echo htmlspecialchars($ubicacionTelefono, ENT_QUOTES, 'UTF-8'); ?></p>
                                <a
                                    class="btn btn-primary text-uppercase"
                                    href="<?php echo htmlspecialchars($ubicacionMapaLink, ENT_QUOTES, 'UTF-8'); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Ver en Google Maps
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Contact-->
        <section class="page-section" id="contact">
            <div class="container">
                <div class="text-center">
                    <h2 class="section-heading text-uppercase">Contactanos</h2>
                    <h3 class="section-subheading text-muted">Envianos tu consulta y te responderemos por correo.</h3>
                </div>
                <?php if ($contactoMensaje !== ''): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($contactoTipo, ENT_QUOTES, 'UTF-8'); ?> text-center" role="alert" id="contactStatusAlert">
                        <?php echo htmlspecialchars($contactoMensaje, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <form id="contactForm" action="/contacto/enviar" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($contactoCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                    <div class="row align-items-stretch mb-5">
                        <div class="col-md-6">
                            <div class="form-group">
                                <input class="form-control" id="nombre" name="nombre" type="text" minlength="2" maxlength="120" placeholder="Tu nombre *" required />
                            </div>
                            <div class="form-group">
                                <input class="form-control" id="email" name="email" type="email" maxlength="120" placeholder="Tu correo *" required />
                            </div>
                            <div class="form-group">
                                <input class="form-control" id="telefono" name="telefono" type="text" maxlength="15" placeholder="Telefono *" pattern="[0-9+\-\s]{1,15}" title="Ingresa solo numeros, espacios, + y -, maximo 15 caracteres." required />
                            </div>
                            <div class="form-group mb-md-0">
                                <input class="form-control" id="asunto" name="asunto" type="text" maxlength="120" placeholder="Asunto *" required />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group form-group-textarea mb-md-0">
                                <textarea class="form-control" id="mensaje" name="mensaje" placeholder="Tu mensaje *" minlength="5" maxlength="5000" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="text-center"><button class="btn btn-primary btn-xl text-uppercase" id="submitButton" type="submit">Enviar mensaje</button></div>
                </form>
            </div>
        </section>
        <!-- Footer-->
        <footer class="footer py-4">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-4 text-lg-start"> &copy; 2020 derechos reservados SIETELSA S.A. DE C.V</div>
                    <div class="col-lg-4 my-3 my-lg-0">
                        <!-- <a class="btn btn-dark btn-social mx-2" href="#!" aria-label="Twitter"><i class="fab fa-twitter"></i></a> -->
                        <a class="btn btn-dark btn-social mx-2" href="https://www.facebook.com/profile.php?id=100054891634300" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a> 
                        <!-- <a class="btn btn-dark btn-social mx-2" href="#!" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a> -->
                    </div>
                    <!-- <div class="col-lg-4 text-lg-end">
                        <a class="link-dark text-decoration-none me-3" href="#!">Privacy Policy</a>
                        <a class="link-dark text-decoration-none" href="#!">Terms of Use</a>
                    </div> -->
                </div>
            </div>
        </footer>
        <!-- Portfolio Modals-->
        <?php if (!empty($portafolio)): ?>
            <?php foreach ($portafolio as $proyecto): ?>
                <?php
                $proyectoId = (int) ($proyecto['id'] ?? 0);
                $modalId = 'portfolioModal' . $proyectoId;
                $carouselId = 'portfolioCarousel' . $proyectoId;
                $tituloProyecto = trim((string) ($proyecto['titulo'] ?? ''));
                $descripcionProyecto = trim((string) ($proyecto['descripcion'] ?? ''));
                $descripcionItems = desglosar_descripcion_portafolio_index($descripcionProyecto);
                $imagenesProyecto = is_array($proyecto['imagenes'] ?? null) ? $proyecto['imagenes'] : [];
                if (empty($imagenesProyecto)) {
                    $imagenPortadaProyecto = trim((string) ($proyecto['imagen_path'] ?? ''));
                    if ($imagenPortadaProyecto !== '') {
                        $imagenesProyecto[] = $imagenPortadaProyecto;
                    }
                }
                ?>
                <div class="portfolio-modal modal fade" id="<?php echo htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1" role="dialog" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="close-modal" data-bs-dismiss="modal"><img src="assets/img/close-icon.svg" alt="Cerrar modal" width="32" height="32" loading="lazy" decoding="async" /></div>
                            <div class="container">
                                <div class="row justify-content-center">
                                    <div class="col-lg-10">
                                        <div class="modal-body">
                                            <div class="portfolio-modal-header">
                                                <h2 class="text-uppercase"><?php echo htmlspecialchars($tituloProyecto !== '' ? $tituloProyecto : 'Proyecto', ENT_QUOTES, 'UTF-8'); ?></h2>
                                                <p class="item-intro text-muted">Galeria de imagenes del proyecto.</p>
                                            </div>

                                            <div id="<?php echo htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8'); ?>" class="carousel slide portfolio-carousel-frame" data-bs-ride="false">
                                                <?php if (count($imagenesProyecto) > 1): ?>
                                                    <div class="carousel-indicators">
                                                        <?php foreach ($imagenesProyecto as $indiceImagen => $_): ?>
                                                            <button
                                                                type="button"
                                                                data-bs-target="#<?php echo htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-bs-slide-to="<?php echo (int) $indiceImagen; ?>"
                                                                class="<?php echo $indiceImagen === 0 ? 'active' : ''; ?>"
                                                                <?php echo $indiceImagen === 0 ? 'aria-current="true"' : ''; ?>
                                                                aria-label="Imagen <?php echo (int) ($indiceImagen + 1); ?>"
                                                            ></button>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="carousel-inner">
                                                    <?php foreach ($imagenesProyecto as $indiceImagen => $rutaImagen): ?>
                                                        <?php
                                                        $imagenProyecto = normalizar_ruta_imagen_portafolio_index((string) $rutaImagen);
                                                        $variantesImagenModal = sietelsa_generar_variantes_imagen($imagenProyecto, 1400, 900, 'contain', 84);
                                                        ?>
                                                        <div class="carousel-item <?php echo $indiceImagen === 0 ? 'active' : ''; ?>">
                                                            <picture>
                                                            <?php if ($variantesImagenModal['webp'] !== null): ?>
                                                                <source type="image/webp" srcset="<?php echo htmlspecialchars((string) $variantesImagenModal['webp'], ENT_QUOTES, 'UTF-8'); ?>" />
                                                            <?php endif; ?>
                                                                <img
                                                                class="portfolio-carousel-image"
                                                                loading="lazy"
                                                                decoding="async"
                                                                src="<?php echo htmlspecialchars((string) $variantesImagenModal['fallback'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                width="<?php echo (int) $variantesImagenModal['width']; ?>"
                                                                height="<?php echo (int) $variantesImagenModal['height']; ?>"
                                                                alt="<?php echo htmlspecialchars($tituloProyecto !== '' ? $tituloProyecto : 'Proyecto del portafolio', ENT_QUOTES, 'UTF-8'); ?>"
                                                            />
                                                            </picture>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <?php if (count($imagenesProyecto) > 1): ?>
                                                    <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8'); ?>" data-bs-slide="prev">
                                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                        <span class="visually-hidden">Anterior</span>
                                                    </button>
                                                    <button class="carousel-control-next" type="button" data-bs-target="#<?php echo htmlspecialchars($carouselId, ENT_QUOTES, 'UTF-8'); ?>" data-bs-slide="next">
                                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                        <span class="visually-hidden">Siguiente</span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <div class="portfolio-detail-box">
                                                <div class="portfolio-detail-title">Descripcion</div>
                                                <?php if (!empty($descripcionItems) && count($descripcionItems) > 1): ?>
                                                    <ol class="portfolio-detail-list">
                                                        <?php foreach ($descripcionItems as $itemDescripcion): ?>
                                                            <li><?php echo htmlspecialchars((string) $itemDescripcion, ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <?php endforeach; ?>
                                                    </ol>
                                                <?php elseif (!empty($descripcionItems)): ?>
                                                    <p class="portfolio-detail-text"><?php echo htmlspecialchars((string) $descripcionItems[0], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <?php else: ?>
                                                    <p class="portfolio-detail-text text-muted">Sin descripcion disponible.</p>
                                                <?php endif; ?>
                                            </div>

                                            <button class="btn btn-primary btn-xl text-uppercase mt-4" data-bs-dismiss="modal" type="button">
                                                <i class="fas fa-xmark me-1"></i>
                                                Cerrar galeria
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <!-- Bootstrap core JS-->
        <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <!-- Core theme JS-->
        <script defer src="<?php echo htmlspecialchars(sietelsa_asset_url('js/scripts.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <script>
            window.addEventListener('load', function () {
                var contactForm = document.getElementById('contactForm');
                var alertBox = document.getElementById('contactStatusAlert');

                function showAlert(icon, title, text) {
                    if (typeof window.Swal !== 'undefined') {
                        window.Swal.fire({
                            icon: icon,
                            title: title,
                            text: text,
                            confirmButtonText: 'Aceptar'
                        });
                        return;
                    }
                    window.alert(text);
                }

                if (alertBox) {
                    var text = (alertBox.textContent || '').trim();
                    if (text !== '') {
                        var isError = alertBox.classList.contains('alert-danger');
                        showAlert(isError ? 'error' : 'success', isError ? 'Error de validacion' : 'Mensaje enviado', text);
                    }
                }

                if (!contactForm) {
                    return;
                }

                contactForm.addEventListener('submit', function (event) {
                    var fields = contactForm.querySelectorAll('input, textarea');
                    var i;
                    for (i = 0; i < fields.length; i++) {
                        var field = fields[i];
                        if (!field || field.disabled || field.type === 'hidden') {
                            continue;
                        }

                        field.value = field.value.trim();
                        if (!field.checkValidity()) {
                            event.preventDefault();
                            var label = '';
                            if (field.id) {
                                var labelEl = document.querySelector('label[for=\"' + field.id + '\"]');
                                label = labelEl ? (labelEl.textContent || '').trim() : '';
                            }
                            if (label === '') {
                                label = field.placeholder || field.name || 'campo';
                            }
                            showAlert('error', 'Error de validacion', 'El campo \"' + label + '\" no cumple con el formato requerido.');
                            field.focus();
                            return;
                        }
                    }
                });
            });
        </script>
        <?php if ($seccionObjetivo !== ''): ?>
            <script>
                window.addEventListener('load', function () {
                    var sectionId = <?php echo json_encode($seccionObjetivo, JSON_UNESCAPED_SLASHES); ?>;
                    var target = document.getElementById(sectionId);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            </script>
        <?php endif; ?>
    </body>
</html>
<?php
if ($__sietelsaPageCacheEnabled && ob_get_level() > 0) {
    $html = ob_get_contents();
    if (is_string($html) && $html !== '' && http_response_code() === 200) {
        sietelsa_cache_set('page', $__sietelsaPageCacheKey, $html, 90);
    }
    ob_end_flush();
}

sietelsa_perf_finish([
    'page_cache' => $__sietelsaPageCacheEnabled ? 'miss' : 'bypass',
    'query_cache_enabled' => sietelsa_cache_enabled(),
]);
