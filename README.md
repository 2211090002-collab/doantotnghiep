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
| WordPress | 7.0+ |
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
2. Cấu hình file `wp-config.php` với thông tin kết nối database (dùng schema `rrp` trong thư mục `mysql/`)
3. Sao chép thư mục `rrp/` vào `wp-content/themes/rrp/`
4. Kích hoạt theme **rrp** trong WordPress Admin → Appearance → Themes
5. Tạo các trang (Pages) tương ứng và gán đúng page template (`page-assessment.php`, `page-dashboard.php`, v.v.)
6. Cấu hình URL Flask API trong `functions.php`

---

## 📂 Cấu trúc thư mục

```
project/
│
├── api/app.py                      # Khởi động Flask API
├── model.py                    # Huấn luyện & lưu mô hình ML
├── import_data.py              # Nhập dữ liệu vào MySQL
├── ctruc1.dia                   # Cấu hình cấu trúc dữ liệu
├── README.md
│
├── data/                           # Dữ liệu đầu vào
│   ├── Bộ dữ liệu triệu chứng bệnh và hồ sơ bệnh nhân.xlxs
│   │                               # Dữ liệu đầu vào gốc (tiếng Việt)
│   ├── Disease_symptom_and_patient_profile_dataset.csv
│   │                               # Dữ liệu đầu vào gốc (tiếng Anh)
│   ├── ICD10.xlsx                  # Danh mục mã bệnh ICD-10
│   ├── ICD10.viv.xlsx              # Danh mục ICD-10 (tiếng Việt)
│   ├── dataset1_csv.csv            # Tập dữ liệu nhập vào 1
│   ├── dataset500_1.csv            # Tập dữ liệu nhập vào 2
│   └── dichicd.py                  # Script dịch nhãn ICD
│
├── mysql/                          # Cơ sở dữ liệu
│   ├── rrp/                        # Schema chính (các bảng .frm/.ibd)
│   │   ├── rip_activation_...      # Bảng kích hoạt người dùng
│   │   ├── rip_activity_log...     # Nhật ký hoạt động
│   │   ├── rip_articles.*          # Bài viết / tài liệu y tế
│   │   ├── rip_assessment.*        # Kết quả đánh giá nguy cơ
│   │   ├── rip_dataset_ver...      # Phiên bản tập dữ liệu
│   │   ├── rip_diseases.*          # Danh mục bệnh hô hấp
│   │   ├── rip_models.*            # Metadata mô hình ML
│   │   ├── rip_predictions.*       # Kết quả dự đoán
│   │   ├── rip_raw_data.*          # Dữ liệu thô người dùng nhập
│   │   ├── rip_roc_curve.*         # Dữ liệu đường cong ROC
│   │   ├── rip_roles.*             # Phân quyền người dùng
│   │   ├── rip_stats_cache.*       # Cache thống kê
│   │   ├── rip_symptoms.*          # Danh mục triệu chứng
│   │   ├── rip_system_con...       # Cấu hình hệ thống
│   │   ├── wp_*.*                  # Các bảng WordPress tích hợp
│   │   └── db.opt                  # Tùy chọn charset database
│   └── README.md
│
└── rrp/                            # WordPress Frontend (PHP)
    ├── assets/
    │   ├── css/rrp-custom.css      # CSS tùy chỉnh giao diện
    │   └── js/rrp-dashboard...    # JS dashboard thống kê
    ├── functions.php               # Đăng ký hook & tính năng WordPress
    ├── page-admin.php              # Trang quản trị hệ thống
    ├── page-article.php            # Trang bài viết y tế
    ├── page-assessment.php         # Trang đánh giá nguy cơ (chính)
    ├── page-chatbot.php            # Trang chatbot hỏi đáp triệu chứng
    ├── page-dashboard.php          # Dashboard thống kê & biểu đồ
    ├── page-home.php               # Trang chủ
    ├── page-list.php               # Danh sách lịch sử đánh giá
    ├── page-login.php              # Đăng nhập
    ├── page-register.php           # Đăng ký tài khoản
    └── README.md
```

---


## 📬 Liên hệ

Mọi thắc mắc về đồ án, vui lòng liên hệ nhóm tác giả:

- **Mai Minh Anh**
- **Nguyễn Thảo Trang**
