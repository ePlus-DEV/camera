<?php

declare(strict_types=1);

const DATA_FILE = __DIR__ . '/../data-camera.json';
const VIETMAP_SEARCH_URL = 'https://maps.vietmap.vn/api/search/v4';
const VIETMAP_AUTOCOMPLETE_URL = 'https://maps.vietmap.vn/api/autocomplete/v4';
const VIETMAP_PLACE_URL = 'https://maps.vietmap.vn/api/place/v4';
const HCM_FOCUS = '10.7769,106.7009';

function optionValue(string $name, ?string $default = null): ?string
{
    global $argv;
    foreach ($argv as $argument) {
        if ($argument === '--' . $name) {
            return '1';
        }
        if (str_starts_with($argument, '--' . $name . '=')) {
            return substr($argument, strlen($name) + 3);
        }
    }
    return $default;
}

function requestJson(string $url, array $query): array
{
    $requestUrl = $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: ePlus-DEV-camera-address-enricher/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($requestUrl, false, $context);
    if ($body === false) {
        throw new RuntimeException('VietMap request failed');
    }

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("VietMap HTTP {$status}");
    }

    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    return is_array($decoded) ? $decoded : [];
}

function boundary(array $item, int $type): ?array
{
    foreach (($item['boundaries'] ?? []) as $boundary) {
        if ((int) ($boundary['type'] ?? -1) !== $type) {
            continue;
        }

        return [
            'id' => isset($boundary['id']) ? (int) $boundary['id'] : null,
            'name' => trim((string) ($boundary['full_name'] ?? $boundary['name'] ?? '')),
        ];
    }
    return null;
}

function normalizeAddress(array $result, ?array $place = null, bool $legacy = false): array
{
    $ward = boundary($result, 2);
    $district = $legacy ? boundary($result, 1) : null;
    $province = boundary($result, 0);

    if ($place) {
        if (($place['ward'] ?? '') !== '') {
            $ward = ['id' => (int) ($place['ward_id'] ?? 0) ?: null, 'name' => trim((string) $place['ward'])];
        }
        if ($legacy && ($place['district'] ?? '') !== '') {
            $district = ['id' => (int) ($place['district_id'] ?? 0) ?: null, 'name' => trim((string) $place['district'])];
        }
        if (($place['city'] ?? '') !== '') {
            $province = ['id' => (int) ($place['city_id'] ?? 0) ?: null, 'name' => trim((string) $place['city'])];
        }
    }

    return [
        'full' => trim((string) ($place['display'] ?? $result['display'] ?? '')) ?: null,
        'name' => trim((string) ($result['name'] ?? '')) ?: null,
        'houseNumber' => $place ? (trim((string) ($place['hs_num'] ?? '')) ?: null) : null,
        'street' => $place ? (trim((string) ($place['street'] ?? '')) ?: null) : null,
        'ward' => $ward,
        'district' => $district,
        'province' => $province,
        'refId' => trim((string) ($result['ref_id'] ?? '')) ?: null,
    ];
}

function isHoChiMinh(array $result): bool
{
    $province = boundary($result, 0);
    $name = mb_strtolower((string) ($province['name'] ?? ''), 'UTF-8');
    return str_contains($name, 'hồ chí minh') || str_contains($name, 'ho chi minh');
}

function findResult(array $results): ?array
{
    foreach ($results as $result) {
        if (is_array($result) && isHoChiMinh($result)) {
            return $result;
        }
    }
    return isset($results[0]) && is_array($results[0]) ? $results[0] : null;
}

$apiKey = trim((string) getenv('VIETMAP_API_KEY'));
if ($apiKey === '') {
    fwrite(STDERR, "VIETMAP_API_KEY is required.\n");
    exit(2);
}

$limit = max(0, (int) (optionValue('limit', '0') ?? '0'));
$offset = max(0, (int) (optionValue('offset', '0') ?? '0'));
$force = optionValue('force') === '1';
$dryRun = optionValue('dry-run') === '1';
$cameraId = trim((string) (optionValue('camera-id', '') ?? ''));

$data = json_decode((string) file_get_contents(DATA_FILE), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($data)) {
    throw new RuntimeException('Camera data must be an array');
}

$processed = 0;
$updated = 0;
$failed = 0;

foreach ($data as $index => &$camera) {
    if ($index < $offset) {
        continue;
    }
    if ($cameraId !== '' && ($camera['CamId'] ?? '') !== $cameraId) {
        continue;
    }
    if ($limit > 0 && $processed >= $limit) {
        break;
    }

    $legacy = $camera['Address']['legacy'] ?? [];
    if (!$force && !empty($legacy['full']) && ($camera['Address']['source'] ?? '') === 'vietmap-v4') {
        continue;
    }

    $processed++;
    $name = trim((string) ($camera['CamName'] ?? ''));
    $district = trim((string) ($legacy['district']['name'] ?? ''));
    $query = implode(', ', array_filter([$name, $district, 'Thành Phố Hồ Chí Minh']));

    try {
        $common = [
            'apikey' => $apiKey,
            'text' => $query,
            'focus' => HCM_FOCUS,
            'display_type' => 6,
        ];

        $results = requestJson(VIETMAP_SEARCH_URL, $common);
        if ($results === []) {
            $results = requestJson(VIETMAP_AUTOCOMPLETE_URL, $common);
        }

        $oldResult = findResult($results);
        if (!$oldResult || empty($oldResult['ref_id'])) {
            throw new RuntimeException('No VietMap result for: ' . $query);
        }

        $place = requestJson(VIETMAP_PLACE_URL, [
            'apikey' => $apiKey,
            'refid' => $oldResult['ref_id'],
        ]);

        $newResult = isset($oldResult['data_new']) && is_array($oldResult['data_new'])
            ? $oldResult['data_new']
            : null;

        $camera['Location'] = [
            'lat' => isset($place['lat']) ? (float) $place['lat'] : null,
            'lng' => isset($place['lng']) ? (float) $place['lng'] : null,
        ];
        $camera['Address'] = [
            'legacy' => normalizeAddress($oldResult, $place, true),
            'current' => $newResult ? normalizeAddress($newResult, null, false) : null,
            'source' => 'vietmap-v4',
            'updatedAt' => gmdate('c'),
        ];

        $updated++;
        echo "[OK] {$camera['CamId']} {$name} -> " . ($camera['Address']['legacy']['full'] ?? 'unknown') . PHP_EOL;
    } catch (Throwable $exception) {
        $failed++;
        fwrite(STDERR, "[WARN] " . ($camera['CamId'] ?? '?') . " {$name}: {$exception->getMessage()}\n");
    }

    usleep(150000);
}
unset($camera);

if (!$dryRun && $updated > 0) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents(DATA_FILE, $json, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write data-camera.json');
    }
}

echo "Processed={$processed} Updated={$updated} Failed={$failed} DryRun=" . ($dryRun ? 'yes' : 'no') . PHP_EOL;
exit($failed > 0 && $updated === 0 ? 1 : 0);
