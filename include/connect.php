<?php
// Prevent multiple inclusions
if (defined('CONNECT_INCLUDED')) {
    return;
}
define('CONNECT_INCLUDED', true);

$host = "82.180.142.204";
$user = "u954141192_mos";
$password = "Mos@2024";
$dbname = "u954141192_mos";

// Set connection options before connecting
$connect = mysqli_init();
mysqli_options($connect, MYSQLI_OPT_CONNECT_TIMEOUT, 60);
mysqli_options($connect, MYSQLI_OPT_READ_TIMEOUT, 300);
@mysqli_real_connect($connect, $host, $user, $password, $dbname);

// Set session timeouts to prevent "MySQL server has gone away" errors
if ($connect && mysqli_ping($connect)) {
    @mysqli_query($connect, "SET SESSION wait_timeout = 600");
    @mysqli_query($connect, "SET SESSION interactive_timeout = 600");
}
$uri = 'https://api.magicofskills.com/storage/app/';
$apiEndpoint = 'https://api.magicofskills.com/public/api/';
date_default_timezone_set('Asia/Kolkata');
$current_time = time();
function callAPI($method, $urlpoint, $data, $token){
    if (!isset($token)) {
        $token = "";
    }
    
    $url = 'https://api.magicofskills.com/public/api/'.$urlpoint.'';
    $curl = curl_init($url);
    switch ($method){
       case "POST":
          curl_setopt($curl, CURLOPT_POST, 1);
          if ($data)
             curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
             
          break;
       case "PUT":
          curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
          if ($data)
             curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
          break;
       default:
          if ($data)
             $url = sprintf("%s?%s", $url, http_build_query($data));
    }
    
    // OPTIONS:
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
       'Content-Type: application/json',
       $token
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER,TRUE);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // EXECUTE:
    $result = curl_exec($curl);
     echo $result;
    if(!$result){echo curl_error($curl);}
    curl_close($curl);
    return $result;
 }
function callAPI1($method, $urlpoint, $data, $token){
    if (!isset($token)) {
        $token = "";
    }
    
    $url = 'https://api.magicofskills.com/public/api/'.$urlpoint.'';
    $curl = curl_init($url);
    switch ($method){
       case "POST":
          curl_setopt($curl, CURLOPT_POST, 1);
          if ($data)
             curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
             
          break;
       case "PUT":
          curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
          if ($data)
             curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
          break;
       default:
          if ($data)
             $url = sprintf("%s?%s", $url, http_build_query($data));
    }
    
    // OPTIONS:
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
       'Content-Type: multipart/form-data',
       $token
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER,TRUE);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // EXECUTE:
    $result = curl_exec($curl);
     echo $result;
    if(!$result){echo curl_error($curl);}
    curl_close($curl);
    return $result;
 }

 // varibale
 if(isset($_SESSION['usertype']) && $_SESSION['usertype']=='admin'){
    $name = $_SESSION['name'];
    $email = $_SESSION['email'];
    $usertype = $_SESSION['usertype'];
    $uid = $_SESSION['userid'];
 }
