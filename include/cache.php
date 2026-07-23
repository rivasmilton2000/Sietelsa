<?php
declare(strict_types=1);

function sietelsa_cache_enabled(): bool
{
    $flag = getenv('SIETELSA_CACHE_ENABLED');
    if ($flag === false) {
        return true;
    }

    $flag = strtolower(trim((string) $flag));
    return !in_array($flag, ['0', 'false', 'off', 'no'], true);
}

function sietelsa_cache_dir(): string
{
    static $dir = null;
    if (is_string($dir)) {
        return $dir;
    }

    $base = getenv('SIETELSA_CACHE_DIR');
    if (!is_string($base) || trim($base) === '') {
        $base = __DIR__ . '/../assets/cache/data';
    }

    $dir = rtrim($base, DIRECTORY_SEPARATOR);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function sietelsa_cache_key_file(string $namespace, string $key): string
{
    $namespaceSafe = preg_replace('/[^a-z0-9_-]/i', '_', strtolower(trim($namespace)));
    if (!is_string($namespaceSafe) || $namespaceSafe === '') {
        $namespaceSafe = 'default';
    }

    $keyHash = sha1($key);
    return sietelsa_cache_dir() . DIRECTORY_SEPARATOR . $namespaceSafe . '_' . $keyHash . '.cache.php';
}

function sietelsa_cache_get(string $namespace, string $key): mixed
{
    if (!sietelsa_cache_enabled()) {
        return null;
    }

    $file = sietelsa_cache_key_file($namespace, $key);
    if (!is_file($file)) {
        return null;
    }

    $payload = @include $file;
    if (!is_array($payload)) {
        return null;
    }

    $expiresAt = (int) ($payload['expires_at'] ?? 0);
    if ($expiresAt > 0 && $expiresAt < time()) {
        @unlink($file);
        return null;
    }

    return $payload['data'] ?? null;
}

function sietelsa_cache_set(string $namespace, string $key, mixed $data, int $ttlSeconds): bool
{
    if (!sietelsa_cache_enabled()) {
        return false;
    }

    $ttlSeconds = max(1, $ttlSeconds);
    $file = sietelsa_cache_key_file($namespace, $key);
    $tmp = $file . '.tmp';

    $payload = [
        'expires_at' => time() + $ttlSeconds,
        'created_at' => time(),
        'data' => $data,
    ];

    $export = var_export($payload, true);
    $content = "<?php\nreturn " . $export . ";\n";

    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        return false;
    }

    return @rename($tmp, $file);
}

function sietelsa_cache_remember(string $namespace, string $key, int $ttlSeconds, callable $resolver): mixed
{
    $cached = sietelsa_cache_get($namespace, $key);
    if ($cached !== null) {
        return $cached;
    }

    $value = $resolver();
    sietelsa_cache_set($namespace, $key, $value, $ttlSeconds);
    return $value;
}
