<?php
// includes/header.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/Auth.php';

$currentUser = Auth::user();
$currentRole = Auth::role();
$schoolName = get_setting('school_name', APP_INSTITUTION);
$appTitle = get_setting('app_title', APP_NAME);

$roleBadges = [
    'admin' => ['label' => 'Administrator', 'class' => 'bg-purple-100 text-purple-700 border-purple-200'],
    'guru' => ['label' => 'Guru Pengajar', 'class' => 'bg-blue-100 text-blue-700 border-blue-200'],
    'operator' => ['label' => 'Proktor / Operator', 'class' => 'bg-amber-100 text-amber-700 border-amber-200'],
];
$badge = $roleBadges[$currentRole] ?? ['label' => ucfirst($currentRole), 'class' => 'bg-slate-100 text-slate-700'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= e($appTitle) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js for Analisis Soal -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
            <!-- Left: Toggle & Brand -->
            <div class="flex items-center space-x-3">
                <button id="sidebarToggle" class="lg:hidden p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 focus:outline-none">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <a href="<?= BASE_URL ?>/" class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 flex items-center justify-center text-white shadow-md shadow-indigo-200">
                        <i class="fa-solid fa-graduation-cap text-lg"></i>
                    </div>
                    <div>
                        <div class="font-extrabold text-slate-900 leading-tight text-base tracking-tight"><?= e($appTitle) ?></div>
                        <div class="text-xs text-slate-500 font-medium"><?= e($schoolName) ?></div>
                    </div>
                </a>
            </div>

            <!-- Right: Role Pill & User Dropdown -->
            <div class="flex items-center space-x-3 sm:space-x-4">
                <span class="hidden sm:inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?= $badge['class'] ?>">
                    <span class="w-1.5 h-1.5 rounded-full bg-current mr-1.5"></span>
                    <?= $badge['label'] ?>
                </span>

                <div class="relative">
                    <div class="flex items-center space-x-3 pl-3 border-l border-slate-200">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-bold text-slate-800"><?= e($currentUser['full_name'] ?? 'Pengguna') ?></div>
                            <div class="text-xs text-slate-400 font-mono">@<?= e($currentUser['username'] ?? '') ?></div>
                        </div>
                        <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center text-sm border border-indigo-200 shadow-sm">
                            <?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?>
                        </div>
                        <a href="<?= BASE_URL ?>/logout.php" title="Keluar" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" onclick="return confirm('Apakah Anda yakin ingin logout?')">
                            <i class="fa-solid fa-power-off text-base"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto">
                <?php render_flash(); ?>
