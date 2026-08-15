<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

$sections = [
    ['type' => 'color', 'label' => '기본색', 'fields' => [['key' => 'theme_text', 'sub' => '색상코드']]],
    ['type' => 'color', 'label' => '배경색', 'fields' => [['key' => 'theme_bg', 'sub' => '색상코드']]],
    ['type' => 'color', 'label' => '강조색', 'fields' => [['key' => 'theme_primary', 'sub' => '색상코드']]],
    ['type' => 'color', 'label' => '서브 글자색', 'fields' => [['key' => 'theme_muted', 'sub' => '색상코드']]],
    ['type' => 'color', 'label' => '입력폼', 'fields' => [
        ['key' => 'theme_input_bg', 'sub' => '배경색상'],
        ['key' => 'theme_input_text', 'sub' => '폰트색상'],
        ['key' => 'theme_input_border', 'sub' => '라인색상'],
    ]],
    ['type' => 'color', 'label' => '기본박스', 'fields' => [
        ['key' => 'theme_box_bg', 'sub' => '배경색상'],
        ['key' => 'theme_box_text', 'sub' => '폰트색상'],
        ['key' => 'theme_box_border', 'sub' => '라인색상'],
    ]],
    ['type' => 'button', 'label' => '기본버튼', 'states' => [
        '일반상태' => [['key' => 'theme_btn_text', 'sub' => '텍스트색상'], ['key' => 'theme_btn_bg', 'sub' => '배경색상'], ['key' => 'theme_btn_border', 'sub' => '라인색상']],
        '마우스 오버' => [['key' => 'theme_btn_text_hover', 'sub' => '텍스트색상'], ['key' => 'theme_btn_bg_hover', 'sub' => '배경색상'], ['key' => 'theme_btn_border_hover', 'sub' => '라인색상']],
    ]],
    ['type' => 'button', 'label' => '포인트버튼', 'states' => [
        '일반상태' => [['key' => 'theme_point_text', 'sub' => '텍스트색상'], ['key' => 'theme_point_bg', 'sub' => '배경색상'], ['key' => 'theme_point_border', 'sub' => '라인색상']],
        '마우스 오버' => [['key' => 'theme_point_text_hover', 'sub' => '텍스트색상'], ['key' => 'theme_point_bg_hover', 'sub' => '배경색상'], ['key' => 'theme_point_border_hover', 'sub' => '라인색상']],
    ]],
    ['type' => 'button', 'label' => '기타버튼', 'states' => [
        '일반상태' => [['key' => 'theme_danger_text', 'sub' => '텍스트색상'], ['key' => 'theme_danger_bg', 'sub' => '배경색상'], ['key' => 'theme_danger_border', 'sub' => '라인색상']],
        '마우스 오버' => [['key' => 'theme_danger_text_hover', 'sub' => '텍스트색상'], ['key' => 'theme_danger_bg_hover', 'sub' => '배경색상'], ['key' => 'theme_danger_border_hover', 'sub' => '라인색상']],
    ]],
];

// 오류 메시지용: key => "섹션라벨 서브라벨"
$field_labels = [];
foreach ($sections as $section) {
    if ($section['type'] === 'color') {
        foreach ($section['fields'] as $f) {
            $field_labels[$f['key']] = $section['label'] . ' ' . $f['sub'];
        }
    } else {
        foreach ($section['states'] as $state_label => $fields) {
            foreach ($fields as $f) {
                $field_labels[$f['key']] = $section['label'] . ' ' . $state_label . ' ' . $f['sub'];
            }
        }
    }
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!empty($_POST['reset'])) {
        foreach (theme_defaults() as $key => $default) {
            save_setting($key, $default);
        }
        $message = '기본값으로 초기화했습니다.';
    } else {
        $errors = [];
        $values = [];

        foreach (theme_defaults() as $key => $default) {
            $value = trim($_POST[$key] ?? '');
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $errors[] = ($field_labels[$key] ?? $key) . '은(는) #RRGGBB 형식의 색상 코드로 입력해 주세요.';
                continue;
            }
            $values[$key] = strtolower($value);
        }

        if (empty($errors)) {
            foreach ($values as $key => $value) {
                save_setting($key, $value);
            }
            $message = '저장되었습니다.';
        } else {
            $message = implode(' ', $errors);
        }
    }
}

function render_color_field(string $key, string $sub_label): void
{
    $value = esc(get_setting($key, theme_defaults()[$key]));
    ?>
    <span class="theme-field">
        <span><?php echo esc($sub_label); ?></span>
        <input type="text" name="<?php echo esc($key); ?>" id="<?php echo esc($key); ?>" value="<?php echo $value; ?>" maxlength="7" pattern="^#[0-9a-fA-F]{6}$" required>
        <input type="color" class="theme-picker" data-target="<?php echo esc($key); ?>" value="<?php echo $value; ?>">
    </span>
    <?php
}

$page_title = '색상 설정';
require_once __DIR__ . '/includes/admin_header.php';
?>

<h1>색상 설정</h1>
<p class="page-desc">사이트 전체에 쓰이는 색상을 직접 지정합니다.</p>

<?php if ($message) { ?>
<div class="alert-box"><p><?php echo esc($message); ?></p></div>
<?php } ?>

<form method="post" action="theme.php" class="write-form">
    <?php echo csrf_field(); ?>
    <div class="table-scroll">
    <table class="theme-table">
        <tbody>
            <?php foreach ($sections as $section) { ?>
                <?php if ($section['type'] === 'color') { ?>
                <tr>
                    <td class="theme-row-label"><?php echo esc($section['label']); ?></td>
                    <td>
                        <div class="theme-field-group">
                            <?php foreach ($section['fields'] as $f) { render_color_field($f['key'], $f['sub']); } ?>
                        </div>
                    </td>
                </tr>
                <?php } else { ?>
                    <?php $first = true; ?>
                    <?php foreach ($section['states'] as $state_label => $fields) { ?>
                    <tr>
                        <?php if ($first) { ?>
                        <td class="theme-row-label" rowspan="<?php echo count($section['states']); ?>"><?php echo esc($section['label']); ?></td>
                        <?php $first = false; } ?>
                        <td>
                            <span class="theme-state-label"><?php echo esc($state_label); ?></span>
                            <div class="theme-field-group theme-field-group-inline">
                                <?php foreach ($fields as $f) { render_color_field($f['key'], $f['sub']); } ?>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
    </div>
    <div class="btn-row">
        <button type="submit" class="btn-primary">저장</button>
        <button type="submit" name="reset" value="1" class="btn-secondary" onclick="return confirm('기본 색상으로 되돌리시겠습니까?');">기본값으로 초기화</button>
    </div>
</form>

<script>
document.querySelectorAll('.theme-picker').forEach(function (picker) {
    var target = document.getElementById(picker.dataset.target);
    picker.addEventListener('input', function () {
        target.value = picker.value;
    });
    target.addEventListener('input', function () {
        if (/^#[0-9a-fA-F]{6}$/.test(target.value)) {
            picker.value = target.value;
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
