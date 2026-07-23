<?php
declare(strict_types=1);

function sietelsa_rate_limit_dir(): string
{
    $dir = sys_get_temp_dir() . '/sietelsa_rate_limit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function sietelsa_rate_limit_file(string $scope, string $key): string
{
    $safeScope = preg_replace('/[^a-z0-9_\-]/i', '_', strtolower(trim($scope))) ?: 'default';
    $hash = sha1($scope . '|' . $key);
    return sietelsa_rate_limit_dir() . '/' . $safeScope . '_' . $hash . '.json';
}

function sietelsa_rate_limit_status(string $scope, string $key, int $windowSeconds): array
{
    $path = sietelsa_rate_limit_file($scope, $key);
    if (!is_file($path)) {
        return ['attempts' => [], 'retry_after' => 0];
    }

    $raw = file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $attempts = is_array($data['attempts'] ?? null) ? $data['attempts'] : [];

    $now = time();
    $validAttempts = [];
    foreach ($attempts as $attemptTs) {
        $attemptTs = (int) $attemptTs;
        if ($attemptTs > ($now - $windowSeconds) && $attemptTs <= $now) {
            $validAttempts[] = $attemptTs;
        }
    }

    $retryAfter = 0;
    if (!empty($validAttempts)) {
        $firstAttempt = (int) min($validAttempts);
        $retryAfter = max(0, ($firstAttempt + $windowSeconds) - $now);
    }

    return ['attempts' => $validAttempts, 'retry_after' => $retryAfter];
}

function sietelsa_rate_limit_guard(string $scope, string $key, int $maxAttempts, int $windowSeconds): array
{
    $status = sietelsa_rate_limit_status($scope, $key, $windowSeconds);
    $attempts = is_array($status['attempts'] ?? null) ? $status['attempts'] : [];

    if (count($attempts) >= $maxAttempts) {
        return [
            'allowed' => false,
            'retry_after' => (int) ($status['retry_after'] ?? $windowSeconds),
        ];
    }

    return ['allowed' => true, 'retry_after' => 0];
}

function sietelsa_rate_limit_register_fail(string $scope, string $key, int $windowSeconds): void
{
    $status = sietelsa_rate_limit_status($scope, $key, $windowSeconds);
    $attempts = is_array($status['attempts'] ?? null) ? $status['attempts'] : [];
    $attempts[] = time();

    $path = sietelsa_rate_limit_file($scope, $key);
    @file_put_contents($path, json_encode(['attempts' => $attempts], JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function sietelsa_rate_limit_clear(string $scope, string $key): void
{
    $path = sietelsa_rate_limit_file($scope, $key);
    if (is_file($path)) {
        @unlink($path);
    }
}
