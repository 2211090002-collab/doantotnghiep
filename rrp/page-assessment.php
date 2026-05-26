<?php
/**
 * Template Name: RRP: Đánh giá nguy cơ
 */
get_header(); ?>

<div id="rrp-assessment" style="max-width:700px;margin:40px auto;padding:0 20px;">

  <h1 style="text-align:center;margin-bottom:8px;">🫁 Đánh giá nguy cơ hô hấp</h1>
  <p style="text-align:center;color:#6b7280;margin-bottom:32px;">
    Nhập thông tin bên dưới để hệ thống đánh giá nguy cơ bệnh hô hấp của bạn.
  </p>

  <!-- FORM -->
  <div class="rrp-chart-box">

    <!-- Triệu chứng -->
    <h3 style="margin-bottom:16px;">1. Triệu chứng hiện tại</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px;">
      <label class="rrp-checkbox">
        <input type="checkbox" id="fever"> <span>🌡️ Sốt</span>
      </label>
      <label class="rrp-checkbox">
        <input type="checkbox" id="cough"> <span>😮‍💨 Ho</span>
      </label>
      <label class="rrp-checkbox">
        <input type="checkbox" id="fatigue"> <span>😴 Mệt mỏi</span>
      </label>
      <label class="rrp-checkbox">
        <input type="checkbox" id="diff_breathing"> <span>🫁 Khó thở</span>
      </label>
    </div>

    <!-- Thông tin cá nhân -->
    <h3 style="margin-bottom:16px;">2. Thông tin cá nhân</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

      <div class="rrp-field">
        <label>Tuổi</label>
        <input type="number" id="age" min="1" max="120" placeholder="Ví dụ: 35">
      </div>

      <div class="rrp-field">
        <label>Giới tính</label>
        <select id="gender_val">
          <option value="">-- Chọn --</option>
          <option value="1">Nam</option>
          <option value="0">Nữ</option>
        </select>
      </div>

      <div class="rrp-field">
        <label>Huyết áp</label>
        <select id="blood_pressure">
          <option value="">-- Chọn --</option>
          <option value="0">Thấp</option>
          <option value="1">Bình thường</option>
          <option value="2">Cao</option>
        </select>
      </div>

      <div class="rrp-field">
        <label>Mức Cholesterol</label>
        <select id="cholesterol">
          <option value="">-- Chọn --</option>
          <option value="0">Thấp</option>
          <option value="1">Bình thường</option>
          <option value="2">Cao</option>
        </select>
      </div>

    </div>

    <!-- Nút submit -->
    <button id="btn-predict" onclick="submitAssessment()"
      style="width:100%;padding:14px;background:#3b82f6;color:#fff;
             border:none;border-radius:8px;font-size:1rem;
             cursor:pointer;font-weight:600;">
      🔍 Đánh giá nguy cơ
    </button>

  </div>

  <!-- KẾT QUẢ -->
  <div id="rrp-result" style="display:none;margin-top:24px;">

    <!-- Risk level -->
    <div id="result-risk" class="rrp-chart-box" style="text-align:center;margin-bottom:16px;">
      <div id="risk-icon" style="font-size:3rem;margin-bottom:8px;"></div>
      <div id="risk-label" style="font-size:1.5rem;font-weight:700;"></div>
      <div id="risk-prob" style="font-size:1rem;color:#6b7280;margin-top:4px;"></div>
      <div id="risk-recommendation"
        style="margin-top:12px;padding:12px;border-radius:8px;font-weight:500;"></div>
    </div>

    <!-- Top 3 bệnh -->
    <div class="rrp-chart-box" style="margin-bottom:16px;">
      <h3 style="margin-bottom:12px;">Top 3 bệnh có khả năng cao nhất</h3>
      <div id="top-diseases"></div>
    </div>

    <!-- Cảnh báo y khoa -->
    <div style="padding:12px 16px;background:#fef3c7;border-left:4px solid #f59e0b;
                border-radius:4px;font-size:0.875rem;color:#92400e;">
      ⚠️ Đây là công cụ hỗ trợ sàng lọc ban đầu, <strong>không thay thế chẩn đoán của bác sĩ</strong>.
      Nếu có triệu chứng nghiêm trọng, hãy đến cơ sở y tế ngay.
    </div>

    <!-- Nút đánh giá lại -->
    <button onclick="resetForm()"
      style="width:100%;margin-top:16px;padding:12px;background:#f3f4f6;
             border:1px solid #e5e7eb;border-radius:8px;cursor:pointer;font-size:0.95rem;">
      🔄 Đánh giá lại
    </button>

  </div>

</div>

<script>
const API = typeof RRP_CONFIG !== 'undefined' ? RRP_CONFIG.api_url : 'http://localhost:5000';

async function submitAssessment() {
    // Validate
    const age        = document.getElementById('age').value;
    const gender_val = document.getElementById('gender_val').value;
    const blood_pressure = document.getElementById('blood_pressure').value;
    const cholesterol    = document.getElementById('cholesterol').value;

    if (!age || gender_val === '' || blood_pressure === '' || cholesterol === '') {
        alert('Vui lòng điền đầy đủ thông tin cá nhân.');
        return;
    }

    const btn = document.getElementById('btn-predict');
    btn.textContent = '⏳ Đang phân tích...';
    btn.disabled    = true;

    const payload = {
        fever         : document.getElementById('fever').checked ? 1 : 0,
        cough         : document.getElementById('cough').checked ? 1 : 0,
        fatigue       : document.getElementById('fatigue').checked ? 1 : 0,
        diff_breathing: document.getElementById('diff_breathing').checked ? 1 : 0,
        age           : parseInt(age),
        gender_val    : parseInt(gender_val),
        blood_pressure: parseInt(blood_pressure),
        cholesterol   : parseInt(cholesterol),
    };

    try {
        const res  = await fetch(`${API}/api/predict`, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify(payload)
        });
        const data = await res.json();
        showResult(data);
    } catch (err) {
        alert('Không thể kết nối đến máy chủ. Hãy chắc chắn Flask đang chạy.');
    } finally {
        btn.textContent = '🔍 Đánh giá nguy cơ';
        btn.disabled    = false;
    }
}

function showResult(data) {
    // Ẩn form, hiện kết quả
    document.getElementById('rrp-result').style.display = 'block';
    document.getElementById('rrp-result').scrollIntoView({ behavior: 'smooth' });

    // Icon + màu theo risk level
    const config = {
        LOW   : { icon: '✅', label: 'Nguy cơ THẤP',    color: '#10b981', bg: '#d1fae5' },
        MEDIUM: { icon: '⚠️', label: 'Nguy cơ TRUNG BÌNH', color: '#f59e0b', bg: '#fef3c7' },
        HIGH  : { icon: '🚨', label: 'Nguy cơ CAO',     color: '#ef4444', bg: '#fee2e2' },
    };
    const c = config[data.risk_level] || config['LOW'];

    document.getElementById('risk-icon').textContent  = c.icon;
    document.getElementById('risk-label').textContent = c.label;
    document.getElementById('risk-label').style.color = c.color;
    document.getElementById('risk-prob').textContent  =
        `Xác suất dương tính: ${data.outcome_prob}%`;

    const rec = document.getElementById('risk-recommendation');
    rec.textContent       = data.recommendation;
    rec.style.background  = c.bg;
    rec.style.color       = c.color;

    // Top 3 bệnh
    const container = document.getElementById('top-diseases');
    container.innerHTML = data.top_diseases.map((d, i) => `
        <div style="display:flex;align-items:center;gap:12px;
                    padding:10px 0;border-bottom:1px solid #f3f4f6;">
            <span style="font-size:1.2rem;font-weight:700;color:#3b82f6;min-width:24px;">
                ${i + 1}
            </span>
            <div style="flex:1;">
                <div style="font-weight:500;">${d.disease}</div>
                <div style="height:6px;background:#e5e7eb;border-radius:3px;margin-top:4px;">
                    <div style="height:100%;width:${d.prob}%;
                                background:#3b82f6;border-radius:3px;"></div>
                </div>
            </div>
            <span style="font-weight:600;color:#3b82f6;min-width:48px;text-align:right;">
                ${d.prob}%
            </span>
        </div>
    `).join('');
}

function resetForm() {
    // Reset checkbox
    ['fever','cough','fatigue','diff_breathing'].forEach(id => {
        document.getElementById(id).checked = false;
    });
    // Reset input
    ['age','gender_val','blood_pressure','cholesterol'].forEach(id => {
        document.getElementById(id).value = '';
    });
    // Ẩn kết quả
    document.getElementById('rrp-result').style.display = 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<?php get_footer(); ?>