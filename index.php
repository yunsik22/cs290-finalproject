<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Login</title>
	
	<link rel="stylesheet" href="styles.css" type="text/css"/>
	
	<script type="text/javascript" src="jquery.js"></script>
	
    <script type="text/javascript">
    	$(document).ready(function() {
        $("#login_form").fadeIn("normal");
        $("#user_name").focus();
        $("#login").click(function() {
           	username = $("#user_name").val();
    		password = $("#password").val();
		$.ajax({
		    type: "POST",
		    url: "login.php",
		    data: "username=" + username + "&pwd=" + password,
		    success: function(response_str) {
			    if(response_str == 'login_admin') {
			        window.location.href = "admin.php";
			    }
			    else if(response_str == 'login_user') {
			        window.location.href = "order.php";
			    }
			    else {
			        $("#msg").html("Wrong username or password!");
			    }
			},
		    beforeSend:function() {
		        $("#msg").html("Loading...")
		    }
		});
		return false;
	    });
	});
    </script>
	
    <style type="text/css">
        body {
            background-color: #01A9DB;
            font-family: Tahoma; }
		header {
            background-color: #3B170B;
            color: khaki;
            text-align: center;
			padding: 5px; }
        a:visited {
            color: #2E2E2E; }
        a:hover {
            color: #FF00FF; }
    </style>
</head>

<body>
	<header>
		<h1>Shopping Online Provided By Little.com</h1>
    </header>
	<br>
	<?php session_start(); ?>
	<div>
		<?php if (isset($_SESSION['user_name'])) { ?>
			<a href='logout.php' id='logout'>Logout</a>
		<?php }?>
		
		<?php if (!isset($_SESSION['user_name'])) { ?>
			<a href='customer.php' id='register'>Register</a>
		<?php }?>
	</div>
		
	<div id="login_form">
		<div class="msg_section" id="msg" style="color:red"></div>
		<br>
		<form action="login.php">
			<label>Username</label>
			<input type="text" id="user_name" name="user_name" />
			<label>Password</label>
			<input type="password" id="password" name="password" />
			<br><br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
			<input type="submit" id="login" style="font-family:Tahoma; color:navy; background-color:#00FF00" value="Login"/>
		</form>	
	</div>
	
</body>
</html>