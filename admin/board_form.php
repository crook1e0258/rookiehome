<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$board_types = ['list' => '일반게시판', 'profile' => '프로필형', 'gallery' => '갤러리형'];

function validate_categories_input(string $raw, array &$errors): string
{
    $parts = array_filter(array_map('trim', explode('|', $raw)), fn($c) => $c !== '');
    $parts = array_values($parts);

    if (empty($parts)) {
        $errors[] = '분류를 최소 1개 이상 입력해 주세요. (예: 질문|답변)';
        return '';
    }
    foreach ($parts as $c) {
        if (mb_strlen($c) > 20) {
            $errors[] = '분류 이름은 각각 20자 이내로 입력해 주세요.';
            return '';
        }
        if (strpos($c, '#') === 0) {
            $errors[] = '분류 이름 첫 글자로 #은 입력하지 마세요.';
            return '';
        }
    }
    if (count($parts) > 20) {
        $errors[] = '분류는 최대 20개까지 등록할 수 있습니다.';
        return '';
    }

    return implode('|', $parts);
}

$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit = $edit_id > 0;
$board = null;

if ($is_edit) {
    $stmt = db()->prepare(
        'SELECT id, slug, name, description, board_type, posts_per_page, use_password, use_categories, categories,
                list_level, read_level, write_level, comment_level
         FROM boards WHERE id = ?'
    );
    $stmt->execute([$edit_id]);
    $board = $stmt->fetch();

    if (!$board) {
        http_response_code(404);
        die('게시판을 찾을 수 없습니다.');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $board_type = $_POST['board_type'] ?? '';
    $posts_per_page = isset($_POST['posts_per_page']) ? (int) $_POST['posts_per_page'] : 15;
    $use_password = isset($_POST['use_password']) ? 1 : 0;
    $board_password_input = $_POST['board_password'] ?? '';
    $use_categories = isset($_POST['use_categories']) ? 1 : 0;
    $categories_input = trim($_POST['categories'] ?? '');
    $list_level = isset($_POST['list_level']) ? (int) $_POST['list_level'] : 1;
    $read_level = isset($_POST['read_level']) ? (int) $_POST['read_level'] : 1;
    $write_level = isset($_POST['write_level']) ? (int) $_POST['write_level'] : 2;
    $comment_level = isset($_POST['comment_level']) ? (int) $_POST['comment_level'] : 2;

    if ($name === '' || mb_strlen($name) > 50) {
        $errors[] = '게시판 이름을 1~50자로 입력해 주세요.';
    }
    if (mb_strlen($description) > 200) {
        $errors[] = '설명은 200자 이내로 입력해 주세요.';
    }
    if (!isset($board_types[$board_type])) {
        $errors[] = '게시판 유형을 선택해 주세요.';
    }
    if ($board_type === 'profile') {
        if ($posts_per_page < 1 || $posts_per_page > 4) {
            $errors[] = '프로필형 게시판의 페이지당 글 수는 1~4 사이로 입력해 주세요.';
        }
    } elseif ($posts_per_page < 5 || $posts_per_page > 100) {
        $errors[] = '페이지당 글 수는 5~100 사이로 입력해 주세요.';
    }
    foreach (['list_level' => $list_level, 'read_level' => $read_level, 'write_level' => $write_level, 'comment_level' => $comment_level] as $lv) {
        if ($lv < 1 || $lv > 10) {
            $errors[] = '권한 레벨은 1~10 사이로 입력해 주세요.';
            break;
        }
    }

    $categories = '';
    if ($use_categories) {
        $categories = validate_categories_input($categories_input, $errors);
    }

    if (!$is_edit) {
        $slug = strtolower(trim($_POST['slug'] ?? ''));

        if (!preg_match('/^[a-z0-9_-]{2,30}$/', $slug)) {
            $errors[] = '게시판 경로는 영문 소문자/숫자/-/_ 로 2~30자여야 합니다.';
        }

        $board_password_hash = null;
        if ($use_password) {
            if ($board_password_input === '') {
                $errors[] = '게시판 비밀번호를 입력해 주세요.';
            } else {
                $board_password_hash = password_hash($board_password_input, PASSWORD_DEFAULT);
            }
        }

        if (empty($errors)) {
            $stmt = db()->prepare('SELECT id FROM boards WHERE slug = ?');
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $errors[] = '이미 사용 중인 게시판 경로입니다.';
            }
        }

        if (empty($errors)) {
            $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM boards');
            $stmt->execute();
            $next_order = (int) $stmt->fetchColumn();

            $stmt = db()->prepare(
                'INSERT INTO boards (slug, name, description, board_type, posts_per_page, use_password, board_password, use_categories, categories, list_level, read_level, write_level, comment_level, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$slug, $name, $description, $board_type, $posts_per_page, $use_password, $board_password_hash, $use_categories, $categories, $list_level, $read_level, $write_level, $comment_level, $next_order]);
            redirect('boards.php');
        }
    } else {
        if (empty($errors)) {
            if ($use_password && $board_password_input !== '') {
                $stmt = db()->prepare('UPDATE boards SET name = ?, description = ?, board_type = ?, posts_per_page = ?, use_password = ?, board_password = ?, use_categories = ?, categories = ?, list_level = ?, read_level = ?, write_level = ?, comment_level = ? WHERE id = ?');
                $stmt->execute([$name, $description, $board_type, $posts_per_page, $use_password, password_hash($board_password_input, PASSWORD_DEFAULT), $use_categories, $categories, $list_level, $read_level, $write_level, $comment_level, $edit_id]);
            } else {
                $stmt = db()->prepare('UPDATE boards SET name = ?, description = ?, board_type = ?, posts_per_page = ?, use_password = ?, use_categories = ?, categories = ?, list_level = ?, read_level = ?, write_level = ?, comment_level = ? WHERE id = ?');
                $stmt->execute([$name, $description, $board_type, $posts_per_page, $use_password, $use_categories, $categories, $list_level, $read_level, $write_level, $comment_level, $edit_id]);
            }
            redirect('boards.php');
        }
    }

    // 검증 실패 시 입력값을 다시 보여주기 위해 $board를 갱신
    $board = [
        'id' => $edit_id, 'slug' => $board['slug'] ?? ($slug ?? ''), 'name' => $name, 'description' => $description,
        'board_type' => $board_type, 'posts_per_page' => $posts_per_page, 'use_password' => $use_password,
        'use_categories' => $use_categories, 'categories' => $categories_input,
        'list_level' => $list_level, 'read_level' => $read_level, 'write_level' => $write_level, 'comment_level' => $comment_level,
    ];
}

$page_title = $is_edit ? '게시판 수정' : '게시판 추가';
require_once __DIR__ . '/includes/admin_header.php';
?>

<h1><?php echo $is_edit ? '게시판 수정 — ' . esc($board['name']) : '게시판 추가'; ?></h1>

<?php if ($errors) { ?>
<div class="alert-box">
    <?php foreach ($errors as $err) { ?>
    <p><?php echo esc($err); ?></p>
    <?php } ?>
</div>
<?php } ?>

<form method="post" action="board_form.php<?php echo $is_edit ? '?id=' . $edit_id : ''; ?>" class="write-form">
    <?php echo csrf_field(); ?>
    <div class="field-row">
        <label for="name">게시판 이름</label>
        <input type="text" id="name" name="name" maxlength="50" placeholder="예: 공지사항" value="<?php echo esc($board['name'] ?? ''); ?>" required>
    </div>
    <?php if ($is_edit) { ?>
    <div class="field-row">
        <label>게시판 경로</label>
        <input type="text" value="/board/<?php echo in_array($board['board_type'], ['profile', 'gallery'], true) ? $board['board_type'] : 'general'; ?>/list.php?board=<?php echo esc($board['slug']); ?>" disabled>
    </div>
    <?php } else { ?>
    <div class="field-row">
        <label for="slug">게시판 경로</label>
        <input type="text" id="slug" name="slug" maxlength="30" placeholder="예: notice (영문/숫자/-/_ 만 가능)" value="<?php echo esc($board['slug'] ?? ''); ?>" required>
    </div>
    <?php } ?>
    <div class="field-row">
        <label for="description">설명 (선택)</label>
        <input type="text" id="description" name="description" maxlength="200" placeholder="게시판 소개 문구" value="<?php echo esc($board['description'] ?? ''); ?>">
    </div>
    <div class="field-row">
        <label for="board_type">게시판 유형</label>
        <select id="board_type" name="board_type">
            <?php foreach ($board_types as $type_key => $type_label) { ?>
            <option value="<?php echo esc($type_key); ?>" <?php echo ($board['board_type'] ?? 'list') === $type_key ? 'selected' : ''; ?>><?php echo esc($type_label); ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="field-row">
        <label for="posts_per_page">페이지당 글 수 (프로필형은 1~4)</label>
        <input type="number" id="posts_per_page" name="posts_per_page"
               min="<?php echo ($board['board_type'] ?? 'list') === 'profile' ? 1 : 5; ?>"
               max="<?php echo ($board['board_type'] ?? 'list') === 'profile' ? 4 : 100; ?>"
               value="<?php echo (int) ($board['posts_per_page'] ?? 15); ?>" required>
    </div>
    <div class="field-row">
        <label class="checkbox-row"><input type="checkbox" name="use_password" value="1" <?php echo (int) ($board['use_password'] ?? 0) === 1 ? 'checked' : ''; ?>> 게시판 비밀번호 사용</label>
        <input type="password" name="board_password" placeholder="<?php echo $is_edit ? '변경하려면 새 비밀번호 입력 (그대로 두면 기존 비밀번호 유지)' : '게시판 입장 비밀번호'; ?>">
    </div>
    <div class="field-row">
        <label class="checkbox-row"><input type="checkbox" name="use_categories" value="1" <?php echo (int) ($board['use_categories'] ?? 0) === 1 ? 'checked' : ''; ?>> 분류 사용</label>
        <input type="text" name="categories" placeholder="분류와 분류 사이는 | 로 구분 (예: 질문|답변)" value="<?php echo esc($board['categories'] ?? ''); ?>">
    </div>
    <div class="field-row">
        <label>권한 (1=비로그인, 2=일반회원, 10=관리자)</label>
        <div class="level-group">
            <label>목록 <input type="number" name="list_level" min="1" max="10" value="<?php echo (int) ($board['list_level'] ?? 1); ?>" required></label>
            <label>읽기 <input type="number" name="read_level" min="1" max="10" value="<?php echo (int) ($board['read_level'] ?? 1); ?>" required></label>
            <label>쓰기 <input type="number" name="write_level" min="1" max="10" value="<?php echo (int) ($board['write_level'] ?? 2); ?>" required></label>
            <label>댓글 <input type="number" name="comment_level" min="1" max="10" value="<?php echo (int) ($board['comment_level'] ?? 2); ?>" required></label>
        </div>
    </div>
    <div class="btn-row">
        <button type="submit" class="btn-primary"><?php echo $is_edit ? '수정하기' : '추가하기'; ?></button>
        <a href="boards.php" class="btn-secondary">취소</a>
    </div>
</form>

<script>
document.getElementById('board_type').addEventListener('change', function () {
    var ppp = document.getElementById('posts_per_page');
    if (this.value === 'profile') {
        ppp.min = 1;
        ppp.max = 4;
        if ((+ppp.value) > 4) ppp.value = 4;
    } else {
        ppp.min = 5;
        ppp.max = 100;
        if ((+ppp.value) < 5) ppp.value = 15;
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
