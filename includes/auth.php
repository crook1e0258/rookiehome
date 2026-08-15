<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    static $user = false; // false = 아직 조회 안 함, null = 비로그인
    if ($user === false) {
        if (empty($_SESSION['member_id'])) {
            $user = null;
        } else {
            $stmt = db()->prepare('SELECT id, username, nickname, email, is_admin, level FROM members WHERE id = ?');
            $stmt->execute([$_SESSION['member_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && (int) $user['is_admin'] === 1;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ((int) $user['is_admin'] !== 1) {
        http_response_code(403);
        die('관리자만 접근할 수 있습니다.');
    }
    return $user;
}

function login_member(array $member, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['member_id'] = $member['id'];

    if ($remember) {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), time() + 60 * 60 * 24 * 30, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
}

function logout_member(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
