<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/nav_data.php';

$__site_title = get_setting('site_title', '루키홈');
$__user = current_user();
$__page_title = isset($page_title) ? $page_title . ' - ' . $__site_title : $__site_title;
$__path_prefix = str_repeat('../', $__up_levels ?? (isset($__in_admin) ? 1 : 0));
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($__page_title); ?></title>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="stylesheet" href="<?php echo $__path_prefix; ?>css/style.css">
<?php echo theme_style_tag(); ?>
</head>
<body>
<script>
const NAV_ITEMS = <?php echo json_encode(build_nav_items($__user, $__path_prefix), JSON_UNESCAPED_UNICODE); ?>;
(function () {
    const menuBtn = document.createElement("button");
    menuBtn.className = "menu-btn";
    menuBtn.id = "menu-btn";
    menuBtn.setAttribute("aria-label", "메뉴");
    menuBtn.textContent = "☰";

    const navMenu = document.createElement("div");
    navMenu.className = "nav-overlay";
    navMenu.id = "nav-menu";
    navMenu.hidden = true;

    const nav = document.createElement("nav");
    nav.className = "nav-list";

    NAV_ITEMS.forEach((item) => {
        const a = document.createElement("a");
        a.href = item.href;
        a.textContent = item.label;
        nav.appendChild(a);
    });

    navMenu.appendChild(nav);
    document.body.prepend(navMenu);
    document.body.prepend(menuBtn);

    function setMenuOpen(open) {
        navMenu.hidden = !open;
        menuBtn.textContent = open ? "✕" : "☰";
    }
    menuBtn.addEventListener("click", () => setMenuOpen(navMenu.hidden));
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && !navMenu.hidden) setMenuOpen(false);
    });
    document.addEventListener("click", (e) => {
        if (!navMenu.hidden && !navMenu.contains(e.target) && e.target !== menuBtn) {
            setMenuOpen(false);
        }
    });

    let seqIndex = 0;
    nav.querySelectorAll("a").forEach((el) => {
        const text = el.textContent;
        el.textContent = "";
        el.setAttribute("aria-label", text);
        [...text].forEach((ch) => {
            const span = document.createElement("span");
            span.className = "char-fast";
            span.setAttribute("aria-hidden", "true");
            span.textContent = ch === " " ? " " : ch;
            span.style.animationDelay = `${seqIndex * 0.025}s`;
            el.appendChild(span);
            seqIndex++;
        });
    });
})();
</script>
<main class="site-main">
