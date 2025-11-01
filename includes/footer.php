</div>

<footer class="site-footer mt-5">
  <div class="container py-4">
    <div class="row g-3 align-items-center">
      <div class="col-md-4 text-center text-md-start">
        <div class="fw-bold small">© <?= date('Y') ?> <?= e($config['app_name']) ?></div>
        <div class="text-muted small">AL AJOURI — Quality Home Appliances</div>
      </div>
      <div class="col-md-4 text-center">
        <a class="link-footer" href="<?= $base ?>/index.php">الرئيسية</a>
        <span class="mx-2">·</span>
        <a class="link-footer" href="<?= $base ?>/cart.php">السلة</a>
        <span class="mx-2">·</span>
        <a class="link-footer" href="<?= $base ?>/checkout.php">إتمام الشراء</a>
      </div>
      <div class="col-md-4 text-center text-md-end">
        <span class="logo-aj"><b>AL</b> <span>AJOURI</span></span>
      </div>
    </div>
  </div>
</footer>

<link rel="preconnect" href="https://cdn.jsdelivr.net" />
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= $base ?>/assets/app.js"></script>
</body></html>
