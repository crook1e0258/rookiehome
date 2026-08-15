<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$admin_user = require_admin();

$__site_title = get_setting('site_title', '루키홈');
$__page_title = (isset($page_title) ? $page_title . ' - ' : '') . '관리자 - ' . $__site_title;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc($__page_title); ?></title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-body">
<header class="admin-topbar">
    <span class="admin-topbar-title">관리자 · <?php echo esc($__site_title); ?></span>
    <nav class="admin-topbar-nav">
        <span class="admin-topbar-user"><?php echo esc($admin_user['nickname']); ?>님</span>
        <a href="../index.php">사이트로 돌아가기</a>
        <a href="../logout.php">로그아웃</a>
    </nav>
</header>
<div class="admin-layout">
    <nav class="admin-nav">
        <a href="settings.php">사이트 설정</a>
        <a href="design.php">디자인 설정</a>
        <a href="theme.php">색상 설정</a>
        <a href="members.php">회원 관리</a>
        <a href="boards.php">게시판 관리</a>
        <a href="menus.php">메뉴 관리</a>
        <a href="posts.php">게시글 관리</a>
    </nav>
    <div class="admin-content">
