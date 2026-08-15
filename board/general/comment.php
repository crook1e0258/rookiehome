<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_site_public();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../index.php');
}

csrf_check();

$user = current_user();
$action = $_POST['action'] ?? '';
$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

$stmt = db()->prepare(
    'SELECT posts.id, boards.id AS board_id, boards.slug AS board_slug, boards.use_password, boards.board_password, boards.comment_level
     FROM posts
     JOIN boards ON boards.id = posts.board_id
     WHERE posts.id = ?'
);
$stmt->execute([$post_id]);
$post_board = $stmt->fetch();

if (!$post_board) {
    http_response_code(404);
    die('글을 찾을 수 없습니다.');
}

$board = ['id' => $post_board['board_id'], 'slug' => $post_board['board_slug'], 'use_password' => $post_board['use_password'], 'board_password' => $post_board['board_password']];
if (!board_is_unlocked($board)) {
    redirect('list.php?board=' . urlencode($post_board['board_slug']));
}

if ($action === 'add') {
    require_board_level((int) $post_board['comment_level'], '이 게시판에는 댓글 권한이 없습니다.');

    $content = trim($_POST['content'] ?? '');
    $valid = $content !== '' && mb_strlen($content) <= 1000 && $post_id > 0;

    if ($valid && $user) {
        $stmt = db()->prepare('INSERT INTO comments (post_id, member_id, content) VALUES (?, ?, ?)');
        $stmt->execute([$post_id, $user['id'], $content]);
    } elseif ($valid) {
        $guest_name = trim($_POST['guest_name'] ?? '');
        $guest_password = $_POST['guest_password'] ?? '';

        if ($guest_name !== '' && mb_strlen($guest_name) <= 20 && strlen($guest_password) >= 4) {
            $stmt = db()->prepare('INSERT INTO comments (post_id, guest_name, guest_password, content) VALUES (?, ?, ?, ?)');
            $stmt->execute([$post_id, $guest_name, password_hash($guest_password, PASSWORD_DEFAULT), $content]);
        }
    }
} elseif ($action === 'delete') {
    $comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;

    $stmt = db()->prepare('SELECT id, member_id, guest_password FROM comments WHERE id = ?');
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();

    if ($comment) {
        $can_delete = false;

        if ($comment['member_id'] !== null) {
            $can_delete = $user && ((int) $user['id'] === (int) $comment['member_id'] || is_admin());
        } elseif ($user && is_admin()) {
            $can_delete = true;
        } else {
            $input_password = $_POST['guest_password'] ?? '';
            $can_delete = $input_password !== '' && password_verify($input_password, (string) $comment['guest_password']);
        }

        if ($can_delete) {
            $stmt = db()->prepare('DELETE FROM comments WHERE id = ?');
            $stmt->execute([$comment_id]);
        }
    }
}

redirect('view.php?id=' . $post_id);
