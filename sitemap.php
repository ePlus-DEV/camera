<?php

declare(strict_types=1);

const BASE_URL = 'https://camera-hcm.eplus.dev';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

function xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

$dataFile = __DIR__ . '/data-camera.json';
$indexFile = __DIR__ . '/index.html';
$detailFile = __DIR__ . '/camera.php';

try {
    $data = json_decode((string) file_get_contents($dataFile), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    exit;
}

$lastModified = max(
    (int) @filemtime($dataFile),
    (int) @filemtime($indexFile),
    (int) @filemtime($detailFile)
);
$lastmod = gmdate('Y-m-d', $lastModified ?: time());

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
echo '  <url><loc>' . xml(BASE_URL . '/') . '</loc><lastmod>' . $lastmod . '</lastmod></url>' . PHP_EOL;

$seen = [];
foreach ($data as $camera) {
    $id = (string) ($camera['CamId'] ?? '');
    if (!preg_match('/^[a-f0-9]{24}$/i', $id) || isset($seen[$id])) {
        continue;
    }

    $seen[$id] = true;
    $url = BASE_URL . '/camera.php?id=' . rawurlencode($id);
    echo '  <url><loc>' . xml($url) . '</loc><lastmod>' . $lastmod . '</lastmod></url>' . PHP_EOL;
}

echo '</urlset>' . PHP_EOL;
