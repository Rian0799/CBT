<?php
// ujian/login.php - Redirect ke halaman login terpadu
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

// Jika siswa sudah login, langsung ke konfirmasi
if (Auth::checkStudent()) {
    redirect(BASE_URL . '/ujian/konfirmasi.php');
}

// Redirect ke login terpadu dengan tab siswa
redirect(BASE_URL . '/login.php#siswa');
