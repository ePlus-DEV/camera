<?php

declare(strict_types=1);

const BASE_URL = 'https://camera-hcm.eplus.dev';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cameraDistrict(array $camera): string
{
    return trim((string) ($camera['Disctrict'] ?? $camera['District'] ?? 'TP.HCM'));
}

$cameraId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';

if (!preg_match('/^[a-f0-9]{24}$/i', $cameraId)) {
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow');
    $cameraId = '';
}

$dataFile = __DIR__ . '/data-camera.json';
$data = [];

try {
    $decoded = json_decode((string) file_get_contents($dataFile), true, 512, JSON_THROW_ON_ERROR);
    if (is_array($decoded)) {
        $data = $decoded;
    }
} catch (Throwable $exception) {
    error_log('Camera detail data error: ' . $exception->getMessage());
}

$camera = null;
foreach ($data as $item) {
    if (($item['CamId'] ?? null) === $cameraId) {
        $camera = $item;
        break;
    }
}

if (!$camera) {
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow');
    $pageTitle = 'Không tìm thấy camera | ePlus.DEV';
    $pageDescription = 'Camera giao thông bạn yêu cầu không tồn tại hoặc đã được gỡ.';
    $canonical = BASE_URL . '/';
    $cameraName = 'Không tìm thấy camera';
    $district = 'TP.HCM';
    $imageUrl = '';
    $related = [];
} else {
    $cameraName = trim((string) $camera['CamName']);
    $district = cameraDistrict($camera);
    $pageTitle = 'Camera ' . $cameraName . ' – ' . $district . ' | TP.HCM';
    $pageDescription = 'Xem camera giao thông ' . $cameraName . ' tại ' . $district . ', TP.HCM. Hình ảnh camera được cập nhật tự động để tiện theo dõi tình hình giao thông.';
    $canonical = BASE_URL . '/camera.php?id=' . rawurlencode($cameraId);
    $imageUrl = BASE_URL . '/proxy.php?id=' . rawurlencode($cameraId);

    $related = array_values(array_filter($data, static function (array $item) use ($cameraId, $district): bool {
        return ($item['CamId'] ?? '') !== $cameraId
            && cameraDistrict($item) === $district
            && !empty($item['CamName'])
            && !empty($item['CamId']);
    }));
    $related = array_slice($related, 0, 6);
}

$schema = $camera ? [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $pageTitle,
    'description' => $pageDescription,
    'url' => $canonical,
    'inLanguage' => 'vi-VN',
    'isPartOf' => [
        '@type' => 'WebSite',
        'name' => 'Camera giao thông TP.HCM',
        'url' => BASE_URL . '/',
    ],
    'about' => [
        '@type' => 'Place',
        'name' => $cameraName,
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => 'Hồ Chí Minh',
            'addressRegion' => $district,
            'addressCountry' => 'VN',
        ],
    ],
    'primaryImageOfPage' => [
        '@type' => 'ImageObject',
        'contentUrl' => $imageUrl,
        'name' => 'Camera ' . $cameraName,
    ],
] : null;

?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <meta name="description" content="<?= h($pageDescription) ?>">
    <meta name="robots" content="<?= $camera ? 'index,follow,max-image-preview:large,max-snippet:-1' : 'noindex,nofollow' ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:site_name" content="Camera giao thông TP.HCM">
    <meta property="og:title" content="<?= h($pageTitle) ?>">
    <meta property="og:description" content="<?= h($pageDescription) ?>">
    <meta property="og:url" content="<?= h($canonical) ?>">
<?php if ($camera): ?>
    <meta property="og:image" content="<?= h($imageUrl) ?>">
    <meta property="og:image:alt" content="<?= h('Camera ' . $cameraName) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?= h($imageUrl) ?>">
<?php else: ?>
    <meta name="twitter:card" content="summary">
<?php endif; ?>
    <meta name="twitter:title" content="<?= h($pageTitle) ?>">
    <meta name="twitter:description" content="<?= h($pageDescription) ?>">
    <title><?= h($pageTitle) ?></title>
<?php if ($schema): ?>
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --surface: #fff;
            --surface-muted: #f1f5f9;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #2563eb;
            --success: #16a34a;
            --danger: #dc2626;
            --shadow: 0 18px 42px rgba(15,23,42,.1);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --bg: #08111f;
                --surface: #0f172a;
                --surface-muted: #172033;
                --text: #e5edf8;
                --muted: #94a3b8;
                --border: #243247;
                --primary: #60a5fa;
                --success: #4ade80;
                --danger: #f87171;
                --shadow: 0 18px 42px rgba(0,0,0,.28);
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: radial-gradient(circle at top left, rgba(37,99,235,.12), transparent 30rem), var(--bg);
            color: var(--text);
        }
        a { color: inherit; }
        button { font: inherit; cursor: pointer; }
        .shell { width: min(1100px, calc(100% - 28px)); margin: 0 auto; padding: 24px 0 42px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; }
        .back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            text-decoration: none;
            font-weight: 700;
            font-size: .9rem;
        }
        .back:hover { color: var(--primary); }
        .share {
            min-height: 40px;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0 14px;
            background: var(--surface);
            color: var(--text);
            font-weight: 700;
        }
        .detail-card {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 22px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }
        .media {
            position: relative;
            aspect-ratio: 16 / 9;
            background: #020617;
            overflow: hidden;
        }
        .media img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .status {
            position: absolute;
            top: 14px;
            left: 14px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(2,6,23,.72);
            color: #fff;
            font-size: .76rem;
            font-weight: 800;
            backdrop-filter: blur(8px);
        }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; }
        .status.ready .dot { background: #4ade80; }
        .status.error .dot { background: #f87171; }
        .content { padding: clamp(18px, 4vw, 30px); }
        .eyebrow { color: var(--primary); font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
        h1 { margin: 7px 0 10px; font-size: clamp(1.45rem, 4vw, 2.15rem); line-height: 1.25; }
        .lead { margin: 0; color: var(--muted); line-height: 1.7; }
        .meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
            color: var(--muted);
            font-size: .85rem;
        }
        .pill {
            padding: 8px 11px;
            border-radius: 999px;
            background: var(--surface-muted);
            border: 1px solid var(--border);
        }
        .notice {
            margin-top: 18px;
            padding: 13px 15px;
            border-radius: 14px;
            background: var(--surface-muted);
            color: var(--muted);
            font-size: .84rem;
            line-height: 1.55;
        }
        .related { margin-top: 24px; }
        .related h2 { margin: 0 0 12px; font-size: 1.08rem; }
        .related-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .related-link {
            display: block;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: var(--surface);
            text-decoration: none;
            color: var(--text);
            font-size: .88rem;
            line-height: 1.45;
        }
        .related-link:hover { border-color: var(--primary); color: var(--primary); }
        .not-found { text-align: center; padding: 64px 24px; }
        .not-found a { color: var(--primary); }
        .sr-status { color: var(--muted); font-size: .8rem; }
        @media (max-width: 680px) {
            .shell { width: min(100% - 20px, 1100px); padding-top: 16px; }
            .related-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main class="shell">
    <div class="topbar">
        <a class="back" href="/" aria-label="Quay về danh sách camera">← Danh sách camera</a>
<?php if ($camera): ?>
        <button id="shareButton" class="share" type="button">Chia sẻ</button>
<?php endif; ?>
    </div>

<?php if (!$camera): ?>
    <section class="detail-card not-found">
        <h1>Không tìm thấy camera</h1>
        <p class="lead">Camera này không tồn tại hoặc đã được gỡ khỏi danh sách.</p>
        <p><a href="/">Quay về danh sách camera giao thông TP.HCM</a></p>
    </section>
<?php else: ?>
    <article class="detail-card">
        <div id="media" class="media">
            <img id="cameraImage" src="proxy.php?id=<?= h($cameraId) ?>&amp;t=<?= time() ?>" alt="<?= h('Camera giao thông ' . $cameraName . ', ' . $district) ?>" width="1280" height="720">
            <div id="status" class="status"><span class="dot"></span><span id="statusText">Đang tải</span></div>
        </div>
        <div class="content">
            <div class="eyebrow">Camera giao thông TP.HCM</div>
            <h1><?= h($cameraName) ?></h1>
            <p class="lead"><?= h($pageDescription) ?></p>
            <div class="meta">
                <span class="pill"><?= h($district) ?></span>
                <span class="pill">Mã camera: <?= h(strtoupper(substr($cameraId, -6))) ?></span>
                <span id="refreshInfo" class="pill">Tự làm mới sau 10 giây</span>
            </div>
            <div class="notice">
                Hình ảnh phụ thuộc vào nguồn camera giao thông TP.HCM và có thể tạm thời không khả dụng.
                Trang sẽ tự làm mới ảnh khi bạn đang mở tab này.
            </div>
        </div>
    </article>

<?php if ($related): ?>
    <section class="related" aria-labelledby="relatedTitle">
        <h2 id="relatedTitle">Camera khác tại <?= h($district) ?></h2>
        <div class="related-grid">
<?php foreach ($related as $item): ?>
            <a class="related-link" href="/camera.php?id=<?= h((string) $item['CamId']) ?>">
                <?= h((string) $item['CamName']) ?>
            </a>
<?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

    <script>
        (() => {
            const image = document.getElementById('cameraImage');
            const status = document.getElementById('status');
            const statusText = document.getElementById('statusText');
            const refreshInfo = document.getElementById('refreshInfo');
            const shareButton = document.getElementById('shareButton');
            const cameraId = <?= json_encode($cameraId) ?>;
            const refreshSeconds = 10;
            let remaining = refreshSeconds;

            function setReady() {
                status.classList.remove('error');
                status.classList.add('ready');
                statusText.textContent = 'Trực tuyến';
            }

            function setError() {
                status.classList.remove('ready');
                status.classList.add('error');
                statusText.textContent = 'Không tải được';
            }

            function refreshImage() {
                if (document.hidden) return;
                status.classList.remove('ready', 'error');
                statusText.textContent = 'Đang tải';
                image.src = 'proxy.php?id=' + encodeURIComponent(cameraId) + '&t=' + Date.now();
                remaining = refreshSeconds;
            }

            image.addEventListener('load', setReady);
            image.addEventListener('error', setError);

            window.setInterval(() => {
                if (document.hidden) {
                    refreshInfo.textContent = 'Tạm dừng khi tab ẩn';
                    return;
                }
                remaining -= 1;
                if (remaining <= 0) {
                    refreshImage();
                }
                refreshInfo.textContent = 'Tự làm mới sau ' + remaining + ' giây';
            }, 1000);

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) refreshImage();
            });

            shareButton.addEventListener('click', async () => {
                const shareData = {
                    title: document.title,
                    text: <?= json_encode('Camera giao thông ' . $cameraName . ' – ' . $district, JSON_UNESCAPED_UNICODE) ?>,
                    url: window.location.href
                };

                try {
                    if (navigator.share) {
                        await navigator.share(shareData);
                    } else {
                        await navigator.clipboard.writeText(window.location.href);
                        shareButton.textContent = 'Đã sao chép link';
                        window.setTimeout(() => shareButton.textContent = 'Chia sẻ', 1800);
                    }
                } catch (error) {
                    if (error && error.name !== 'AbortError') {
                        shareButton.textContent = 'Không thể chia sẻ';
                        window.setTimeout(() => shareButton.textContent = 'Chia sẻ', 1800);
                    }
                }
            });
        })();
    </script>
<?php endif; ?>
</main>
</body>
</html>
