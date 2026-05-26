<?php
/**
 * Template Name: RRP: Đăng nhập
 */

// Nếu đã đăng nhập → redirect về trang chủ
if ( is_user_logged_in() ) {
    wp_redirect( home_url() );
    exit;
}

// Xử lý đăng nhập
$error   = '';
$success = '';

if ( isset($_POST['rrp_login_nonce']) &&
     wp_verify_nonce($_POST['rrp_login_nonce'], 'rrp_login') ) {

    $username = sanitize_text_field($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $user = wp_authenticate($username, $password);

    if ( is_wp_error($user) ) {
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
    } else {
        wp_set_auth_cookie($user->ID, $remember);

        // Redirect theo role
        $redirect = home_url('/');

        // Nếu có redirect_to thì ưu tiên
        if ( !empty($_GET['redirect_to']) ) {
            $redirect = esc_url($_GET['redirect_to']);
        }
        wp_redirect($redirect);
        exit;
    }
}

get_header(); ?>

<div style="min-height:80vh;display:flex;align-items:center;
            justify-content:center;padding:40px 20px;">

  <div style="width:100%;max-width:420px;">

    <!-- Logo / Tiêu đề -->
    <div style="text-align:center;margin-bottom:32px;">
      <div style="font-size:3rem;margin-bottom:8px;">🫁</div>
      <h1 style="font-size:1.5rem;font-weight:700;margin:0;">
        Respiratory Risk Prediction
      </h1>
      <p style="color:#6b7280;margin-top:6px;font-size:0.9rem;">
        Đăng nhập để sử dụng hệ thống
      </p>
    </div>

    <!-- Thông báo lỗi -->
    <?php if ($error): ?>
    <div style="padding:12px 16px;background:#fee2e2;border-left:4px solid #ef4444;
                border-radius:6px;color:#991b1b;margin-bottom:16px;font-size:0.9rem;">
      ❌ <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Thông báo không có quyền -->
    <?php if ( isset($_GET['rrp_error']) && $_GET['rrp_error'] === 'no_permission' ): ?>
    <div style="padding:12px 16px;background:#fef3c7;border-left:4px solid #f59e0b;
                border-radius:6px;color:#92400e;margin-bottom:16px;font-size:0.9rem;">
      ⚠️ Bạn không có quyền truy cập trang này.
    </div>
    <?php endif; ?>

    <!-- Form đăng nhập -->
    <div class="rrp-chart-box">
      <form method="POST">
        <?php wp_nonce_field('rrp_login', 'rrp_login_nonce'); ?>

        <div class="rrp-field" style="margin-bottom:16px;">
          <label>Tên đăng nhập</label>
          <input type="text" name="username"
            value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>"
            placeholder="Nhập tên đăng nhập..."
            required autofocus>
        </div>

        <div class="rrp-field" style="margin-bottom:20px;">
          <label>Mật khẩu</label>
          <div style="position:relative;">
            <input type="password" name="password" id="rrp-password"
              placeholder="Nhập mật khẩu..."
              style="width:100%;padding-right:44px;box-sizing:border-box;"
              required>
            <button type="button" onclick="togglePassword()"
              style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                     background:none;border:none;cursor:pointer;font-size:1.1rem;">
              👁️
            </button>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;
                    margin-bottom:20px;">
          <label style="display:flex;align-items:center;gap:8px;
                        font-size:0.875rem;cursor:pointer;">
            <input type="checkbox" name="remember"> Ghi nhớ đăng nhập
          </label>
        </div>

        <button type="submit"
          style="width:100%;padding:13px;background:#3b82f6;color:#fff;
                 border:none;border-radius:8px;font-size:1rem;
                 font-weight:600;cursor:pointer;">
          Đăng nhập →
        </button>

      </form>
    </div>

    <!-- Phân cách -->
    <div style="text-align:center;margin-top:20px;color:#6b7280;font-size:0.85rem;">
      Chưa có tài khoản?
      <a href="<?php echo home_url('/dang-ky/'); ?>"
         style="color:#3b82f6;text-decoration:none;font-weight:500;">
        Đăng ký ngay
      </a>
    </div>

    <!-- Truy cập không cần đăng nhập -->
    <div style="text-align:center;margin-top:12px;">
      <a href="<?php echo home_url('/danh-gia-nguy-co/'); ?>"
         style="color:#6b7280;font-size:0.85rem;text-decoration:none;">
        Dùng thử không cần đăng nhập →
      </a>
    </div>

  </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('rrp-password');
    input.type  = input.type === 'password' ? 'text' : 'password';
}
</script>

<?php get_footer(); ?>