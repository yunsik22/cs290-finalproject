<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'storedinfo.php';


// ************************************ USER INPUT REQUIRED HERE ****************************

$mysqli = new mysqli('oniddb.cws.oregonstate.edu', 'choiy-db', $mypassword, 'choiy-db');

// ******************************************************************************************



session_start();

$username = $_POST['username'];
$pwd = $_POST['pwd'];

// encrypt the password
$pwd_base64 = strtr(base64_encode($pw_addthis . $pwd), '+/=', '-_,');

$query = "SELECT * FROM members WHERE username = '$username' AND password = '$pwd_base64'";

$result = mysqli_query($mysqli, $query) or die(mysqli_error());

if(mysqli_num_rows($result) == 1) {
	$row = mysqli_fetch_array($result);
	
	if ($row['username'] == 'admin') echo 'login_admin';
	else echo 'login_user';
	
	$_SESSION['user_name'] = $row['username'];
}

else
	echo 'login_failure';
?>