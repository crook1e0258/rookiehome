<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, username, password_hash, is_admin FROM members WHERE username = ?');
    $stmt->execute([$username]);
    $member = $stmt->fetch();

    if ($member && password_verify($password, $member['password_hash'])) {
        login_member($member, isset($_POST['remember']));
        redirect('index.php');
    } else {
        $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
    }
}

$page_title = '로그인';
$login_image = get_setting('login_image');
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <?php if ($login_image) { ?>
    <img src="<?php echo esc($login_image); ?>" alt="<?php echo esc(get_setting('site_title', '루키홈')); ?>" class="auth-logo-image">
    <?php } ?>
    <div class="auth-card">
        <?php if ($error) { ?>
        <div class="alert-box"><p><?php echo esc($error); ?></p></div>
        <?php } ?>

        <form method="post" action="login.php" autocomplete="off" class="login-form">
            <?php echo csrf_field(); ?>
            <div class="login-top">
                <div class="login-inputs">
                    <input type="text" name="username" placeholder="아이디" value="<?php echo esc($username); ?>" maxlength="20" required autofocus>
                    <input type="password" name="password" placeholder="비밀번호" maxlength="72" required>
                </div>
                <button type="submit" class="btn-primary login-submit">LOGIN</button>
            </div>
            <div class="login-extra">
                <?php if (registration_is_open()) { ?>
                <a href="register.php">회원가입</a>
                <?php } else { ?>
                <span></span>
                <?php } ?>
                <label class="checkbox-row"><input type="checkbox" name="remember"> 자동로그인</label>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
