<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_site_public();

$slug = trim($_GET['board'] ?? '');
if ($slug === '') {
    redirect('../../index.php');
}

$stmt = db()->prepare("SELECT id, slug, name, description, posts_per_page, use_password, board_password, list_level, write_level FROM boards WHERE slug = ? AND board_type = 'profile'");
$stmt->execute([$slug]);
$board = $stmt->fetch();

if (!$board) {
    http_response_code(404);
    die('게시판을 찾을 수 없습니다.');
}

require_board_level((int) $board['list_level'], '이 게시판을 볼 권한이 없습니다.');

$user = current_user();

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
        'SELECT posts.id, posts.member_id, posts.profile_image, posts.name_en, posts.name_ko, posts.tags, posts.content
         FROM posts
         WHERE posts.board_id = ?
         ORDER BY posts.id ASC
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $board['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $profiles = $stmt->fetchAll();
}

$page_title = $board['name'];
$__up_levels = 2;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="board-page profile-page">
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

    <?php if (empty($profiles)) { ?>
    <p class="empty-state">아직 등록된 프로필이 없습니다.</p>
    <?php } else { ?>
    <div class="profile-grid">
        <?php foreach ($profiles as $i => $p) { ?>
        <?php if ($i > 0) { ?>
        <div class="pair-divider" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span>
        </div>
        <?php } ?>
        <?php
            $p_is_guest = $p['member_id'] === null;
            $p_can_manage = $p_is_guest ? true : ($user && ((int) $user['id'] === (int) $p['member_id'] || is_admin()));
            $p_tags = $p['tags'] !== '' && $p['tags'] !== null ? explode('|', $p['tags']) : [];
        ?>
        <section class="profile-card">
            <?php if ($p['profile_image']) { ?>
            <img class="avatar" src="../../<?php echo esc($p['profile_image']); ?>" alt="<?php echo esc($p['name_ko']); ?>">
            <?php } else { ?>
            <span class="avatar avatar-placeholder"><?php echo esc(mb_substr((string) $p['name_ko'], 0, 1)); ?></span>
            <?php } ?>
            <div class="profile-name">
                <span class="name-en"><?php echo esc($p['name_en']); ?></span>
                <span class="name-ko"><?php echo esc($p['name_ko']); ?></span>
            </div>
            <?php if (!empty($p_tags)) { ?>
            <div class="profile-tags">
                <?php foreach ($p_tags as $tag) { ?>
                <span class="tag-pill">#<?php echo esc($tag); ?></span>
                <?php } ?>
            </div>
            <?php } ?>
            <?php if ($p['content'] !== '') { ?>
            <div class="quote-box">
                <p class="quote-text"><?php echo esc($p['content']); ?></p>
            </div>
            <?php } ?>
            <?php if ($p_can_manage) { ?>
            <div class="profile-card-actions">
                <a href="write.php?id=<?php echo (int) $p['id']; ?>">수정</a>
                <?php if ($p_is_guest && !is_admin()) { ?>
                <form method="post" action="delete.php" onsubmit="return promptGuestPassword(this);">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                    <input type="hidden" name="guest_password" value="">
                    <button type="submit">삭제</button>
                </form>
                <?php } else { ?>
                <form method="post" action="delete.php" onsubmit="return confirm('삭제하시겠습니까?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                    <button type="submit">삭제</button>
                </form>
                <?php } ?>
            </div>
            <?php } ?>
        </section>
        <?php } ?>
    </div>

    <?php if ($total_pages > 1) { ?>
    <nav class="pagination">
        <?php for ($pg = 1; $pg <= $total_pages; $pg++) { ?>
        <a href="list.php?board=<?php echo esc($board['slug']); ?>&page=<?php echo $pg; ?>" class="<?php echo $pg === $page ? 'active' : ''; ?>"><?php echo $pg; ?></a>
        <?php } ?>
    </nav>
    <?php } ?>
    <?php } ?>

    <?php if (current_level() >= (int) $board['write_level']) { ?>
    <div class="board-write-row">
        <a href="write.php?board=<?php echo esc($board['slug']); ?>" class="btn-primary">프로필 추가</a>
    </div>
    <?php } ?>
    <?php } ?>
</div>

<script>
function promptGuestPassword(form) {
    if (!confirm('삭제하시겠습니까?')) return false;
    var pw = prompt('비밀번호를 입력하세요 (4자 이상)');
    if (pw === null || pw.length < 4) return false;
    form.querySelector('input[name="guest_password"]').value = pw;
    return true;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
