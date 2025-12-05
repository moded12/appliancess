<?php
// المسار: includes/auth.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function is_admin_logged_in(): bool {
    return !empty($_SESSION['admin']);
}

function require_admin(): void {
    if (!is_admin_logged_in()) {
        if (!headers_sent()) {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        }
        exit;
    }
}

function admin_login(string $id, string $email): void {
    $_SESSION['admin'] = true;
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_email'] = $email;
    session_regenerate_id(true);
}

function admin_logout(bool $fullDestroy = false): void {
    foreach (['admin','admin_id','admin_email'] as $k) {
        unset($_SESSION[$k]);
    }
    if ($fullDestroy) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000,
                $p['path'],$p['domain'],$p['secure'],$p['httponly']
            );
        }
        session_destroy();
    }
    session_regenerate_id(true);
}