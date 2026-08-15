<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_site_public();

$user = current_user();

$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit = $post_id > 0;
$content = '';
$guest_name = '';
$name_en = '';
$name_ko = '';
$tags_input = '';
$new_profile_image = null;
$errors = [];
$board = null;
$is_guest_post = false;
$post = null;

if ($is_edit) {
    $stmt = db()->prepare(
        "SELECT posts.id, posts.member_id, posts.guest_password, posts.content,
                posts.profile_image, posts.name_en, posts.name_ko, posts.tags,
                boards.id AS board_id, boards.slug, boards.name, boards.use_password, boards.board_password
         FROM posts
         JOIN boards ON boards.id = posts.board_id
         WHERE posts.id = ? AND boards.board_type = 'profile'"
    );
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        http_response_code(404);
        die('프로필을 찾을 수 없습니다.');
    }

    if ($post['member_id'] !== null) {
        if (!$user || ((int) $user['id'] !== (int) $post['member_id'] && !is_admin())) {
            http_response_code(403);
            die('수정 권한이 없습니다.');
        }
    } else {
        $is_guest_post = true;
    }

    $content = $post['content'];
    $name_en = (string) $post['name_en'];
    $name_ko = (string) $post['name_ko'];
    $tags_input = str_replace('|', ', ', (string) $post['tags']);
    $board = [
        'id' => $post['board_id'], 'slug' => $post['slug'], 'name' => $post['name'],
        'use_password' => $post['use_password'], 'board_password' => $post['board_password'],
    ];
} else {
    $slug = trim($_GET['board'] ?? '');
    $stmt = db()->prepare("SELECT id, slug, name, use_password, board_password, write_level FROM boards WHERE slug = ? AND board_type = 'profile'");
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

    $name_en = trim($_POST['name_en'] ?? '');
    $name_ko = trim($_POST['name_ko'] ?? '');
    $tags_input = trim($_POST['tags'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($name_en === '' || mb_strlen($name_en) > 30) {
        $errors[] = '영문 이름을 1~30자로 입력해 주세요.';
    }
    if ($name_ko === '' || mb_strlen($name_ko) > 30) {
        $errors[] = '한글 이름을 1~30자로 입력해 주세요.';
    }
    if (mb_strlen($content) > 80) {
        $errors[] = '소개 문구는 80자 이내로 입력해 주세요.';
    }
    $tags = implode('|', parse_tag_list($tags_input));
    $title = $name_en;

    $new_profile_image = handle_uploaded_image('profile_image', 'profile', $errors);

    if (empty($errors)) {
        if ($is_edit) {
            if ($new_profile_image !== null) {
                if (!empty($post['profile_image']) && is_file(__DIR__ . '/../../' . $post['profile_image'])) {
                    @unlink(__DIR__ . '/../../' . $post['profile_image']);
                }
                $stmt = db()->prepare('UPDATE posts SET title = ?, content = ?, name_en = ?, name_ko = ?, tags = ?, profile_image = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$title, $content, $name_en, $name_ko, $tags, $new_profile_image, $post_id]);
            } else {
                $stmt = db()->prepare('UPDATE posts SET title = ?, content = ?, name_en = ?, name_ko = ?, tags = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$title, $content, $name_en, $name_ko, $tags, $post_id]);
            }
        } elseif ($user) {
            $stmt = db()->prepare('INSERT INTO posts (board_id, member_id, title, content, name_en, name_ko, tags, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$board['id'], $user['id'], $title, $content, $name_en, $name_ko, $tags, $new_profile_image]);
        } else {
            $stmt = db()->prepare('INSERT INTO posts (board_id, guest_name, guest_password, title, content, name_en, name_ko, tags, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$board['id'], $guest_name, password_hash($guest_password_input, PASSWORD_DEFAULT), $title, $content, $name_en, $name_ko, $tags, $new_profile_image]);
        }
        redirect('list.php?board=' . urlencode($board['slug']));
    }
}

$page_title = $is_edit ? '프로필 수정' : '프로필 추가';
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
        <div class="field-row">
            <label for="profile_image">프로필 사진</label>
            <?php if ($is_edit && !empty($post['profile_image'])) { ?>
            <img src="../../<?php echo esc($post['profile_image']); ?>" alt="" class="settings-image-preview" style="border-radius:50%;width:96px;height:96px;">
            <?php } ?>
            <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
        <div class="field-row">
            <label for="name_en">영문 이름</label>
            <input type="text" id="name_en" name="name_en" maxlength="30" placeholder="예: ROOKIE" value="<?php echo esc($name_en); ?>" required autofocus>
        </div>
        <div class="field-row">
            <label for="name_ko">한글 이름</label>
            <input type="text" id="name_ko" name="name_ko" maxlength="30" placeholder="예: 루키" value="<?php echo esc($name_ko); ?>" required>
        </div>
        <div class="field-row">
            <label for="tags">해시태그 (쉼표로 구분, 선택)</label>
            <input type="text" id="tags" name="tags" maxlength="100" placeholder="예: 크림소다, 사이버, 천사" value="<?php echo esc($tags_input); ?>">
        </div>
        <div class="field-row">
            <label for="content">소개 문구 (선택)</label>
            <input type="text" id="content" name="content" maxlength="80" placeholder="예: 클릭해 주셔서 감사합니다." value="<?php echo esc($content); ?>">
        </div>
        <div class="btn-row">
            <button type="submit" class="btn-primary"><?php echo $is_edit ? '수정하기' : '등록하기'; ?></button>
            <a href="list.php?board=<?php echo esc($board['slug']); ?>" class="btn-secondary">취소</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
