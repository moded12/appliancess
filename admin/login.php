// المسار: admin/login.php

<?php
session_start();

// بيانات الأدمن - يفضل استدعاؤها من قاعدة بيانات في التطبيق الحقيقي
$admin_email = 'admin@yourdomain.com';
$admin_pass  = 'admin123';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    // تحقق من صحة البريد وكلمة المرور
    if ($email === $admin_email && $pass === $admin_pass) {
        $_SESSION['admin'] = $admin_email;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'بيانات الدخول غير صحيحة!';
    }
}
?>

<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تسجيل دخول الأدمن</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5" style="max-width:400px;">
    <h1 class="mb-4 text-center">دخول الأدمن</h1>
    <?php if ($error): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="glass p-3 rounded shadow">
        <div class="mb-3">
            <label class="form-label">البريد الإلكتروني</label>
            <input type="email" name="email" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">كلمة المرور</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-success w-100">دخول</button>
    </form>
</div>
</body>
</html>