<?php
require_once __DIR__ . '/includes/auth.php';

logout_member();
header('Location: index.php');
exit;
