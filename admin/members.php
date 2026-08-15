<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin_user = require_admin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = $_POST['action'] ?? '';
    $target_id = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;

    if ($target_id === (int) $admin_user['id']) {
        $message = '본인 계정은 여기서 변경할 수 없습니다.';
    } elseif ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM members WHERE id = ?');
        $stmt->execute([$target_id]);
        $message = '회원을 삭제했습니다.';
    } elseif ($action === 'toggle_admin') {
        $stmt = db()->prepare('UPDATE members SET is_admin = 1 - is_admin WHERE id = ?');
        $stmt->execute([$target_id]);
        $message = '권한을 변경했습니다.';
    } elseif ($action === 'set_level') {
        $level = isset($_POST['level']) ? (int) $_POST['level'] : 2;
        if ($level < 1 || $level > 9) {
            $message = '레벨은 1~9 사이로 입력해 주세요.';
        } else {
            $stmt = db()->prepare('UPDATE members SET level = ? WHERE id = ?');
            $stmt->execute([$level, $target_id]);
            $message = '레벨을 변경했습니다.';
        }
    }
}

$members = db()->query('SELECT id, username, nickname, email, is_admin, level, created_at FROM members ORDER BY id DESC')->fetchAll();

$page_title = '회원 관리';
require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>회원 관리</h1>

<?php if ($message) { ?>
<div class="alert-box"><p><?php echo esc($message); ?></p></div>
<?php } ?>

<div class="table-scroll">
<table class="admin-table">
    <thead>
        <tr>
            <th>아이디</th>
            <th>닉네임</th>
            <th>이메일</th>
            <th>관리자</th>
            <th>권한(레벨)</th>
            <th>가입일</th>
            <th>관리</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($members as $m) { ?>
        <tr>
            <td><?php echo esc($m['username']); ?></td>
            <td><?php echo esc($m['nickname']); ?></td>
            <td><?php echo esc($m['email']); ?></td>
            <td><?php echo ((int) $m['is_admin'] === 1) ? '예' : ''; ?></td>
            <td>
                <?php if ((int) $m['is_admin'] === 1) { ?>
                10 (관리자)
                <?php } elseif ((int) $m['id'] !== (int) $admin_user['id']) { ?>
                <form method="post" action="members.php" style="display:inline-flex;gap:6px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="member_id" value="<?php echo (int) $m['id']; ?>">
                    <input type="hidden" name="action" value="set_level">
                    <input type="number" name="level" min="1" max="9" value="<?php echo (int) $m['level']; ?>" style="width:56px;padding:6px 8px;border:1px solid var(--border);border-radius:8px;">
                    <button type="submit" class="btn-secondary">저장</button>
                </form>
                <?php } else { ?>
                <?php echo (int) $m['level']; ?>
                <?php } ?>
            </td>
            <td><?php echo format_date($m['created_at']); ?></td>
            <td>
                <?php if ((int) $m['id'] !== (int) $admin_user['id']) { ?>
                <form method="post" action="members.php" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="member_id" value="<?php echo (int) $m['id']; ?>">
                    <input type="hidden" name="action" value="toggle_admin">
                    <button type="submit" class="btn-secondary"><?php echo ((int) $m['is_admin'] === 1) ? '관리자 해제' : '관리자 지정'; ?></button>
                </form>
                <form method="post" action="members.php" style="display:inline;" onsubmit="return confirm('이 회원을 삭제하시겠습니까? 작성한 글/댓글도 함께 삭제됩니다.');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="member_id" value="<?php echo (int) $m['id']; ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn-secondary btn-danger">삭제</button>
                </form>
                <?php } ?>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
