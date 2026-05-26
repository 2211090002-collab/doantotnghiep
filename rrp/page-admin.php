<?php
/**
 * Template Name: RRP: Quản trị
 */
get_header(); ?>

<div id="rrp-admin" style="max-width:1100px;margin:40px auto;padding:0 20px;">

  <h1 style="text-align:center;margin-bottom:32px;">⚙️ Quản trị hệ thống — RRP</h1>

  <!-- TABS -->
  <div style="display:flex;gap:8px;margin-bottom:24px;
              border-bottom:2px solid #e5e7eb;flex-wrap:wrap;">
    <button class="rrp-tab active" onclick="switchTab('tab-config', this)">
      ⚙️ Cấu hình hệ thống
    </button>
    <button class="rrp-tab" onclick="switchTab('tab-codes', this)">
      🔑 Mã kích hoạt
    </button>
    <button class="rrp-tab" onclick="switchTab('tab-users', this)">
      👥 Người dùng
    </button>
    <button class="rrp-tab" onclick="switchTab('tab-logs', this)">
      📋 Nhật ký
    </button>
  </div>

  <!-- TAB 1: CẤU HÌNH -->
  <div id="tab-config" class="rrp-tab-content">
    <div class="rrp-chart-box">
      <h3 style="margin-bottom:20px;">Cấu hình ngưỡng đánh giá</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
        <div class="rrp-field">
          <label>Ngưỡng LOW → MEDIUM</label>
          <input type="number" id="cfg-threshold-low" step="0.05" min="0" max="1">
          <small style="color:#6b7280;">Dưới mức này = LOW. Mặc định: 0.4</small>
        </div>
        <div class="rrp-field">
          <label>Ngưỡng MEDIUM → HIGH</label>
          <input type="number" id="cfg-threshold-high" step="0.05" min="0" max="1">
          <small style="color:#6b7280;">Trên mức này = HIGH. Mặc định: 0.7</small>
        </div>
        <div class="rrp-field">
          <label>Số bệnh gợi ý (Top-K)</label>
          <input type="number" id="cfg-top-k" min="1" max="10">
          <small style="color:#6b7280;">Số bệnh hiển thị trong kết quả dự đoán</small>
        </div>
        <div class="rrp-field">
          <label>Ngưỡng ICD Fuzzy Match</label>
          <input type="number" id="cfg-icd-threshold" min="50" max="100">
          <small style="color:#6b7280;">Mặc định: 85</small>
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:12px;padding:16px;
                  background:#f9fafb;border-radius:8px;margin-bottom:20px;">
        <label style="font-weight:500;">Trạng thái Chatbot AI:</label>
        <label style="position:relative;display:inline-block;width:48px;height:26px;">
          <input type="checkbox" id="cfg-chatbot" style="opacity:0;width:0;height:0;">
          <span id="toggle-slider"
            style="position:absolute;cursor:pointer;inset:0;background:#ccc;
                   border-radius:26px;transition:.3s;"></span>
        </label>
        <span id="chatbot-status-label" style="color:#6b7280;">Đang tải...</span>
      </div>

      <button onclick="saveConfig()"
        style="padding:12px 32px;background:#3b82f6;color:#fff;
               border:none;border-radius:8px;cursor:pointer;font-weight:600;">
        💾 Lưu cấu hình
      </button>
      <div id="config-msg" style="margin-top:12px;font-size:0.9rem;"></div>
    </div>

    <!-- Cấu hình hiện tại -->
    <div class="rrp-chart-box" style="margin-top:24px;">
      <h3 style="margin-bottom:16px;">Cấu hình hiện tại</h3>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
        <div class="rrp-card">
          <div class="rrp-card-val" id="cur-low">...</div>
          <div class="rrp-card-lbl">Ngưỡng LOW</div>
        </div>
        <div class="rrp-card">
          <div class="rrp-card-val" id="cur-high">...</div>
          <div class="rrp-card-lbl">Ngưỡng HIGH</div>
        </div>
        <div class="rrp-card">
          <div class="rrp-card-val" id="cur-topk">...</div>
          <div class="rrp-card-lbl">Top-K bệnh</div>
        </div>
        <div class="rrp-card">
          <div class="rrp-card-val" id="cur-chatbot">...</div>
          <div class="rrp-card-lbl">Chatbot</div>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB 2: MÃ KÍCH HOẠT -->
  <div id="tab-codes" class="rrp-tab-content" style="display:none;">
    <div class="rrp-chart-box">
      <div style="display:flex;justify-content:space-between;
                  align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;">Quản lý mã kích hoạt</h3>
        <button onclick="showAddCode()"
          style="padding:8px 16px;background:#3b82f6;color:#fff;
                 border:none;border-radius:8px;cursor:pointer;font-weight:600;">
          + Thêm mã mới
        </button>
      </div>

      <!-- Form thêm mã -->
      <div id="add-code-form"
        style="display:none;padding:16px;background:#f8fafc;
               border-radius:8px;margin-bottom:16px;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
          <div class="rrp-field">
            <label>Mã kích hoạt</label>
            <input type="text" id="new-code" placeholder="VD: BACSI2025"
              style="text-transform:uppercase;">
          </div>
          <div class="rrp-field">
            <label>Vai trò</label>
            <select id="new-role">
              <option value="medical_staff">👨‍⚕️ Cán bộ y tế</option>
              <option value="researcher">🔬 Nhà nghiên cứu</option>
              <option value="rrp_admin">⚙️ Quản trị RRP</option>
            </select>
          </div>
          <div class="rrp-field">
            <label>Mô tả</label>
            <input type="text" id="new-desc" placeholder="Ghi chú...">
          </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
          <button onclick="saveCode()"
            style="padding:8px 20px;background:#10b981;color:#fff;
                   border:none;border-radius:6px;cursor:pointer;font-weight:600;">
            💾 Lưu
          </button>
          <button onclick="document.getElementById('add-code-form').style.display='none'"
            style="padding:8px 20px;background:#f3f4f6;color:#374151;
                   border:none;border-radius:6px;cursor:pointer;">
            Hủy
          </button>
        </div>
      </div>

      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#f9fafb;">
            <th class="rrp-th">Mã</th>
            <th class="rrp-th">Vai trò</th>
            <th class="rrp-th">Mô tả</th>
            <th class="rrp-th">Trạng thái</th>
            <th class="rrp-th">Thao tác</th>
          </tr>
        </thead>
        <tbody id="codes-body">
          <tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af;">
            Đang tải...
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- TAB 3: NGƯỜI DÙNG -->
  <div id="tab-users" class="rrp-tab-content" style="display:none;">
    <div class="rrp-chart-box">
      <h3 style="margin-bottom:16px;">Danh sách người dùng</h3>
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#f9fafb;">
            <th class="rrp-th">Tên</th>
            <th class="rrp-th">Username</th>
            <th class="rrp-th">Email</th>
            <th class="rrp-th">Vai trò</th>
            <th class="rrp-th">Ngày tạo</th>
            <th class="rrp-th">Trạng thái</th>
            <th class="rrp-th">Thao tác</th>
          </tr>
        </thead>
        <tbody id="users-body">
          <tr><td colspan="7" style="text-align:center;padding:20px;color:#9ca3af;">
            Đang tải...
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- TAB 4: NHẬT KÝ -->
  <div id="tab-logs" class="rrp-tab-content" style="display:none;">
    <div class="rrp-chart-box">
      <h3 style="margin-bottom:16px;">Nhật ký hoạt động</h3>
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#f9fafb;">
            <th class="rrp-th">Thời gian</th>
            <th class="rrp-th">User ID</th>
            <th class="rrp-th">Hành động</th>
            <th class="rrp-th">Mô tả</th>
            <th class="rrp-th">IP</th>
          </tr>
        </thead>
        <tbody id="logs-body">
          <tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af;">
            Đang tải...
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php get_footer(); ?>