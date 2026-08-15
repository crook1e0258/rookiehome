<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_site_public();

$slug = trim($_GET['board'] ?? '');
if ($slug === '') {
    redirect('../../index.php');
}

$stmt = db()->prepare("SELECT id, slug, name, description, posts_per_page, use_password, board_password, list_level, write_level FROM boards WHERE slug = ? AND board_type = 'gallery'");
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
        'SELECT posts.id, posts.member_id, posts.profile_image AS image, posts.content AS description, posts.created_at
         FROM posts
         WHERE posts.board_id = ?
         ORDER BY posts.id DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $board['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $images = $stmt->fetchAll();
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

    <?php if (empty($images)) { ?>
    <p class="empty-state">아직 등록된 이미지가 없습니다.</p>
    <?php } else { ?>
    <div class="gallery-grid">
        <?php foreach ($images as $img) { ?>
        <?php
            $img_is_guest = $img['member_id'] === null;
            $img_can_manage = $img_is_guest ? true : ($user && ((int) $user['id'] === (int) $img['member_id'] || is_admin()));
            $img_src = resolve_image_src($img['image'], '../../');
        ?>
        <button type="button" class="gallery-thumb"
                data-image="<?php echo esc($img_src); ?>"
                data-id="<?php echo (int) $img['id']; ?>"
                data-can-manage="<?php echo $img_can_manage ? '1' : '0'; ?>"
                data-guest="<?php echo $img_is_guest ? '1' : '0'; ?>"
                data-description="<?php echo esc($img['description']); ?>"
                data-date="<?php echo esc(format_date($img['created_at'])); ?>">
            <img src="<?php echo esc($img_src); ?>" alt="">
        </button>
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
        <a href="write.php?board=<?php echo esc($board['slug']); ?>" class="btn-primary">이미지 추가</a>
    </div>
    <?php } ?>

    <div class="lightbox-overlay" id="lightbox" hidden>
        <button type="button" class="lightbox-close" id="lightbox-close" aria-label="닫기">✕</button>
        <img src="" alt="" id="lightbox-image" class="lightbox-image">
        <div class="lightbox-meta">
            <p class="lightbox-description" id="lightbox-description"></p>
            <span class="lightbox-date" id="lightbox-date"></span>
        </div>
        <div class="lightbox-actions" id="lightbox-actions">
            <a href="write.php" id="lightbox-edit">수정</a>
            <form method="post" action="delete.php" id="lightbox-delete-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="" id="lightbox-delete-id">
                <input type="hidden" name="guest_password" value="" id="lightbox-guest-password">
                <button type="submit">삭제</button>
            </form>
        </div>
    </div>
    <?php } ?>
</div>

<script>
var IS_ADMIN = <?php echo is_admin() ? 'true' : 'false'; ?>;
var lightbox = document.getElementById('lightbox');
var lightboxImage = document.getElementById('lightbox-image');
var lightboxActions = document.getElementById('lightbox-actions');
var lightboxEdit = document.getElementById('lightbox-edit');
var lightboxDeleteForm = document.getElementById('lightbox-delete-form');
var lightboxDeleteId = document.getElementById('lightbox-delete-id');
var lightboxDescription = document.getElementById('lightbox-description');
var lightboxDate = document.getElementById('lightbox-date');

document.querySelectorAll('.gallery-thumb').forEach(function (btn) {
    btn.addEventListener('click', function () {
        lightboxImage.src = btn.dataset.image;
        lightboxDescription.textContent = btn.dataset.description || '';
        lightboxDescription.hidden = !btn.dataset.description;
        lightboxDate.textContent = btn.dataset.date || '';
        var canManage = btn.dataset.canManage === '1';
        lightboxActions.style.display = canManage ? 'flex' : 'none';
        if (canManage) {
            lightboxEdit.href = 'write.php?id=' + btn.dataset.id;
            lightboxDeleteId.value = btn.dataset.id;
            lightboxDeleteForm.dataset.guest = btn.dataset.guest;
        }
        lightbox.hidden = false;
    });
});

if (lightbox) {
    document.getElementById('lightbox-close').addEventListener('click', function () {
        lightbox.hidden = true;
    });
    lightbox.addEventListener('click', function (e) {
        if (e.target === lightbox) lightbox.hidden = true;
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !lightbox.hidden) lightbox.hidden = true;
    });
    lightboxDeleteForm.addEventListener('submit', function (e) {
        var isGuestImage = this.dataset.guest === '1' && !IS_ADMIN;
        if (isGuestImage) {
            if (!confirm('삭제하시겠습니까?')) { e.preventDefault(); return; }
            var pw = prompt('비밀번호를 입력하세요 (4자 이상)');
            if (pw === null || pw.length < 4) { e.preventDefault(); return; }
            document.getElementById('lightbox-guest-password').value = pw;
        } else {
            if (!confirm('삭제하시겠습니까?')) e.preventDefault();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
