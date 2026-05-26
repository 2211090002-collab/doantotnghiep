# ============================================================
#  api/app.py  —  Flask API (cập nhật theo schema mới)
#  Chạy: python api/app.py
# ============================================================

import joblib, io, re, os, sys, torch, subprocess, shutil
import pandas as pd
import numpy as np
from flask import Flask, jsonify, request, render_template
from flask_cors import CORS
from sqlalchemy import create_engine, text
from transformers import AutoTokenizer, AutoModelForCausalLM
from sklearn.metrics import accuracy_score, f1_score, precision_score, recall_score, roc_curve, roc_auc_score
from scipy.stats import chi2_contingency, ttest_ind
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
    

ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.append(ROOT_DIR)

from import_data import process_uploaded_dataset


print("Đang load Qwen2.5-1.5B...")
QWEN_MODEL = "Qwen/Qwen2.5-1.5B-Instruct"
qwen_tokenizer = AutoTokenizer.from_pretrained(QWEN_MODEL)
qwen_model     = AutoModelForCausalLM.from_pretrained(
    QWEN_MODEL,
    torch_dtype=torch.float32,  # CPU dùng float32
    device_map="cpu"
)
print("✅ Load Qwen xong!")

app    = Flask(__name__)
CORS(app, origins="*")

ENGINE = create_engine("mysql+pymysql://root:@127.0.0.1/rrp?charset=utf8mb4")
MODELS_DIR = "models/"
BASE_DIR   = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

FEATURE_COLS = ["fever","cough","fatigue","diff_breathing",
                "gender_val","age","blood_pressure","cholesterol"]

def load_active_models():
    """Load model production từ bộ dữ liệu GỐC — không bao giờ thay đổi 
    dù có upload bộ mới. data_version=0 là production."""
    try:
        active_models = pd.read_sql("""
            SELECT model_name, target, pkl_path 
            FROM rrp_models 
            WHERE status = 'active' AND data_version = 0
        """, ENGINE)
        
        if len(active_models) < 2:
            raise Exception("Chưa có model active. Vui lòng train model trước.")

        # Outcome Model
        outcome_row = active_models[active_models['target'] == 'outcome'].iloc[0]
        rf_outcome = joblib.load(outcome_row['pkl_path'])
        
        # Disease Model
        disease_row = active_models[active_models['target'] == 'disease'].iloc[0]
        rf_disease = joblib.load(disease_row['pkl_path'])
        
        # Label Encoder
        le_path = os.path.join(MODELS_DIR, "label_encoder.pkl")
        le = joblib.load(le_path)
        
        print(f"✅ Load model thành công!")
        print(f"   • Outcome: {outcome_row['model_name']}")
        print(f"   • Disease: {disease_row['model_name']}")
        
        return rf_outcome, rf_disease, le, outcome_row, disease_row
        
    except Exception as e:
        print("❌ Lỗi load model:", str(e))
        raise

def get_config():
    return pd.read_sql(
        "SELECT * FROM rrp_system_configs WHERE config_name='default' LIMIT 1",
        ENGINE
    ).iloc[0]

def get_recommendation(risk_level):
    return {
        "LOW"   : "Triệu chứng nhẹ. Theo dõi tại nhà, nghỉ ngơi và uống đủ nước.",
        "MEDIUM": "Nên đến gặp bác sĩ để được tư vấn và kiểm tra thêm.",
        "HIGH"  : "Cần đến cơ sở y tế ngay. Không nên chủ quan."
    }.get(risk_level, "")

def log_action(user_id, action, description, ip=None):
    with ENGINE.begin() as conn:
        conn.execute(text("""
            INSERT INTO rrp_activity_logs (user_id, action, description, ip_address)
            VALUES (:uid, :act, :desc, :ip)
        """), {"uid": user_id, "act": action, "desc": description, "ip": ip})

# ════════════════════════════════════════════════════════════
# ROUTE 1 — Thống kê tổng quan (PATIENT + MEDICAL_STAFF)
# GET /api/stats
# ════════════════════════════════════════════════════════════
@app.route("/api/stats")
def get_stats():
    # version=0 → bộ gốc (is_base=1)
    # version=N → bộ upload thứ N (is_base=0)
    version = request.args.get("version", 0, type=int)
    where   = "WHERE is_base = 1" if version == 0 else f"WHERE data_version = {version} AND is_base = 0"

    # Tính summary trực tiếp từ raw_data theo version
    # (không dùng stats_cache vì cache không filter theo version)
    summary_df = pd.read_sql(f"""
        SELECT
            COUNT(*)                        AS total_records,
            SUM(outcome)                    AS positive_cases,
            SUM(1 - outcome)                AS negative_cases,
            ROUND(AVG(age), 1)              AS avg_age
        FROM rrp_raw_data {where}
    """, ENGINE)
    summary = summary_df.iloc[0].to_dict()

    top10 = pd.read_sql(f"""
        SELECT 
            r.disease,
            COALESCE(d.disease_vi, r.disease) AS disease_vi,
            COUNT(*) AS count
        FROM rrp_raw_data r
        LEFT JOIN rrp_diseases d ON LOWER(TRIM(r.disease)) = LOWER(TRIM(d.disease))
        {where}
        GROUP BY r.disease, d.disease_vi
        ORDER BY count DESC
        LIMIT 10
    """, ENGINE)

    by_gender = pd.read_sql(f"""
        SELECT gender_val, COUNT(*) AS count
        FROM rrp_raw_data {where}
        GROUP BY gender_val
    """, ENGINE)

    by_bp = pd.read_sql(f"""
        SELECT blood_pressure, COUNT(*) AS count
        FROM rrp_raw_data {where}
        GROUP BY blood_pressure
    """, ENGINE)

    by_age = pd.read_sql(f"""
        SELECT
            CASE
                WHEN age < 18 THEN 'Dưới 18'
                WHEN age < 35 THEN '18–34'
                WHEN age < 50 THEN '35–49'
                WHEN age < 65 THEN '50–64'
                ELSE 'Trên 65'
            END AS age_group,
            COUNT(*) AS count
        FROM rrp_raw_data {where}
        GROUP BY age_group
    """, ENGINE)

    by_outcome = pd.read_sql(f"""
        SELECT outcome, COUNT(*) AS count
        FROM rrp_raw_data {where}
        GROUP BY outcome
    """, ENGINE)

    # =========================
    # Thống kê mô tả tuổi
    # =========================
    age_data = pd.read_sql(
        f"SELECT age FROM rrp_raw_data {where}",
        ENGINE
    )["age"].dropna()

    if len(age_data) == 0:
        age_stats = {
        "count": 0,
        "mean": 0,
        "std": 0,
        "min": 0,
        "median": 0,
        "max": 0,
    }
    else:
        age_stats = {
        "count": int(len(age_data)),
        "mean": round(float(age_data.mean()), 1),
        "std": round(float(age_data.std()), 1),
        "min": int(age_data.min()),
        "median": round(float(age_data.median()), 1),
        "max": int(age_data.max()),
    }

    # =========================
    # Phân bố triệu chứng
    # =========================
    sym_df = pd.read_sql(f"""
        SELECT
            SUM(fever) AS fever_has,
            SUM(1 - fever) AS fever_not,
            SUM(cough) AS cough_has,
            SUM(1 - cough) AS cough_not,
            SUM(fatigue) AS fatigue_has,
            SUM(1 - fatigue) AS fatigue_not,
            SUM(diff_breathing) AS breath_has,
            SUM(1 - diff_breathing) AS breath_not
        FROM rrp_raw_data {where}
    """, ENGINE).iloc[0]

    by_symptoms = [
        {
            "symptom": "Sốt",
            "has": int(sym_df["fever_has"]),
            "has_not": int(sym_df["fever_not"]),
        },
        {
            "symptom": "Ho",
            "has": int(sym_df["cough_has"]),
            "has_not": int(sym_df["cough_not"]),
        },
        {
            "symptom": "Mệt mỏi",
            "has": int(sym_df["fatigue_has"]),
            "has_not": int(sym_df["fatigue_not"]),
        },
        {
            "symptom": "Khó thở",
            "has": int(sym_df["breath_has"]),
            "has_not": int(sym_df["breath_not"]),
        },
    ]

    # =========================
    # Phân bố cholesterol
    # =========================
    by_chol = pd.read_sql(f"""
        SELECT cholesterol, COUNT(*) AS total
        FROM rrp_raw_data {where}
        GROUP BY cholesterol
        ORDER BY cholesterol
    """, ENGINE)

    chol_label = {
        0: "Thấp",
        1: "Bình thường",
        2: "Cao"
    }

    by_cholesterol = [
        {
            "label": chol_label.get(
                int(row["cholesterol"]),
                str(row["cholesterol"])
            ),
            "count": int(row["total"])
        }
        for _, row in by_chol.iterrows()
    ]

    # =========================
    # Tỷ lệ dương tính theo nhóm tuổi
    # =========================
    pos_age = pd.read_sql(f"""
        SELECT
            CASE
                WHEN age < 18 THEN 'Dưới 18'
                WHEN age < 35 THEN '18–34'
                WHEN age < 50 THEN '35–49'
                WHEN age < 65 THEN '50–64'
                ELSE 'Trên 65'
            END AS age_group,
            ROUND(AVG(outcome) * 100, 2) AS positive_rate,
            COUNT(*) AS count
        FROM rrp_raw_data {where}
        GROUP BY age_group
    """, ENGINE)

    # =========================
    # Trả kết quả
    # =========================
    return jsonify({
        "summary": summary,
        "top_diseases": top10.to_dict(orient="records"),
        "by_gender": by_gender.to_dict(orient="records"),
        "by_bp": by_bp.to_dict(orient="records"),
        "by_age_group": by_age.to_dict(orient="records"),
        "by_outcome": by_outcome.to_dict(orient="records"),
        "age_stats": age_stats,
        "by_symptoms": by_symptoms,
        "by_cholesterol": by_cholesterol,
        "positive_by_age": pos_age.to_dict(orient="records"),
    })

# ════════════════════════════════════════════════════════════
# ROUTE 2 — Kiểm định thống kê (RESEARCHER + MEDICAL_STAFF)
# GET /api/stats/tests
# ════════════════════════════════════════════════════════════
@app.route("/api/stats/tests")
def get_stat_tests():
    version = request.args.get("version", 0, type=int)

    where = (
        "WHERE is_base = 1"
        if version == 0
        else f"WHERE data_version = {version} AND is_base = 0"
    )

    df = pd.read_sql(
        f"SELECT * FROM rrp_raw_data {where}",
        ENGINE
    )

    results = []

    # Các biến phân loại — Chi-square
    for col in ["fever", "cough", "fatigue", "diff_breathing", "blood_pressure", "cholesterol"]:
        ct = pd.crosstab(df[col], df["outcome"])
        if ct.shape[0] < 2 or ct.shape[1] < 2:
            continue
        _, p, _, _ = chi2_contingency(ct)
        results.append({
            "feature"    : col,
            "test"       : "Chi-square",
            "p_value"    : round(float(p), 4),
            "significant": bool(p < 0.05)
        })

    # Tuổi — T-test
    p_age = None
    g1 = df[df["outcome"] == 1]["age"].dropna()
    g0 = df[df["outcome"] == 0]["age"].dropna()
    if len(g1) >= 2 and len(g0) >= 2:
        _, p_age = ttest_ind(g1, g0)

    if p_age is not None:
        results.append({
            "feature"    : "age",
            "test"       : "T-test",
            "p_value"    : round(float(p_age), 4),
            "significant": bool(p_age < 0.05)
        })

    return jsonify(results)
# ════════════════════════════════════════════════════════════
# ROUTE 2B — Correlation Matrix
# GET /api/correlation
# RESEARCHER + MEDICAL_STAFF
# ════════════════════════════════════════════════════════════
@app.route("/api/correlation")
def correlation():

    version = request.args.get("version", 0, type=int)

    where = (
        "WHERE is_base = 1"
        if version == 0
        else f"WHERE data_version = {version}"
    )

    df = pd.read_sql(f"""
        SELECT
            fever,
            cough,
            fatigue,
            diff_breathing,
            age,
            blood_pressure,
            cholesterol,
            outcome
        FROM rrp_raw_data
        {where}
    """, ENGINE)

    corr = df.corr(numeric_only=True)

    return jsonify(
        corr.round(3).to_dict()
    )

# ════════════════════════════════════════════════════════════
# ROUTE 3 — Dự đoán nguy cơ (PATIENT + MEDICAL_STAFF)
# POST /api/predict
# ════════════════════════════════════════════════════════════
@app.route("/api/predict", methods=["POST"])
def predict():
    data = request.get_json()

    for col in FEATURE_COLS:
        if col not in data:
            return jsonify({"error": f"Thiếu trường: {col}"}), 400

    sample = pd.DataFrame([data]).reindex(columns=FEATURE_COLS, fill_value=0)
    cfg = get_config()

    # Load model active
    try:
        rf_outcome, rf_disease, le, outcome_row, disease_row = load_active_models()
    except Exception as e:
        return jsonify({"error": f"Không thể load model: {str(e)}"}), 500

    # ====================== DỰ ĐOÁN ======================
    outcome_prob  = float(rf_outcome.predict_proba(sample)[0][1])
    outcome_value = 1 if outcome_prob >= 0.5 else 0

    risk_level = (
        "HIGH"   if outcome_prob >= float(cfg["threshold_high"]) else
        "MEDIUM" if outcome_prob >= float(cfg["threshold_low"])  else
        "LOW"
    )

    # Top-k bệnh
    top_k   = int(cfg["top_k_disease"])
    probs2  = rf_disease.predict_proba(sample)[0]
    top_idx = probs2.argsort()[-top_k:][::-1]
    
    # Lấy map disease_en → disease_vi
    try:
        disease_map = pd.read_sql(
            "SELECT disease, disease_vi FROM rrp_diseases", ENGINE
        ).set_index("disease")["disease_vi"].to_dict()
    except Exception:
        disease_map = {}

    top_diseases = []
    for i in top_idx:
        disease_en = le.inverse_transform([i])[0]
        disease_vi = disease_map.get(disease_en, disease_en)
        top_diseases.append({
            "disease"   : disease_en,
            "disease_vi": disease_vi,
            "prob"      : round(float(probs2[i]) * 100, 2)
        })

    # Lưu log prediction
    session_id = data.get("session_id")
    if session_id:
        recommendation = get_recommendation(risk_level)
        with ENGINE.begin() as conn:
            conn.execute(text("""
                INSERT INTO rrp_predictions
                (session_id, outcome_model_id, outcome_value, outcome_prob,
                 risk_level, disease_model_id,
                 top1_disease, top1_prob, top2_disease, top2_prob,
                 top3_disease, top3_prob, recommendation)
                VALUES (:sid, :om, :ov, :op, :rl, :dm, :d1, :p1, :d2, :p2, :d3, :p3, :rec)
            """), {
                "sid": session_id,
                "om": int(outcome_row.name) if hasattr(outcome_row, 'name') else None,
                "ov": outcome_value,
                "op": round(outcome_prob, 4),
                "rl": risk_level,
                "dm": int(disease_row.name) if hasattr(disease_row, 'name') else None,
                "d1": top_diseases[0]["disease"], "p1": top_diseases[0]["prob"],
                "d2": top_diseases[1]["disease"] if len(top_diseases)>1 else None,
                "p2": top_diseases[1]["prob"] if len(top_diseases)>1 else None,
                "d3": top_diseases[2]["disease"] if len(top_diseases)>2 else None,
                "p3": top_diseases[2]["prob"] if len(top_diseases)>2 else None,
                "rec": recommendation
            })

    return jsonify({
        "outcome_prob"   : round(outcome_prob * 100, 1),
        "outcome_label"  : "Dương tính" if outcome_value else "Âm tính",
        "risk_level"     : risk_level,
        "recommendation" : get_recommendation(risk_level),
        "top_diseases"   : [
        {
            "disease" : d["disease_vi"] or d["disease"],  # tiếng Việt cho frontend
            "prob"    : d["prob"]
        }
        for d in top_diseases
    ],
    })


# ════════════════════════════════════════════════════════════
# ROUTE 4 — Danh sách + chi tiết model (RESEARCHER + ADMIN)
# GET /api/models
# ════════════════════════════════════════════════════════════
@app.route("/api/models")
def get_models():
    try:
        version = request.args.get("version", 0, type=int)

        if version == 0:
            query = "SELECT * FROM rrp_models ORDER BY model_id DESC"
            params = {}
        else:
            query = "SELECT * FROM rrp_models WHERE data_version = :v ORDER BY model_id DESC"
            params = {"v": version}

        models = pd.read_sql(text(query), ENGINE, params=params)

        # Xử lý NaN/Inf
        models = models.replace({float("nan"): None, float("inf"): None, float("-inf"): None})
        models = models.where(models.notna(), other=None)

        for col in models.columns:
            if str(models[col].dtype).startswith("datetime"):
                models[col] = models[col].astype(str).replace({"NaT": None})

        return jsonify(models.to_dict(orient="records"))

    except Exception as e:
        print("❌ get_models error:", str(e))
        return jsonify({"error": str(e)}), 500

# ════════════════════════════════════════════════════════════
# ROUTE 4B — ROC Curve dữ liệu cho dashboard ML
# GET /api/roc?model=RandomForest
# RESEARCHER + ADMIN
# ════════════════════════════════════════════════════════════

@app.route("/api/roc")
def get_roc_curve():

    model = request.args.get("model")

    query = """
        SELECT
            model_name,
            target,
            fpr,
            tpr,
            threshold_val
        FROM rrp_roc_curve
    """

    params = {}

    if model:
        query += " WHERE model_name = :model"
        params["model"] = model

    query += " ORDER BY model_name, fpr"

    roc = pd.read_sql(
        text(query),
        ENGINE,
        params=params
    )

    return jsonify(
        roc.to_dict(orient="records")
    )

# ════════════════════════════════════════════════════════════
# ROUTE 5 — Cấu hình hệ thống (ADMIN)
# GET|POST /api/config
# ════════════════════════════════════════════════════════════
@app.route("/api/config", methods=["GET","POST"])
def config():
    if request.method == "GET":
        cfg = pd.read_sql("SELECT * FROM rrp_system_configs LIMIT 1", ENGINE)
        return jsonify(cfg.iloc[0].to_dict())

    data = request.get_json()
    with ENGINE.begin() as conn:
        conn.execute(text("""
            UPDATE rrp_system_configs SET
                threshold_low  = :tl,
                threshold_high = :th,
                top_k_disease  = :tk,
                chatbot_status = :cs
            WHERE config_name = 'default'
        """), {
            "tl": data.get("threshold_low",  0.4),
            "th": data.get("threshold_high", 0.7),
            "tk": data.get("top_k_disease",  3),
            "cs": data.get("chatbot_status", 1),
        })
    return jsonify({"status": "ok"})

# ════════════════════════════════════════════════════════════
# ROUTE 6 — Lịch sử phiên đánh giá (MEDICAL_STAFF)
# GET /api/sessions?user_id=1
# ════════════════════════════════════════════════════════════
@app.route("/api/sessions")
def get_sessions():
    user_id = request.args.get("user_id")
    query = """
        SELECT s.*, p.risk_level, p.outcome_prob, p.top1_disease, p.recommendation
        FROM rrp_assessment_sessions s
        LEFT JOIN rrp_predictions p ON s.session_id = p.session_id
        WHERE s.user_id = :uid
        ORDER BY s.session_date DESC
    """
    sessions = pd.read_sql(query, ENGINE, params={"uid": user_id})
    return jsonify(sessions.to_dict(orient="records"))

# ════════════════════════════════════════════════════════════
# ROUTE 7 — Chatbot AI (tất cả người dùng)
# POST /api/chat
# ════════════════════════════════════════════════════════════
@app.route("/api/chat", methods=["POST"])
def chat():
    data    = request.get_json()
    message = data.get("message", "")
    role    = data.get("role", "PATIENT")

    if not message:
        return jsonify({"error": "Thiếu message"}), 400

    diseases     = pd.read_sql("SELECT disease FROM rrp_diseases", ENGINE)
    disease_list = ", ".join(diseases["disease"].tolist())

    if role == "MEDICAL_STAFF":
        system = f"Bạn là trợ lý y tế chuyên nghiệp của hệ thống RRP (Respiratory Risk Prediction). Các bệnh trong hệ thống: {disease_list}. Trả lời chuyên sâu bằng tiếng Việt, ngắn gọn dưới 150 từ."
    elif role == "RESEARCHER":
        system = "Bạn là trợ lý phân tích dữ liệu của hệ thống RRP. Hỗ trợ thống kê và đánh giá mô hình ML. Trả lời bằng tiếng Việt, ngắn gọn dưới 150 từ."
    else:
        system = f"Bạn là trợ lý sức khỏe của hệ thống RRP. Các bệnh: {disease_list}. Trả lời đơn giản, dễ hiểu bằng tiếng Việt, dưới 100 từ. Luôn nhắc gặp bác sĩ nếu nghiêm trọng."

    messages = [
        {"role": "system",    "content": system},
        {"role": "user",      "content": message}
    ]

    try:
        text = qwen_tokenizer.apply_chat_template(
            messages,
            tokenize=False,
            add_generation_prompt=True
        )
        inputs  = qwen_tokenizer([text], return_tensors="pt")
        outputs = qwen_model.generate(
            **inputs,
            max_new_tokens=200,
            temperature=0.7,
            do_sample=True,
            pad_token_id=qwen_tokenizer.eos_token_id
        )
        # Chỉ lấy phần sinh ra, bỏ phần prompt
        new_tokens = outputs[0][inputs["input_ids"].shape[-1]:]
        reply      = qwen_tokenizer.decode(new_tokens, skip_special_tokens=True).strip()

    except Exception as e:
        reply = f"Lỗi model: {str(e)}"

    return jsonify({"reply": reply})

# ════════════════════════════════════════════════════════════
# ROUTE 8 — Logs hoạt động
# GET /api/logs
# ════════════════════════════════════════════════════════════

@app.route("/api/logs")
def get_logs():
    logs = pd.read_sql("""
        SELECT * FROM rrp_activity_logs
        ORDER BY created_at DESC
        LIMIT 100
    """, ENGINE)
    return jsonify(logs.to_dict(orient="records"))

# ════════════════════════════════════════════════════════════
# ROUTE 9 — Danh sách bài viết + chi tiết (Tất cả người dùng)
# GET /api/articles
# GET /api/articles/<id>
# ════════════════════════════════════════════════════════════

# ── GET /api/articles ─────────────────────────────────────
@app.route("/api/articles")
def get_articles():
    page     = int(request.args.get("page", 1))
    per_page = int(request.args.get("per_page", 6))
    offset   = (page - 1) * per_page

    # Dùng f-string thay vì named params cho LIMIT/OFFSET
    articles = pd.read_sql(f"""
        SELECT article_id, url, title, description,
               thumbnail, source, published_at
        FROM rrp_articles
        WHERE status = 1
        ORDER BY crawled_at DESC
        LIMIT {per_page} OFFSET {offset}
    """, ENGINE)

    total = pd.read_sql(
        "SELECT COUNT(*) as cnt FROM rrp_articles WHERE status=1",
        ENGINE
    ).iloc[0]["cnt"]

    return jsonify({
        "articles"   : articles.to_dict(orient="records"),
        "total"      : int(total),
        "page"       : page,
        "total_pages": int((total + per_page - 1) // per_page)
    })

# ── GET /api/articles/<id> ────────────────────────────────
@app.route("/api/articles/<int:article_id>")
def get_article_detail(article_id):
    try:
        with ENGINE.connect() as conn:
            result = conn.execute(text("""
                SELECT * FROM rrp_articles 
                WHERE article_id = :id AND status = 1
            """), {"id": article_id})
            
            row = result.fetchone()
            if row is None:
                return jsonify({"error": "Không tìm thấy bài viết"}), 404

            return jsonify(dict(row._mapping))

    except Exception as e:
        print("❌ Lỗi get_article_detail:", str(e))
        return jsonify({"error": str(e)}), 500

# ── RENDER TRANG HTML CHI TIẾT BÀI VIẾT ─────────────────────────────

@app.route("/chi-tiet-bai-viet")
def chi_tiet_bai_viet():
    article_id = request.args.get("id")
    
    if not article_id:
        return "Thiếu ID bài viết", 400
    
    try:
        article_id = int(article_id)
    except ValueError:
        return "ID bài viết không hợp lệ", 400

    article = pd.read_sql("""
        SELECT * FROM rrp_articles 
        WHERE article_id = :id AND status = 1
    """, ENGINE, params={"id": article_id})

    if article.empty:
        return render_template("404.html", message="Bài viết không tồn tại hoặc đã bị xóa."), 404

    art = article.iloc[0].to_dict()

    return render_template("chi-tiet-bai-viet.html", 
                           article=art,
                           title=art.get("title", "Chi tiết bài viết"))

# ════════════════════════════════════════════════════════════
# ROUTE 10 — Xử lý upload dataset (RESEARCHER + ADMIN)

# ====================== PREVIEW ======================
@app.route("/api/dataset/preview", methods=["POST"])
def preview_dataset():
    if "file" not in request.files:
        return jsonify({"error": "Không có file"}), 400

    file = request.files["file"]
    try:
        if file.filename.endswith(".csv"):
            df = pd.read_csv(file, encoding="utf-8-sig")
        elif file.filename.endswith((".xlsx", ".xls")):
            df = pd.read_excel(file)
        else:
            return jsonify({"error": "Chỉ hỗ trợ CSV/Excel"}), 400
    except Exception as e:
        return jsonify({"error": f"Đọc file thất bại: {str(e)}"}), 400

    result = process_uploaded_dataset(
    df,
    version_name=None,
    description=None
)

    return jsonify({
        "rows": result["rows"],
        "col_mapping": result["col_mapping"],
        "preview": result["df"].head(10).to_dict(orient="records"),
        "warnings": result["warnings"]
    })


# ====================== VERSIONS ======================
@app.route("/api/dataset/versions")
def get_dataset_versions():
    try:
        query = """
        SELECT
            dv.version_id AS data_version,
            dv.version_name,
            COUNT(r.disease) AS rows_added,
            COALESCE(MAX(r.is_base),0) AS is_base,
            dv.created_at
        FROM rrp_dataset_versions dv
        LEFT JOIN rrp_raw_data r
            ON dv.version_id = r.data_version
        GROUP BY
            dv.version_id,
            dv.version_name,
            dv.created_at
        ORDER BY dv.version_id DESC
        """

        versions = pd.read_sql(query, ENGINE)

        for col in versions.columns:
            if str(versions[col].dtype).startswith("datetime"):
                versions[col] = versions[col].astype(str).replace({"NaT": None})

        return jsonify(versions.to_dict(orient="records"))

    except Exception as e:
        print("❌ get_dataset_versions:", str(e))
        return jsonify({"error": str(e)}), 500


# ====================== UPLOAD ======================
@app.route("/api/dataset/upload", methods=["POST"])
def upload_dataset():
    if "file" not in request.files:
        return jsonify({"error": "Không có file"}), 400

    file         = request.files["file"]
    mode         = request.form.get("mode", "append")
    user_role    = request.form.get("user_role", "RESEARCHER")
    do_retrain   = request.form.get("retrain", "0") == "1"
    version_name = request.form.get("version_name", "Cập nhật mới")
    description  = request.form.get("description", "")

    ADMIN_ROLES = {"ADMIN", "rrp_admin", "administrator"}

    # ========================
    # 1. CHECK PERMISSION
    # ========================
    if mode == "replace" and user_role not in ADMIN_ROLES:
        return jsonify({"error": "Chỉ Admin mới được thay thế dữ liệu gốc."}), 403

    if do_retrain and user_role not in ADMIN_ROLES:
        return jsonify({"error": "Chỉ Admin mới được train lại model chính."}), 403

    # ========================
    # 2. READ FILE
    # ========================
    try:
        if file.filename.endswith(".csv"):
            df_raw = pd.read_csv(file, encoding="utf-8-sig")
        elif file.filename.endswith((".xlsx", ".xls")):
            df_raw = pd.read_excel(file)
        else:
            return jsonify({"error": "Chỉ hỗ trợ CSV/Excel"}), 400
    except Exception as e:
        return jsonify({"error": f"Không đọc được file: {str(e)}"}), 400

    # ========================
    # 3. PROCESS DATA
    # ========================
    result = process_uploaded_dataset(
        df_raw,
        version_name=version_name,
        description=description
    )

    if result["rows"] == 0:
        return jsonify({
            "error": "Không có dữ liệu hợp lệ sau khi lọc.",
            "warnings": result.get("warnings", [])
        }), 400

    df_insert = result["df"]

    # ========================
    # 4. GET DB SCHEMA
    # ========================
    try:
        cols_df = pd.read_sql(text("SHOW COLUMNS FROM rrp_raw_data"), ENGINE)
        db_cols = cols_df["Field"].tolist()
    except Exception as e:
        return jsonify({"error": f"Không đọc được schema DB: {str(e)}"}), 500


    # ========================
    # 5. FILTER COLUMNS (SAFE INSERT)
    # ========================
    keep_cols = [c for c in df_insert.columns if c in db_cols]
    df_insert = df_insert[keep_cols].copy()

    print(f"✅ Upload {len(df_insert)} rows | Columns: {keep_cols}")

    # ========================
    # 6. INSERT DATA
    # ========================
    with ENGINE.begin() as conn:
        conn.execute(text("""
            INSERT INTO rrp_diseases (disease, icd_code, disease_vi)
            SELECT DISTINCT 
                TRIM(disease),
                TRIM(icd_code),
                TRIM(disease_vi)
            FROM rrp_raw_data
            WHERE disease IS NOT NULL AND TRIM(disease) != ''
            ON DUPLICATE KEY UPDATE
                icd_code   = VALUES(icd_code),
                disease_vi = VALUES(disease_vi)
        """))
    
    try:
        with ENGINE.begin() as conn:

            # Replace mode (giữ base dataset an toàn)
            if mode == "replace":
                conn.execute(text("""
                    DELETE FROM rrp_raw_data
                    WHERE is_base = 0
                """))

            df_insert.to_sql(
                "rrp_raw_data",
                conn,
                if_exists="append",
                index=False
            )

        # ========================
        # 7. STATS
        # ========================
        total_rows = pd.read_sql(
            "SELECT COUNT(*) AS cnt FROM rrp_raw_data",
            ENGINE
        ).iloc[0]["cnt"]

        return jsonify({
    "status": "ok",
    "rows_added": len(df_insert),
    "total_rows": int(total_rows),
    "valid_rows": result["rows"],          # số dòng sau xử lý
    "warnings": result.get("warnings", []),
    "version": result.get("version")
})

    except Exception as e:
        print("❌ upload_dataset error:", str(e))
        return jsonify({
            "error": f"Lỗi lưu dữ liệu: {str(e)}"
        }), 500
# ════════════════════════════════════════════════════════════
# ROUTE — Mã kích hoạt
# ════════════════════════════════════════════════════════════

@app.route("/api/activation-codes")
def get_codes():
    codes = pd.read_sql("SELECT * FROM rrp_activation_codes ORDER BY created_at DESC", ENGINE)
    return jsonify(codes.to_dict(orient="records"))

@app.route("/api/activation-codes", methods=["POST"])
def add_code():
    data = request.get_json()
    try:
        with ENGINE.begin() as conn:
            conn.execute(text("""
                INSERT INTO rrp_activation_codes (code, role, description)
                VALUES (:code, :role, :desc)
            """), {
                "code": data["code"].upper().strip(),
                "role": data["role"],
                "desc": data.get("description", "")
            })
        return jsonify({"status": "ok"})
    except Exception as e:
        return jsonify({"error": str(e)}), 400

@app.route("/api/activation-codes/<int:code_id>", methods=["PUT"])
def update_code(code_id):
    data = request.get_json()
    with ENGINE.begin() as conn:
        conn.execute(text("""
            UPDATE rrp_activation_codes
            SET is_active = :active WHERE code_id = :id
        """), {"active": data["is_active"], "id": code_id})
    return jsonify({"status": "ok"})

@app.route("/api/activation-codes/<int:code_id>", methods=["DELETE"])
def delete_code(code_id):
    with ENGINE.begin() as conn:
        conn.execute(text(
            "UPDATE rrp_activation_codes SET is_active=0 WHERE code_id=:id"
        ), {"id": code_id})
    return jsonify({"status": "ok"})

@app.route("/api/check-code")
def check_code():
    code = request.args.get("code", "").upper().strip()
    result = pd.read_sql(
    text("SELECT role FROM rrp_activation_codes WHERE code = :code AND is_active = 1"),
    ENGINE, params={"code": code}
)
    if result.empty:
        return jsonify({"error": "Mã không hợp lệ"}), 404
    return jsonify({"role": result.iloc[0]["role"]})

@app.route("/api/users")
def get_users():
    try:
        result = subprocess.run([
            'php', '-r',
            'define("ABSPATH",""); require "/path/to/wp-load.php";'
        ])
    except:
        pass

    # Lấy từ bảng WordPress users
    users = pd.read_sql("""
        SELECT u.ID as user_id,
               u.display_name as full_name,
               u.user_login as username,
               u.user_email as email,
               u.user_registered as created_at,
               um.meta_value as role
        FROM wp_users u
        LEFT JOIN wp_usermeta um
            ON u.ID = um.user_id
            AND um.meta_key = 'wp_capabilities'
        ORDER BY u.user_registered DESC
    """, ENGINE)

    def parse_role(cap_str):
        if not cap_str: return 'subscriber'
        if 'rrp_admin'     in str(cap_str): return 'rrp_admin'
        if 'researcher'    in str(cap_str): return 'researcher'
        if 'medical_staff' in str(cap_str): return 'medical_staff'
        if 'administrator' in str(cap_str): return 'administrator'
        return 'subscriber'

    users['role'] = users['role'].apply(parse_role)
    return jsonify(users.to_dict(orient="records"))

@app.route("/api/users/<int:user_id>/role", methods=["PUT"])
def update_user_role(user_id):
    data     = request.get_json()
    new_role = data.get("role")
    # Cập nhật wp_usermeta
    cap_value = f'a:1:{{s:{len(new_role)}:"{new_role}";b:1;}}'
    with ENGINE.begin() as conn:
        conn.execute(text("""
            UPDATE wp_usermeta
            SET meta_value = :cap
            WHERE user_id = :uid AND meta_key = 'wp_capabilities'
        """), {"cap": cap_value, "uid": user_id})
    return jsonify({"status": "ok"})

@app.route("/api/users/<int:user_id>/status", methods=["PUT"])
def update_user_status(user_id):
    data   = request.get_json()
    status = data.get("status", 1)
    # Dùng user_status của WordPress (0=inactive, 1=active... nhưng WP không dùng nhiều)
    # Ta lưu vào usermeta riêng
    with ENGINE.begin() as conn:
        # Kiểm tra đã có chưa
        existing = pd.read_sql("""
            SELECT umeta_id FROM wp_usermeta
            WHERE user_id=:uid AND meta_key='rrp_status'
        """, ENGINE, params={"uid": user_id})

        if existing.empty:
            conn.execute(text("""
                INSERT INTO wp_usermeta (user_id, meta_key, meta_value)
                VALUES (:uid, 'rrp_status', :status)
            """), {"uid": user_id, "status": status})
        else:
            conn.execute(text("""
                UPDATE wp_usermeta SET meta_value=:status
                WHERE user_id=:uid AND meta_key='rrp_status'
            """), {"uid": user_id, "status": status})
    return jsonify({"status": "ok"})

# ════════════════════════════════════════════════════════════
# ROUTE — Train model thử nghiệm từ bộ dữ liệu version N
# POST /api/models/train  {"version": 1, "user_role": "RESEARCHER"}
# Researcher, Medical Staff, Admin đều dùng được
# ════════════════════════════════════════════════════════════
@app.route("/api/models/train", methods=["POST"])
def train_model_for_version():
    data      = request.get_json()
    version   = int(data.get("version", 0))
    user_role = data.get("user_role", "RESEARCHER")

    if version == 0:
        return jsonify({"error": "Không được train lại model production (version=0)."}), 400

    df = pd.read_sql(
        text("SELECT * FROM rrp_raw_data WHERE data_version = :v AND is_base = 0"),
        ENGINE, params={"v": version}
    )

    if len(df) < 10:
        return jsonify({"error": f"Chỉ có {len(df)} hàng, tối thiểu 10."}), 400

    FEATURE_COLS = ["fever", "cough", "fatigue", "diff_breathing",
                    "gender_val", "age", "blood_pressure", "cholesterol"]

    X = df[FEATURE_COLS].apply(pd.to_numeric, errors="coerce").fillna(0)

    results = []
    pkl_dir = f"models/v{version}/"
    os.makedirs(pkl_dir, exist_ok=True)

    try:
        # ══════════════════════════════
        # Outcome model (binary)
        # ══════════════════════════════
        y_out = df["outcome"]
        X_tr, X_te, y_tr, y_te = train_test_split(X, y_out, test_size=0.2, random_state=42)

        m_out = RandomForestClassifier(n_estimators=100, random_state=42)
        m_out.fit(X_tr, y_tr)
        y_pred = m_out.predict(X_te)
        y_prob = m_out.predict_proba(X_te)[:, 1]

        fpr, tpr, thresholds = roc_curve(y_te, y_prob)
        auc_out = round(float(roc_auc_score(y_te, y_prob)), 4)

        pkl_out = f"{pkl_dir}outcome_rf.pkl"
        joblib.dump(m_out, pkl_out)

        with ENGINE.begin() as conn:
            result_out = conn.execute(text("""
                INSERT INTO rrp_models
                    (model_name, version, algorithm, target, accuracy, f1_score,
                     precision_score, recall_score, roc_auc, pkl_path,
                     train_size, test_size, notes, status, data_version)
                VALUES
                    (:name,'1.0',:algo,'outcome',:acc,:f1,:prec,:rec,:auc,
                     :pkl,:tr,:te,:notes,'inactive',:dver)
            """), {
                "name" : f"RandomForest Outcome v{version}",
                "algo" : "RandomForest",
                "acc"  : round(accuracy_score(y_te, y_pred), 4),
                "f1"   : round(f1_score(y_te, y_pred, average="weighted"), 4),
                "prec" : round(precision_score(y_te, y_pred, average="weighted"), 4),
                "rec"  : round(recall_score(y_te, y_pred, average="weighted"), 4),
                "auc"  : auc_out,
                "pkl"  : pkl_out,
                "tr"   : len(X_tr), "te": len(X_te),
                "notes": f"version={version} | by={user_role}",
                "dver" : version,
            })
            model_id_out = result_out.lastrowid

            # Lưu ROC curve — outcome
            pd.DataFrame({
                "model_name"    : f"RandomForest Outcome v{version}",
                "target"        : "outcome",
                "fpr"           : fpr.tolist(),
                "tpr"           : tpr.tolist(),
                "threshold_val" : thresholds.tolist(),
            }).to_sql("rrp_roc_curves", conn, if_exists="append", index=False)

        results.append({
            "model_id": model_id_out,
            "target"  : "outcome",
            "accuracy": round(accuracy_score(y_te, y_pred), 4),
            "f1"      : round(f1_score(y_te, y_pred, average="weighted"), 4),
            "roc_auc" : auc_out,
        })

        # ══════════════════════════════
        # Disease model (multi-class)
        # ══════════════════════════════
        class_counts   = df["disease"].value_counts()
        valid_diseases = class_counts[class_counts >= 2].index.tolist()
        df_filtered    = df[df["disease"].isin(valid_diseases)]
        X2             = df_filtered[FEATURE_COLS].apply(pd.to_numeric, errors="coerce").fillna(0)

        le2 = LabelEncoder()
        y2  = le2.fit_transform(df_filtered["disease"])
        joblib.dump(le2, f"{pkl_dir}label_encoder.pkl")

        X_tr2, X_te2, y_tr2, y_te2 = train_test_split(
            X2, y2, test_size=0.2, random_state=42, stratify=y2
        )
        m_dis = RandomForestClassifier(n_estimators=100, random_state=42)
        m_dis.fit(X_tr2, y_tr2)
        y_pred2 = m_dis.predict(X_te2)
        y_prob2 = m_dis.predict_proba(X_te2)

        # ROC AUC macro — multi-class ovr
        try:
            auc_dis = round(float(roc_auc_score(
                y_te2, y_prob2, multi_class="ovr", average="macro"
            )), 4)
        except:
            auc_dis = None

        pkl_dis = f"{pkl_dir}disease_rf.pkl"
        joblib.dump(m_dis, pkl_dis)

        with ENGINE.begin() as conn:
            result_dis = conn.execute(text("""
                INSERT INTO rrp_models
                    (model_name, version, algorithm, target, accuracy, f1_score,
                     precision_score, recall_score, roc_auc, pkl_path,
                     train_size, test_size, notes, status, data_version)
                VALUES
                    (:name,'1.0',:algo,'disease',:acc,:f1,:prec,:rec,:auc,
                     :pkl,:tr,:te,:notes,'inactive',:dver)
            """), {
                "name" : f"RandomForest Disease v{version}",
                "algo" : "RandomForest",
                "acc"  : round(accuracy_score(y_te2, y_pred2), 4),
                "f1"   : round(f1_score(y_te2, y_pred2, average="weighted", zero_division=0), 4),
                "prec" : round(precision_score(y_te2, y_pred2, average="weighted", zero_division=0), 4),
                "rec"  : round(recall_score(y_te2, y_pred2, average="weighted", zero_division=0), 4),
                "auc"  : auc_dis,
                "pkl"  : pkl_dis,
                "tr"   : len(X_tr2), "te": len(X_te2),
                "notes": f"version={version} | Classes={len(le2.classes_)} | by={user_role}",
                "dver" : version,
            })
            model_id_dis = result_dis.lastrowid

            # Lưu ROC curve — disease (one-vs-rest per class)
            roc_rows = []
            for i, cls in enumerate(le2.classes_):
                y_bin = (y_te2 == i).astype(int)
                if y_bin.sum() == 0:
                    continue
                fpr2, tpr2, thr2 = roc_curve(y_bin, y_prob2[:, i])
                for f, t, th in zip(fpr2, tpr2, thr2):
                    roc_rows.append({
                        "model_name"    : f"RandomForest Disease v{version}",
                        "target"        : f"disease_{cls}",
                        "fpr"           : float(f),
                        "tpr"           : float(t),
                        "threshold_val" : float(th),
                    })
            if roc_rows:
                pd.DataFrame(roc_rows).to_sql(
                    "rrp_roc_curves", conn, if_exists="append", index=False
                )

        results.append({
            "model_id": model_id_dis,
            "target"  : "disease",
            "accuracy": round(accuracy_score(y_te2, y_pred2), 4),
            "f1"      : round(f1_score(y_te2, y_pred2, average="weighted", zero_division=0), 4),
            "roc_auc" : auc_dis,
        })

        # Cập nhật version → trained
        with ENGINE.begin() as conn:
            conn.execute(text("""
                UPDATE rrp_dataset_versions
                SET model_status = 'trained'
                WHERE version_id = :vid
            """), {"vid": version})

        return jsonify({
            "status" : "ok",
            "version": version,
            "models" : results,
            "message": "Train thành công. Model production KHÔNG bị ảnh hưởng.",
        })

    except Exception as e:
        print("❌ train_model_for_version error:", str(e))
        return jsonify({"error": str(e)}), 500


# ════════════════════════════════════════════════════════════
# ROUTE — Promote model thử nghiệm lên production
# POST /api/models/promote  {"model_id": 5, "user_role": "ADMIN"}
# Chỉ ADMIN
# ════════════════════════════════════════════════════════════
@app.route("/api/models/promote", methods=["POST"])
def promote_model():

    data      = request.get_json()
    model_id  = int(data.get("model_id"))
    user_role = data.get("user_role", "")

    ADMIN_ROLES = {"ADMIN", "rrp_admin", "administrator"}
    if user_role not in ADMIN_ROLES:
        return jsonify({"error": "Chỉ Admin mới được promote model lên production."}), 403

    model_info = pd.read_sql("""
        SELECT * FROM rrp_models WHERE model_id = :mid
    """, ENGINE, params={"mid": model_id})

    if model_info.empty:
        return jsonify({"error": "Không tìm thấy model."}), 404

    row    = model_info.iloc[0]
    target = row["target"]   # 'outcome' hoặc 'disease'

    # Copy pkl sang folder production (models/)
    old_pkl = row["pkl_path"]
    new_pkl = f"models/{target}_promoted_from_v{row['data_version']}.pkl"
    shutil.copy2(old_pkl, new_pkl)
    if target == "disease":
        le_old = os.path.join(os.path.dirname(old_pkl), "label_encoder.pkl")
        if os.path.exists(le_old):
            shutil.copy2(le_old, os.path.join(MODELS_DIR, "label_encoder.pkl"))

    with ENGINE.begin() as conn:
        # Hạ tất cả model production cũ cùng target xuống inactive
        conn.execute(text("""
            UPDATE rrp_models
            SET status = 'inactive'
            WHERE data_version = 0 AND target = :target AND status = 'active'
        """), {"target": target})

        # Promote model này: data_version → 0, status → active, pkl → production path
        conn.execute(text("""
            UPDATE rrp_models
            SET status = 'active', data_version = 0, pkl_path = :pkl
            WHERE model_id = :mid
        """), {"pkl": new_pkl, "mid": model_id})

    return jsonify({
        "status" : "ok",
        "target" : target,
        "message": f"Model '{row['model_name']}' đã được promote lên production. /api/predict sẽ dùng model mới này.",
    })

# ════════════════════════════════════════════════════════════
if __name__ == "__main__":
    print("🚀 RRP Flask API — http://localhost:5000")
    print("   GET  /api/stats")
    print("   GET  /api/stats/tests")
    print("   POST /api/predict")
    print("   GET  /api/models")
    print("   GET|POST /api/config")
    print("   GET  /api/sessions?user_id=X")
    print("   POST /api/chat")
    print("   GET  /api/logs")
    app.run(port=5000, debug=True)