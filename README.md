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
