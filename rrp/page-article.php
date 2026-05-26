<?php
/**
 * Template Name: RRP: Chi tiết bài viết
 */
get_header(); ?>

<div style="max-width:860px;margin:40px auto;padding:0 20px;">

  <!-- Nút quay lại -->
  <a href="<?php echo home_url('/bai-viet/'); ?>"
    style="display:inline-flex;align-items:center;gap:6px;color:#6b7280;
           text-decoration:none;font-size:0.9rem;margin-bottom:24px;">
    ← Danh sách bài viết
  </a>

  <!-- Skeleton -->
  <div id="article-skeleton">
    <div style="height:36px;background:#e5e7eb;border-radius:6px;margin-bottom:16px;animation:pulse 1.5s infinite;"></div>
    <div style="height:20px;background:#e5e7eb;border-radius:6px;width:60%;margin-bottom:24px;animation:pulse 1.5s infinite;"></div>
    <div style="height:400px;background:#e5e7eb;border-radius:12px;margin-bottom:24px;animation:pulse 1.5s infinite;"></div>
  </div>

  <!-- Nội dung bài viết -->
  <div id="article-content" style="display:none;">
    <div style="margin-bottom:20px;">
      <div id="art-source" style="font-size:0.85rem;color:#3b82f6;font-weight:700;text-transform:uppercase;margin-bottom:8px;"></div>
      <h1 id="art-title" style="font-size:2rem;font-weight:700;color:#1f2937;line-height:1.3;margin:0 0 12px;"></h1>
      <div style="color:#9ca3af;font-size:0.9rem;" id="art-date"></div>
    </div>

    <!-- Thumbnail -->
    <div id="art-thumb-wrap" style="margin-bottom:28px;display:none;">
      <img id="art-thumb" style="width:100%;border-radius:12px;" src="" alt="">
    </div>

    <!-- Nội dung đầy đủ -->
    <div id="art-body" 
         style="font-size:1.05rem; line-height:1.85; color:#374151;">
    </div>

    <!-- Link gốc -->
    <div style="margin-top:40px;padding:20px;background:#eff6ff;border-radius:10px;text-align:center;">
      <p style="margin:0 0 10px;color:#6b7280;">Nguồn bài viết gốc:</p>
      <a id="art-original" href="#" target="_blank" 
         style="color:#2563eb;font-weight:600;word-break:break-all;"></a>
    </div>
  </div>

  <!-- Lỗi -->
  <div id="article-error" style="display:none;text-align:center;padding:80px 20px;">
    <div style="font-size:3.5rem;margin-bottom:16px;">😕</div>
    <p style="color:#6b7280;font-size:1.1rem;">Không tìm thấy bài viết hoặc bài viết đang được cập nhật.</p>
    <a href="<?php echo home_url('/bai-viet/'); ?>" 
       style="color:#3b82f6;text-decoration:none;margin-top:20px;display:inline-block;">
      ← Quay lại danh sách bài viết
    </a>
  </div>

</div>

<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
#art-body img { max-width:100%; height:auto; border-radius:10px; margin:20px 0; }
#art-body p   { margin-bottom:18px; }
#art-body h2, #art-body h3 { margin:28px 0 14px; color:#1f2937; }
</style>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const API = typeof RRP_CONFIG !== 'undefined' 
              ? RRP_CONFIG.api_url 
              : 'http://localhost:5000';
    
    const params = new URLSearchParams(window.location.search);
    const articleId = params.get('id');

    const skeleton = document.getElementById('article-skeleton');
    const content  = document.getElementById('article-content');
    const error    = document.getElementById('article-error');

    if (!articleId) {
        skeleton.style.display = 'none';
        error.style.display = 'block';
        return;
    }

    try {
        const res = await fetch(`${API}/api/articles/${articleId}`);
        
        if (!res.ok) throw new Error('Không tìm thấy bài viết');

        const data = await res.json();

        if (data.error) throw new Error(data.error);

        skeleton.style.display = 'none';
        content.style.display  = 'block';

        // Điền thông tin cơ bản
        document.getElementById('art-source').textContent = (data.source || 'RRP').toUpperCase();
        document.getElementById('art-title').textContent  = data.title || 'Không có tiêu đề';
        document.getElementById('art-date').textContent   = data.published_at 
            ? data.published_at.substring(0, 10) : 'Không rõ ngày';

        // Hiển thị THUMBNAIL
        if (data.thumbnail) {
            document.getElementById('art-thumb').src = data.thumbnail;
            document.getElementById('art-thumb-wrap').style.display = 'block';
        }

        // ==================== HIỂN THỊ NỘI DUNG ĐẦY ĐỦ & SẠCH ====================
        const bodyEl = document.getElementById('art-body');
        
        let finalContent = '';

        if (data.content && data.content.length > 200) {
            finalContent = data.content;
        } else if (data.description) {
            finalContent = data.description;
        } else {
            finalContent = 'Nội dung bài viết đang được cập nhật...';
        }

        // Làm sạch và format nội dung
        finalContent = finalContent
            .replace(/<article[^>]*>/gi, '')
            .replace(/<\/article>/gi, '')
            .replace(/\n+/g, '</p><p>');

        bodyEl.innerHTML = '<p>' + finalContent + '</p>';

        // Link gốc
        if (data.url) {
            document.getElementById('art-original').href = data.url;
            document.getElementById('art-original').textContent = data.url;
        }

        document.title = (data.title || 'Chi tiết bài viết') + ' — RRP';

    } catch (e) {
        console.error("Lỗi load bài viết:", e);
        skeleton.style.display = 'none';
        error.style.display = 'block';
    }
});
</script>

<?php get_footer(); ?>