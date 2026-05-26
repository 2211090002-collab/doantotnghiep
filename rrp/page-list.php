<?php
/**
 * Template Name: RRP: Danh sách bài viết
 */
get_header(); ?>

<div style="max-width:1100px;margin:40px auto;padding:0 20px;">

  <h1 style="text-align:center;margin-bottom:8px;">📰 Tin tức y tế</h1>
  <p style="text-align:center;color:#6b7280;margin-bottom:32px;">
    Thông tin về bệnh hô hấp từ các cơ sở y tế uy tín
  </p>

  <div id="articles-grid"
    style="display:grid;grid-template-columns:repeat(3,1fr);
           gap:24px;margin-bottom:28px;">
    <?php for($i=0;$i<6;$i++): ?>
    <div style="background:#e5e7eb;border-radius:12px;height:320px;
                animation:pulse 1.5s infinite;"></div>
    <?php endfor; ?>
  </div>

  <div id="articles-pagination"
    style="display:flex;justify-content:center;gap:8px;margin-bottom:40px;">
  </div>

</div>

<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
</style>

<?php get_footer(); ?>