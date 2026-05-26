<?php
/**
 * Template Name: RRP: Trang chủ
 */
get_header(); ?>

<!-- HERO SECTION -->
<div style="background:linear-gradient(135deg,#1e40af,#3b82f6);
            color:#fff;padding:80px 20px;text-align:center;">
  <div style="max-width:800px;margin:0 auto;">
    <div style="font-size:4rem;margin-bottom:16px;">🫁</div>
    <h1 style="font-size:2.5rem;font-weight:700;margin:0 0 16px;">
      Respiratory Risk Prediction
    </h1>
    <p style="font-size:1.1rem;opacity:0.9;margin-bottom:32px;line-height:1.7;">
      Hệ thống đánh giá nguy cơ bệnh hô hấp dựa trên triệu chứng,
      ứng dụng mô hình học máy và phương pháp thống kê.
    </p>
    <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
      <a href="<?php echo home_url('/danh-gia-nguy-co/'); ?>"
        style="padding:14px 32px;background:#fff;color:#1e40af;
               border-radius:8px;font-weight:700;text-decoration:none;
               font-size:1rem;">
        🔍 Đánh giá nguy cơ ngay
      </a>
      <a href="<?php echo home_url('/chatbot-ai/'); ?>"
        style="padding:14px 32px;background:rgba(255,255,255,0.15);
               color:#fff;border:2px solid rgba(255,255,255,0.5);
               border-radius:8px;font-weight:600;text-decoration:none;
               font-size:1rem;">
        🤖 Chat với AI
      </a>
    </div>
  </div>
</div>

<!-- TIN TỨC Y TẾ -->
<div style="padding:60px 20px;">
  <div style="max-width:1100px;margin:0 auto;">

    <h2 style="text-align:center;font-size:1.8rem;margin-bottom:8px;">
      📰 Tin tức y tế
    </h2>
    <p style="text-align:center;color:#6b7280;margin-bottom:32px;">
      Thông tin về bệnh hô hấp từ các cơ sở y tế uy tín
    </p>

    <div id="articles-grid"
      style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:28px;">
      <?php for($i=0;$i<6;$i++): ?>
      <div style="background:#e5e7eb;border-radius:12px;height:320px;
                  animation:pulse 1.5s infinite;"></div>
      <?php endfor; ?>
    </div>

    <div id="articles-pagination"
      style="display:flex;justify-content:center;gap:8px;"></div>

  </div>
</div>

<!-- TÍNH NĂNG -->
<div style="padding:60px 20px;">
  <div style="max-width:900px;margin:0 auto;">
    <h2 style="text-align:center;font-size:1.8rem;margin-bottom:40px;">
      Tính năng hệ thống
    </h2>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;">

      <div class="rrp-chart-box" style="text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:12px;">🔍</div>
        <h3 style="margin:0 0 8px;">Đánh giá nguy cơ</h3>
        <p style="color:#6b7280;font-size:0.9rem;line-height:1.6;margin:0 0 16px;">
          Nhập triệu chứng → nhận kết quả nguy cơ LOW/MEDIUM/HIGH
          kèm top 3 bệnh có khả năng cao nhất.
        </p>
        <a href="<?php echo home_url('/danh-gia-nguy-co/'); ?>"
          style="color:#3b82f6;font-weight:600;text-decoration:none;">
          Thử ngay →
        </a>
      </div>

      <div class="rrp-chart-box" style="text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:12px;">📊</div>
        <h3 style="margin:0 0 8px;">Dashboard thống kê</h3>
        <p style="color:#6b7280;font-size:0.9rem;line-height:1.6;margin:0 0 16px;">
          Biểu đồ phân tích dữ liệu, kiểm định thống kê Chi-square,
          T-test dành cho cán bộ y tế và nhà nghiên cứu.
        </p>
        <a href="<?php echo home_url('/dashboard/'); ?>"
          style="color:#3b82f6;font-weight:600;text-decoration:none;">
          Xem Dashboard →
        </a>
      </div>

      <div class="rrp-chart-box" style="text-align:center;">
        <div style="font-size:2.5rem;margin-bottom:12px;">🤖</div>
        <h3 style="margin:0 0 8px;">Chatbot AI</h3>
        <p style="color:#6b7280;font-size:0.9rem;line-height:1.6;margin:0 0 16px;">
          Hỏi đáp về triệu chứng, bệnh hô hấp và kết quả đánh giá
          với trợ lý AI thông minh.
        </p>
        <a href="<?php echo home_url('/chatbot-ai/'); ?>"
          style="color:#3b82f6;font-weight:600;text-decoration:none;">
          Chat ngay →
        </a>
      </div>

    </div>
  </div>
</div>

<!-- ĐĂNG NHẬP / ĐĂNG KÝ (chỉ hiện khi chưa đăng nhập) -->
<?php if ( !is_user_logged_in() ): ?>
<div style="background:#eff6ff;padding:60px 20px;text-align:center;">
  <div style="max-width:600px;margin:0 auto;">
    <h2 style="font-size:1.5rem;margin-bottom:12px;">
      Tạo tài khoản để lưu lịch sử đánh giá
    </h2>
    <p style="color:#6b7280;margin-bottom:24px;">
      Đăng ký miễn phí để theo dõi lịch sử đánh giá nguy cơ của bạn.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;">
      <a href="<?php echo home_url('/dang-ky/'); ?>"
        style="padding:12px 28px;background:#3b82f6;color:#fff;
               border-radius:8px;font-weight:600;text-decoration:none;">
        Đăng ký miễn phí
      </a>
      <a href="<?php echo home_url('/dang-nhap/'); ?>"
        style="padding:12px 28px;background:#fff;color:#3b82f6;
               border:1px solid #3b82f6;border-radius:8px;
               font-weight:600;text-decoration:none;">
        Đăng nhập
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- THÔNG BÁO SAU ĐĂNG NHẬP -->
<?php if ( is_user_logged_in() ):
  $user     = wp_get_current_user();
  $roles    = $user->roles;
  $is_admin = in_array('rrp_admin',$roles)||in_array('administrator',$roles);
  $is_res   = in_array('researcher',$roles);
  $is_staff = in_array('medical_staff',$roles);
?>
<div style="background:#f0fdf4;padding:40px 20px;text-align:center;">
  <div style="max-width:600px;margin:0 auto;">
    <h2 style="font-size:1.3rem;margin-bottom:8px;">
      Xin chào, <?php echo esc_html($user->display_name); ?>! 👋
    </h2>
    <p style="color:#6b7280;margin-bottom:20px;">
      <?php
        if ($is_admin)      echo 'Bạn đang đăng nhập với tư cách Quản trị viên.';
        elseif ($is_res)    echo 'Bạn đang đăng nhập với tư cách Nhà nghiên cứu.';
        elseif ($is_staff)  echo 'Bạn đang đăng nhập với tư cách Cán bộ y tế.';
        else                echo 'Bạn đang đăng nhập với tư cách Người dùng.';
      ?>
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
      <?php if ($is_admin || $is_res || $is_staff): ?>
      <a href="<?php echo home_url('/dashboard/'); ?>"
        style="padding:10px 24px;background:#3b82f6;color:#fff;
               border-radius:8px;font-weight:600;text-decoration:none;">
        📊 Xem Dashboard
      </a>
      <?php endif; ?>
      <?php if ($is_admin): ?>
      <a href="<?php echo home_url('/quan-tri/'); ?>"
        style="padding:10px 24px;background:#6366f1;color:#fff;
               border-radius:8px;font-weight:600;text-decoration:none;">
        ⚙️ Quản trị
      </a>
      <?php endif; ?>
      <a href="<?php echo wp_logout_url(home_url()); ?>"
        style="padding:10px 24px;background:#f3f4f6;color:#374151;
               border-radius:8px;font-weight:600;text-decoration:none;">
        Đăng xuất
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- CẢNH BÁO Y TẾ -->
<div style="background:#fef3c7;padding:20px;text-align:center;">
  <p style="margin:0;color:#92400e;font-size:0.875rem;">
    ⚠️ Hệ thống RRP chỉ mang tính chất <strong>hỗ trợ sàng lọc ban đầu</strong>,
    không thay thế chẩn đoán y khoa chuyên nghiệp.
    Hãy tham khảo ý kiến bác sĩ khi có triệu chứng nghiêm trọng.
  </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const API  = typeof RRP_CONFIG !== 'undefined' ? RRP_CONFIG.api_url : 'http://localhost:5000';
        const res  = await fetch(`${API}/api/stats`);
        const data = await res.json();
        const s    = data.summary;

        document.getElementById('hs-total').textContent    = s.total_records.toLocaleString();
        document.getElementById('hs-diseases').textContent = data.top_diseases.length;
        document.getElementById('hs-positive').textContent = s.positive_cases.toLocaleString();
        document.getElementById('hs-age').textContent      = s.avg_age;
    } catch(e) {
        ['hs-total','hs-diseases','hs-positive','hs-age'].forEach(id => {
            document.getElementById(id).textContent = '--';
        });
    }
});
</script>
<?php if ( isset($_COOKIE['rrp_notice']) && $_COOKIE['rrp_notice'] === 'no_permission' ):
  // Xóa cookie ngay sau khi đọc
  setcookie('rrp_notice', '', time() - 3600, '/');
?>
<div id="rrp-notice-overlay"
  style="position:fixed;inset:0;background:rgba(0,0,0,0.6);
         z-index:99999;display:flex;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:40px;
              max-width:400px;width:90%;text-align:center;
              box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="font-size:3rem;margin-bottom:12px;">🔒</div>
    <h2 style="font-size:1.3rem;font-weight:700;margin:0 0 10px;color:#1f2937;">
      Không có quyền truy cập
    </h2>
    <p style="color:#6b7280;font-size:0.9rem;line-height:1.6;margin-bottom:24px;">
      <?php if ( !is_user_logged_in() ): ?>
        Bạn cần đăng nhập để xem trang này.
      <?php else: ?>
        Tài khoản của bạn không có quyền truy cập trang này.
      <?php endif; ?>
    </p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <?php if ( !is_user_logged_in() ): ?>
      <a href="<?php echo home_url('/dang-nhap/'); ?>"
        style="padding:10px 24px;background:#3b82f6;color:#fff;
               border-radius:8px;font-weight:600;text-decoration:none;">
        Đăng nhập
      </a>
      <?php endif; ?>
      <button onclick="document.getElementById('rrp-notice-overlay').style.display='none'"
        style="padding:10px 24px;background:#f3f4f6;color:#374151;
               border:none;border-radius:8px;font-weight:600;cursor:pointer;">
        Đóng
      </button>
    </div>
  </div>
</div>
<?php endif; ?>
<?php get_footer(); ?>