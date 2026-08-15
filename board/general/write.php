<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_site_public();

$user = current_user();

$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit = $post_id > 0;
$title = '';
$content = '';
$category = '';
$guest_name = '';
$errors = [];
$board = null;
$is_guest_post = false;
$post = null;

if ($is_edit) {
    $stmt = db()->prepare(
        "SELECT posts.id, posts.member_id, posts.guest_password, posts.title, posts.content, posts.category,
                boards.id AS board_id, boards.slug, boards.name, boards.use_password, boards.board_password,
                boards.use_categories, boards.categories
         FROM posts
         JOIN boards ON boards.id = posts.board_id
         WHERE posts.id = ? AND boards.board_type = 'list'"
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
            die('수정 권한이 없습니다.');
        }
    } else {
        $is_guest_post = true;
    }

    $title = $post['title'];
    $content = $post['content'];
    $category = $post['category'];
    $board = [
        'id' => $post['board_id'], 'slug' => $post['slug'], 'name' => $post['name'],
        'use_password' => $post['use_password'], 'board_password' => $post['board_password'],
        'use_categories' => $post['use_categories'], 'categories' => $post['categories'],
    ];
} else {
    $slug = trim($_GET['board'] ?? '');
    $stmt = db()->prepare("SELECT id, slug, name, use_password, board_password, use_categories, categories, write_level FROM boards WHERE slug = ? AND board_type = 'list'");
    $stmt->execute([$slug]);
    $board = $stmt->fetch();

    if (!$board) {
        http_response_code(404);
        die('게시판을 찾을 수 없습니다.');
    }

    require_board_level((int) $board['write_level'], '이 게시판에는 글쓰기 권한이 없습니다.');
}

if (!board_is_unlocked($board)) {
    redirect('list.php?board=' . urlencode($board['slug']));
}

$available_categories = board_categories($board);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? '');

    if ($title === '' || mb_strlen($title) > 200) {
        $errors[] = '제목을 1~200자로 입력해 주세요.';
    }
    if ($content === '') {
        $errors[] = '내용을 입력해 주세요.';
    }
    if (!empty($available_categories) && !in_array($category, $available_categories, true)) {
        $errors[] = '분류를 선택해 주세요.';
    }
    if (empty($available_categories)) {
        $category = '';
    }

    $guest_password_input = '';
    if ($is_edit && $is_guest_post && !is_admin()) {
        $guest_password_input = $_POST['guest_password'] ?? '';
        if ($guest_password_input === '' || !password_verify($guest_password_input, (string) $post['guest_password'])) {
            $errors[] = '비밀번호가 올바르지 않습니다.';
        }
    } elseif (!$is_edit && !$user) {
        $guest_name = trim($_POST['guest_name'] ?? '');
        $guest_password_input = $_POST['guest_password'] ?? '';
        if ($guest_name === '' || mb_strlen($guest_name) > 20) {
            $errors[] = '닉네임을 1~20자로 입력해 주세요.';
        }
        if (strlen($guest_password_input) < 4) {
            $errors[] = '비밀번호는 4자 이상이어야 합니다.';
        }
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = db()->prepare('UPDATE posts SET title = ?, content = ?, category = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$title, $content, $category, $post_id]);
            redirect('view.php?id=' . $post_id);
        } elseif ($user) {
            $stmt = db()->prepare('INSERT INTO posts (board_id, member_id, title, content, category) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$board['id'], $user['id'], $title, $content, $category]);
            redirect('view.php?id=' . db()->lastInsertId());
        } else {
            $stmt = db()->prepare('INSERT INTO posts (board_id, guest_name, guest_password, title, content, category) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$board['id'], $guest_name, password_hash($guest_password_input, PASSWORD_DEFAULT), $title, $content, $category]);
            redirect('view.php?id=' . db()->lastInsertId());
        }
    }
}

$page_title = $is_edit ? '글 수정' : '글쓰기';
$__up_levels = 2;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="board-page">
    <h1><?php echo esc($board['name']); ?> · <?php echo $is_edit ? '글 수정' : '글쓰기'; ?></h1>

    <?php if ($errors) { ?>
    <div class="alert-box">
        <?php foreach ($errors as $err) { ?>
        <p><?php echo esc($err); ?></p>
        <?php } ?>
    </div>
    <?php } ?>

    <form method="post" action="write.php<?php echo $is_edit ? '?id=' . $post_id : '?board=' . esc($board['slug']); ?>" class="write-form">
        <?php echo csrf_field(); ?>
        <?php if (!$is_edit && !$user) { ?>
        <div class="guest-fields">
            <input type="text" name="guest_name" placeholder="닉네임" maxlength="20" value="<?php echo esc($guest_name); ?>" required>
            <input type="password" name="guest_password" placeholder="비밀번호 (수정/삭제 시 필요, 4자 이상)" minlength="4" maxlength="72" required>
        </div>
        <?php } ?>
        <?php if ($is_edit && $is_guest_post && !is_admin()) { ?>
        <div class="field-row">
            <label for="guest_password">비밀번호 확인</label>
            <input type="password" id="guest_password" name="guest_password" maxlength="72" required>
        </div>
        <?php } ?>
        <?php if (!empty($available_categories)) { ?>
        <div class="field-row">
            <label for="category">분류</label>
            <select id="category" name="category" required>
                <option value="">분류 선택</option>
                <?php foreach ($available_categories as $c) { ?>
                <option value="<?php echo esc($c); ?>" <?php echo $c === $category ? 'selected' : ''; ?>><?php echo esc($c); ?></option>
                <?php } ?>
            </select>
        </div>
        <?php } ?>
        <div class="field-row">
            <label for="title">제목</label>
            <input type="text" id="title" name="title" value="<?php echo esc($title); ?>" maxlength="200" required autofocus>
        </div>
        <div class="field-row">
            <label for="content">내용</label>
            <textarea id="content" name="content" rows="14" required><?php echo esc($content); ?></textarea>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn-primary"><?php echo $is_edit ? '수정하기' : '등록하기'; ?></button>
            <a href="<?php echo $is_edit ? 'view.php?id=' . $post_id : 'list.php?board=' . esc($board['slug']); ?>" class="btn-secondary">취소</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
