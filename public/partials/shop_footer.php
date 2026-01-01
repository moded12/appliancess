<?php
// المسار: public/partials/shop_footer.php

if (!isset($config)) { $config = require __DIR__ . '/../../config.php'; }
$base   = rtrim($config['base_url'] ?? '', '/');     // مثال: https://www.shneler.com/xx
$public = $base . '/public';

if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$year = date('Y');
?>
<!-- ===== Amazon-like Footer ===== -->

<!-- شريط الرجوع للأعلى (مثل أمازون) -->
<div class="footer-backbar" id="footerBackbar" role="button" aria-label="العودة إلى أعلى الصفحة">
  العودة إلى أعلى
</div>

<footer class="site-footer" role="contentinfo">

  <div class="footer-top">
    <div class="container">

      <div class="footer-grid">

        <!-- Column 1: Brand -->
        <div class="footer-col footer-brand">
          <div class="brand-name">MyStore.shop</div>
          <p>تسوق الأجهزة المنزلية والإلكترونيات بأفضل العروض، مع دعم سريع وتجربة شراء سهلة.</p>

          <div class="footer-socials" aria-label="روابط التواصل">
            <a class="social-icon" href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
            <a class="social-icon" href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
            <a class="social-icon" href="#" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
            <a class="social-icon" href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
          </div>
        </div>

        <!-- Column 2: Customer Service -->
        <div class="footer-col">
          <h6>خدمة العملاء</h6>
          <ul class="footer-links">
            <li><a href="<?= e($public) ?>/contact.php">اتصل بنا</a></li>
            <li><a href="<?= e($public) ?>/shipping.php">الشحن والتوصيل</a></li>
            <li><a href="<?= e($public) ?>/returns.php">الإرجاع والاستبدال</a></li>
            <li><a href="<?= e($public) ?>/faq.php">الأسئلة الشائعة</a></li>
          </ul>
        </div>

        <!-- Column 3: Your Account -->
        <div class="footer-col">
          <h6>حسابك</h6>
          <ul class="footer-links">
            <li><a href="<?= e($public) ?>/account.php">حسابي</a></li>
            <li><a href="<?= e($public) ?>/orders.php">طلباتي</a></li>
            <li><a href="<?= e($public) ?>/cart.php">سلة التسوق</a></li>
            <li><a href="<?= e($public) ?>/index.php?featured=1">منتجات مميزة</a></li>
          </ul>
        </div>

        <!-- Column 4: Subscribe / Payments -->
        <div class="footer-col">
          <h6>اشترك بالعروض</h6>
          <div class="footer-news" role="form" aria-label="اشترك بالنشرة البريدية">
            <input type="email" class="form-control" placeholder="بريدك الإلكتروني">
            <button class="btn" type="button">اشتراك</button>
          </div>

          <div style="margin-top:14px; font-weight:900; color:#fff;">طرق الدفع</div>
          <div class="payment-icons" aria-label="وسائل الدفع">
            <!-- إذا عندك صور لوسائل الدفع ضعها هنا -->
            <!-- مثال:
            <img class="payment-icon" src="<?= e($public) ?>/assets/img/payments/visa.svg" alt="Visa">
            -->
            <span class="badge-pill" style="background:rgba(255,255,255,.10); color:#fff; border:1px solid rgba(255,255,255,.10);">
              <i class="bi bi-credit-card"></i> Visa
            </span>
            <span class="badge-pill" style="background:rgba(255,255,255,.10); color:#fff; border:1px solid rgba(255,255,255,.10);">
              <i class="bi bi-credit-card-2-front"></i> MasterCard
            </span>
            <span class="badge-pill" style="background:rgba(255,255,255,.10); color:#fff; border:1px solid rgba(255,255,255,.10);">
              <i class="bi bi-cash-coin"></i> نقدًا
            </span>
          </div>
        </div>

      </div><!-- /footer-grid -->

    </div><!-- /container -->
  </div><!-- /footer-top -->

  <div class="footer-bottom">
    <div class="container">
      <div class="footer-bottom-row">
        <div>© <?= (int)$year ?> MyStore.shop — جميع الحقوق محفوظة</div>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
          <a href="<?= e($public) ?>/privacy.php">سياسة الخصوصية</a>
          <a href="<?= e($public) ?>/terms.php">الشروط والأحكام</a>
          <a href="<?= e($public) ?>/about.php">من نحن</a>
        </div>
      </div>
    </div>
  </div>

</footer>

<!-- زر عائم (اختياري) -->
<button class="back-to-top" id="backToTopBtn" aria-label="العودة للأعلى">
  <i class="bi bi-arrow-up"></i>
</button>

<!-- Bootstrap Bundle (مهم لعمل Offcanvas في الهيدر) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  // Back to top bar + floating button
  const backbar = document.getElementById('footerBackbar');
  const btn = document.getElementById('backToTopBtn');

  function scrollTopSmooth(){
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  if (backbar) backbar.addEventListener('click', scrollTopSmooth);
  if (btn) btn.addEventListener('click', scrollTopSmooth);

  function onScroll(){
    const show = window.scrollY > 600;
    if (btn) btn.classList.toggle('visible', show);
  }
  window.addEventListener('scroll', onScroll, { passive:true });
  onScroll();
})();
</script>

</body>
</html>
