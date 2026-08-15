<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

if (!registration_is_open()) {
    http_response_code(403);
    die('현재 회원가입을 받고 있지 않습니다.');
}

$is_first_member = (int) db()->query('SELECT COUNT(*) FROM members')->fetchColumn() === 0;

$errors = [];
$username = '';
$nickname = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_re = $_POST['password_re'] ?? '';
    $nickname = trim($_POST['nickname'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = '아이디는 영문/숫자/밑줄로 3~20자여야 합니다.';
    }
    if (strlen($password) < 4) {
        $errors[] = '비밀번호는 4자 이상이어야 합니다.';
    }
    if ($password !== $password_re) {
        $errors[] = '비밀번호가 서로 다릅니다.';
    }
    if ($nickname === '' || mb_strlen($nickname) > 20) {
        $errors[] = '닉네임을 1~20자로 입력해 주세요.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '올바른 이메일 주소를 입력해 주세요.';
    }

    if (empty($errors)) {
        $stmt = db()->prepare('SELECT id FROM members WHERE username = ? OR nickname = ? OR email = ?');
        $stmt->execute([$username, $nickname, $email]);
        if ($stmt->fetch()) {
            $errors[] = '이미 사용 중인 아이디, 닉네임 또는 이메일입니다.';
        }
    }

    if (empty($errors)) {
        // 가입하는 첫 번째 회원은 자동으로 관리자가 됩니다.
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO members (username, password_hash, nickname, email, is_admin) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, $hash, $nickname, $email, $is_first_member ? 1 : 0]);

        $member_id = (int) db()->lastInsertId();
        $stmt = db()->prepare('SELECT id, is_admin FROM members WHERE id = ?');
        $stmt->execute([$member_id]);
        login_member($stmt->fetch());

        redirect('index.php');
    }
}

$page_title = '회원가입';
$register_image = get_setting('register_image');
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <?php if ($register_image) { ?>
    <img src="<?php echo esc($register_image); ?>" alt="<?php echo esc(get_setting('site_title', '루키홈')); ?>" class="auth-logo-image">
    <?php } ?>
    <div class="auth-card">
        <h1>회원가입</h1>

        <?php if ($errors) { ?>
        <div class="alert-box">
            <?php foreach ($errors as $err) { ?>
            <p><?php echo esc($err); ?></p>
            <?php } ?>
        </div>
        <?php } ?>

        <form method="post" action="register.php" autocomplete="off">
            <?php echo csrf_field(); ?>
            <div class="field-row">
                <label for="username">아이디</label>
                <input type="text" id="username" name="username" value="<?php echo esc($username); ?>" maxlength="20" required>
            </div>
            <div class="field-row">
                <label for="password">비밀번호</label>
                <input type="password" id="password" name="password" maxlength="72" required>
            </div>
            <div class="field-row">
                <label for="password_re">비밀번호 확인</label>
                <input type="password" id="password_re" name="password_re" maxlength="72" required>
            </div>
            <div class="field-row">
                <label for="nickname">닉네임</label>
                <input type="text" id="nickname" name="nickname" value="<?php echo esc($nickname); ?>" maxlength="20" required>
            </div>
            <div class="field-row">
                <label for="email">이메일</label>
                <input type="email" id="email" name="email" value="<?php echo esc($email); ?>" maxlength="100" required>
            </div>
            <button type="submit" class="btn-primary btn-block">회원가입</button>
        </form>
        <p class="auth-switch"><a href="login.php">이미 계정이 있으신가요? 로그인</a></p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
