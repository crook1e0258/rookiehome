<?php
// 햄버거 메뉴에 쓸 네비게이션 목록을 로그인 상태에 맞게 만들어줍니다.
// $user 는 auth.php의 current_user() 결과. $prefix 는 현재 페이지 기준 루트까지의 상대경로(예: board/ 폴더 안이면 '../').

function build_nav_items(?array $user, string $prefix = ''): array
{
    $items = [
        ['href' => $prefix . 'index.php', 'label' => 'HOME'],
    ];

    $menus = db()->query('SELECT label, url FROM menus ORDER BY sort_order ASC, id ASC')->fetchAll();
    foreach ($menus as $menu) {
        $items[] = ['href' => $prefix . $menu['url'], 'label' => $menu['label']];
    }

    if ($user) {
        if ((int) $user['is_admin'] === 1) {
            $items[] = ['href' => $prefix . 'admin/settings.php', 'label' => 'ADMIN'];
        }
        $items[] = ['href' => $prefix . 'logout.php', 'label' => 'LOGOUT'];
    } else {
        $items[] = ['href' => $prefix . 'login.php', 'label' => 'LOGIN'];
        if (registration_is_open()) {
            $items[] = ['href' => $prefix . 'register.php', 'label' => 'JOIN'];
        }
    }

    return $items;
}
