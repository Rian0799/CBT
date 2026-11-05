<?php
// logout.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/Auth.php';

Auth::logout();
set_flash('info', 'Anda telah berhasil keluar dari sistem.');
redirect(BASE_URL . '/index.php');
