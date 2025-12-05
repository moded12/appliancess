<?php
// Path: public/partials/shop_footer.php
// UTF-8 (no BOM)
// Clean, production-ready footer partial using footer.css
// Upload this file to public/partials/shop_footer.php

if (!isset($config)) {
    $config = require __DIR__ . '/../../config.php';
}
$base = rtrim($config['base_url'] ?? '', '/');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('e')) {
    function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('asset_url')) {
    function asset_url(string $path): string {
        global $base;
        $p = trim($path);
        if ($p==='') return '';
        if (preg_match('~^https?://~i',$p)) return $p;
        if (str_starts_with($p,'/uploads/')) return $base . '/public' . $p;
        if (str_starts_with($p,'/public/'))  return $base . $p;
        if ($p[0]==='/') return $base . $p;
        return $base . '/' . ltrim($p,'/');
    }
}
?>
<footer id="siteFooter" class="site-footer" role="contentinfo" aria-label="Footer">
  <div class="container">
    <div class="row gy-4 py-3">
      <!-- Brand -->
      <div class="col-12 col-md-4 footer-col">
        <div class="footer-brand">
          <h4><?= e($config['app_name'] ?? 'المتجر') ?></h4>
          <p class="small text-muted">متجر إلكتروني للأجهزة المنزلية — عروض مميزة، خدمة عملاء سريعة، وشحن موثوق.</p>

          <div class="footer-socials" role="list">
            <a class="social-icon" aria-label="Facebook" href="#" role="listitem"><i class="bi bi-facebook"></i></a>
            <a class="social-icon" aria-label="WhatsApp" href="#" role="listitem"><i class="bi bi-whatsapp"></i></a>
            <a class="social-icon" aria-label="Instagram" href="#" role="listitem"><i class="bi bi-instagram"></i></a>
            <a class="social-icon" aria-label="YouTube" href="#" role="listitem"><i class="bi bi-youtube"></i></a>
          </div>
        </div>
      </div>

      <!-- Quick links -->
      <div class="col-6 col-md-2 footer-col">
        <h6>روابط سريعة</h6>
        <ul>
          <li><a href="<?= e($base) ?>/public/index.php">المنتجات</a></li>
          <li><a href="<?= e($base) ?>/public/cart.php">عرض السلة</a></li>
          <li><a href="<?= e($base) ?>/public/checkout.php">إتمام الشراء</a></li>
        </ul>
      </div>

      <!-- Help -->
      <div class="col-6 col-md-2 footer-col">
        <h6>مساعدة</h6>
        <ul>
          <li><a href="<?= e($base) ?>/public/terms.php">الشروط</a></li>
          <li><a href="<?= e($base) ?>/public/privacy.php">الخصوصية</a></li>
          <li><a href="<?= e($base) ?>/public/returns.php">سياسة الإرجاع</a></li>
        </ul>
      </div>

      <!-- Newsletter + payments -->
      <div class="col-12 col-md-4 footer-col">
        <h6>اشترك بالنشرة</h6>
        <p class="small text-muted">احصل على أحدث العروض مباشرة في بريدك الإلكتروني.</p>

        <form id="footer-newsletter" class="footer-news" method="post" action="<?= e($base) ?>/public/newsletter_subscribe.php" novalidate>
          <input type="email" name="email" placeholder="بريدك الإلكتروني" required class="form-control form-control-sm" aria-label="Email">
          <button class="btn btn-primary btn-sm" type="submit" aria-label="اشتراك">اشتراك</button>
        </form>
        <div id="newsletter-msg" class="mt-2 small text-success d-none" role="status">تم الاشتراك. شكراً!</div>
        <div id="newsletter-err" class="mt-2 small text-danger d-none" role="alert"></div>

        <div class="payment-icons" aria-hidden="false">
          <?php
            $payments = [
              ['file'=>'visa.svg','alt'=>'Visa'],
              ['file'=>'mastercard.svg','alt'=>'Mastercard'],
              ['file'=>'paypal.svg','alt'=>'PayPal'],
              ['file'=>'cash.svg','alt'=>'نقداً']
            ];
            $paymentsDir = __DIR__ . '/../assets/img/payments/';
            foreach ($payments as $p):
              $fs = $paymentsDir . $p['file'];
              $web = asset_url('/assets/img/payments/' . $p['file']);
              if (is_file($fs) && filesize($fs) > 0):
          ?>
              <img src="<?= e($web) ?>" alt="<?= e($p['alt']) ?>" class="payment-icon" loading="lazy">
          <?php else: ?>
              <span class="payment-fallback" title="<?= e($p['alt']) ?>">
                <!-- small inline svg card -->
                <svg width="36" height="24" viewBox="0 0 36 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                  <rect width="36" height="24" rx="3" fill="#eef2ff"/>
                  <rect x="3" y="5" width="30" height="6" rx="1" fill="#dbeafe"/>
                  <rect x="3" y="14" width="18" height="4" rx="1" fill="#c7d2fe"/>
                </svg>
                <span class="small text-muted d-none d-md-inline" style="margin-left:6px;"><?= e($p['alt']) ?></span>
              </span>
          <?php
              endif;
            endforeach;
          ?>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="site-copyright">&copy; <?= date('Y') ?> <?= e($config['app_name'] ?? 'المتجر') ?>. جميع الحقوق محفوظة.</div>
      <div class="small text-muted">مشروع محلي — الأسعار لا تشمل الضريبة إلا إن ذكر خلاف ذلك.</div>
    </div>
  </div>

  <button id="backToTop" class="back-to-top" aria-label="العودة للأعلى" title="العودة للأعلى">
    <i class="bi bi-arrow-up" aria-hidden="true"></i>
  </button>
</footer>

<script>
(function(){
  // Newsletter AJAX (graceful fallback if server side handles POST)
  const form = document.getElementById('footer-newsletter');
  const msg = document.getElementById('newsletter-msg');
  const err = document.getElementById('newsletter-err');
  if (form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      msg.classList.add('d-none'); err.classList.add('d-none');
      const fd = new FormData(form);
      const email = (fd.get('email')||'').toString().trim();
      if (!/^\S+@\S+\.\S+$/.test(email)) {
        err.textContent = 'أدخل بريد إلكتروني صالحاً.'; err.classList.remove('d-none'); return;
      }
      fetch(form.action, { method:'POST', body: fd, credentials:'same-origin' })
        .then(r => r.json ? r.json() : r.text())
        .then(j => {
          if (j && j.ok) { msg.classList.remove('d-none'); form.reset(); }
          else { err.textContent = j && j.error ? j.error : 'حدث خطأ، حاول لاحقاً.'; err.classList.remove('d-none'); }
        }).catch(()=>{ msg.classList.remove('d-none'); form.reset(); });
    });
  }

  // back to top button
  const btn = document.getElementById('backToTop');
  if (btn){
    const show = () => { if (window.scrollY > 220) btn.classList.add('visible'); else btn.classList.remove('visible'); };
    window.addEventListener('scroll', show);
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    btn.style.zIndex = 1400;
    show();
  }

  // reserve bottom space when mobile buy bar present
  const mobileBar = document.querySelector('.mobile-buy-bar');
  if (mobileBar){
    const h = Math.ceil(mobileBar.getBoundingClientRect().height);
    document.body.style.paddingBottom = (h + 24) + 'px';
    if (btn) btn.style.bottom = (h + 30) + 'px';
  }
})();
</script>