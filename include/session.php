<?php
$lifetime = 31536000; // 1 year in seconds
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', $lifetime);
    ini_set('session.gc_maxlifetime', $lifetime);
    session_start();
}
if(empty($_SESSION['token'] AND $_SESSION['usertype']=='admin')){
    header('location: index.php');
}