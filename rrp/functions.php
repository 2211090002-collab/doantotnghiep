<?php
/**
 * Blocksy functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Blocksy
 */

if (version_compare(PHP_VERSION, '5.7.0', '<')) {
	require get_template_directory() . '/inc/php-fallback.php';
	return;
}

require get_template_directory() . '/inc/init.php';


// ============================================================
//  RRP — Đăng ký page templates + scripts
// ============================================================
function rrp_register_templates( $templates ) {
    $templates['page-dashboard.php']  = 'RRP: Dashboard';
    $templates['page-assessment.php'] = 'RRP: Đánh giá nguy cơ';
    $templates['page-chatbot.php']    = 'RRP: Chatbot AI';
    $templates['page-admin.php']      = 'RRP: Quản trị';
    $templates['page-home.php']       = 'RRP: Trang chủ';
    $templates['page-login.php']      = 'RRP: Đăng nhập';
    $templates['page-register.php']   = 'RRP: Đăng ký';
    $templates['page-articles.php']   = 'RRP: Danh sách bài viết';
    $templates['page-article.php']    = 'RRP: Chi tiết bài viết';
    return $templates;
}
add_filter( 'theme_page_templates', 'rrp_register_templates' );

function rrp_load_template( $template ) {
    $post = get_post();
    if ( !$post ) return $template;
    $page_template = get_post_meta( $post->ID, '_wp_page_template', true );

    $rrp_templates = [
        'page-dashboard.php',
        'page-assessment.php',
        'page-chatbot.php',
        'page-admin.php',
        'page-home.php',
        'page-login.php',
        'page-register.php',
        'page-articles.php',
        'page-article.php',
    ];

    if ( in_array( $page_template, $rrp_templates ) ) {
        $located = get_template_directory() . '/' . $page_template;
        if ( file_exists( $located ) ) {
            return $located;
        }
    }
    return $template;
}
add_filter( 'template_include', 'rrp_load_template' );

function rrp_enqueue_scripts() {
    wp_enqueue_script( 'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js',
        [], null, true );
    wp_enqueue_script( 'rrp-dashboard',
    get_template_directory_uri() . '/assets/js/rrp-dashboard.js',
    ['jquery'],
    filemtime(get_template_directory() . '/assets/js/rrp-dashboard.js'), true);
    wp_enqueue_style( 'rrp-style',
        get_template_directory_uri() . '/assets/css/rrp-custom.css',
        [], '1.0' );
    wp_localize_script( 'rrp-dashboard', 'RRP_CONFIG', [
        'api_url'          => 'http://localhost:5000',
        'site_url'         => get_site_url(),
        'articles_url'     => home_url('/bai-viet/'),
        'article_detail_url' => home_url('/chi-tiet-bai-viet/'),
    ]);
}
add_action( 'wp_enqueue_scripts', 'rrp_enqueue_scripts' );

// ============================================================
//  RRP — Tạo roles
// ============================================================
function rrp_create_roles() {
    if ( !get_role('medical_staff') )
        add_role('medical_staff', 'Cán bộ y tế', ['read' => true]);
    if ( !get_role('researcher') )
        add_role('researcher', 'Nhà nghiên cứu', ['read' => true]);
    if ( !get_role('rrp_admin') )
        add_role('rrp_admin', 'Quản trị RRP', ['read' => true]);
}
add_action('init', 'rrp_create_roles');

// ============================================================
//  RRP — Kiểm soát quyền truy cập
// ============================================================
function rrp_restrict_pages() {
    if ( !is_page() ) return;

    $template = get_post_meta( get_the_ID(), '_wp_page_template', true );

    $public_pages = [
        'page-assessment.php',
        'page-chatbot.php',
        'page-login.php',
        'page-register.php',
        'page-home.php',
        'page-articles.php',   // bài viết public
        'page-article.php',    // chi tiết public
    ];

    $staff_pages = ['page-dashboard.php'];
    $admin_pages = ['page-admin.php'];

    if ( in_array($template, $public_pages) ) return;

    $is_logged     = is_user_logged_in();
    $roles         = $is_logged ? wp_get_current_user()->roles : [];
    $is_admin      = in_array('administrator', $roles) || in_array('rrp_admin', $roles);
    $is_researcher = in_array('researcher', $roles) || $is_admin;
    $is_staff      = in_array('medical_staff', $roles) || $is_researcher;

    if ( in_array($template, $admin_pages) && !$is_admin ) {
        $key = 'rrp_notice_' . md5($_SERVER['REMOTE_ADDR']);
        set_transient($key, 'no_permission', 30);
        wp_redirect( home_url('/') );
        exit;
    }

    if ( in_array($template, $staff_pages) && !$is_staff ) {
        $key = 'rrp_notice_' . md5($_SERVER['REMOTE_ADDR']);
        set_transient($key, 'no_permission', 30);
        wp_redirect( home_url('/') );
        exit;
    }
}
add_action('template_redirect', 'rrp_restrict_pages');

// ============================================================
//  RRP — Popup cảnh báo trang chủ
// ============================================================
function rrp_maybe_show_popup() {
    if ( !is_front_page() && !is_home() ) return;
    $key    = 'rrp_notice_' . md5($_SERVER['REMOTE_ADDR']);
    $notice = get_transient($key);
    if ( $notice !== 'no_permission' ) return;
    delete_transient($key);
    $is_logged = is_user_logged_in();
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
          <?php if ( !$is_logged ): ?>
            Bạn cần đăng nhập để xem trang này.
          <?php else: ?>
            Tài khoản của bạn không có quyền truy cập trang này.
          <?php endif; ?>
        </p>
        <div style="display:flex;gap:10px;justify-content:center;">
          <?php if ( !$is_logged ): ?>
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
    <?php
}
add_action('wp_footer', 'rrp_maybe_show_popup');

// ============================================================
//  RRP — Lấy role hiện tại
// ============================================================
function rrp_get_current_role() {
    if ( !is_user_logged_in() ) return 'PATIENT';
    $roles = wp_get_current_user()->roles;
    if ( in_array('administrator', $roles) || in_array('rrp_admin', $roles) )
        return 'ADMIN';
    if ( in_array('researcher', $roles) )
        return 'RESEARCHER';
    if ( in_array('medical_staff', $roles) )
        return 'MEDICAL_STAFF';
    return 'PATIENT';
}

// ============================================================
//  RRP — Truyền thông tin user vào JavaScript
// ============================================================
function rrp_localize_user_data() {
    wp_localize_script('rrp-dashboard', 'RRP_USER', [
        'role'      => rrp_get_current_role(),
        'is_logged' => is_user_logged_in() ? 'true' : 'false',
        'username'  => is_user_logged_in() ? wp_get_current_user()->display_name : '',
        'user_id'   => is_user_logged_in() ? get_current_user_id() : 0,
    ]);
}
add_action('wp_enqueue_scripts', 'rrp_localize_user_data');

// ============================================================
//  RRP — Style header
// ============================================================
function rrp_custom_header_styles() { ?>
<style>
.site-header .ct-header-phone,
.site-header [class*="phone"],
.site-header [class*="emergency"] {
    display: none !important;
}
.site-header .ct-menu > li > a {
    color: #374151 !important;
    font-weight: 500 !important;
    font-size: 0.9rem !important;
}
.site-header .ct-menu > li > a:hover {
    color: #3b82f6 !important;
}
</style>
<?php }
add_action('wp_head', 'rrp_custom_header_styles');

add_filter('show_admin_bar', '__return_false');