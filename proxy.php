<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Throwable;

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function failResponse(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo $message;
    exit;
}

$cameraId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';

if ($cameraId === '' || !preg_match('/^[a-f0-9]{24}$/i', $cameraId)) {
    failResponse(400, 'Invalid camera id.');
}

$client = new Client([
    'timeout' => 8.0,
    'connect_timeout' => 3.0,
    'verify' => true,
    'http_errors' => false,
]);

try {
    $response = $client->request('GET', 'https://giaothong.hochiminhcity.gov.vn:8007/Render/CameraHandler.ashx', [
        'query' => [
            'id' => $cameraId,
            '_' => (string) time(),
        ],
        'headers' => [
            'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            'Accept-Language' => 'vi,en;q=0.8',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Referer' => 'https://giaothong.hochiminhcity.gov.vn/',
            'User-Agent' => 'Mozilla/5.0 CameraViewer/1.0',
        ],
    ]);

    $status = $response->getStatusCode();
    $contentType = strtolower(trim($response->getHeaderLine('Content-Type')));

    if ($status < 200 || $status >= 300) {
        failResponse(502, 'Camera source returned an error.');
    }

    if ($contentType === '' || strpos($contentType, 'image/') !== 0) {
        failResponse(502, 'Camera source did not return an image.');
    }

    http_response_code(200);
    header('Content-Type: ' . $contentType);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo (string) $response->getBody();
} catch (Throwable $exception) {
    error_log('Camera proxy error: ' . $exception->getMessage());
    failResponse(502, 'Unable to load camera image.');
}
