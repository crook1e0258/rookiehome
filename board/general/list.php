<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_site_public();

$slug = trim($_GET['board'] ?? '');
if ($slug === '') {
    redirect('../../index.php');
}

$stmt = db()->prepare("SELECT id, slug, name, description, posts_per_page, use_password, board_password, use_categories, categories, list_level, write_level FROM boards WHERE slug = ? AND board_type = 'list'");
$stmt->execute([$slug]);
$board = $stmt->fetch();

if (!$board) {
    http_response_code(404);
    die('게시판을 찾을 수 없습니다.');
}

require_board_level((int) $board['list_level'], '이 게시판을 볼 권한이 없습니다.');

$unlock_error = '';
if (!board_is_unlocked($board) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $input_password = $_POST['board_password'] ?? '';
    if ($input_password !== '' && password_verify($input_password, (string) $board['board_password'])) {
        $_SESSION['unlocked_boards'][$board['id']] = true;
        redirect('list.php?board=' . urlencode($board['slug']));
    }
    $unlock_error = '비밀번호가 올바르지 않습니다.';
}

$locked = !board_is_unlocked($board);

if (!$locked) {
    $per_page = (int) $board['posts_per_page'];
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $offset = ($page - 1) * $per_page;

    $stmt = db()->prepare('SELECT COUNT(*) FROM posts WHERE board_id = ?');
    $stmt->execute([$board['id']]);
    $total = (int) $stmt->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $per_page));

    $stmt = db()->prepare(
        'SELECT posts.id, posts.title, posts.category, posts.created_at, COALESCE(members.nickname, posts.guest_name) AS author_name
         FROM posts
         LEFT JOIN members ON members.id = posts.member_id
         WHERE posts.board_id = ?
         ORDER BY posts.id DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $board['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll();
}

$page_title = $board['name'];
$__up_levels = 2;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="board-page">
    <div class="board-title-row">
        <h1 class="board-name"><?php echo esc($board['name']); ?></h1>
    </div>

    <?php if ($locked) { ?>
    <form method="post" class="write-form board-password-form">
        <?php echo csrf_field(); ?>
        <?php if ($unlock_error) { ?>
        <div class="alert-box"><p><?php echo esc($unlock_error); ?></p></div>
        <?php } ?>
        <div class="field-row">
            <label for="board_password">비밀번호가 필요한 게시판입니다</label>
            <input type="password" id="board_password" name="board_password" required autofocus>
        </div>
        <button type="submit" class="btn-primary btn-block">입장하기</button>
    </form>
    <?php } else { ?>

    <?php if (empty($posts)) { ?>
    <p class="empty-state">아직 등록된 글이 없습니다.</p>
    <?php } else { ?>
    <ul class="post-list">
        <?php foreach ($posts as $post) { ?>
        <li class="post-item">
            <a href="view.php?id=<?php echo (int) $post['id']; ?>" class="post-item-title"><?php if ($post['category'] !== '') { ?><span class="category-tag">[<?php echo esc($post['category']); ?>]</span> <?php } ?><?php echo esc($post['title']); ?></a>
            <span class="post-item-author"><?php echo esc($post['author_name']); ?></span>
            <span class="post-item-date"><?php echo format_date($post['created_at']); ?></span>
        </li>
        <?php } ?>
    </ul>

    <?php if ($total_pages > 1) { ?>
    <nav class="pagination">
        <?php for ($p = 1; $p <= $total_pages; $p++) { ?>
        <a href="list.php?board=<?php echo esc($board['slug']); ?>&page=<?php echo $p; ?>" class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
        <?php } ?>
    </nav>
    <?php } ?>
    <?php } ?>

    <?php if (current_level() >= (int) $board['write_level']) { ?>
    <div class="board-write-row">
        <a href="write.php?board=<?php echo esc($board['slug']); ?>" class="btn-primary">글쓰기</a>
    </div>
    <?php } ?>
    <?php } ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
