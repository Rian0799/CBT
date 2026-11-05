<?php
// app/helpers.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function render_flash(): void {
    $flash = get_flash();
    if ($flash) {
        $colors = [
            'success' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'error'   => 'bg-rose-50 text-rose-800 border-rose-200',
            'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
            'info'    => 'bg-blue-50 text-blue-800 border-blue-200',
        ];
        $icons = [
            'success' => 'fa-circle-check text-emerald-500',
            'error'   => 'fa-circle-exclamation text-rose-500',
            'warning' => 'fa-triangle-exclamation text-amber-500',
            'info'    => 'fa-circle-info text-blue-500',
        ];
        $colorClass = $colors[$flash['type']] ?? $colors['info'];
        $iconClass = $icons[$flash['type']] ?? $icons['info'];

        echo "<div class='p-4 mb-5 border rounded-xl flex items-center space-x-3 text-sm font-medium shadow-sm $colorClass'>
                <i class='fa-solid $iconClass text-lg'></i>
                <div class='flex-1'>" . e($flash['message']) . "</div>
              </div>";
    }
}

function get_setting(string $key, string $default = ''): string {
    static $settingsCache = null;
    if ($settingsCache === null) {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $settingsCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (Exception $e) {
            $settingsCache = [];
        }
    }
    return $settingsCache[$key] ?? $default;
}

function generate_random_token(int $length = 6): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Tanpa I, O, 0, 1 untuk menghindari ambigu
    $token = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, $max)];
    }
    return $token;
}

function format_datetime_id(string $datetime): string {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $d = date('d', $timestamp);
    $m = $bulan[(int)date('m', $timestamp)];
    $y = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    return "$d $m $y, $time WIB";
}

function format_duration_minutes(int $seconds): string {
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d', $m, $s);
}
