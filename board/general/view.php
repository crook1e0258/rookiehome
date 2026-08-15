<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_site_public();

$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = db()->prepare(
    'SELECT posts.id, posts.member_id, posts.title, posts.content, posts.category, posts.created_at, posts.updated_at,
            COALESCE(members.nickname, posts.guest_name) AS author_name,
            boards.id AS board_id, boards.slug AS board_slug, boards.name AS board_name,
            boards.use_password, boards.board_password, boards.read_level, boards.comment_level
     FROM posts
     LEFT JOIN members ON members.id = posts.member_id
     JOIN boards ON boards.id = posts.board_id
     WHERE posts.id = ?'
);
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    die('글을 찾을 수 없습니다.');
}

require_board_level((int) $post['read_level'], '이 글을 볼 권한이 없습니다.');

$board = ['id' => $post['board_id'], 'slug' => $post['board_slug'], 'use_password' => $post['use_password'], 'board_password' => $post['board_password']];
if (!board_is_unlocked($board)) {
    redirect('list.php?board=' . urlencode($post['board_slug']));
}

$stmt = db()->prepare(
    "SELECT comments.id, comments.member_id, comments.guest_name, comments.content, comments.created_at,
            COALESCE(members.nickname, comments.guest_name) AS display_name
     FROM comments
     LEFT JOIN members ON members.id = comments.member_id
     WHERE comments.post_id = ?
     ORDER BY comments.id DESC"
);
$stmt->execute([$post_id]);
$comments = $stmt->fetchAll();

$user = current_user();
$is_guest_post = $post['member_id'] === null;
$can_manage_post = $is_guest_post ? true : ($user && ((int) $user['id'] === (int) $post['member_id'] || is_admin()));
$can_comment = current_level() >= (int) $post['comment_level'];

$page_title = $post['title'];
$__up_levels = 2;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="board-page">
    <article class="post-view">
        <div class="post-view-head">
            <p class="post-item-meta"><a href="list.php?board=<?php echo esc($post['board_slug']); ?>"><?php echo esc($post['board_name']); ?></a></p>
            <h1><?php if ($post['category'] !== '') { ?><span class="category-tag">[<?php echo esc($post['category']); ?>]</span> <?php } ?><?php echo esc($post['title']); ?></h1>
            <p class="post-item-meta">
                <?php echo esc($post['author_name']); ?> · <?php echo format_date($post['created_at']); ?>
                <?php if ($post['updated_at'] !== $post['created_at']) { ?>(수정됨)<?php } ?>
            </p>
        </div>
        <div class="post-content"><?php echo nl2br(esc($post['content'])); ?></div>
    </article>

    <div class="btn-row post-actions">
        <a href="list.php?board=<?php echo esc($post['board_slug']); ?>" class="btn-secondary">목록</a>
        <?php if ($can_manage_post) { ?>
        <a href="write.php?id=<?php echo (int) $post['id']; ?>" class="btn-secondary">수정</a>
        <?php if ($is_guest_post && !is_admin()) { ?>
        <form method="post" action="delete.php" onsubmit="return promptGuestPassword(this);" style="display:inline;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
            <input type="hidden" name="guest_password" value="">
            <button type="submit" class="btn-secondary btn-danger">삭제</button>
        </form>
        <?php } else { ?>
        <form method="post" action="delete.php" onsubmit="return confirm('정말 삭제하시겠습니까?');" style="display:inline;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
            <button type="submit" class="btn-secondary btn-danger">삭제</button>
        </form>
        <?php } ?>
        <?php } ?>
    </div>

    <section class="comment-section">
        <div class="comment-head">
            <h2>댓글 <?php echo count($comments); ?></h2>
            <?php if ($can_comment) { ?>
            <button type="submit" form="comment-form" class="btn-primary">등록</button>
            <?php } ?>
        </div>

        <?php if ($can_comment) { ?>
        <form method="post" action="comment.php" class="comment-form" id="comment-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
            <?php if (!$user) { ?>
            <div class="guest-fields">
                <input type="text" name="guest_name" placeholder="닉네임" maxlength="20" required>
                <input type="password" name="guest_password" placeholder="비밀번호 (삭제 시 필요, 4자 이상)" minlength="4" maxlength="72" required>
            </div>
            <?php } ?>
            <textarea name="content" rows="3" maxlength="1000" placeholder="댓글을 입력하세요" required></textarea>
        </form>
        <?php } elseif ($user) { ?>
        <p class="empty-state">이 게시판에는 댓글 권한이 없습니다.</p>
        <?php } else { ?>
        <p class="empty-state"><a href="../../login.php">로그인</a> 후 댓글을 작성할 수 있습니다.</p>
        <?php } ?>

        <ul class="comment-list">
            <?php foreach ($comments as $comment) { ?>
            <li class="comment-item">
                <span class="comment-meta"><?php echo esc($comment['display_name']); ?> · <?php echo format_date($comment['created_at']); ?></span>
                <p class="comment-content"><?php echo nl2br(esc($comment['content'])); ?></p>
                <?php if ($comment['member_id'] !== null) { ?>
                    <?php if ($user && ((int) $user['id'] === (int) $comment['member_id'] || is_admin())) { ?>
                    <form method="post" action="comment.php" class="comment-actions" onsubmit="return confirm('댓글을 삭제하시겠습니까?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                        <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                        <button type="submit" class="comment-delete">삭제</button>
                    </form>
                    <?php } ?>
                <?php } elseif (is_admin()) { ?>
                <form method="post" action="comment.php" class="comment-actions" onsubmit="return confirm('댓글을 삭제하시겠습니까?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                    <button type="submit" class="comment-delete">삭제</button>
                </form>
                <?php } else { ?>
                <form method="post" action="comment.php" class="comment-actions" onsubmit="return promptGuestPassword(this);">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="comment_id" value="<?php echo (int) $comment['id']; ?>">
                    <input type="hidden" name="post_id" value="<?php echo (int) $post['id']; ?>">
                    <input type="hidden" name="guest_password" value="">
                    <button type="submit" class="comment-delete">삭제</button>
                </form>
                <?php } ?>
            </li>
            <?php } ?>
        </ul>
    </section>
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
