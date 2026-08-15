<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $label = trim($_POST['label'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $errors = [];

        if ($label === '' || mb_strlen($label) > 30) {
            $errors[] = '메뉴 이름을 1~30자로 입력해 주세요.';
        }
        if ($url === '' || mb_strlen($url) > 255) {
            $errors[] = '링크 주소를 1~255자로 입력해 주세요.';
        }

        if (empty($errors)) {
            $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM menus');
            $stmt->execute();
            $next_order = (int) $stmt->fetchColumn();

            $stmt = db()->prepare('INSERT INTO menus (label, url, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$label, $url, $next_order]);
            $message = '메뉴를 추가했습니다.';
        } else {
            $message = implode(' ', $errors);
        }
    } elseif ($action === 'delete') {
        $menu_id = isset($_POST['menu_id']) ? (int) $_POST['menu_id'] : 0;
        $stmt = db()->prepare('DELETE FROM menus WHERE id = ?');
        $stmt->execute([$menu_id]);
        $message = '메뉴를 삭제했습니다.';
    } elseif ($action === 'move_up' || $action === 'move_down') {
        $menu_id = isset($_POST['menu_id']) ? (int) $_POST['menu_id'] : 0;
        $stmt = db()->prepare('SELECT id, sort_order FROM menus WHERE id = ?');
        $stmt->execute([$menu_id]);
        $current = $stmt->fetch();

        if ($current) {
            if ($action === 'move_up') {
                $stmt = db()->prepare('SELECT id, sort_order FROM menus WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1');
            } else {
                $stmt = db()->prepare('SELECT id, sort_order FROM menus WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1');
            }
            $stmt->execute([$current['sort_order']]);
            $neighbor = $stmt->fetch();

            if ($neighbor) {
                $pdo = db();
                $pdo->prepare('UPDATE menus SET sort_order = ? WHERE id = ?')->execute([$neighbor['sort_order'], $current['id']]);
                $pdo->prepare('UPDATE menus SET sort_order = ? WHERE id = ?')->execute([$current['sort_order'], $neighbor['id']]);
            }
        }
    }
}

$menus = db()->query('SELECT id, label, url, sort_order FROM menus ORDER BY sort_order ASC, id ASC')->fetchAll();

$page_title = '메뉴 관리';
require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>메뉴 관리</h1>
<p class="page-desc">햄버거 메뉴에 표시할 항목을 직접 추가·삭제·순서 변경할 수 있습니다. (HOME은 항상 맨 위에 고정, 로그인/로그아웃 항목은 자동으로 붙습니다)</p>

<?php if ($message) { ?>
<div class="alert-box"><p><?php echo esc($message); ?></p></div>
<?php } ?>

<table class="admin-table">
    <thead>
        <tr>
            <th>이름</th>
            <th>링크 주소</th>
            <th>순서</th>
            <th>관리</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($menus as $i => $m) { ?>
        <tr>
            <td><?php echo esc($m['label']); ?></td>
            <td><?php echo esc($m['url']); ?></td>
            <td>
                <form method="post" action="menus.php" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="move_up">
                    <input type="hidden" name="menu_id" value="<?php echo (int) $m['id']; ?>">
                    <button type="submit" class="btn-secondary" <?php echo $i === 0 ? 'disabled' : ''; ?>>▲</button>
                </form>
                <form method="post" action="menus.php" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="move_down">
                    <input type="hidden" name="menu_id" value="<?php echo (int) $m['id']; ?>">
                    <button type="submit" class="btn-secondary" <?php echo $i === count($menus) - 1 ? 'disabled' : ''; ?>>▼</button>
                </form>
            </td>
            <td>
                <form method="post" action="menus.php" onsubmit="return confirm('이 메뉴를 삭제하시겠습니까?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="menu_id" value="<?php echo (int) $m['id']; ?>">
                    <button type="submit" class="btn-secondary btn-danger">삭제</button>
                </form>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>

<h2 class="admin-subheading">메뉴 추가</h2>

<form method="post" action="menus.php" class="write-form">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="add">
    <div class="field-row">
        <label for="label">메뉴 이름</label>
        <input type="text" id="label" name="label" maxlength="30" placeholder="예: BOARD, 공지사항, 방명록" required>
    </div>
    <div class="field-row">
        <label for="url">링크 주소</label>
        <input type="text" id="url" name="url" maxlength="255" placeholder="예: board/general/list.php?board=free" required>
    </div>
    <button type="submit" class="btn-primary">추가하기</button>
</form>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
