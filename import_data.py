# ============================================================
#  import_data.py
#  Xử lý dữ liệu - Tách biệt logic xử lý khỏi API
#  Hỗ trợ triệu chứng mô tả + versioning + ICD fuzzy match
# ============================================================

import re
from flask import session
import pandas as pd
from rapidfuzz import process, fuzz
from sqlalchemy import create_engine, text
import numpy as np

# ====================== CẤU HÌNH ======================
ENGINE = create_engine("mysql+pymysql://root:@127.0.0.1/rrp?charset=utf8mb4")

FUZZY_THRESHOLD = 85

# Đường dẫn file gốc (chỉ dùng import ban đầu)
DATA_PATH = r"D:\downloads\doantotnghiep\data\3_Disease_symptom_and_patient_profile_dataset\Disease_symptom_and_patient_profile_dataset.csv"
ICD_PATH  = r"D:\downloads\doantotnghiep\data\ICD10_vi.xlsx"


# ====================== CACHE ======================
_DB_COLS_CACHE = None
_ICD_CACHE     = None


def get_db_columns():
    """
    Đọc danh sách cột hợp lệ từ schema rrp_raw_data.
    Là nguồn sự thật duy nhất — không hardcode.
    """
    global _DB_COLS_CACHE
    if _DB_COLS_CACHE is None:
        try:
            result = pd.read_sql(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS "
                "WHERE TABLE_SCHEMA = 'rrp' AND TABLE_NAME = 'rrp_raw_data'",
                ENGINE
            )
            _DB_COLS_CACHE = set(result["COLUMN_NAME"].tolist())
        except Exception:
            # Fallback nếu không query được DB
            _DB_COLS_CACHE = {
                "fever", "cough", "fatigue", "diff_breathing",
                "age", "gender_val", "blood_pressure", "cholesterol",
                "disease", "disease_vi", "icd_code", "outcome",
                "data_version", "version_name", "is_base",
            }
    return _DB_COLS_CACHE


def get_icd_data():
    """
    Load ICD-10 Chapter J (hô hấp) một lần — cache lại.
    """
    global _ICD_CACHE
    if _ICD_CACHE is None:
        try:
            icd = pd.read_excel(ICD_PATH)
            icd["desc_clean"] = icd["description"].apply(normalize_text)
            # Chỉ lấy Chapter J — bệnh hô hấp (J00–J99)
            _ICD_CACHE = icd[
                icd["code"].str.startswith("J", na=False)
            ].copy()
        except Exception as e:
            print(f"⚠️ Không load được ICD-10: {e}")
            _ICD_CACHE = pd.DataFrame()
    return _ICD_CACHE


def match_disease_to_icd(disease_name, threshold=75):
    """
    Fuzzy match tên bệnh → ICD-10 Chapter J.
    Dùng description_vi có sẵn — không cần Google Translate.
    Trả về (icd_code, disease_vi, score).
    score < threshold → trả về "UNKNOWN" để lọc ra sau.
    """
    if pd.isna(disease_name) or str(disease_name).strip() == "":
        return "N/A", str(disease_name), 0

    icd = get_icd_data()
    if icd.empty:
        return "N/A", str(disease_name), 0

    query      = normalize_text(str(disease_name))
    candidates = icd["desc_clean"].dropna().tolist()

    match, score, _ = process.extractOne(
        query, candidates, scorer=fuzz.partial_ratio
    )

    if score >= threshold:
        row        = icd[icd["desc_clean"] == match].iloc[0]
        icd_code   = str(row.get("code", "N/A"))
        disease_vi = str(row.get("description_vi", disease_name))
        return icd_code, disease_vi, score
    else:
        return "UNKNOWN", str(disease_name), score


# ====================== MAPPING TRIỆU CHỨNG ======================
SYMPTOM_MAPPING = {
    # Tiếng Việt
    "ho": "cough",
    "ho khan": "cough",
    "ho có đờm": "cough",
    "ho từng cơn": "cough",

    "sốt": "fever",
    "sốt cao": "fever",
    "sốt nhẹ": "fever",
    "ớn lạnh": "fever",
    "rét run": "fever",

    "mệt": "fatigue",
    "mệt mỏi": "fatigue",
    "kiệt sức": "fatigue",
    "uể oải": "fatigue",

    "khó thở": "diff_breathing",
    "thở gấp": "diff_breathing",
    "thở nhanh": "diff_breathing",
    "tức ngực": "diff_breathing",
    "nghẹt thở": "diff_breathing",
    "không hít đủ không khí": "diff_breathing",

    # Tiếng Anh
    "cough": "cough",
    "coughing": "cough",
    "dry cough": "cough",
    "wet cough": "cough",
    "persistent cough": "cough",
    "cough with mucus": "cough",
    "dry coughing": "cough",
    "yellow stuff": "cough",
    "green stuff": "cough",
    "mucus": "cough",

    "fever": "fever",
    "high fever": "fever",
    "low-grade fever": "fever",
    "chills": "fever",
    "hot body": "fever",
    "feeling hot": "fever",

    "fatigue": "fatigue",
    "tiredness": "fatigue",
    "exhaustion": "fatigue",
    "lethargy": "fatigue",
    "general weakness": "fatigue",

    "shortness of breath": "diff_breathing",
    "difficulty breathing": "diff_breathing",
    "dyspnea": "diff_breathing",
    "wheezing": "diff_breathing",
    "chest tightness": "diff_breathing",
    "tight feeling in the chest": "diff_breathing",
    "breathlessness": "diff_breathing",
    "hard to breathe": "diff_breathing",
    "can't breathe": "diff_breathing",
    "breathe well at night": "diff_breathing",
    "noisy breathing": "diff_breathing",

}

# Từ KHÔNG được map dù có trong text — không đặc hiệu cho hô hấp
SYMPTOM_BLACKLIST = {
    "muscle cramps", "muscle weakness",
    "cold hands", "cold feet",
    "headache", "nausea",
    "back pain", "stiffness",
}

# Negation detection — Chapman et al. (2001)
NEGATION_PRE = [
    "no ", "without ", "denies ", "denied ",
    "absence of ", "negative for ", "not ", "never ", "free of ",
    "không ", "không có ", "chưa ", "phủ nhận ", "không bị ",
]
NEGATION_POST = [
    " free", " absent", " negative", " denied",
    " không", " âm tính",
]


def normalize_text(text):
    if pd.isna(text):
        return ""
    text = str(text).lower().strip()
    text = re.sub(r"\(.*?\)", "", text)
    text = re.sub(
        r"[^a-zA-Z0-9\sàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễ"
        r"ìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]",
        " ", text
    )
    return re.sub(r"\s+", " ", text).strip()


def map_descriptive_symptoms(df, symptom_col, target_col):
    """
    Trích xuất binary feature từ cột triệu chứng dạng văn bản.
    Có xử lý phủ định và blacklist.
    """
    if symptom_col not in df.columns:
        return df

    def detect_symptom(text):
        if pd.isna(text):
            return 0
        text_lower = str(text).lower().strip()

        for keyword, target in SYMPTOM_MAPPING.items():
            if target != target_col:
                continue
            if keyword not in text_lower:
                continue
            # Bỏ qua keyword không đặc hiệu
            if keyword in SYMPTOM_BLACKLIST:
                continue

            idx = text_lower.find(keyword)

            # Kiểm tra phủ định trước keyword (trong 30 ký tự)
            prefix = text_lower[max(0, idx - 30):idx]
            if any(t in prefix for t in NEGATION_PRE):
                continue

            # Kiểm tra phủ định sau keyword (trong 20 ký tự)
            suffix = text_lower[idx + len(keyword): idx + len(keyword) + 20]
            if any(t in suffix for t in NEGATION_POST):
                continue

            return 1
        return 0

    df[target_col] = df[symptom_col].apply(detect_symptom)
    return df


# ====================== XỬ LÝ DỮ LIỆU UPLOAD ======================
def process_uploaded_dataset(
    df_raw: pd.DataFrame,
    version_name: str = None,
    description: str = None,
    user_col_mapping: dict = None
):
    df = df_raw.copy()
    warnings = []
    col_map_result = {}
    new_version = -1

    # =====================================================
    # 0. TẠO VERSION (Cải tiến)
    # =====================================================
    if version_name and version_name.strip():
        try:
            max_ver = pd.read_sql(
                "SELECT COALESCE(MAX(version_id), 0) AS mv FROM rrp_dataset_versions", 
                ENGINE
            ).iloc[0]["mv"]
            new_version = int(max_ver) + 1
            uploaded_by = session.get("user_id")
            with ENGINE.begin() as conn:
                conn.execute(text("""
                    INSERT INTO rrp_dataset_versions 
                    (version_id, version_name, description, rows_added, uploaded_by, is_active, created_at)
                    VALUES (:vid, :vname, :desc, :rows, :uploaded_by, 'pending', NOW())
                """), {
                    "vid": new_version,
                    "vname": version_name.strip(),
                    "desc": description or f"Upload lúc {pd.Timestamp.now().strftime('%Y-%m-%d %H:%M')}",
                    "rows": int(len(df)),
                    "uploaded_by": uploaded_by, # Lấy user thực tế nếu có auth
                })
            print(f"✅ TẠO VERSION THÀNH CÔNG: {new_version} - {version_name}")
        except Exception as e:
            print(f"❌ Lỗi tạo version: {e}")
            return {
        "df": pd.DataFrame(),
        "warnings": [f"Lỗi tạo version: {e}"],
        "col_mapping": col_map_result,
        "rows": 0,
        "version": None,
    }
        
    # =====================================================
    # 1. Ánh xạ tên cột
    # =====================================================
    COL_MAPPING = {
        # Tên bệnh
        "disease"              : "disease",
        "bệnh"                 : "disease",
        "benh"                 : "disease",
        "disease prediction"   : "disease",

        # Triệu chứng binary (dataset chuẩn)
        "fever"                : "fever",
        "sốt"                  : "fever",
        "sot"                  : "fever",

        "cough"                : "cough",
        "ho"                   : "cough",

        "fatigue"              : "fatigue",
        "mệt mỏi"             : "fatigue",
        "met moi"              : "fatigue",
        "mệt"                  : "fatigue",

        "difficulty breathing" : "diff_breathing",
        "khó thở"              : "diff_breathing",
        "kho tho"              : "diff_breathing",

        # Thông tin cá nhân
        "age"                  : "age",
        "tuổi"                 : "age",
        "tuoi"                 : "age",

        "gender"               : "gender_val",
        "sex"                  : "gender_val",
        "giới tính"            : "gender_val",
        "gioi tinh"            : "gender_val",

        "blood pressure"       : "blood_pressure",
        "huyết áp"             : "blood_pressure",
        "huyet ap"             : "blood_pressure",

        "cholesterol"          : "cholesterol",
        "mức cholesterol"      : "cholesterol",
        "muc cholesterol"      : "cholesterol",

        "outcome"              : "outcome",
        "biến kết quả"         : "outcome",
        "bien ket qua"         : "outcome",

        "icd_code"             : "icd_code",
        "icd code"             : "icd_code",

        # Cột triệu chứng dạng văn bản — xử lý NLP rồi drop
        "symptoms"             : "symptoms_raw",
        "symptom"              : "symptoms_raw",
        "symptoms question"    : "symptoms_raw",  # normalize "/" → " "
        "question"             : "symptoms_raw",
        "symptom description"  : "symptoms_raw",
        "symptom_description"  : "symptoms_raw",

        # Cột phụ — drop sau khi xử lý
        "nature"               : "_drop",
        "treatment"            : "_drop",
        "recommended medicines": "_drop",
        "advice"               : "_drop",
        "id"                   : "_drop",
    }

    rename_dict = {}

    for col in df.columns:
        norm = normalize_text(col)
        if norm in COL_MAPPING:
            std = COL_MAPPING[norm]
            if std == "_drop":
                col_map_result[col] = {"mapped_to": None, "status": "skipped"}
            else:
                rename_dict[col] = std
                col_map_result[col] = {"mapped_to": std, "status": "ok"}
        else:
            col_map_result[col] = {"mapped_to": None, "status": "skipped"}

    df = df.rename(columns=rename_dict)
    

    # =====================================================
    # 1b. Match tên bệnh → ICD-10 + lọc domain hô hấp
    # =====================================================
    if "disease" in df.columns:
        icd_results      = df["disease"].apply(match_disease_to_icd)
        df["icd_code"]   = icd_results.apply(lambda x: x[0])
        df["disease_vi"] = icd_results.apply(lambda x: x[1])
        df["_icd_score"] = icd_results.apply(lambda x: x[2])

        before     = len(df)
        df_valid   = df[df["icd_code"] != "UNKNOWN"].copy()
        df_invalid = df[df["icd_code"] == "UNKNOWN"].copy()

        if len(df_invalid) > 0:
            dropped_list = df_invalid["disease"].value_counts().head(5).to_dict()
            warnings.append(
                f"🚫 Loại {len(df_invalid)}/{before} hàng "
                f"không thuộc ICD-10 hô hấp (Chapter J):"
            )
            for d, cnt in dropped_list.items():
                warnings.append(f"   • '{d}': {cnt} hàng")

        df = df_valid.drop(columns=["_icd_score"], errors="ignore")

        if len(df) == 0:
            warnings.append("❌ Không còn dữ liệu hô hấp hợp lệ sau khi lọc.")
            return {
                "df"         : pd.DataFrame(),
                "warnings"   : warnings,
                "col_mapping": col_map_result,
                "rows"       : 0,
                "version"    : None,
            }

    # =====================================================
    # 2. Trích xuất triệu chứng từ cột văn bản
    # Tìm symptoms_raw (sau rename) hoặc fallback tên gốc
    # =====================================================
    sym_col = None
    if "symptoms_raw" in df.columns:
        sym_col = "symptoms_raw"
    else:
        for col in df.columns:
            if normalize_text(col) in ["triệu chứng", "symptoms", "symptom"]:
                sym_col = col
                break

    if sym_col:
        for target in ["fever", "cough", "fatigue", "diff_breathing"]:
            df = map_descriptive_symptoms(df, sym_col, target)
        warnings.append(f"Đã trích xuất triệu chứng từ cột: '{sym_col}'")
            # Loại bỏ các dòng không phát hiện được bất kỳ triệu chứng nào
        symptom_cols = ["fever", "cough", "fatigue", "diff_breathing"]

        before_rows = len(df)

        df = df[
            df[symptom_cols].fillna(0).sum(axis=1) > 0
        ].copy()

        removed_rows = before_rows - len(df)

        if removed_rows > 0:
            warnings.append(
                f"🚫 Loại {removed_rows} dòng không phát hiện triệu chứng hô hấp hợp lệ"
            )

    # =====================================================
    # 3. Giữ cột — so sánh với schema DB thực tế
    # =====================================================
    DB_COLS  = get_db_columns()
    RAW_COLS = {"symptoms_raw", "nature_raw", "treatment_raw",
                "advice_raw", "_drop"}

    keep_cols = [
        c for c in df.columns
        if c in DB_COLS and c not in RAW_COLS
    ]

    dropped_cols = [c for c in df.columns if c not in keep_cols]
    if dropped_cols:
        warnings.append(
            f"Bỏ {len(dropped_cols)} cột không có trong DB schema"
        )

    df = df[keep_cols].copy()

    # =====================================================
    # 4. CHUẨN HÓA DỮ LIỆU (ĐẦY ĐỦ - BAO GỒM OUTCOME)
    # =====================================================
    def normalize_binary(val):
        if pd.isna(val) or val == "":
            return 0
        val = str(val).lower().strip()
        positive = ["1", "yes", "có", "co", "true", "positive", "dương tính", "bị", "xuất hiện", "present", "y"]
        negative = ["0", "no", "không", "khong", "false", "negative", "n"]
        if any(k in val for k in positive):
            return 1
        if any(k in val for k in negative):
            return 0
        return 0

    # Triệu chứng binary
    for col in ["fever", "cough", "fatigue", "diff_breathing"]:
        if col in df.columns:
            df[col] = df[col].apply(normalize_binary)
            warnings.append(f"✅ Chuẩn hóa {col}: {df[col].sum()} giá trị = 1")

    # Gender
    if "gender_val" in df.columns:
        gmap = {"male":1, "nam":1, "m":1, "1":1, "female":0, "nữ":0, "nu":0, "f":0, "0":0}
        df["gender_val"] = df["gender_val"].astype(str).str.lower().map(gmap).fillna(1).astype(int)

    # Age
    if "age" in df.columns:
        df["age"] = pd.to_numeric(df["age"], errors="coerce").fillna(35).astype(int)

    # Blood Pressure & Cholesterol
    level_map = {
        "low":0, "thấp":0, "thap":0, "0":0,
        "normal":1, "bình thường":1, "binh thuong":1, "trung binh":1, "trung bình":1, "1":1,
        "high":2, "cao":2, "2":2
    }
    for col in ["blood_pressure", "cholesterol"]:
        if col in df.columns:
            temp = df[col].astype(str).str.lower().str.strip()
            df[col] = temp.map(level_map).fillna(1).astype(int)
            warnings.append(f"✅ Chuẩn hóa {col}")

    # === OUTCOME (KẾT QUẢ) ===
    if "outcome" in df.columns:
        omap = {
            "positive":1, "dương tính":1, "1":1, "yes":1, "true":1,
            "negative":0, "âm tính":0, "0":0, "no":0, "false":0
        }
        df["outcome"] = df["outcome"].astype(str).str.lower().map(omap).fillna(0).astype(int)
        warnings.append(f"✅ Chuẩn hóa outcome: {df['outcome'].sum()} giá trị dương tính")

    # Điền cột thiếu
    REQUIRED_COLS = ["fever", "cough", "fatigue", "diff_breathing", "age", "gender_val", "blood_pressure", "cholesterol"]
    for col in REQUIRED_COLS:
        if col not in df.columns:
            df[col] = 0 if col != "age" else 35
            warnings.append(f"Thiếu cột '{col}' → điền mặc định")

    # =====================================================
    # 5. Điền cột thiếu với giá trị mặc định
    # =====================================================
    REQUIRED_COLS = ["fever", "cough", "fatigue", "diff_breathing", "age", "gender_val", "blood_pressure", "cholesterol"]
    for col in REQUIRED_COLS:
        if col not in df.columns:
            df[col] = 0 if col != "age" else 35
            warnings.append(f"Thiếu cột '{col}' → điền mặc định")

    df["data_version"] = new_version
    df["is_base"] = 0

    return {
        "df": df,
        "warnings": warnings,
        "col_mapping": col_map_result,
        "rows": int(len(df)),
        "version": new_version,
    }


# ====================== IMPORT DỮ LIỆU GỐC (Chạy 1 lần) ======================
def import_original_data():
    print("Đang tải dữ liệu gốc...")
    df  = pd.read_csv(DATA_PATH)
    icd = pd.read_excel(ICD_PATH)

    print(f"  Bệnh nhân : {df.shape[0]:,} hàng | ICD-10: {icd.shape[0]:,} mã")

    def normalize(text):
        text = str(text).lower()
        text = re.sub(r"\(.*?\)", "", text)
        return text.replace("-", " ").strip()

    icd["desc_clean"] = icd["description"].apply(normalize)
    icd_list   = icd["desc_clean"].dropna().unique()
    icd_map    = dict(zip(icd["desc_clean"], icd["code"]))
    icd_vi_map = dict(zip(icd["desc_clean"], icd["description_vi"]))

    df["disease_clean"] = df["Disease"].apply(normalize)

    def match_icd(disease):
        match, score, _ = process.extractOne(
            disease, icd_list, scorer=fuzz.partial_ratio
        )
        return score, match

    df[["score", "matched_icd"]] = df["disease_clean"].apply(
        lambda x: pd.Series(match_icd(x))
    )
    df["icd_code"]   = df["matched_icd"].map(icd_map)
    df["disease_vi"] = df["matched_icd"].map(icd_vi_map)
    df = df[df["score"] >= FUZZY_THRESHOLD].copy()

    COL_MAP = {
        "Disease": "disease", "Fever": "fever", "Cough": "cough",
        "Fatigue": "fatigue", "Difficulty Breathing": "diff_breathing",
        "Age": "age", "Gender": "gender_val",
        "Blood Pressure": "blood_pressure",
        "Cholesterol Level": "cholesterol",
        "Outcome Variable": "outcome",
    }
    df = df.rename(columns=COL_MAP)

    for col in ["fever", "cough", "fatigue", "diff_breathing"]:
        df[col] = df[col].str.lower().map({"yes": 1, "no": 0}).fillna(0)

    df["gender_val"]     = df["gender_val"].str.lower().map({"male": 1, "female": 0}).fillna(1)
    df["age"]            = pd.to_numeric(df["age"], errors="coerce")
    df["blood_pressure"] = df["blood_pressure"].map({"Low": 0, "Normal": 1, "High": 2}).fillna(1)
    df["cholesterol"]    = df["cholesterol"].map({"Low": 0, "Normal": 1, "High": 2}).fillna(1)
    df["outcome"]        = df["outcome"].map({"Positive": 1, "Negative": 0}).fillna(0)

    df["data_version"] = 0
    df["is_base"]      = 1

    df.to_sql("rrp_raw_data", ENGINE, if_exists="replace", index=False)
    print(f"✅ Import dữ liệu gốc hoàn tất: {len(df):,} hàng")
    return len(df)


if __name__ == "__main__":
    import_original_data()