<?php
declare(strict_types=1);

function sietelsa_perf_enabled(): bool
{
    $flag = getenv('SIETELSA_PERF_ENABLED');
    if ($flag === false) {
        return false;
    }

    $flag = strtolower(trim((string) $flag));
    return in_array($flag, ['1', 'true', 'on', 'yes'], true);
}

function sietelsa_perf_log_path(): string
{
    $path = getenv('SIETELSA_PERF_LOG');
    if (!is_string($path) || trim($path) === '') {
        return __DIR__ . '/../assets/cache/logs/perf.log';
    }

    return $path;
}

function sietelsa_perf_start(string $route): void
{
    if (!sietelsa_perf_enabled()) {
        return;
    }

    if (!isset($GLOBALS['__sietelsa_perf'])) {
        $GLOBALS['__sietelsa_perf'] = [];
    }

    $GLOBALS['__sietelsa_perf'] = [
        'route' => $route,
        'start' => microtime(true),
        'marks' => [],
        'queries' => [],
    ];
}

function sietelsa_perf_mark(string $label, array $meta = []): void
{
    if (!sietelsa_perf_enabled()) {
        return;
    }

    if (!isset($GLOBALS['__sietelsa_perf']['marks']) || !is_array($GLOBALS['__sietelsa_perf']['marks'])) {
        return;
    }

    $GLOBALS['__sietelsa_perf']['marks'][] = [
        'label' => $label,
        'time' => microtime(true),
        'meta' => $meta,
    ];
}

function sietelsa_perf_query(string $sql, float $ms, array $meta = []): void
{
    if (!sietelsa_perf_enabled()) {
        return;
    }

    if (!isset($GLOBALS['__sietelsa_perf']['queries']) || !is_array($GLOBALS['__sietelsa_perf']['queries'])) {
        return;
    }

    $GLOBALS['__sietelsa_perf']['queries'][] = [
        'sql' => preg_replace('/\s+/', ' ', trim($sql)) ?: $sql,
        'ms' => round($ms, 3),
        'meta' => $meta,
    ];
}

function sietelsa_perf_finish(array $extra = []): void
{
    if (!sietelsa_perf_enabled()) {
        return;
    }

    $perf = $GLOBALS['__sietelsa_perf'] ?? null;
    if (!is_array($perf)) {
        return;
    }

    $start = (float) ($perf['start'] ?? microtime(true));
    $route = (string) ($perf['route'] ?? 'unknown');
    $elapsedMs = round((microtime(true) - $start) * 1000, 3);

    $queries = is_array($perf['queries'] ?? null) ? $perf['queries'] : [];
    $totalQueryMs = 0.0;
    foreach ($queries as $query) {
        $totalQueryMs += (float) ($query['ms'] ?? 0.0);
    }

    $record = [
        'at' => date(DATE_ATOM),
        'route' => $route,
        'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'duration_ms' => $elapsedMs,
        'query_count' => count($queries),
        'query_total_ms' => round($totalQueryMs, 3),
        'marks' => $perf['marks'] ?? [],
        'queries' => $queries,
        'extra' => $extra,
    ];

    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line) || $line === '') {
        return;
    }

    $logPath = sietelsa_perf_log_path();
    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
