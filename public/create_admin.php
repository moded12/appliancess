<?php require __DIR__.'/../includes/db.php';
$email='admin@local'; $pass='admin123';
$hash=password_hash($pass,PASSWORD_DEFAULT);
$pdo->prepare('INSERT IGNORE INTO users (email,password_hash,role) VALUES (:e,:p,"admin")')->execute([':e'=>$email, ':p'=>$hash]);
echo "تم إنشاء الأدمن: $email / $pass . احذف هذا الملف فورًا!";
