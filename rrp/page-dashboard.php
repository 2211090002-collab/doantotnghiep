<?php
/**
 * Template Name: RRP: Dashboard
 */
get_header();

$current_role = rrp_get_current_role();
$is_medical_staff = $current_role === 'MEDICAL_STAFF';
$is_researcher    = $current_role === 'RESEARCHER';
$is_admin         = $current_role === 'ADMIN';
?>

<div id="rrp-dashboard" style="max-width:1200px;margin:40px auto;padding:0 20px;">

  <h1 style="text-align:center;margin-bottom:8px;">📊 Dashboard — RRP</h1>
  <p style="text-align:center;color:#6b7280;margin-bottom:24px;">
    Phân tích dữ liệu bệnh hô hấp
  </p>

  <!-- TABS -->
  <div style="display:flex;gap:8px;margin-bottom:24px;border-bottom:2px solid #e5e7eb;flex-wrap:wrap;">
    <button class="rrp-tab active" onclick="switchTab('tab-stats', this)">
      📊 Thống kê tổng quan
    </button>
    <?php if ($is_medical_staff || $is_researcher || $is_admin): ?>
    <button class="rrp-tab" onclick="switchTab('tab-tests', this)">
      🔬 Kiểm định thống kê
    </button>
    <?php endif; ?>
    <?php if ($is_researcher || $is_admin): ?>
    <button class="rrp-tab" onclick="switchTab('tab-models', this)">
      🤖 Mô hình ML
    </button>
    <?php endif; ?>
    <?php if ($is_medical_staff || $is_researcher || $is_admin): ?>
    <button class="rrp-tab" onclick="switchTab('tab-upload', this)">
      📥 Nhập dữ liệu
    </button>
    <?php endif; ?>
  </div>

  <!-- ═══════════════════════════════════════════════════════
       TAB 1: THỐNG KÊ TỔNG QUAN
  ════════════════════════════════════════════════════════ -->
  <div id="tab-stats" class="rrp-tab-content">

    <!-- Thẻ tổng quan -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:32px;">
      <div class="rrp-card" style="background:linear-gradient(135deg,#1e40af,#3b82f6);color:white;">
        <div class="rrp-card-val" id="stat-total">...</div>
        <div class="rrp-card-lbl">Tổng bệnh nhân</div>
      </div>
      <div class="rrp-card" style="background:linear-gradient(135deg,#b91c1c,#ef4444);color:white;">
        <div class="rrp-card-val" id="stat-positive">...</div>
        <div class="rrp-card-lbl">Dương tính</div>
      </div>
      <div class="rrp-card" style="background:linear-gradient(135deg,#15803d,#22c55e);color:white;">
        <div class="rrp-card-val" id="stat-negative">...</div>
        <div class="rrp-card-lbl">Âm tính</div>
      </div>
      <div class="rrp-card" style="background:linear-gradient(135deg,#92400e,#f59e0b);color:white;">
        <div class="rrp-card-val" id="stat-age">...</div>
        <div class="rrp-card-lbl">Tuổi trung bình</div>
      </div>
    </div>

    <div id="dashboard-summary"
      style="margin-bottom:24px;padding:20px 24px;
             background:linear-gradient(135deg,#eff6ff,#ffffff);
             border-left:5px solid #3b82f6;border-radius:12px;
             box-shadow:0 4px 12px rgba(0,0,0,0.06);">
    </div>

    <!-- Hàng 1: Top 10 bệnh + Phân phối giới tính -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:24px;">
      <div class="rrp-chart-box">
        <h3>Top 10 bệnh phổ biến</h3>
        <canvas id="chart-diseases"></canvas>
        <div id="insight-diseases" class="rrp-mini-insight"></div>
      </div>
      <div class="rrp-chart-box">
        <h3>Phân phối giới tính</h3>
        <canvas id="chart-gender"></canvas>
        <div id="insight-gender" class="rrp-mini-insight"></div>
      </div>
    </div>

    <!-- Hàng 2: Phân bố độ tuổi + Thống kê mô tả tuổi -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:24px;">
      <div class="rrp-chart-box">
        <h3>Phân bố độ tuổi</h3>
        <canvas id="chart-age-hist"></canvas>
        <div id="insight-age-hist" class="rrp-mini-insight"></div>
      </div>
      <div class="rrp-chart-box">
        <h3>Thống kê mô tả — Tuổi</h3>
        <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:10px 4px;">Số mẫu</td>
            <td style="text-align:right;font-weight:600;" id="desc-count">—</td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:10px 4px;">Trung bình</td>
            <td style="text-align:right;font-weight:600;" id="desc-mean">—</td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:10px 4px;">Độ lệch chuẩn</td>
            <td style="text-align:right;font-weight:600;" id="desc-std">—</td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:10px 4px;">Nhỏ nhất</td>
            <td style="text-align:right;font-weight:600;" id="desc-min">—</td>
          </tr>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:10px 4px;">Trung vị</td>
            <td style="text-align:right;font-weight:600;" id="desc-median">—</td>
          </tr>
          <tr>
            <td style="padding:10px 4px;">Lớn nhất</td>
            <td style="text-align:right;font-weight:600;" id="desc-max">—</td>
          </tr>
        </table>
        <div id="insight-age-stats" class="rrp-mini-insight"></div>
      </div>
    </div>

    <!-- Hàng 3: Nhóm tuổi + Huyết áp + Cholesterol -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:24px;margin-bottom:24px;">
      <div class="rrp-chart-box">
        <h3>Nhóm tuổi</h3>
        <canvas id="chart-age"></canvas>
      </div>
      <div class="rrp-chart-box">
        <h3>Phân phối huyết áp</h3>
        <canvas id="chart-bp"></canvas>
        <div id="insight-bp" class="rrp-mini-insight"></div>
      </div>
      <div class="rrp-chart-box">
        <h3>Cholesterol</h3>
        <canvas id="chart-cholesterol"></canvas>
        <div id="insight-cholesterol" class="rrp-mini-insight"></div>
      </div>
    </div>

    <!-- Hàng 4: Triệu chứng -->
    <div class="rrp-chart-box" style="margin-bottom:24px;">
      <h3>Phân bố triệu chứng (Có / Không)</h3>
      <canvas id="chart-symptoms" height="120"></canvas>
      <div id="insight-symptoms" class="rrp-mini-insight"></div>
    </div>

    <!-- Hàng 5: Tỷ lệ dương tính + Kết quả chẩn đoán -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
      <div class="rrp-chart-box">
        <h3>Tỷ lệ dương tính theo nhóm tuổi</h3>
        <canvas id="chart-positive-age"></canvas>
        <div id="insight-positive-age" class="rrp-mini-insight"></div>
      </div>
      <div class="rrp-chart-box">
        <h3>Kết quả chẩn đoán tổng quan</h3>
        <canvas id="chart-outcome"></canvas>
        <div id="insight-outcome" class="rrp-mini-insight"></div>
      </div>
    </div>

  </div><!-- /tab-stats -->

  <?php if ($is_medical_staff || $is_researcher || $is_admin): ?>

  <!-- ═══════════════════════════════════════════════════════
       TAB 2: KIỂM ĐỊNH THỐNG KÊ
  ════════════════════════════════════════════════════════ -->
  <div id="tab-tests" class="rrp-tab-content" style="display:none;">
    <div class="rrp-chart-box">
      <h3 style="margin-bottom:16px;">Kiểm định thống kê</h3>
      <p style="color:#6b7280;font-size:0.875rem;margin-bottom:16px;">
        Chi-square test cho các biến phân loại, T-test cho biến liên tục (Tuổi).
        Ngưỡng có ý nghĩa thống kê: p &lt; 0.05
      </p>

      <div id="test-insight"
        style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;
               background:#eff6ff;border-radius:8px;border-left:4px solid #3b82f6;
               margin-bottom:16px;">
      </div>

      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#f9fafb;">
            <th class="rrp-th">Biến</th>
            <th class="rrp-th">Kiểm định</th>
            <th class="rrp-th">P-value</th>
            <th class="rrp-th">Kết luận</th>
          </tr>
        </thead>
        <tbody id="test-body">
          <tr><td colspan="4" style="text-align:center;padding:20px;color:#9ca3af;">Đang tải...</td></tr>
        </tbody>
      </table>

      <!-- Correlation Matrix -->
      <div class="rrp-chart-box" style="margin-top:24px;">
        <h3>Ma trận tương quan đặc trưng</h3>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px;font-size:0.8rem;color:#6b7280;">
          <span>Đọc bảng:</span>
          <span style="display:flex;align-items:center;gap:4px;">
            <span style="display:inline-block;width:14px;height:14px;background:#ef4444;border-radius:2px;"></span>
            Tương quan dương (càng đậm càng mạnh)
          </span>
          <span style="display:flex;align-items:center;gap:4px;">
            <span style="display:inline-block;width:14px;height:14px;background:#3b82f6;border-radius:2px;"></span>
            Tương quan âm
          </span>
          <span style="display:flex;align-items:center;gap:4px;">
            <span style="display:inline-block;width:14px;height:14px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:2px;"></span>
            Không tương quan
          </span>
        </div>
        <div style="overflow-x:auto;">
          <canvas id="chart-correlation"></canvas>
        </div>
        <div style="margin-top:12px;padding:12px;background:#f9fafb;border-radius:8px;font-size:0.875rem;color:#374151;">
          🔍 <strong>Đọc kết quả:</strong> Giá trị gần <strong>+1</strong> (đỏ đậm) = tương quan thuận mạnh;
          gần <strong>-1</strong> (xanh đậm) = tương quan nghịch; gần <strong>0</strong> = ít liên hệ.
          Ô đường chéo luôn = 1.
        </div>
      </div>

    </div>
  </div>
<?php endif; ?>
<?php if ($is_researcher || $is_admin): ?>
  <!-- ═══════════════════════════════════════════════════════
       TAB 3: MÔ HÌNH ML
  ════════════════════════════════════════════════════════ -->
  <div id="tab-models" class="rrp-tab-content" style="display:none;">

    <div class="rrp-chart-box" style="margin-bottom:24px;">
      <h3 style="margin-bottom:16px;">Danh sách mô hình ML</h3>

      <div id="model-insight"
        style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;
               background:#f0fdf4;border-radius:8px;border-left:4px solid #10b981;
               margin-bottom:16px;">
      </div>

      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#f9fafb;">
            <th class="rrp-th">Tên model</th>
            <th class="rrp-th">Phiên bản</th>
            <th class="rrp-th">Thuật toán</th>
            <th class="rrp-th">Mục tiêu</th>
            <th class="rrp-th">Accuracy</th>
            <th class="rrp-th">F1</th>
            <th class="rrp-th">Precision</th>
            <th class="rrp-th">Recall</th>
            <th class="rrp-th">Trạng thái</th>
          </tr>
        </thead>
        <tbody id="table-models">
          <tr><td colspan="9" style="text-align:center;padding:20px;color:#9ca3af;">Đang tải...</td></tr>
        </tbody>
      </table>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
      <div class="rrp-chart-box">
        <h3>So sánh Accuracy</h3>
        <canvas id="chart-model-accuracy"></canvas>
      </div>
      <div class="rrp-chart-box">
        <h3>So sánh F1 Score</h3>
        <canvas id="chart-model-f1"></canvas>
      </div>
    </div>

    <!-- ROC Curve -->
    <div class="rrp-chart-box" style="margin-bottom:24px;">
      <h3>ROC Curve</h3>
      <canvas id="chart-roc"></canvas>
      <div style="margin-top:12px;padding:12px;background:#eff6ff;
                  border-radius:8px;font-size:0.875rem;color:#1e40af;">
        📈 ROC Curve thể hiện khả năng phân biệt giữa hai lớp Dương tính / Âm tính của mô hình.
      </div>
    </div>

    <div style="margin-top:16px;padding:12px 16px;background:#eff6ff;border-radius:8px;font-size:0.875rem;color:#1e40af;">
      💡 <strong>Ghi chú:</strong>
      Model <em>outcome</em> dự đoán kết quả Dương/Âm tính.
      Model <em>disease</em> dự đoán tên bệnh (multi-class).
      Dữ liệu huấn luyện: 78 mẫu sau lọc ICD-10.
    </div>

  </div>
<?php endif; ?>
<?php if ($is_medical_staff || $is_researcher || $is_admin): ?>
  <!-- ═══════════════════════════════════════════════════════
       TAB 4: NHẬP DỮ LIỆU
  ════════════════════════════════════════════════════════ -->
  <div id="tab-upload" class="rrp-tab-content" style="display:none;">

    <!-- BƯỚC 1: MÔ TẢ -->
    <div class="rrp-chart-box" style="margin-bottom:20px;">
      <h3 style="margin:0 0 16px;">📝 Thông tin bản cập nhật</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="rrp-field">
          <label>Tên bản cập nhật</label>
          <input type="text" id="version-name" placeholder="VD: Cập nhật Q1/2025">
        </div>
        <div class="rrp-field">
          <label>Mô tả <span style="color:#9ca3af;font-weight:400;font-size:0.8rem;">(không bắt buộc)</span></label>
          <input type="text" id="version-desc" placeholder="VD: Nguồn dữ liệu BV Bạch Mai Q1/2025...">
        </div>
      </div>
    </div>

    <!-- BƯỚC 2: UPLOAD ZONE -->
    <div class="rrp-chart-box" style="margin-bottom:20px;">
      <h3 style="margin:0 0 16px;">📥 Chọn file dữ liệu</h3>
      <div id="upload-zone"
        style="border:2px dashed #e5e7eb;border-radius:12px;padding:48px 20px;
               text-align:center;cursor:pointer;transition:all 0.2s;"
        onclick="document.getElementById('file-input').click()"
        ondragover="event.preventDefault();this.style.borderColor='#3b82f6';this.style.background='#eff6ff'"
        ondragleave="this.style.borderColor='#e5e7eb';this.style.background=''"
        ondrop="handleDrop(event)">
        <div style="font-size:3rem;margin-bottom:12px;">📂</div>
        <p style="margin:0;color:#374151;font-size:1rem;font-weight:500;">
          Kéo thả file vào đây hoặc <span style="color:#3b82f6;font-weight:600;">click để chọn</span>
        </p>
        <p style="margin:8px 0 0;color:#9ca3af;font-size:0.85rem;">Hỗ trợ: CSV, XLSX, XLS</p>
        <input type="file" id="file-input" accept=".csv,.xlsx,.xls"
               style="display:none;" onchange="handleFileSelect(this.files[0])">
      </div>
    </div>

    <!-- BƯỚC 3: PREVIEW (ẩn ban đầu) -->
    <div id="upload-preview" style="display:none;">

      <!-- Thông tin file -->
      <div style="padding:14px 18px;background:#f0fdf4;border-radius:10px;
                  margin-bottom:16px;display:flex;gap:14px;align-items:center;
                  border:1px solid #bbf7d0;">
        <span style="font-size:2rem;">📄</span>
        <div style="flex:1;">
          <div id="file-info-name" style="font-weight:600;color:#1f2937;font-size:1rem;"></div>
          <div id="file-info-rows" style="font-size:0.85rem;color:#6b7280;margin-top:2px;"></div>
        </div>
        <button onclick="resetUpload()"
          style="padding:6px 14px;background:#f3f4f6;border:none;border-radius:6px;
                 cursor:pointer;font-size:0.8rem;color:#6b7280;">
          ✕ Đổi file
        </button>
      </div>

      <!-- Ánh xạ cột -->
      <div class="rrp-chart-box" style="margin-bottom:16px;background:#f8fafc;">
        <h4 style="margin:0 0 12px;">🗂 Ánh xạ cột tự động</h4>
        <div id="col-mapping-table"></div>
      </div>

      <!-- Cảnh báo từ server -->
      <div id="upload-warnings" style="margin-bottom:16px;"></div>

      <!-- Preview bảng dữ liệu -->
      <div class="rrp-chart-box" style="margin-bottom:16px;">
        <h4 style="margin:0 0 12px;">👁 Xem trước dữ liệu (10 hàng đầu)</h4>
        <div style="overflow-x:auto;">
          <table id="preview-table"
            style="width:100%;border-collapse:collapse;font-size:0.8rem;min-width:600px;">
          </table>
        </div>
      </div>

      <!-- Tùy chọn nhập -->
      <div class="rrp-chart-box" style="margin-bottom:16px;">
        <h4 style="margin:0 0 14px;">⚙️ Tùy chọn nhập</h4>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
            <input type="radio" name="import-mode" value="append" checked style="margin-top:3px;">
            <div>
              <div style="font-weight:600;">Thêm vào (Append)</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Checkbox Train lại -->
      <?php if ($is_admin): ?>
      <label style="display:flex;align-items:center;gap:10px;
                    cursor:pointer;margin-top:4px;">
          <input type="checkbox" id="auto-retrain">
          <span>Tự động <strong>train lại model chính</strong>
          sau khi nhập</span>
      </label>
      <?php endif; ?>

      <!-- Nút hành động -->
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <button id="btn-confirm-upload" onclick="confirmUpload()"
          style="padding:12px 32px;background:#10b981;color:#fff;border:none;
                 border-radius:8px;font-weight:600;cursor:pointer;font-size:0.95rem;">
          ✅ Xác nhận nhập dữ liệu
        </button>
        <button onclick="resetUpload()"
          style="padding:12px 20px;background:#f3f4f6;color:#374151;border:none;
                 border-radius:8px;font-weight:600;cursor:pointer;">
          🔄 Đặt lại
        </button>
      </div>

      <div id="upload-result" style="margin-top:20px;"></div>
    </div>

    <!-- BẢNG LỊCH SỬ VERSIONS -->
    <div class="rrp-chart-box" style="margin-top:28px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;">📋 Lịch sử cập nhật dữ liệu</h3>
        <button onclick="loadVersions()"
          style="padding:6px 14px;background:#f3f4f6;border:none;border-radius:6px;
                 cursor:pointer;font-size:0.82rem;color:#374151;">
          🔄 Làm mới
        </button>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="background:#f9fafb;">
              <th class="rrp-th">Version</th>
              <th class="rrp-th">Tên bản cập nhật</th>
              <th class="rrp-th">Số hàng</th>
              <th class="rrp-th">Ngày tạo</th>
              <th class="rrp-th">Trạng thái</th>
            </tr>
          </thead>
          <tbody id="versions-body">
            <tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af;">
              Đang tải...
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /tab-upload -->

  <?php endif; ?>

</div><!-- /rrp-dashboard -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2.0.1/dist/chartjs-chart-matrix.min.js"></script>

<?php get_footer(); ?>