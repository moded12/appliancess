<?php
// المسار: includes/upload.php

if (!function_exists('ensure_upload_dir')) {
  function ensure_upload_dir(): string {
    // نرفع داخل public/uploads ليكون متاحاً عبر الويب
    $dir = __DIR__ . '/../public/uploads';
    if (!is_dir($dir)) {
      @mkdir($dir, 0775, true);
    }
    return $dir;
  }
}

if (!function_exists('save_upload')) {
  // $type: 'image' | 'video'
  function save_upload(array $file, string $type = 'image'): array {
    // return ['ok'=>bool, 'path'=>string|null, 'error'=>string|null]
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
      return ['ok'=>false, 'path'=>null, 'error'=>'لم يتم رفع أي ملف.'];
    }

    $allowedImages = ['image/jpeg','image/png','image/webp','image/gif'];
    $allowedVideos = ['video/mp4','video/webm'];
    $maxImage = 5 * 1024 * 1024;   // 5MB
    $maxVideo = 50 * 1024 * 1024;  // 50MB

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']) ?: '';
    finfo_close($finfo);

    $size = (int)$file['size'];
    if ($type === 'image') {
      if (!in_array($mime, $allowedImages, true)) {
        return ['ok'=>false, 'path'=>null, 'error'=>'نوع الصورة غير مسموح.'];
      }
      if ($size > $maxImage) {
        return ['ok'=>false, 'path'=>null, 'error'=>'حجم الصورة كبير. الحد الأقصى 5MB'];
      }
    } else {
      if (!in_array($mime, $allowedVideos, true)) {
        return ['ok'=>false, 'path'=>null, 'error'=>'نوع الفيديو غير مسموح.'];
      }
      if ($size > $maxVideo) {
        return ['ok'=>false, 'path'=>null, 'error'=>'حجم الفيديو كبير. الحد الأقصى 50MB'];
      }
    }

    $extMap = [
      'image/jpeg' => '.jpg',
      'image/png'  => '.png',
      'image/webp' => '.webp',
      'image/gif'  => '.gif',
      'video/mp4'  => '.mp4',
      'video/webm' => '.webm',
    ];
    $ext = $extMap[$mime] ?? '';
    $dir = ensure_upload_dir();

    $name = bin2hex(random_bytes(8)) . $ext;
    $dest = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
      return ['ok'=>false, 'path'=>null, 'error'=>'تعذر حفظ الملف المرفوع.'];
    }

    // نُرجع مسارًا عامًا صالحًا للعرض من المتصفح: /public/uploads/filename
    $relative = '/public/uploads/' . $name;

    return ['ok'=>true, 'path'=>$relative, 'error'=>null];
  }
}