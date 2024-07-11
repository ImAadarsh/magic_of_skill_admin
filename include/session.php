<?php
session_start();
if(empty($_SESSION['token'] AND $_SESSION['usertype']=='admin')){
    header('location: index.php');
}