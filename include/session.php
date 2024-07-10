<?php
session_start();
if(empty($_SESSION['email'] AND $_SESSION['usertype']=='admin')){
    header('location: index.php');
}