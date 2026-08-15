<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$message = '';
$allowed_ext = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
$max_size = 8 * 1024 * 1024; // 8MB

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function handle_image_upload(string $field, string $setting_key, array $allowed_ext, int $max_size, array &$errors): ?string
{
    if (!empty($_POST['remove_' . $field])) {
        $old = get_setting($setting_key);
        if ($old && is_file(__DIR__ . '/../' . $old)) {
            @unlink(__DIR__ . '/../' . $old);
        }
        return '';
    }

    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = '이미지 업로드에 실패했습니다.';
        return null;
    }
    if ($file['size'] > $max_size) {
        $errors[] = '이미지 용량은 8MB 이하여야 합니다.';
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $image_info = @getimagesize($file['tmp_name']);

    if (!isset($allowed_ext[$ext]) || $image_info === false || $image_info['mime'] !== $allowed_ext[$ext]) {
        $errors[] = 'jpg, png, gif, webp 형식의 이미지만 업로드할 수 있습니다.';
        return null;
    }

    $upload_dir = __DIR__ . '/../uploads';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = $field . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $filename)) {
        $errors[] = '이미지 저장에 실패했습니다.';
        return null;
    }

    compress_image_file($upload_dir . '/' . $filename, $ext, $image_info[0], $image_info[1], 1920);

    $old = get_setting($setting_key);
    if ($old && is_file(__DIR__ . '/../' . $old)) {
        @unlink(__DIR__ . '/../' . $old);
    }
    return 'uploads/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $hero_handle = trim($_POST['hero_handle'] ?? '');
    $hero_year = trim($_POST['hero_year'] ?? '');
    $hero_corner_text = trim($_POST['hero_corner_text'] ?? '');
    $errors = [];

    if (mb_strlen($hero_handle) > 40) {
        $errors[] = '상단 문구는 40자 이내로 입력해 주세요.';
    }
    if (mb_strlen($hero_year) > 20) {
        $errors[] = '하단 문구는 20자 이내로 입력해 주세요.';
    }
    if (mb_strlen($hero_corner_text) !== 4) {
        $errors[] = '모서리 글자는 정확히 4글자로 입력해 주세요.';
    }

    $new_home_image = handle_image_upload('home_image', 'home_image', $allowed_ext, $max_size, $errors);
    $new_login_image = handle_image_upload('login_image', 'login_image', $allowed_ext, $max_size, $errors);
    $new_register_image = handle_image_upload('register_image', 'register_image', $allowed_ext, $max_size, $errors);

    if (empty($errors)) {
        save_setting('hero_handle', $hero_handle);
        save_setting('hero_year', $hero_year);
        save_setting('hero_corner_text', $hero_corner_text);
        if ($new_home_image !== null) {
            save_setting('home_image', $new_home_image);
        }
        if ($new_login_image !== null) {
            save_setting('login_image', $new_login_image);
        }
        if ($new_register_image !== null) {
            save_setting('register_image', $new_register_image);
        }
        $message = '저장되었습니다.';
    } else {
        $message = implode(' ', $errors);
    }
}

$page_title = '디자인 설정';
require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>디자인 설정</h1>
<p class="page-desc">메인화면(홈)에 표시되는 문구와 이미지, 로그인/회원가입 화면 이미지를 설정합니다.</p>

<?php if ($message) { ?>
<div class="alert-box"><p><?php echo esc($message); ?></p></div>
<?php } ?>

<form method="post" action="design.php" class="write-form" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <div class="field-row">
        <label for="hero_handle">메인화면 상단 문구</label>
        <input type="text" id="hero_handle" name="hero_handle" value="<?php echo esc(get_setting('hero_handle', 'WELCOME TO MY HOME')); ?>" maxlength="40">
    </div>
    <div class="field-row">
        <label for="hero_year">메인화면 하단 문구</label>
        <input type="text" id="hero_year" name="hero_year" value="<?php echo esc(get_setting('hero_year', date('Y'))); ?>" maxlength="20">
    </div>
    <div class="field-row">
        <label for="hero_corner_text">메인화면 모서리 글자 (정확히 4글자, 순서: 좌상단·우상단·좌하단·우하단)</label>
        <input type="text" id="hero_corner_text" name="hero_corner_text" value="<?php echo esc(get_setting('hero_corner_text', '近墨者黑')); ?>" maxlength="4" required>
    </div>
    <div class="image-settings-grid">
        <div class="image-settings-card">
            <label for="home_image">메인화면 이미지</label>
            <?php $home_image = get_setting('home_image'); ?>
            <?php if ($home_image) { ?>
            <img src="../<?php echo esc($home_image); ?>" alt="메인화면 이미지" class="settings-image-preview">
            <label class="checkbox-row"><input type="checkbox" name="remove_home_image" value="1"> 이미지 삭제</label>
            <?php } ?>
            <input type="file" id="home_image" name="home_image" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
        <div class="image-settings-card">
            <label for="login_image">로그인 화면 이미지</label>
            <?php $login_image = get_setting('login_image'); ?>
            <?php if ($login_image) { ?>
            <img src="../<?php echo esc($login_image); ?>" alt="로그인 화면 이미지" class="settings-image-preview">
            <label class="checkbox-row"><input type="checkbox" name="remove_login_image" value="1"> 이미지 삭제</label>
            <?php } ?>
            <input type="file" id="login_image" name="login_image" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
        <div class="image-settings-card">
            <label for="register_image">회원가입 화면 이미지</label>
            <?php $register_image = get_setting('register_image'); ?>
            <?php if ($register_image) { ?>
            <img src="../<?php echo esc($register_image); ?>" alt="회원가입 화면 이미지" class="settings-image-preview">
            <label class="checkbox-row"><input type="checkbox" name="remove_register_image" value="1"> 이미지 삭제</label>
            <?php } ?>
            <input type="file" id="register_image" name="register_image" accept="image/jpeg,image/png,image/gif,image/webp">
        </div>
    </div>
    <button type="submit" class="btn-primary">저장</button>
</form>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
