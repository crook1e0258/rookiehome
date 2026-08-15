<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$board_types = ['list' => '일반게시판', 'profile' => '프로필형', 'gallery' => '갤러리형'];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $board_id = isset($_POST['board_id']) ? (int) $_POST['board_id'] : 0;

        $total_boards = (int) db()->query('SELECT COUNT(*) FROM boards')->fetchColumn();
        if ($total_boards <= 1) {
            $message = '게시판이 최소 1개는 있어야 합니다.';
        } else {
            $stmt = db()->prepare('DELETE FROM boards WHERE id = ?');
            $stmt->execute([$board_id]);
            $message = '게시판을 삭제했습니다. (해당 게시판의 글도 함께 삭제됩니다)';
        }
    }
}

$boards = db()->query(
    'SELECT boards.id, boards.slug, boards.name, boards.description, boards.board_type, boards.posts_per_page,
            boards.use_password, boards.use_categories, boards.categories,
            boards.list_level, boards.read_level, boards.write_level, boards.comment_level,
            (SELECT COUNT(*) FROM posts WHERE posts.board_id = boards.id) AS post_count
     FROM boards
     ORDER BY boards.sort_order ASC, boards.id ASC'
)->fetchAll();

$page_title = '게시판 관리';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="section-head">
    <h1>게시판 관리</h1>
    <a href="board_form.php" class="btn-primary">게시판 추가</a>
</div>
<p class="page-desc">사이트에서 사용할 게시판을 추가하고 관리합니다.</p>

<?php if ($message) { ?>
<div class="alert-box"><p><?php echo esc($message); ?></p></div>
<?php } ?>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>이름</th>
            <th>경로</th>
            <th>유형</th>
            <th>설명</th>
            <th>비밀번호</th>
            <th>분류</th>
            <th>권한(목록/읽기/쓰기/댓글)</th>
            <th>글 수</th>
            <th>관리</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($boards as $b) { ?>
        <tr>
            <td><?php echo esc($b['name']); ?></td>
            <td>/board/<?php echo in_array($b['board_type'], ['profile', 'gallery'], true) ? $b['board_type'] : 'general'; ?>/list.php?board=<?php echo esc($b['slug']); ?></td>
            <td><?php echo esc($board_types[$b['board_type']] ?? $b['board_type']); ?></td>
            <td><?php echo esc($b['description']); ?></td>
            <td><?php echo (int) $b['use_password'] === 1 ? '사용' : '-'; ?></td>
            <td><?php echo (int) $b['use_categories'] === 1 ? esc($b['categories']) : '-'; ?></td>
            <td><?php echo (int) $b['list_level']; ?>/<?php echo (int) $b['read_level']; ?>/<?php echo (int) $b['write_level']; ?>/<?php echo (int) $b['comment_level']; ?></td>
            <td><?php echo (int) $b['post_count']; ?></td>
            <td>
                <a href="board_form.php?id=<?php echo (int) $b['id']; ?>" class="btn-secondary">수정</a>
                <form method="post" action="boards.php" onsubmit="return confirm('이 게시판과 안에 있는 글을 모두 삭제하시겠습니까?');" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="board_id" value="<?php echo (int) $b['id']; ?>">
                    <button type="submit" class="btn-secondary btn-danger">삭제</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
