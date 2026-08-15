<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_site_public();

$user = current_user();

$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit = $post_id > 0;
$guest_name = '';
$image_url = '';
$description = '';
$errors = [];
$board = null;
$is_guest_post = false;
$post = null;

if ($is_edit) {
    $stmt = db()->prepare(
        "SELECT posts.id, posts.member_id, posts.guest_password, posts.profile_image, posts.content,
                boards.id AS board_id, boards.slug, boards.name, boards.use_password, boards.board_password
         FROM posts
         JOIN boards ON boards.id = posts.board_id
         WHERE posts.id = ? AND boards.board_type = 'gallery'"
    );
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        http_response_code(404);
        die('이미지를 찾을 수 없습니다.');
    }

    if ($post['member_id'] !== null) {
        if (!$user || ((int) $user['id'] !== (int) $post['member_id'] && !is_admin())) {
            http_response_code(403);
            die('수정 권한이 없습니다.');
        }
    } else {
        $is_guest_post = true;
    }

    $description = (string) $post['content'];
    $board = [
        'id' => $post['board_id'], 'slug' => $post['slug'], 'name' => $post['name'],
        'use_password' => $post['use_password'], 'board_password' => $post['board_password'],
    ];
} else {
    $slug = trim($_GET['board'] ?? '');
    $stmt = db()->prepare("SELECT id, slug, name, use_password, board_password, write_level FROM boards WHERE slug = ? AND board_type = 'gallery'");
    $stmt->execute([$slug]);
    $board = $stmt->fetch();

    if (!$board) {
        http_response_code(404);
        die('게시판을 찾을 수 없습니다.');
    }

    require_board_level((int) $board['write_level'], '이 게시판에는 등록 권한이 없습니다.');
}

if (!board_is_unlocked($board)) {
    redirect('list.php?board=' . urlencode($board['slug']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

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

    $image_url = trim($_POST['image_url'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($image_url !== '') {
        if (mb_strlen($image_url) > 500) {
            $errors[] = '이미지 링크는 500자 이내여야 합니다.';
        } elseif (!filter_var($image_url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $image_url)) {
            $errors[] = 'http:// 또는 https://로 시작하는 올바른 이미지 링크를 입력해 주세요.';
        }
    }
    if (mb_strlen($description) > 200) {
        $errors[] = '설명은 200자 이내로 입력해 주세요.';
    }

    // 업로드된 파일이 있으면 그걸 우선 사용, 없으면 입력한 링크를 사용
    $uploaded_image = handle_uploaded_image('image', 'gallery', $errors);
    $new_image = $uploaded_image !== null ? $uploaded_image : ($image_url !== '' ? $image_url : null);

    if (!$is_edit && $new_image === null && empty($errors)) {
        $errors[] = '이미지를 업로드하거나 링크를 입력해 주세요.';
    }

    if (empty($errors)) {
        if ($is_edit) {
            if ($new_image !== null && $new_image !== $post['profile_image']) {
                if (!empty($post['profile_image']) && is_file(__DIR__ . '/../../' . $post['profile_image'])) {
                    @unlink(__DIR__ . '/../../' . $post['profile_image']);
                }
                $stmt = db()->prepare('UPDATE posts SET profile_image = ?, content = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$new_image, $description, $post_id]);
            } else {
                $stmt = db()->prepare('UPDATE posts SET content = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$description, $post_id]);
            }
        } elseif ($user) {
            $stmt = db()->prepare("INSERT INTO posts (board_id, member_id, title, content, profile_image) VALUES (?, ?, '', ?, ?)");
            $stmt->execute([$board['id'], $user['id'], $description, $new_image]);
        } else {
            $stmt = db()->prepare("INSERT INTO posts (board_id, guest_name, guest_password, title, content, profile_image) VALUES (?, ?, ?, '', ?, ?)");
            $stmt->execute([$board['id'], $guest_name, password_hash($guest_password_input, PASSWORD_DEFAULT), $description, $new_image]);
        }
        redirect('list.php?board=' . urlencode($board['slug']));
    }
}

$page_title = $is_edit ? '이미지 수정' : '이미지 추가';
$__up_levels = 2;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="board-page">
    <h1><?php echo esc($board['name']); ?> · <?php echo $is_edit ? '수정' : '추가'; ?></h1>

    <?php if ($errors) { ?>
    <div class="alert-box">
        <?php foreach ($errors as $err) { ?>
        <p><?php echo esc($err); ?></p>
        <?php } ?>
    </div>
    <?php } ?>

    <form method="post" action="write.php<?php echo $is_edit ? '?id=' . $post_id : '?board=' . esc($board['slug']); ?>" class="write-form" enctype="multipart/form-data">
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
        <?php if ($is_edit && !empty($post['profile_image'])) { ?>
        <div class="field-row">
            <label>현재 이미지</label>
            <img src="<?php echo esc(resolve_image_src($post['profile_image'], '../../')); ?>" alt="" class="settings-image-preview">
        </div>
        <?php } ?>
        <div class="field-row">
            <label for="image">이미지 업로드</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
        <div class="field-row">
            <label for="image_url">또는 이미지 링크 (URL) — 업로드와 링크 중 하나만 입력</label>
            <input type="text" id="image_url" name="image_url" maxlength="500" placeholder="https://example.com/image.jpg" value="<?php echo esc($image_url); ?>">
        </div>
        <div class="field-row">
            <label for="description">설명 (선택, 최대 200자)</label>
            <textarea id="description" name="description" rows="3" maxlength="200"><?php echo esc($description); ?></textarea>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn-primary"><?php echo $is_edit ? '수정하기' : '등록하기'; ?></button>
            <a href="list.php?board=<?php echo esc($board['slug']); ?>" class="btn-secondary">취소</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
