<?php
declare(strict_types=1);

require_once __DIR__ . '/db_setup.php';
require_once __DIR__ . '/cache.php';

function normalizar_codigo_pais_visita(?string $codigo): string
{
    $codigo = strtoupper(trim((string) $codigo));
    if ($codigo === '' || preg_match('/^[A-Z]{2}$/', $codigo) !== 1) {
        return '';
    }

    return $codigo;
}

function nombre_pais_desde_codigo_visita(string $codigo): string
{
    $codigo = normalizar_codigo_pais_visita($codigo);
    if ($codigo === '') {
        return '';
    }

    if (class_exists('Locale')) {
        $nombre = Locale::getDisplayRegion('-' . $codigo, 'es');
        if (is_string($nombre)) {
            $nombre = trim($nombre);
            if ($nombre !== '') {
                return $nombre;
            }
        }
    }

    return $codigo;
}

function visitas_env_bool(string $key, bool $default): bool
{
    $valor = strtolower(trim((string) getenv($key)));
    if ($valor === '') {
        return $default;
    }

    if (in_array($valor, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($valor, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function visitas_env_int(string $key, int $default, int $min, int $max): int
{
    $valorRaw = trim((string) getenv($key));
    if ($valorRaw === '' || !preg_match('/^-?\d+$/', $valorRaw)) {
        return $default;
    }

    $valor = (int) $valorRaw;
    if ($valor < $min) {
        $valor = $min;
    }
    if ($valor > $max) {
        $valor = $max;
    }

    return $valor;
}

function visitas_geoip_http_habilitado(): bool
{
    return visitas_env_bool('SIETELSA_VISITAS_GEOIP_HTTP_ENABLED', true);
}

function visitas_geoip_backfill_habilitado(): bool
{
    return visitas_env_bool('SIETELSA_VISITAS_GEOIP_BACKFILL_ENABLED', true);
}

function visitas_geoip_url_template(): string
{
    $template = trim((string) getenv('SIETELSA_VISITAS_GEOIP_URL_TEMPLATE'));
    if ($template === '') {
        return 'https://ipapi.co/{ip}/json/';
    }

    return $template;
}

function visitas_geoip_cache_ttl_ok_segundos(): int
{
    return visitas_env_int('SIETELSA_VISITAS_GEOIP_CACHE_TTL_OK', 60 * 60 * 24 * 30, 300, 60 * 60 * 24 * 365);
}

function visitas_geoip_cache_ttl_error_segundos(): int
{
    return visitas_env_int('SIETELSA_VISITAS_GEOIP_CACHE_TTL_ERROR', 30 * 60, 60, 60 * 60 * 24);
}

function visitas_geoip_backfill_limit(): int
{
    return visitas_env_int('SIETELSA_VISITAS_GEOIP_BACKFILL_LIMIT', 25, 1, 200);
}

function visitas_geoip_backfill_ttl_segundos(): int
{
    return visitas_env_int('SIETELSA_VISITAS_GEOIP_BACKFILL_TTL', 120, 30, 3600);
}

function construir_url_geoip_visita(string $ip): string
{
    $template = visitas_geoip_url_template();
    $ipUrl = rawurlencode($ip);
    if (strpos($template, '{ip}') !== false) {
        return str_replace('{ip}', $ipUrl, $template);
    }
    if (strpos($template, '%s') !== false) {
        return sprintf($template, $ipUrl);
    }

    return rtrim($template, '/') . '/' . $ipUrl;
}

function ip_publica_para_geo_visita(string $ip): bool
{
    $ip = trim($ip);
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    if (function_exists('sietelsa_ip_es_localhost') && sietelsa_ip_es_localhost($ip)) {
        return false;
    }

    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    ) !== false;
}

function resolver_pais_por_ip_visita(string $ip): array
{
    $ip = trim($ip);
    if (!ip_publica_para_geo_visita($ip)) {
        return [
            'codigo' => '',
            'nombre' => '',
        ];
    }

    $cacheKey = 'geoip_ip_v1|' . sha1($ip);
    $cached = sietelsa_cache_get('analytics', $cacheKey);
    if (is_array($cached)) {
        $codigoCache = normalizar_codigo_pais_visita((string) ($cached['codigo'] ?? ''));
        $nombreCache = trim((string) ($cached['nombre'] ?? ''));
        return [
            'codigo' => $codigoCache,
            'nombre' => $nombreCache,
        ];
    }

    if (!visitas_geoip_http_habilitado() || !function_exists('curl_init')) {
        return [
            'codigo' => '',
            'nombre' => '',
        ];
    }

    $ch = curl_init();
    if ($ch === false) {
        return [
            'codigo' => '',
            'nombre' => '',
        ];
    }

    $url = construir_url_geoip_visita($ip);
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 1200,
        CURLOPT_TIMEOUT_MS => 1800,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'SietelsaAnalytics/1.0',
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($raw) || $raw === '' || $httpCode >= 400) {
        sietelsa_cache_set('analytics', $cacheKey, ['codigo' => '', 'nombre' => ''], visitas_geoip_cache_ttl_error_segundos());
        return [
            'codigo' => '',
            'nombre' => '',
        ];
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        sietelsa_cache_set('analytics', $cacheKey, ['codigo' => '', 'nombre' => ''], visitas_geoip_cache_ttl_error_segundos());
        return [
            'codigo' => '',
            'nombre' => '',
        ];
    }

    $codigo = '';
    $fuentesCodigo = [
        (string) ($payload['country_code'] ?? ''),
        (string) ($payload['countryCode'] ?? ''),
        (string) ($payload['country_iso_code'] ?? ''),
        (string) ($payload['country_code2'] ?? ''),
    ];
    foreach ($fuentesCodigo as $candidatoCodigo) {
        $normalizado = normalizar_codigo_pais_visita($candidatoCodigo);
        if ($normalizado !== '') {
            $codigo = $normalizado;
            break;
        }
    }

    $nombre = trim((string) ($payload['country_name'] ?? ''));
    if ($nombre === '') {
        $nombre = trim((string) ($payload['countryName'] ?? ''));
    }
    if ($nombre === '' && $codigo === '') {
        $candidatoNombre = trim((string) ($payload['country'] ?? ''));
        if ($candidatoNombre !== '' && preg_match('/^[A-Za-z ]{3,}$/', $candidatoNombre) === 1) {
            $nombre = $candidatoNombre;
        }
    }
    if ($nombre === '' && $codigo !== '') {
        $nombre = nombre_pais_desde_codigo_visita($codigo);
    }

    $resultado = [
        'codigo' => $codigo,
        'nombre' => $nombre,
    ];
    $ttl = ($codigo !== '' || $nombre !== '') ? visitas_geoip_cache_ttl_ok_segundos() : visitas_geoip_cache_ttl_error_segundos();
    sietelsa_cache_set('analytics', $cacheKey, $resultado, $ttl);

    return $resultado;
}

function detectar_pais_visita(): array
{
    $codigo = '';
    $fuentesCodigo = [
        (string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''),
        (string) ($_SERVER['GEOIP_COUNTRY_CODE'] ?? ''),
        (string) ($_SERVER['HTTP_X_COUNTRY_CODE'] ?? ''),
        (string) ($_SERVER['HTTP_X_COUNTRY'] ?? ''),
        (string) ($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] ?? ''),
        (string) ($_SERVER['HTTP_FASTLY_COUNTRY_CODE'] ?? ''),
        (string) ($_SERVER['HTTP_X_APPENGINE_COUNTRY'] ?? ''),
    ];

    foreach ($fuentesCodigo as $candidato) {
        $normalizado = normalizar_codigo_pais_visita($candidato);
        if ($normalizado !== '') {
            $codigo = $normalizado;
            break;
        }
    }

    $nombre = '';
    $fuentesNombre = [
        (string) ($_SERVER['GEOIP_COUNTRY_NAME'] ?? ''),
        (string) ($_SERVER['HTTP_X_COUNTRY_NAME'] ?? ''),
    ];
    foreach ($fuentesNombre as $candidatoNombre) {
        $candidatoNombre = trim($candidatoNombre);
        if ($candidatoNombre !== '') {
            $nombre = $candidatoNombre;
            break;
        }
    }

    if ($codigo === '' || strcasecmp($nombre, 'Desconocido') === 0) {
        $ip = obtener_ip_cliente_visita();
        $paisPorIp = resolver_pais_por_ip_visita($ip);
        $codigoPorIp = normalizar_codigo_pais_visita((string) ($paisPorIp['codigo'] ?? ''));
        $nombrePorIp = trim((string) ($paisPorIp['nombre'] ?? ''));
        if ($codigo === '' && $codigoPorIp !== '') {
            $codigo = $codigoPorIp;
        }
        if (($nombre === '' || strcasecmp($nombre, 'Desconocido') === 0) && $nombrePorIp !== '') {
            $nombre = $nombrePorIp;
        }
    }

    if ($nombre === '' && $codigo !== '') {
        $nombre = nombre_pais_desde_codigo_visita($codigo);
    }

    if ($nombre === '') {
        $nombre = 'Desconocido';
    }

    return [
        'codigo' => $codigo,
        'nombre' => $nombre,
    ];
}

function obtener_ip_cliente_visita(): string
{
    $candidatos = [
        (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string) ($_SERVER['HTTP_CLIENT_IP'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidatos as $valor) {
        if ($valor === '') {
            continue;
        }

        $partes = array_map('trim', explode(',', $valor));
        foreach ($partes as $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }
    }

    return '';
}

function formatear_label_dia_visita(string $fechaYmd): string
{
    $timestamp = strtotime($fechaYmd);
    if ($timestamp === false) {
        return $fechaYmd;
    }

    return date('d/m', $timestamp);
}

function normalizar_fecha_filtro_visita(?string $fechaRaw): ?string
{
    $fechaRaw = trim((string) $fechaRaw);
    if ($fechaRaw === '') {
        return null;
    }

    $fecha = DateTimeImmutable::createFromFormat('Y-m-d', $fechaRaw);
    if (!$fecha instanceof DateTimeImmutable) {
        return null;
    }

    if ($fecha->format('Y-m-d') !== $fechaRaw) {
        return null;
    }

    return $fechaRaw;
}

function construir_clave_usuario_visita(?string $ipOrigen, ?string $userAgent): string
{
    $ipOrigen = trim((string) $ipOrigen);
    if ($ipOrigen !== '') {
        return 'ip:' . $ipOrigen;
    }

    $userAgent = trim((string) $userAgent);
    if ($userAgent !== '') {
        return 'ua:' . sha1($userAgent);
    }

    return 'anonimo';
}

function clasificar_dispositivo_visita(?string $userAgent): string
{
    $ua = strtolower(trim((string) $userAgent));
    if ($ua === '') {
        return 'desktop';
    }

    $esTablet = strpos($ua, 'ipad') !== false
        || strpos($ua, 'tablet') !== false
        || strpos($ua, 'kindle') !== false
        || strpos($ua, 'silk/') !== false
        || strpos($ua, 'sm-t') !== false
        || strpos($ua, 'nexus 7') !== false
        || strpos($ua, 'nexus 10') !== false;
    if ($esTablet) {
        return 'tablet';
    }

    $esMobile = strpos($ua, 'mobile') !== false
        || strpos($ua, 'iphone') !== false
        || strpos($ua, 'ipod') !== false
        || strpos($ua, 'android') !== false
        || strpos($ua, 'windows phone') !== false
        || strpos($ua, 'blackberry') !== false;
    if ($esMobile) {
        return 'mobile';
    }

    return 'desktop';
}

function clasificar_navegador_visita(?string $userAgent): string
{
    $ua = strtolower(trim((string) $userAgent));
    if ($ua === '') {
        return 'Desconocido';
    }

    if (strpos($ua, 'edg/') !== false || strpos($ua, 'edge/') !== false) {
        return 'Edge';
    }
    if (strpos($ua, 'opr/') !== false || strpos($ua, 'opera') !== false) {
        return 'Opera';
    }
    if (strpos($ua, 'brave/') !== false || strpos($ua, '"brave"') !== false || strpos($ua, ' brave') !== false) {
        return 'Brave';
    }
    if (strpos($ua, 'samsungbrowser/') !== false) {
        return 'Samsung Internet';
    }
    if (strpos($ua, 'firefox/') !== false) {
        return 'Firefox';
    }
    if (strpos($ua, 'chrome/') !== false || strpos($ua, 'crios/') !== false) {
        return 'Chrome';
    }
    if (strpos($ua, 'safari/') !== false) {
        return 'Safari';
    }
    if (strpos($ua, 'trident/') !== false || strpos($ua, 'msie ') !== false) {
        return 'Internet Explorer';
    }

    return 'Otros';
}

function ordenar_listado_por_total(array &$listado, string $campoNombre): void
{
    usort(
        $listado,
        static function (array $a, array $b) use ($campoNombre): int {
            $totalA = (int) ($a['total'] ?? 0);
            $totalB = (int) ($b['total'] ?? 0);
            if ($totalA === $totalB) {
                return strcmp((string) ($a[$campoNombre] ?? ''), (string) ($b[$campoNombre] ?? ''));
            }

            return $totalB <=> $totalA;
        }
    );
}

function registrar_visita_sitio(PDO $pdo, string $pagina = 'inicio'): void
{
    $pagina = trim($pagina);
    if ($pagina === '') {
        $pagina = 'inicio';
    }

    $pagina = substr($pagina, 0, 120);
    $ip = obtener_ip_cliente_visita();
    $pais = detectar_pais_visita();
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $clientHintsUa = trim((string) ($_SERVER['HTTP_SEC_CH_UA'] ?? ''));
    if ($clientHintsUa !== '' && stripos($clientHintsUa, 'brave') !== false && stripos($userAgent, 'brave') === false) {
        $userAgent = trim($userAgent . ' Brave');
    }
    $userAgent = $userAgent !== '' ? substr($userAgent, 0, 255) : null;

    $stmt = $pdo->prepare(
        'INSERT INTO visitas_sitio (pagina, ip_origen, pais_codigo, pais_nombre, user_agent)
         VALUES (:pagina, :ip_origen, :pais_codigo, :pais_nombre, :user_agent)'
    );
    $stmt->execute([
        ':pagina' => $pagina,
        ':ip_origen' => $ip !== '' ? $ip : null,
        ':pais_codigo' => $pais['codigo'] !== '' ? $pais['codigo'] : null,
        ':pais_nombre' => $pais['nombre'] !== '' ? $pais['nombre'] : null,
        ':user_agent' => $userAgent,
    ]);
}

function registrar_visita_sitio_controlado(PDO $pdo, string $pagina = 'inicio', int $minIntervaloSegundos = 900): void
{
    $minIntervaloSegundos = max(60, $minIntervaloSegundos);
    $ip = obtener_ip_cliente_visita();
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $fingerprint = sha1($pagina . '|' . $ip . '|' . $ua);
    $cacheKey = 'visit_throttle_v1|' . $fingerprint;

    if (sietelsa_cache_get('analytics', $cacheKey) !== null) {
        return;
    }

    registrar_visita_sitio($pdo, $pagina);
    sietelsa_cache_set('analytics', $cacheKey, 1, $minIntervaloSegundos);
}

function enriquecer_visitas_sin_pais(PDO $pdo, string $pagina = 'inicio', int $limite = 25): int
{
    $pagina = trim($pagina);
    if ($pagina === '') {
        $pagina = 'inicio';
    }
    $limite = max(1, min(500, $limite));

    $stmtPendientes = $pdo->prepare(
        'SELECT id, ip_origen
         FROM visitas_sitio
         WHERE pagina = :pagina
           AND (
               pais_codigo IS NULL
               OR TRIM(pais_codigo) = \'\'
               OR UPPER(TRIM(pais_codigo)) = \'??\'
           )
           AND ip_origen IS NOT NULL
           AND TRIM(ip_origen) <> \'\'
         ORDER BY creado_en DESC
         LIMIT :limite'
    );
    $stmtPendientes->bindValue(':pagina', $pagina, PDO::PARAM_STR);
    $stmtPendientes->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmtPendientes->execute();
    $filasPendientes = $stmtPendientes->fetchAll();
    if (!is_array($filasPendientes) || $filasPendientes === []) {
        return 0;
    }

    $paisPorIp = [];
    $stmtActualizar = $pdo->prepare(
        'UPDATE visitas_sitio
         SET pais_codigo = :pais_codigo,
             pais_nombre = :pais_nombre
         WHERE id = :id'
    );

    $actualizados = 0;
    foreach ($filasPendientes as $fila) {
        $id = (int) ($fila['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $ip = trim((string) ($fila['ip_origen'] ?? ''));
        if ($ip === '') {
            continue;
        }

        if (!array_key_exists($ip, $paisPorIp)) {
            $paisPorIp[$ip] = resolver_pais_por_ip_visita($ip);
        }

        $pais = (array) $paisPorIp[$ip];
        $codigo = normalizar_codigo_pais_visita((string) ($pais['codigo'] ?? ''));
        $nombre = trim((string) ($pais['nombre'] ?? ''));
        if ($nombre === '' && $codigo !== '') {
            $nombre = nombre_pais_desde_codigo_visita($codigo);
        }
        if ($codigo === '' && $nombre === '') {
            continue;
        }

        $stmtActualizar->execute([
            ':pais_codigo' => $codigo !== '' ? $codigo : null,
            ':pais_nombre' => $nombre !== '' ? $nombre : 'Desconocido',
            ':id' => $id,
        ]);
        if ($stmtActualizar->rowCount() > 0) {
            $actualizados++;
        }
    }

    return $actualizados;
}

function obtener_paises_disponibles_visitas(PDO $pdo, string $pagina = 'inicio'): array
{
    $pagina = trim($pagina);
    if ($pagina === '') {
        $pagina = 'inicio';
    }

    $stmtPaises = $pdo->prepare(
        'SELECT COALESCE(NULLIF(TRIM(pais_codigo), \'\'), \'??\') AS pais_codigo,
                COALESCE(NULLIF(TRIM(pais_nombre), \'\'), \'Desconocido\') AS pais_nombre,
                COUNT(*) AS total
         FROM visitas_sitio
         WHERE pagina = :pagina
         GROUP BY COALESCE(NULLIF(TRIM(pais_codigo), \'\'), \'??\'),
                  COALESCE(NULLIF(TRIM(pais_nombre), \'\'), \'Desconocido\')
         ORDER BY total DESC, pais_nombre ASC'
    );
    $stmtPaises->execute([
        ':pagina' => $pagina,
    ]);

    $paises = [];
    foreach ($stmtPaises->fetchAll() as $fila) {
        $codigo = strtoupper(trim((string) ($fila['pais_codigo'] ?? '??')));
        if ($codigo === '') {
            $codigo = '??';
        }

        $nombre = trim((string) ($fila['pais_nombre'] ?? 'Desconocido'));
        if ($nombre === '') {
            $nombre = 'Desconocido';
        }

        $paises[] = [
            'pais_codigo' => $codigo,
            'pais_nombre' => $nombre,
            'total' => (int) ($fila['total'] ?? 0),
        ];
    }

    return $paises;
}

function obtener_resumen_visitas_sitio(
    PDO $pdo,
    int $dias = 14,
    string $pagina = 'inicio',
    ?string $fechaInicio = null,
    ?string $fechaFin = null,
    string $paisCodigoFiltro = ''
): array
{
    $dias = max(1, min(365, $dias));
    $pagina = trim($pagina);
    if ($pagina === '') {
        $pagina = 'inicio';
    }

    if (visitas_geoip_backfill_habilitado()) {
        $backfillCacheKey = 'geoip_backfill_v1|' . $pagina;
        if (sietelsa_cache_get('analytics', $backfillCacheKey) === null) {
            try {
                enriquecer_visitas_sin_pais($pdo, $pagina, visitas_geoip_backfill_limit());
            } catch (Throwable $e) {
                sietelsa_log('GeoIP backfill visitas fallido', [
                    'pagina' => $pagina,
                    'error' => $e->getMessage(),
                ]);
            }
            sietelsa_cache_set('analytics', $backfillCacheKey, 1, visitas_geoip_backfill_ttl_segundos());
        }
    }

    $hoy = new DateTimeImmutable('today');
    $fechaFinNormalizada = normalizar_fecha_filtro_visita($fechaFin) ?? $hoy->format('Y-m-d');
    $fin = new DateTimeImmutable($fechaFinNormalizada . ' 00:00:00');

    $fechaInicioNormalizada = normalizar_fecha_filtro_visita($fechaInicio);
    if ($fechaInicioNormalizada === null) {
        $inicio = $fin->modify('-' . ($dias - 1) . ' days');
    } else {
        $inicio = new DateTimeImmutable($fechaInicioNormalizada . ' 00:00:00');
    }

    if ($inicio > $fin) {
        $tmp = $inicio;
        $inicio = $fin;
        $fin = $tmp;
    }

    $diasRango = ((int) $inicio->diff($fin)->format('%a')) + 1;
    if ($diasRango > 366) {
        $inicio = $fin->modify('-365 days');
        $diasRango = 366;
    }

    $inicioSql = $inicio->format('Y-m-d 00:00:00');
    $finMasUnDiaSql = $fin->modify('+1 day')->format('Y-m-d 00:00:00');
    $hoyClave = $hoy->format('Y-m-d');

    $visitasPorDia = [];
    $paisesPorDia = [];
    for ($i = 0; $i < $diasRango; $i++) {
        $fecha = $inicio->modify('+' . $i . ' days')->format('Y-m-d');
        $visitasPorDia[$fecha] = 0;
        $paisesPorDia[$fecha] = 0;
    }

    $paisCodigoFiltro = strtoupper(trim($paisCodigoFiltro));
    if ($paisCodigoFiltro !== '??') {
        $paisCodigoFiltro = normalizar_codigo_pais_visita($paisCodigoFiltro);
    }

    $wherePaisSql = '';
    $paramPais = [];
    if ($paisCodigoFiltro === '??') {
        $wherePaisSql = " AND COALESCE(NULLIF(TRIM(pais_codigo), ''), '??') = '??'";
    } elseif ($paisCodigoFiltro !== '') {
        $wherePaisSql = ' AND pais_codigo = :pais_codigo';
        $paramPais[':pais_codigo'] = $paisCodigoFiltro;
    }

    $sqlSerie = 'SELECT DATE(creado_en) AS fecha,
                        COUNT(*) AS total_visitas,
                        COUNT(DISTINCT COALESCE(NULLIF(TRIM(pais_codigo), \'\'), \'??\')) AS total_paises
                 FROM visitas_sitio
                 WHERE pagina = :pagina
                   AND creado_en >= :inicio
                   AND creado_en < :fin_mas_uno'
                 . $wherePaisSql
                 . ' GROUP BY DATE(creado_en)';
    $stmtSerie = $pdo->prepare($sqlSerie);
    $paramsSerie = [
        ':pagina' => $pagina,
        ':inicio' => $inicioSql,
        ':fin_mas_uno' => $finMasUnDiaSql,
    ] + $paramPais;
    $stmtSerie->execute($paramsSerie);

    foreach ($stmtSerie->fetchAll() as $fila) {
        $fecha = (string) ($fila['fecha'] ?? '');
        if (!array_key_exists($fecha, $visitasPorDia)) {
            continue;
        }

        $visitasPorDia[$fecha] = (int) ($fila['total_visitas'] ?? 0);
        $paisesPorDia[$fecha] = (int) ($fila['total_paises'] ?? 0);
    }

    $labels = [];
    $visitas = [];
    $paisesDistintos = [];
    $diasListado = [];
    foreach ($visitasPorDia as $fecha => $totalVisitas) {
        $label = formatear_label_dia_visita($fecha);
        $totalPaisesDia = (int) ($paisesPorDia[$fecha] ?? 0);

        $labels[] = $label;
        $visitas[] = (int) $totalVisitas;
        $paisesDistintos[] = $totalPaisesDia;
        $diasListado[] = [
            'fecha' => $fecha,
            'label' => $label,
            'visitas' => (int) $totalVisitas,
            'paises_distintos' => $totalPaisesDia,
        ];
    }

    $sqlPaises = 'SELECT COALESCE(NULLIF(TRIM(pais_codigo), \'\'), \'??\') AS pais_codigo,
                         COALESCE(NULLIF(TRIM(pais_nombre), \'\'), \'Desconocido\') AS pais_nombre,
                         COUNT(*) AS total
                  FROM visitas_sitio
                  WHERE pagina = :pagina
                    AND creado_en >= :inicio
                    AND creado_en < :fin_mas_uno'
                  . $wherePaisSql
                  . ' GROUP BY COALESCE(NULLIF(TRIM(pais_codigo), \'\'), \'??\'),
                           COALESCE(NULLIF(TRIM(pais_nombre), \'\'), \'Desconocido\')
                    ORDER BY total DESC, pais_nombre ASC';
    $stmtPaises = $pdo->prepare($sqlPaises);
    $paramsPaises = [
        ':pagina' => $pagina,
        ':inicio' => $inicioSql,
        ':fin_mas_uno' => $finMasUnDiaSql,
    ] + $paramPais;
    $stmtPaises->execute($paramsPaises);

    $paisesListado = [];
    foreach ($stmtPaises->fetchAll() as $filaPais) {
        $codigo = strtoupper(trim((string) ($filaPais['pais_codigo'] ?? '??')));
        if ($codigo === '') {
            $codigo = '??';
        }

        $nombre = trim((string) ($filaPais['pais_nombre'] ?? 'Desconocido'));
        if ($nombre === '') {
            $nombre = 'Desconocido';
        }

        $paisesListado[] = [
            'pais_codigo' => $codigo,
            'pais_nombre' => $nombre,
            'total' => (int) ($filaPais['total'] ?? 0),
        ];
    }

    $sqlEventos = 'SELECT pagina, ip_origen, user_agent, creado_en
                   FROM visitas_sitio
                   WHERE pagina = :pagina
                     AND creado_en >= :inicio
                     AND creado_en < :fin_mas_uno'
                   . $wherePaisSql
                   . ' ORDER BY creado_en ASC';
    $stmtEventos = $pdo->prepare($sqlEventos);
    $paramsEventos = [
        ':pagina' => $pagina,
        ':inicio' => $inicioSql,
        ':fin_mas_uno' => $finMasUnDiaSql,
    ] + $paramPais;
    $stmtEventos->execute($paramsEventos);

    $usuariosUnicos = [];
    $totalPaginasVistas = 0;
    $dispositivosMap = [
        'desktop' => 0,
        'mobile' => 0,
        'tablet' => 0,
    ];
    $navegadoresMap = [];
    $paginasMap = [];
    $sesionesActivas = [];
    $duracionTotalSesionesSegundos = 0;
    $totalSesiones = 0;
    $umbralNuevaSesionSegundos = 30 * 60;

    foreach ($stmtEventos->fetchAll() as $evento) {
        $ipOrigen = trim((string) ($evento['ip_origen'] ?? ''));
        $userAgent = trim((string) ($evento['user_agent'] ?? ''));
        $claveUsuario = construir_clave_usuario_visita($ipOrigen, $userAgent);
        $usuariosUnicos[$claveUsuario] = 1;

        $paginaVisita = trim((string) ($evento['pagina'] ?? 'inicio'));
        if ($paginaVisita === '') {
            $paginaVisita = 'inicio';
        }
        $paginasMap[$paginaVisita] = (int) (($paginasMap[$paginaVisita] ?? 0) + 1);
        $totalPaginasVistas++;

        $dispositivo = clasificar_dispositivo_visita($userAgent);
        if (!array_key_exists($dispositivo, $dispositivosMap)) {
            $dispositivosMap[$dispositivo] = 0;
        }
        $dispositivosMap[$dispositivo]++;

        $navegador = clasificar_navegador_visita($userAgent);
        $navegadoresMap[$navegador] = (int) (($navegadoresMap[$navegador] ?? 0) + 1);

        $timestamp = strtotime((string) ($evento['creado_en'] ?? ''));
        if ($timestamp === false) {
            continue;
        }

        if (!isset($sesionesActivas[$claveUsuario])) {
            $sesionesActivas[$claveUsuario] = [
                'inicio' => $timestamp,
                'ultimo' => $timestamp,
            ];
            continue;
        }

        $sesionActual = $sesionesActivas[$claveUsuario];
        $tiempoSinActividad = $timestamp - (int) ($sesionActual['ultimo'] ?? $timestamp);
        if ($tiempoSinActividad > $umbralNuevaSesionSegundos) {
            $duracionTotalSesionesSegundos += max(
                0,
                (int) ($sesionActual['ultimo'] ?? $timestamp) - (int) ($sesionActual['inicio'] ?? $timestamp)
            );
            $totalSesiones++;
            $sesionesActivas[$claveUsuario] = [
                'inicio' => $timestamp,
                'ultimo' => $timestamp,
            ];
            continue;
        }

        $sesionesActivas[$claveUsuario]['ultimo'] = max(
            (int) ($sesionActual['ultimo'] ?? $timestamp),
            $timestamp
        );
    }

    foreach ($sesionesActivas as $sesion) {
        $inicioSesion = (int) ($sesion['inicio'] ?? 0);
        $ultimoEvento = (int) ($sesion['ultimo'] ?? $inicioSesion);
        $duracionTotalSesionesSegundos += max(0, $ultimoEvento - $inicioSesion);
        $totalSesiones++;
    }

    $promedioSesionSegundos = $totalSesiones > 0
        ? (int) round($duracionTotalSesionesSegundos / $totalSesiones)
        : 0;

    $dispositivosListado = [
        [
            'dispositivo' => 'desktop',
            'total' => (int) ($dispositivosMap['desktop'] ?? 0),
        ],
        [
            'dispositivo' => 'mobile',
            'total' => (int) ($dispositivosMap['mobile'] ?? 0),
        ],
        [
            'dispositivo' => 'tablet',
            'total' => (int) ($dispositivosMap['tablet'] ?? 0),
        ],
    ];

    $navegadoresListado = [];
    foreach ($navegadoresMap as $navegadorNombre => $totalNavegador) {
        $navegadoresListado[] = [
            'navegador' => (string) $navegadorNombre,
            'total' => (int) $totalNavegador,
        ];
    }
    ordenar_listado_por_total($navegadoresListado, 'navegador');

    $paginasListado = [];
    foreach ($paginasMap as $nombrePagina => $totalPagina) {
        $paginasListado[] = [
            'pagina' => (string) $nombrePagina,
            'total' => (int) $totalPagina,
        ];
    }
    ordenar_listado_por_total($paginasListado, 'pagina');

    return [
        'labels' => $labels,
        'visitas' => $visitas,
        'paises_distintos' => $paisesDistintos,
        'dias' => $diasListado,
        'total_periodo' => array_sum($visitas),
        'total_hoy' => (int) ($visitasPorDia[$hoyClave] ?? 0),
        'usuarios_unicos' => count($usuariosUnicos),
        'paginas_vistas' => $totalPaginasVistas,
        'promedio_sesion_segundos' => $promedioSesionSegundos,
        'sesiones' => $totalSesiones,
        'dispositivos' => $dispositivosListado,
        'navegadores' => $navegadoresListado,
        'paginas_mas_visitadas' => array_slice($paginasListado, 0, 10),
        'top_paises' => array_slice($paisesListado, 0, 5),
        'paises' => $paisesListado,
        'fecha_inicio' => $inicio->format('Y-m-d'),
        'fecha_fin' => $fin->format('Y-m-d'),
        'filtro_pais' => $paisCodigoFiltro,
    ];
}
