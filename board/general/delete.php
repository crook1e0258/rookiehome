<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_site_public();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../index.php');
}

csrf_check();

$user = current_user();
$post_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

$stmt = db()->prepare(
    'SELECT posts.id, posts.member_id, posts.guest_password, boards.id AS board_id, boards.slug AS board_slug,
            boards.use_password, boards.board_password
     FROM posts
     JOIN boards ON boards.id = posts.board_id
     WHERE posts.id = ?'
);
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    die('글을 찾을 수 없습니다.');
}

if ($post['member_id'] !== null) {
    if (!$user || ((int) $user['id'] !== (int) $post['member_id'] && !is_admin())) {
        http_response_code(403);
        die('삭제 권한이 없습니다.');
    }
} elseif (!($user && is_admin())) {
    $input_password = $_POST['guest_password'] ?? '';
    if ($input_password === '' || !password_verify($input_password, (string) $post['guest_password'])) {
        http_response_code(403);
        die('비밀번호가 올바르지 않습니다.');
    }
}

$board = ['id' => $post['board_id'], 'slug' => $post['board_slug'], 'use_password' => $post['use_password'], 'board_password' => $post['board_password']];
if (!board_is_unlocked($board)) {
    redirect('list.php?board=' . urlencode($post['board_slug']));
}

$stmt = db()->prepare('DELETE FROM posts WHERE id = ?');
$stmt->execute([$post_id]);

redirect('list.php?board=' . urlencode($post['board_slug']));
