<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $stmt = db()->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $message = '게시글을 삭제했습니다.';
}

$posts = db()->query(
    'SELECT posts.id, posts.title, posts.created_at, COALESCE(members.nickname, posts.guest_name) AS author_name,
            boards.name AS board_name, boards.slug AS board_slug, boards.board_type
     FROM posts
     LEFT JOIN members ON members.id = posts.member_id
     JOIN boards ON boards.id = posts.board_id
     ORDER BY posts.id DESC'
)->fetchAll();

$page_title = '게시글 관리';
require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>게시글 관리</h1>

<?php if ($message) { ?>
<div class="alert-box"><p><?php echo esc($message); ?></p></div>
<?php } ?>

<table class="admin-table">
    <thead>
        <tr>
            <th>게시판</th>
            <th>제목</th>
            <th>작성자</th>
            <th>작성일</th>
            <th>관리</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($posts as $p) { ?>
        <tr>
            <td><?php echo esc($p['board_name']); ?></td>
            <td>
                <?php if ($p['board_type'] === 'profile') { ?>
                <a href="../board/profile/list.php?board=<?php echo esc($p['board_slug']); ?>"><?php echo esc($p['title']); ?></a>
                <?php } elseif ($p['board_type'] === 'gallery') { ?>
                <a href="../board/gallery/list.php?board=<?php echo esc($p['board_slug']); ?>">[이미지 #<?php echo (int) $p['id']; ?>]</a>
                <?php } else { ?>
                <a href="../board/general/view.php?id=<?php echo (int) $p['id']; ?>"><?php echo esc($p['title']); ?></a>
                <?php } ?>
            </td>
            <td><?php echo esc($p['author_name']); ?></td>
            <td><?php echo format_date($p['created_at']); ?></td>
            <td>
                <form method="post" action="posts.php" onsubmit="return confirm('이 게시글을 삭제하시겠습니까?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="post_id" value="<?php echo (int) $p['id']; ?>">
                    <button type="submit" class="btn-secondary btn-danger">삭제</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
