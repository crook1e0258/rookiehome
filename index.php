<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nav_data.php';

require_site_public();

$site_title = get_setting('site_title', '루키홈');
$hero_handle = get_setting('hero_handle', 'WELCOME TO MY HOME');
$hero_year = get_setting('hero_year', date('Y'));
$hero_corner_chars = preg_split('//u', get_setting('hero_corner_text', '近墨者黑'), -1, PREG_SPLIT_NO_EMPTY);
$user = current_user();
$nav_items = build_nav_items($user);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($site_title); ?></title>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link
    rel="preload" as="font" type="font/woff2" crossorigin
    href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/woff2/Pretendard-Regular.woff2">
<link
    rel="preload" as="font" type="font/woff2" crossorigin
    href="https://cdn.jsdelivr.net/gh/projectnoonnu/noonfonts_2307-1@1.1/PartialSansKR-Regular.woff2">
<link rel="stylesheet" href="css/style.css">
<?php echo theme_style_tag(); ?>
</head>
<body>
<main class="home">
    <div class="paper-card">
        <span class="corner-number corner-tl"><?php echo esc($hero_corner_chars[0] ?? ''); ?></span>
        <span class="corner-number corner-tr"><?php echo esc($hero_corner_chars[1] ?? ''); ?></span>
        <span class="corner-number corner-bl"><?php echo esc($hero_corner_chars[2] ?? ''); ?></span>
        <span class="corner-number corner-br"><?php echo esc($hero_corner_chars[3] ?? ''); ?></span>

        <div class="card-center">
            <div class="handle handle-arc" id="handle-arc"><?php echo esc($hero_handle); ?></div>
            <h1 class="big-title" data-split><?php echo esc($site_title); ?></h1>
            <?php $home_image = get_setting('home_image'); ?>
            <?php if ($home_image) { ?>
            <img class="hero-image" src="<?php echo esc($home_image); ?>" alt="<?php echo esc($site_title); ?>">
            <?php } ?>
            <span class="card-date"><?php echo esc($hero_year); ?></span>
        </div>
    </div>
</main>

<script>
const NAV_ITEMS = <?php echo json_encode($nav_items, JSON_UNESCAPED_UNICODE); ?>;

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

// @핸들 글자를 반원 모양으로 배치
const handleArc = document.getElementById("handle-arc");
if (handleArc) {
    const text = handleArc.textContent;
    handleArc.textContent = "";
    handleArc.setAttribute("aria-label", text);
    const chars = [...text];
    const mid = (chars.length - 1) / 2;
    const step = 5;
    const radius = 100;
    chars.forEach((ch, i) => {
        const angle = (i - mid) * step;
        const span = document.createElement("span");
        span.setAttribute("aria-hidden", "true");
        span.textContent = ch;
        span.style.transform = `translateX(-50%) rotate(${angle}deg) translateY(-${radius}px)`;
        handleArc.appendChild(span);
    });
}

// 큰 제목 글자를 하나씩 순서대로 나타나게
document.querySelectorAll("[data-split]").forEach((el) => {
    const text = el.textContent;
    el.textContent = "";
    el.setAttribute("aria-label", text);
    [...text].forEach((ch, i) => {
        const span = document.createElement("span");
        span.className = "char";
        span.setAttribute("aria-hidden", "true");
        span.textContent = ch === " " ? " " : ch;
        span.style.animationDelay = `${0.3 + i * 0.12}s`;
        el.appendChild(span);
    });
});
</script>
</body>
</html>
