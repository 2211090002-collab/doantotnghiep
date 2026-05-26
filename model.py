import joblib
import pandas as pd
import numpy as np
from sqlalchemy import create_engine, text
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder
from sklearn.metrics import (accuracy_score, f1_score, precision_score,
                              recall_score, roc_auc_score, roc_curve)
from sklearn.linear_model import LogisticRegression
from sklearn.ensemble import RandomForestClassifier
from xgboost import XGBClassifier
from lightgbm import LGBMClassifier
import os

# ====================== CONFIG ======================
ENGINE   = create_engine("mysql+pymysql://root:@127.0.0.1/rrp?charset=utf8mb4")
PKL_DIR  = "models/"
os.makedirs(PKL_DIR, exist_ok=True)

FEATURE_COLS = [
    "fever", "cough", "fatigue", "diff_breathing",
    "gender_val", "age", "blood_pressure", "cholesterol"
]

MODELS_CONFIG = {
    "LogisticRegression": lambda: LogisticRegression(max_iter=1000),
    "RandomForest"      : lambda: RandomForestClassifier(n_estimators=300, random_state=42, n_jobs=-1),
    "XGBoost"           : lambda: XGBClassifier(n_estimators=300, learning_rate=0.1, max_depth=6,
                                                 random_state=42, n_jobs=-1, eval_metric="logloss"),
    "LightGBM"          : lambda: LGBMClassifier(n_estimators=300, learning_rate=0.1, max_depth=6,
                                                  random_state=42, n_jobs=-1, verbose=-1),
}
# ====================================================

print("Đọc dữ liệu...")
df = pd.read_sql("SELECT * FROM rrp_raw_data WHERE is_base = 1", ENGINE)
X  = df[FEATURE_COLS].apply(pd.to_numeric, errors="coerce").fillna(0)

# Xóa ROC cũ (production) trước khi train lại
with ENGINE.begin() as conn:
    conn.execute(text("DELETE FROM rrp_roc_curve WHERE target IN ('outcome') OR target LIKE 'disease_%'"))

# ✅ Chọn best có ưu tiên active khi bằng nhau
def pick_best(results, target):
    try:
        active_df = pd.read_sql(
            text("SELECT model_name FROM rrp_models WHERE target=:t AND status='active' AND data_version=0 LIMIT 1"),
            ENGINE, params={"t": target}
        )
        active_name = active_df.iloc[0]["model_name"] if not active_df.empty else None
    except:
        active_name = None

    top_f1 = max(r["f1_score"] for r in results)
    tied   = [r for r in results if r["f1_score"] == top_f1]
    for r in tied:
        if r["model_name"] == active_name:
            return r
    return tied[0]

# ====================================================
# MODEL 1: OUTCOME (Binary)
# ====================================================
print("\n" + "="*60)
print("TRAINING MODELS FOR OUTCOME (Binary)")
print("="*60)

y_outcome = df["outcome"]
X_train, X_test, y_train, y_test = train_test_split(
    X, y_outcome, test_size=0.2, random_state=42, stratify=y_outcome
)

results_outcome = []

for name, model_func in MODELS_CONFIG.items():
    print(f"\n→ Training {name}...")
    model = model_func()
    model.fit(X_train, y_train)

    y_pred = model.predict(X_test)
    y_prob = model.predict_proba(X_test)[:, 1]

    acc     = accuracy_score(y_test, y_pred)
    f1      = f1_score(y_test, y_pred, average="weighted", zero_division=0)
    prec    = precision_score(y_test, y_pred, average="weighted", zero_division=0)
    rec     = recall_score(y_test, y_pred, average="weighted", zero_division=0)
    roc_auc = round(float(roc_auc_score(y_test, y_prob)), 4)

    # Lưu ROC curve
    fpr, tpr, thresholds = roc_curve(y_test, y_prob)
    fpr        = fpr[1:]
    tpr        = tpr[1:]
    thresholds = thresholds[1:]

    roc_df = pd.DataFrame({
        "model_name"    : name,
        "target"        : "outcome",
        "fpr"           : fpr,
        "tpr"           : tpr,
        "threshold_val" : thresholds,
    })
    roc_df["threshold_val"] = roc_df["threshold_val"].replace([np.inf, -np.inf], np.nan)
    roc_df.to_sql("rrp_roc_curve", ENGINE, if_exists="append", index=False)

    print(f"   Accuracy: {acc:.4f} | F1: {f1:.4f} | Precision: {prec:.4f} | Recall: {rec:.4f} | ROC-AUC: {roc_auc:.4f}")

    pkl_path = f"{PKL_DIR}outcome_{name}.pkl"
    joblib.dump(model, pkl_path)

    results_outcome.append({
        "model_name"      : f"{name} Outcome",
        "algorithm"       : name,
        "target"          : "outcome",
        "accuracy"        : round(acc, 4),
        "f1_score"        : round(f1, 4),
        "precision_score" : round(prec, 4),
        "recall_score"    : round(rec, 4),
        "roc_auc"         : roc_auc,
        "pkl_path"        : pkl_path,
        "notes"           : f"Train size: {len(X_train)}",
    })

best_outcome = pick_best(results_outcome)
print("\nBẢNG KẾT QUẢ OUTCOME")
print(pd.DataFrame(results_outcome)[["algorithm","accuracy","f1_score","precision_score","recall_score","roc_auc"]])
print(f"\n🏆 Best Outcome Model: {best_outcome['model_name']} (F1 = {best_outcome['f1_score']})")

# ====================================================
# MODEL 2: DISEASE (Multi-class)
# ====================================================
print("\n" + "="*60)
print("TRAINING MODELS FOR DISEASE (Multi-class)")
print("="*60)

print("Phân bố disease ban đầu:")
print(df["disease"].value_counts().sort_values(ascending=False))

le            = LabelEncoder()
class_counts  = df["disease"].value_counts()
valid_diseases = class_counts[class_counts >= 2].index.tolist()

mask       = df["disease"].isin(valid_diseases)
X_filtered = X[mask].reset_index(drop=True)
y_raw      = df["disease"][mask]
y_filtered = le.fit_transform(y_raw)

joblib.dump(le, f"{PKL_DIR}label_encoder.pkl")

print(f"\nSau lọc: {len(X_filtered)} samples | {len(valid_diseases)} classes")

X_train2, X_test2, y_train2, y_test2 = train_test_split(
    X_filtered, y_filtered, test_size=0.2, random_state=42, stratify=y_filtered
)

results_disease = []

for name, model_func in MODELS_CONFIG.items():
    print(f"\n→ Training {name}...")
    model       = model_func()
    num_classes = len(le.classes_)

    if name == "XGBoost":
        model.set_params(objective="multi:softprob", num_class=num_classes)
    elif name == "LightGBM":
        model.set_params(objective="multiclass", num_class=num_classes)

    model.fit(X_train2, y_train2)
    y_pred2 = model.predict(X_test2)
    y_prob2 = model.predict_proba(X_test2)

    acc  = accuracy_score(y_test2, y_pred2)
    f1   = f1_score(y_test2, y_pred2, average="weighted", zero_division=0)
    prec = precision_score(y_test2, y_pred2, average="weighted", zero_division=0)
    rec  = recall_score(y_test2, y_pred2, average="weighted", zero_division=0)

# ROC AUC macro OvR
    try:
        auc_dis = round(float(roc_auc_score(
            y_test2, y_prob2, multi_class="ovr", average="macro"
        )), 4)
    except:
        auc_dis = None

    # Lưu ROC curve macro average cho disease
    try:
        fpr_grid  = np.linspace(0, 1, 100)
        tpr_macro = np.zeros(100)
        count     = 0
        for i in range(len(le.classes_)):
            y_bin = (y_test2 == i).astype(int)
            if y_bin.sum() == 0 or y_bin.sum() == len(y_bin):
                continue
            fpr_i, tpr_i, _ = roc_curve(y_bin, y_prob2[:, i])
            tpr_macro += np.interp(fpr_grid, fpr_i, tpr_i)
            count     += 1
        if count > 0:
            tpr_macro /= count
            pd.DataFrame({
                "model_name"    : name,
                "target"        : "disease",
                "fpr"           : fpr_grid,
                "tpr"           : tpr_macro,
                "threshold_val" : None,
            }).to_sql("rrp_roc_curve", ENGINE, if_exists="append", index=False)
    except Exception as e:
        print(f"   ROC disease error: {e}")

    print(f"   Accuracy: {acc:.4f} | F1: {f1:.4f} | ROC-AUC: {auc_dis}")

    pkl_path = f"{PKL_DIR}disease_{name}.pkl"
    joblib.dump(model, pkl_path)

    results_disease.append({
        "model_name"      : f"{name} Disease",
        "algorithm"       : name,
        "target"          : "disease",
        "accuracy"        : round(acc, 4),
        "f1_score"        : round(f1, 4),
        "precision_score" : round(prec, 4),
        "recall_score"    : round(rec, 4),
        "roc_auc"         : auc_dis,
        "pkl_path"        : pkl_path,
        "notes"           : f"Classes: {num_classes} | Samples: {len(X_filtered)} (filtered)",
    })

best_disease = pick_best(results_disease)
print("\nBẢNG KẾT QUẢ DISEASE")
print(pd.DataFrame(results_disease)[["algorithm","accuracy","f1_score","precision_score","recall_score","roc_auc"]])
print(f"\n🏆 Best Disease Model: {best_disease['model_name']} (F1 = {best_disease['f1_score']})")

# ====================================================
# LƯU VÀO DATABASE
# ====================================================
print("\nCập nhật bảng rrp_models...")

with ENGINE.begin() as conn:
    conn.execute(text("UPDATE rrp_models SET status = 'inactive' WHERE data_version = 0"))

    for res in results_outcome + results_disease:
        is_best = (
            res["model_name"] == best_outcome["model_name"] or
            res["model_name"] == best_disease["model_name"]
        )
        conn.execute(text("""
            INSERT INTO rrp_models
                (model_name, version, algorithm, target, accuracy, f1_score,
                 precision_score, recall_score, roc_auc, pkl_path,
                 train_size, test_size, notes, status, data_version)
            VALUES
                (:name, '1.0', :algo, :target, :acc, :f1, :prec, :rec, :roc_auc,
                 :pkl, :tr, :te, :notes, :status, 0)
            ON DUPLICATE KEY UPDATE
                accuracy        = VALUES(accuracy),
                f1_score        = VALUES(f1_score),
                precision_score = VALUES(precision_score),
                recall_score    = VALUES(recall_score),
                roc_auc         = VALUES(roc_auc),
                pkl_path        = VALUES(pkl_path),
                train_size      = VALUES(train_size),
                test_size       = VALUES(test_size),
                notes           = VALUES(notes),
                status          = VALUES(status)
        """), {
            "name"    : res["model_name"],
            "algo"    : res["algorithm"],
            "target"  : res["target"],
            "acc"     : res["accuracy"],
            "f1"      : res["f1_score"],
            "prec"    : res["precision_score"],
            "rec"     : res["recall_score"],
            "roc_auc" : res.get("roc_auc"),
            "pkl"     : res["pkl_path"],
            "tr"      : len(X_train)  if res["target"] == "outcome" else len(X_train2),
            "te"      : len(X_test)   if res["target"] == "outcome" else len(X_test2),
            "notes"   : res["notes"],
            "status"  : "active" if is_best else "inactive",
        })


print("✅ Hoàn tất! Chỉ active 2 model tốt nhất.")
print(f"   Outcome best : {best_outcome['model_name']}")
print(f"   Disease best : {best_disease['model_name']}")