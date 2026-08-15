<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$message = '';

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $site_title = trim($_POST['site_title'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $site_public = isset($_POST['site_public']) ? '1' : '0';
    $registration_open = isset($_POST['registration_open']) ? '1' : '0';
    $errors = [];

    if ($site_title === '') {
        $errors[] = '사이트 제목을 입력해 주세요.';
    }
    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '올바른 관리자 이메일을 입력해 주세요.';
    }

    if (empty($errors)) {
        save_setting('site_title', $site_title);
        save_setting('admin_email', $admin_email);
        save_setting('site_public', $site_public);
        save_setting('registration_open', $registration_open);
        $message = '저장되었습니다.';
    } else {
        $message = implode(' ', $errors);
    }
}

$page_title = '사이트 설정';
require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>사이트 설정</h1>

<?php if ($message) { ?>
<div class="alert-box"><p><?php echo esc($message); ?></p></div>
<?php } ?>

<form method="post" action="settings.php" class="write-form">
    <?php echo csrf_field(); ?>
    <div class="field-row">
        <label for="site_title">사이트 제목</label>
        <input type="text" id="site_title" name="site_title" value="<?php echo esc(get_setting('site_title')); ?>" maxlength="50" required>
    </div>
    <div class="field-row">
        <label for="admin_email">관리자 이메일</label>
        <input type="email" id="admin_email" name="admin_email" value="<?php echo esc(get_setting('admin_email')); ?>" maxlength="100" required>
    </div>
    <div class="field-row">
        <label>공개설정</label>
        <div class="checkbox-group">
            <label class="checkbox-row"><input type="checkbox" name="site_public" value="1" <?php echo get_setting('site_public', '0') === '1' ? 'checked' : ''; ?>> 사이트공개</label>
            <label class="checkbox-row"><input type="checkbox" name="registration_open" value="1" <?php echo get_setting('registration_open', '0') === '1' ? 'checked' : ''; ?>> 계정생성 가능</label>
        </div>
    </div>
    <button type="submit" class="btn-primary">저장</button>
</form>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
