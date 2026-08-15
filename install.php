<?php
// 루키홈 설치마법사
// 설치가 끝나면 이 파일은 반드시 삭제하세요 (보안).

$lock_file = __DIR__ . '/install.lock';
if (file_exists($lock_file)) {
    die('이미 설치가 완료되었습니다. install.php 파일을 삭제해 주세요.');
}

$errors = [];
$success = false;

$db_host = $_POST['db_host'] ?? 'localhost';
$db_name = $_POST['db_name'] ?? '';
$db_user = $_POST['db_user'] ?? '';
$db_pass = $_POST['db_pass'] ?? '';
$site_title = $_POST['site_title'] ?? '루키홈';
$admin_email = $_POST['admin_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($db_name === '' || $db_user === '') {
        $errors[] = 'DB 이름과 DB 아이디를 입력해 주세요.';
    }
    if ($site_title === '') {
        $errors[] = '사이트 제목을 입력해 주세요.';
    }
    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '올바른 관리자 이메일을 입력해 주세요.';
    }

    $pdo = null;
    if (empty($errors)) {
        try {
            $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $errors[] = 'DB 접속에 실패했습니다. 입력한 정보를 확인해 주세요. (' . $e->getMessage() . ')';
        }
    }

    if (empty($errors) && $pdo) {
        try {
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            $sql = str_replace("'루키홈'", $pdo->quote($site_title), $sql);
            $sql = str_replace("'admin@example.com'", $pdo->quote($admin_email), $sql);

            // 세미콜론 기준으로 문장을 나눠 하나씩 실행 (PDO는 한번에 여러 쿼리를 못 돌림)
            $statements = array_filter(array_map('trim', explode(";", $sql)));
            foreach ($statements as $stmt) {
                if ($stmt === '' || strpos($stmt, '--') === 0) {
                    continue;
                }
                $pdo->exec($stmt);
            }

            $config_content = "<?php\n"
                . "define('DB_HOST', " . var_export($db_host, true) . ");\n"
                . "define('DB_NAME', " . var_export($db_name, true) . ");\n"
                . "define('DB_USER', " . var_export($db_user, true) . ");\n"
                . "define('DB_PASS', " . var_export($db_pass, true) . ");\n"
                . "define('DB_CHARSET', 'utf8mb4');\n\n"
                . "define('SITE_URL', '');\n\n"
                . "date_default_timezone_set('Asia/Seoul');\n";

            if (!is_writable(__DIR__ . '/config.php') && file_exists(__DIR__ . '/config.php')) {
                $errors[] = 'config.php 파일에 쓰기 권한이 없습니다. FTP에서 config.php 권한을 646 또는 666으로 바꿔주세요.';
            } else {
                file_put_contents(__DIR__ . '/config.php', $config_content);
                file_put_contents($lock_file, date('Y-m-d H:i:s'));
                $success = true;
            }
        } catch (PDOException $e) {
            $errors[] = '테이블 생성 중 오류가 발생했습니다: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>루키홈 설치</title>
<style>
    body { font-family: 'Pretendard', 'Noto Sans KR', -apple-system, sans-serif; background: #ececea; margin: 0; padding: 40px 20px; }
    .box { max-width: 420px; margin: 0 auto; background: #fff; border: 1px solid #dcdcd8; border-radius: 10px; padding: 28px; }
    h1 { font-size: 1.2rem; text-align: center; margin: 0 0 20px; }
    .row { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
    label { font-size: 0.85rem; color: #8a8a86; }
    input { padding: 10px 12px; border: 1px solid #dcdcd8; border-radius: 8px; font-size: 0.95rem; }
    button { width: 100%; padding: 12px; border: none; border-radius: 8px; background: #6f809a; color: #fff; font-weight: 600; font-size: 0.95rem; cursor: pointer; margin-top: 6px; }
    button:hover { background: #56657a; }
    .alert { background: #fbeceb; border: 1px solid #f0c9c6; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; color: #c0564f; font-size: 0.88rem; }
    .alert p { margin: 2px 0; }
    .ok { background: #eaf3ea; border: 1px solid #c9e0c9; color: #3f7a3f; border-radius: 8px; padding: 16px; text-align: center; }
    .ok a { color: #3f7a3f; font-weight: 600; }
</style>
</head>
<body>
<div class="box">
<h1>루키홈 설치</h1>

<?php if ($success) { ?>
<div class="ok">
    <p>설치가 완료되었습니다!</p>
    <p><strong>보안을 위해 지금 바로 install.php와 install.lock 파일을 서버에서 삭제해 주세요.</strong></p>
    <p style="margin-top:14px;"><a href="register.php">회원가입하러 가기 (첫 계정은 자동 관리자)</a></p>
</div>
<?php } else { ?>

<?php if ($errors) { ?>
<div class="alert">
    <?php foreach ($errors as $err) { ?>
    <p><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php } ?>
</div>
<?php } ?>

<form method="post" action="install.php">
    <div class="row">
        <label for="db_host">DB 호스트</label>
        <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($db_host, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div class="row">
        <label for="db_name">DB 이름</label>
        <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div class="row">
        <label for="db_user">DB 아이디</label>
        <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($db_user, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div class="row">
        <label for="db_pass">DB 비밀번호</label>
        <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($db_pass, ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <div class="row">
        <label for="site_title">사이트 제목</label>
        <input type="text" id="site_title" name="site_title" value="<?php echo htmlspecialchars($site_title, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div class="row">
        <label for="admin_email">관리자 이메일</label>
        <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($admin_email, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <button type="submit">설치하기</button>
</form>
<?php } ?>
</div>
</body>
</html>
