# Công cụ Hỗ trợ Đánh giá Nguy cơ Mắc Bệnh Hô hấp

> **Đồ án Nhóm 2**

| | |
|---|---|
| **Thành viên** | Mai Minh Anh · Nguyễn Thảo Trang |
| **Chủ đề** | Xây dựng công cụ hỗ trợ đánh giá nguy cơ mắc bệnh hô hấp dựa vào mô hình học máy và các phương pháp thống kê cơ bản |

---

## 📋 Mục lục

- [Giới thiệu](#-giới-thiệu)
- [Kiến trúc hệ thống](#-kiến-trúc-hệ-thống)
- [Yêu cầu môi trường](#-yêu-cầu-môi-trường)
- [Cài đặt](#-cài-đặt)
- [Sử dụng](#-sử-dụng)
- [Cấu trúc thư mục](#-cấu-trúc-thư-mục)

---

## 📖 Giới thiệu

Hệ thống hỗ trợ đánh giá nguy cơ mắc các bệnh về đường hô hấp thông qua việc kết hợp:

- **Mô hình học máy** (Random Forest, XGBoost, LightGBM) để phân loại mức độ nguy cơ
- **Phương pháp thống kê cơ bản** để phân tích và trực quan hóa dữ liệu
- **Giao diện người dùng** tích hợp WordPress để dễ dàng tiếp cận

---

## 🏗 Kiến trúc hệ thống

```
┌─────────────────────┐        ┌──────────────────────┐
│   WordPress (Front) │◄──────►│   Flask API (Back)   │
│   PHP 8.0+          │  REST  │   Python 3.10+       │
└─────────────────────┘        └──────────┬───────────┘
                                           │
                               ┌───────────▼───────────┐
                               │   MySQL Database       │
                               │   + ML Models (.pkl)   │
                               └───────────────────────┘
```

---

## ⚙️ Yêu cầu môi trường

| Thành phần | Phiên bản tối thiểu |
|---|---|
| Python | 3.10+ |
| PHP | 8.0+ |
| MySQL | 8.0+ |
| WordPress | 6.0+ |
| RAM | 8 GB |

---

## Cài đặt

### Bước 1 — Cài đặt thư viện Python

```bash
pip install flask flask-cors sqlalchemy pymysql \
            scikit-learn xgboost lightgbm joblib \
            pandas numpy rapidfuzz transformers torch \
            scipy openpyxl
```

### Bước 2 — Khởi tạo cơ sở dữ liệu

Đảm bảo MySQL đang chạy và đã tạo database, sau đó chạy:

```bash
python import_data.py
```

### Bước 3 — Huấn luyện mô hình

```bash
python model.py
```

### Bước 4 — Khởi động Flask API

```bash
python app.py
```

API mặc định chạy tại `http://localhost:5000`.

### Bước 5 — Cài đặt và cấu hình WordPress

1. Cài đặt WordPress theo hướng dẫn tại [wordpress.org](https://wordpress.org/support/article/how-to-install-wordpress/)
2. Cấu hình file `wp-config.php` với thông tin kết nối database
3. Cài đặt plugin kết nối với Flask API (xem thư mục `wordpress-plugin/`)
4. Nhập cấu hình theme và trang từ file `wordpress-export.xml` (nếu có)

---

## 📂 Cấu trúc thư mục

```
doantotnghiep/
├── api/app.py                  # Flask API chính
├── model.py                # Huấn luyện mô hình học máy
├── import_data.py          # Khởi tạo & nhập dữ liệu vào MySQL
├── data/                   # Dữ liệu thô và dữ liệu đã xử lý
├── wordpress-plugin/       # Plugin WordPress kết nối API
└── README.md
```

---

## 📬 Liên hệ

Mọi thắc mắc về đồ án, vui lòng liên hệ nhóm tác giả:

- **Mai Minh Anh**
- **Nguyễn Thảo Trang**
