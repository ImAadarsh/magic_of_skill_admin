<?php
$lifetime = 315360000; // 10 years (effectively never expires)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.gc_maxlifetime', $lifetime);
    session_start();
}
if (empty($_SESSION['token']) || $_SESSION['usertype'] !== 'admin') {
    header('location: index.php');
    exit;
}