<?php
/**
 * Template Name: RRP: Đăng ký
 */

if ( is_user_logged_in() ) {
    wp_redirect( home_url() );
    exit;
}

$error   = '';
$success = '';

if ( isset($_POST['rrp_register_nonce']) &&
     wp_verify_nonce($_POST['rrp_register_nonce'], 'rrp_register') ) {

    $username  = sanitize_user($_POST['username'] ?? '');
    $email     = sanitize_email($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $fullname  = sanitize_text_field($_POST['fullname'] ?? '');
    $act_code  = strtoupper(trim($_POST['activation_code'] ?? ''));

    if ( empty($username) || empty($email) || empty($password) || empty($fullname) ) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } elseif ( strlen($password) < 8 ) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif ( $password !== $password2 ) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif ( username_exists($username) ) {
        $error = 'Tên đăng nhập đã tồn tại.';
    } elseif ( email_exists($email) ) {
        $error = 'Email đã được sử dụng.';
    } else {
        // Kiểm tra mã kích hoạt nếu có
        $role_to_set = 'subscriber';

        if ( !empty($act_code) ) {
            $api_response = wp_remote_get(
                'http://localhost:5000/api/check-code?code=' . urlencode($act_code),
                ['timeout' => 5]
            );
            if ( is_wp_error($api_response) ) {
                $error = 'Không thể kết nối máy chủ. Vui lòng thử lại.';
            } else {
                $api_data = json_decode(wp_remote_retrieve_body($api_response), true);
                if ( isset($api_data['role']) ) {
                    $role_to_set = $api_data['role'];
                } else {
                    $error = 'Mã kích hoạt không hợp lệ hoặc đã bị tắt.';
                }
            }
        }

        if ( empty($error) ) {
            $user_id = wp_create_user($username, $password, $email);

            if ( is_wp_error($user_id) ) {
                $error = $user_id->get_error_message();
            } else {
                $user = new WP_User($user_id);
                $user->set_role($role_to_set);

                wp_update_user([
                    'ID'           => $user_id,
                    'display_name' => $fullname,
                    'first_name'   => $fullname,
                ]);

                $role_label = [
                    'subscriber'    => 'Người dùng',
                    'medical_staff' => 'Cán bộ y tế',
                    'researcher'    => 'Nhà nghiên cứu',
                    'rrp_admin'     => 'Quản trị viên',
                ];
                $label   = $role_label[$role_to_set] ?? 'Người dùng';
                $success = "Đăng ký thành công với vai trò: <strong>{$label}</strong>! Đang chuyển hướng...";
                echo "<script>setTimeout(() => window.location.href = '" .
                    home_url('/dang-nhap/') . "', 2000);</script>";
            }
        }
    }
}

get_header(); ?>

<div style="min-height:80vh;display:flex;align-items:center;
            justify-content:center;padding:40px 20px;">
  <div style="width:100%;max-width:460px;">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:32px;">
      <div style="font-size:3rem;margin-bottom:8px;">🫁</div>
      <h1 style="font-size:1.5rem;font-weight:700;margin:0;">Tạo tài khoản</h1>
      <p style="color:#6b7280;margin-top:6px;font-size:0.9rem;">
        Đăng ký để lưu lịch sử đánh giá nguy cơ
      </p>
    </div>

    <!-- Thông báo lỗi -->
    <?php if ($error): ?>
    <div style="padding:12px 16px;background:#fee2e2;border-left:4px solid #ef4444;
                border-radius:6px;color:#991b1b;margin-bottom:16px;font-size:0.9rem;">
      ❌ <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Thông báo thành công -->
    <?php if ($success): ?>
    <div style="padding:12px 16px;background:#d1fae5;border-left:4px solid #10b981;
                border-radius:6px;color:#065f46;margin-bottom:16px;font-size:0.9rem;">
      ✅ <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="rrp-chart-box">
      <form method="POST">
        <?php wp_nonce_field('rrp_register', 'rrp_register_nonce'); ?>

        <div class="rrp-field" style="margin-bottom:14px;">
          <label>Họ và tên <span style="color:#ef4444;">*</span></label>
          <input type="text" name="fullname"
            value="<?php echo esc_attr($_POST['fullname'] ?? ''); ?>"
            placeholder="Nguyễn Văn A" required>
        </div>

        <div class="rrp-field" style="margin-bottom:14px;">
          <label>Tên đăng nhập <span style="color:#ef4444;">*</span></label>
          <input type="text" name="username"
            value="<?php echo esc_attr($_POST['username'] ?? ''); ?>"
            placeholder="vd: nguyenvana" required>
        </div>

        <div class="rrp-field" style="margin-bottom:14px;">
          <label>Email <span style="color:#ef4444;">*</span></label>
          <input type="email" name="email"
            value="<?php echo esc_attr($_POST['email'] ?? ''); ?>"
            placeholder="email@example.com" required>
        </div>

        <div class="rrp-field" style="margin-bottom:14px;">
          <label>Mật khẩu <span style="color:#ef4444;">*</span></label>
          <input type="password" name="password"
            placeholder="Ít nhất 8 ký tự" required>
        </div>

        <div class="rrp-field" style="margin-bottom:14px;">
          <label>Xác nhận mật khẩu <span style="color:#ef4444;">*</span></label>
          <input type="password" name="password2"
            placeholder="Nhập lại mật khẩu" required>
        </div>

        <!-- Mã kích hoạt -->
        <div class="rrp-field" style="margin-bottom:20px;">
          <label>
            Mã kích hoạt
            <span style="color:#9ca3af;font-weight:400;font-size:0.8rem;">
              (không bắt buộc)
            </span>
          </label>
          <input type="text" name="activation_code"
            value="<?php echo esc_attr($_POST['activation_code'] ?? ''); ?>"
            placeholder="Nhập mã nếu được cấp bởi quản trị viên"
            style="text-transform:uppercase;">
          <small style="color:#9ca3af;font-size:0.78rem;margin-top:4px;display:block;">
            💡 Không có mã → đăng ký tài khoản thông thường (Người dùng)
          </small>
        </div>

        <button type="submit"
          style="width:100%;padding:13px;background:#10b981;color:#fff;
                 border:none;border-radius:8px;font-size:1rem;
                 font-weight:600;cursor:pointer;">
          Tạo tài khoản →
        </button>

      </form>
    </div>

    <div style="text-align:center;margin-top:20px;color:#6b7280;font-size:0.85rem;">
      Đã có tài khoản?
      <a href="<?php echo home_url('/dang-nhap/'); ?>"
        style="color:#3b82f6;text-decoration:none;font-weight:500;">
        Đăng nhập
      </a>
    </div>

  </div>
</div>

<?php get_footer(); ?>