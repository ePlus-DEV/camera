# Camera giao thông TP.HCM

Ứng dụng web nhỏ gọn để theo dõi nhanh camera giao thông tại TP.HCM theo từng khu vực.

## Tính năng

- Hiển thị camera theo dạng dashboard responsive cho desktop và mobile.
- Tìm kiếm theo tên đường, giao lộ hoặc khu vực.
- Lọc theo quận/khu vực.
- Tùy chọn 6–24 camera mỗi trang.
- Tự làm mới theo chu kỳ 5/10/15/30 giây hoặc tắt hoàn toàn.
- Làm mới từng camera hoặc toàn bộ camera đang hiển thị.
- Hiển thị trạng thái tải/online/lỗi cho từng camera.
- Dark mode và các tùy chọn hiển thị được lưu trên trình duyệt.
- Phân trang đồng bộ với query string `?page=`.
- Proxy chỉ chấp nhận `CamId` hợp lệ, không cho phép proxy URL tùy ý.

## Yêu cầu

- PHP >= 7.4
- Composer

## Cài đặt

```bash
git clone https://github.com/ePlus-DEV/camera.git
cd camera
composer install
php -S localhost:8000
```

Mở <http://localhost:8000>.

## Cấu trúc chính

- `index.html`: giao diện và logic hiển thị camera.
- `data-camera.json`: danh sách camera.
- `proxy.php`: proxy ảnh camera từ nguồn giao thông TP.HCM.
- `composer.json`: dependency PHP.

## Bảo mật

`proxy.php` chỉ nhận tham số `id` là `CamId` 24 ký tự hex. Endpoint upstream được cố định trong source để tránh biến ứng dụng thành open proxy/SSRF.

Ví dụ:

```text
proxy.php?id=586e28a0f9fab7001111b0b3
```

Không lưu cookie/session của website nguồn trong repository.

## Nguồn dữ liệu

Hình ảnh camera được lấy từ Cổng thông tin giao thông TP.HCM. Tình trạng camera phụ thuộc vào nguồn upstream và có thể tạm thời không khả dụng.

---

© ePlus.DEV


## SEO & discovery

Production URL: <https://camera-hcm.eplus.dev/>

The project exposes crawlable resources in addition to the JavaScript camera directory:

- `camera.php?id={CamId}` — server-rendered canonical page for each camera.
- `sitemap.xml` — sitemap index.
- `sitemap.php` — dynamic sitemap containing the homepage and all camera detail pages.
- `robots.txt` — crawler directives and sitemap discovery.
- `llms.txt` — concise AI/LLM discovery file.
- `agents.md` — machine-agent guidance and data semantics.
- `manifest.webmanifest` and `favicon.svg` — app/site identity metadata.

The homepage and detail pages include canonical URLs, social metadata and Schema.org JSON-LD.

## Đồng bộ tọa độ & địa chỉ

`data-camera.json` lưu tọa độ gốc từ `Location.Shape` của nguồn camera TP.HCM:

```json
{
  "Latitude": 10.7918902432446,
  "Longitude": 106.691054105759
}
```

`scripts/sync-cameras.mjs` thực hiện:

1. Gọi `SearchQuery` với `Publish:true`.
2. Parse AjaxPro `DataTable` và lấy `POINT(longitude latitude)`.
3. Thêm camera mới và cập nhật tọa độ nguồn.
4. Giữ lại camera cũ không còn xuất hiện ở nguồn để review thủ công.
5. Nếu có `VIETMAP_API_KEY`, gọi VietMap Reverse v4 (`display_type=6`) để bổ sung `Address`, `Ward`, `District` và `Province`.
6. Chỉ reverse-geocode camera chưa có địa chỉ hoặc camera có tọa độ thay đổi.

GitHub Actions chạy mỗi 6 giờ và chỉ tạo/update PR `data/auto-sync-cameras` khi `data-camera.json` thực sự thay đổi.

### Repository secrets

Vào **Settings → Secrets and variables → Actions** và thêm:

- `VIETMAP_API_KEY` — API key VietMap. Không được commit key vào repository.
- `HCM_TRAFFIC_COOKIE` — tùy chọn; chỉ cần nếu endpoint nguồn bắt buộc session/cookie khi chạy từ GitHub Actions.

Có thể chạy tay workflow **Sync camera data** và bật `force_geocode` khi muốn reverse-geocode lại toàn bộ camera có tọa độ.

