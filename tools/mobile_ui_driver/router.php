<?php

declare(strict_types=1);

const DEFAULT_REQUEST_URI = '/';

$projectRoot = dirname(__DIR__, 2);
$publicRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'public');
$requestUri = $_SERVER['REQUEST_URI'] ?? DEFAULT_REQUEST_URI;
$requestPath = rawurldecode((string) parse_url($requestUri, PHP_URL_PATH));
if ($publicRoot !== false && !str_contains($requestPath, "\0")) {
    $candidate = realpath($publicRoot . DIRECTORY_SEPARATOR . ltrim($requestPath, '/\\'));
    $publicPrefix = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $isPublicFile = $candidate !== false && is_file($candidate);
    $isWithinPublicRoot = $candidate !== false
        && str_starts_with(strtolower($candidate), strtolower($publicPrefix));
    if ($isPublicFile && $isWithinPublicRoot) {
        return false;
    }
}

require $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
