<?php
/**
 * Admin Yetkilendirme
 */

session_name(SESSION_NAME);
session_start();

// Giriş kontrolü
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_user']);
}

// Giriş gerektir
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

// Giriş yap
function login($username, $password) {
    $user = db()->fetch(
        "SELECT * FROM admin_kullanicilar WHERE kullanici_adi = :username",
        ['username' => $username]
    );

    if ($user && password_verify($password, $user['sifre_hash'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_user'] = $user['kullanici_adi'];
        return true;
    }

    return false;
}

// Çıkış yap
function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Şifre değiştir
function changePassword($userId, $newPassword) {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->update('admin_kullanicilar', ['sifre_hash' => $hash], 'id = :id', ['id' => $userId]);
}
