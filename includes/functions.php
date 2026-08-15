<?php

function esc(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('잘못된 요청입니다. 다시 시도해 주세요.');
    }
}

function get_setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $stmt = db()->query('SELECT setting_key, setting_value FROM settings');
        foreach ($stmt->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function format_date(string $datetime): string
{
    return date('Y-m-d H:i', strtotime($datetime));
}

function board_is_unlocked(array $board): bool
{
    if ((int) $board['use_password'] !== 1) {
        return true;
    }
    return !empty($_SESSION['unlocked_boards'][$board['id']]);
}

function require_site_public(): void
{
    if (get_setting('site_public', '0') === '1' || is_admin()) {
        return;
    }
    $has_members = (int) db()->query('SELECT COUNT(*) FROM members')->fetchColumn() > 0;
    if (!$has_members) {
        return;
    }
    http_response_code(503);
    die('현재 사이트가 비공개 상태입니다.');
}

function registration_is_open(): bool
{
    if (get_setting('registration_open', '0') === '1') {
        return true;
    }
    return (int) db()->query('SELECT COUNT(*) FROM members')->fetchColumn() === 0;
}

function current_level(): int
{
    $user = current_user();
    if (!$user) {
        return 1;
    }
    if ((int) $user['is_admin'] === 1) {
        return 10;
    }
    return (int) $user['level'];
}

function require_board_level(int $required, string $message = '이 게시판에 대한 권한이 없습니다.'): void
{
    if (current_level() < $required) {
        http_response_code(403);
        die($message);
    }
}

function theme_defaults(): array
{
    return [
        'theme_text' => '#1a1a1a',
        'theme_bg' => '#ececea',
        'theme_primary' => '#6f809a',
        'theme_muted' => '#8a8a86',

        'theme_input_bg' => '#ffffff',
        'theme_input_text' => '#1a1a1a',
        'theme_input_border' => '#dcdcd8',

        'theme_box_bg' => '#ffffff',
        'theme_box_text' => '#1a1a1a',
        'theme_box_border' => '#dcdcd8',

        'theme_btn_bg' => '#ffffff',
        'theme_btn_text' => '#1a1a1a',
        'theme_btn_border' => '#dcdcd8',
        'theme_btn_bg_hover' => '#ffffff',
        'theme_btn_text_hover' => '#6f809a',
        'theme_btn_border_hover' => '#6f809a',

        'theme_point_bg' => '#6f809a',
        'theme_point_text' => '#ffffff',
        'theme_point_border' => '#6f809a',
        'theme_point_bg_hover' => '#56657a',
        'theme_point_text_hover' => '#ffffff',
        'theme_point_border_hover' => '#56657a',

        'theme_danger_bg' => '#ffffff',
        'theme_danger_text' => '#c0564f',
        'theme_danger_border' => '#dcdcd8',
        'theme_danger_bg_hover' => '#ffffff',
        'theme_danger_text_hover' => '#c0564f',
        'theme_danger_border_hover' => '#c0564f',
    ];
}

function theme_style_tag(): string
{
    $var_map = [
        'theme_text' => '--text',
        'theme_bg' => '--bg',
        'theme_primary' => '--primary',
        'theme_muted' => '--muted',

        'theme_input_bg' => '--input-bg',
        'theme_input_text' => '--input-text',
        'theme_input_border' => '--input-border',

        'theme_box_bg' => '--box-bg',
        'theme_box_text' => '--box-text',
        'theme_box_border' => '--box-border',

        'theme_btn_bg' => '--btn-secondary-bg',
        'theme_btn_text' => '--btn-secondary-text',
        'theme_btn_border' => '--btn-secondary-border',
        'theme_btn_bg_hover' => '--btn-secondary-bg-hover',
        'theme_btn_text_hover' => '--btn-secondary-text-hover',
        'theme_btn_border_hover' => '--btn-secondary-border-hover',

        'theme_point_bg' => '--btn-primary-bg',
        'theme_point_text' => '--btn-primary-text',
        'theme_point_border' => '--btn-primary-border',
        'theme_point_bg_hover' => '--btn-primary-bg-hover',
        'theme_point_text_hover' => '--btn-primary-text-hover',
        'theme_point_border_hover' => '--btn-primary-border-hover',

        'theme_danger_bg' => '--btn-danger-bg',
        'theme_danger_text' => '--btn-danger-text',
        'theme_danger_border' => '--btn-danger-border',
        'theme_danger_bg_hover' => '--btn-danger-bg-hover',
        'theme_danger_text_hover' => '--btn-danger-text-hover',
        'theme_danger_border_hover' => '--btn-danger-border-hover',
    ];

    $css = ':root{';
    foreach (theme_defaults() as $key => $default) {
        $value = get_setting($key, $default);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            $value = $default;
        }
        $css .= $var_map[$key] . ':' . $value . ';';
    }
    $css .= '}';

    return '<style>' . $css . '</style>';
}

function board_categories(array $board): array
{
    if ((int) $board['use_categories'] !== 1 || trim($board['categories']) === '') {
        return [];
    }
    return array_filter(array_map('trim', explode('|', $board['categories'])), fn($c) => $c !== '');
}

function resolve_image_src(?string $path, string $prefix = ''): string
{
    $path = (string) $path;
    if ($path === '') {
        return '';
    }
    return preg_match('#^https?://#i', $path) ? $path : $prefix . $path;
}

function parse_tag_list(string $raw): array
{
    $parts = array_filter(array_map('trim', explode(',', $raw)), fn($t) => $t !== '');
    return array_slice(array_values($parts), 0, 8);
}

function handle_uploaded_image(string $field, string $prefix, array &$errors): ?string
{
    $allowed_ext = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $max_size = 8 * 1024 * 1024;

    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = '이미지 업로드에 실패했습니다.';
        return null;
    }
    if ($file['size'] > $max_size) {
        $errors[] = '이미지 용량은 8MB 이하여야 합니다.';
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $image_info = @getimagesize($file['tmp_name']);

    if (!isset($allowed_ext[$ext]) || $image_info === false || $image_info['mime'] !== $allowed_ext[$ext]) {
        $errors[] = 'jpg, png, gif, webp 형식의 이미지만 업로드할 수 있습니다.';
        return null;
    }

    $upload_dir = __DIR__ . '/../uploads';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $filename)) {
        $errors[] = '이미지 저장에 실패했습니다.';
        return null;
    }

    compress_image_file($upload_dir . '/' . $filename, $ext, $image_info[0], $image_info[1]);

    return 'uploads/' . $filename;
}

function compress_image_file(string $path, string $ext, int $width, int $height, int $max_dimension = 1600, int $jpeg_quality = 82): void
{
    // 애니메이션이 깨지는 gif는 손대지 않고, GD를 못 쓰면 원본을 그대로 둔다
    if ($ext === 'gif' || !function_exists('imagecreatetruecolor') || $width <= 0 || $height <= 0) {
        return;
    }

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $src = @imagecreatefromjpeg($path);
            break;
        case 'png':
            $src = @imagecreatefrompng($path);
            break;
        case 'webp':
            $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
            break;
        default:
            $src = false;
    }

    if (!$src) {
        return;
    }

    $ratio = min(1, $max_dimension / max($width, $height));
    if ($ratio < 1) {
        $new_w = max(1, (int) round($width * $ratio));
        $new_h = max(1, (int) round($height * $ratio));
        $resized = imagecreatetruecolor($new_w, $new_h);
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
        imagedestroy($src);
        $src = $resized;
    }

    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($src, $path, $jpeg_quality);
            break;
        case 'png':
            imagepng($src, $path, 6);
            break;
        case 'webp':
            if (function_exists('imagewebp')) {
                imagewebp($src, $path, $jpeg_quality);
            }
            break;
    }

    imagedestroy($src);
}
