<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'storedinfo.php';

session_start();



// ************************************ USER INPUT REQUIRED HERE ****************************

$mysqli = new mysqli('oniddb.cws.oregonstate.edu', 'choiy-db', $mypassword, 'choiy-db');

// ******************************************************************************************



if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_errno . '<br>';
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnAddCustomer'])) {
    $input_valid = true;
    
    if (!isset($_POST['username']) || trim($_POST['username']) == '') {
        echo 'Enter your username.<br>';
        $input_valid = false;
    }
    
    if (!isset($_POST['password']) || trim($_POST['password']) == '') {
        echo 'Enter your password.<br>';
        $input_valid = false;
    }
    
    if (!isset($_POST['fname']) || trim($_POST['fname']) == '') {
        echo 'Enter your first name.<br>';
        $input_valid = false;
    }
    if (!isset($_POST['lname']) || trim($_POST['lname']) == '') {
        echo 'Enter your last name.<br>';
        $input_valid = false;
    }
    if (!isset($_POST['addr']) || trim($_POST['addr']) == '') {
        echo 'Enter your address.<br>';
        $input_valid = false;
    }
    if (!isset($_POST['stateid']) || trim($_POST['stateid']) == '' || !isint_ref($_POST['stateid'])) {
        //|| intval($_POST['stateid']) < 1 || intval($_POST['stateid']) > 51) {
        echo 'Enter your state number (1 thru 51).<br>';
        $input_valid = false;
    }

    if ($input_valid) {    
        $fname = trim($_POST['fname']);
        $lname = trim($_POST['lname']);
        $addr = trim($_POST['addr']);
        
        if (mysqli_num_rows($mysqli->query("SELECT id FROM users WHERE fname = '$fname'
                                           AND lname = '$lname' AND address = '$addr'")))
            echo "The same data already exists. Try again.<br><br>";
        else {
            // see if there is a duplicate username
            $username = trim($_POST['username']);
            if (mysqli_num_rows($mysqli->query("SELECT id FROM members WHERE username = '$username'")))
                echo "The same username already exists. Try again.<br><br>";
            else {
                $stateid = trim($_POST['stateid']);
                $insert_failed = false;
                if (!$mysqli->query("INSERT INTO users (fname, lname, address, state, username) VALUES ('$fname', '$lname', '$addr', $stateid, '$username')")) {
                    echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
                    $insert_failed = true;
                }
                // insert password into members table
                $password = trim($_POST['password']);
                // encode the password before inserting it
                $pwd_base64 = strtr(base64_encode($pw_addthis . $password), '+/=', '-_,');
                
                if (!$mysqli->query("INSERT INTO members (username, password) VALUES ('$username', '$pwd_base64')")) {
                    echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
                    if (!$insert_failed) $insert_failed = true;
                }
                if (!$insert_failed) {
                    $_SESSION['user_name'] = $username;
                }
            }
        }
    }
}


function displayTable(&$mysqli, $table) {
    if (!mysqli_num_rows($mysqli->query("SELECT * FROM $table"))) {
        return;
    }
    
    $stmt = NULL;
    
    if ($table == 'states')
        $stmt = $mysqli->prepare("SELECT id, name FROM $table");
    else if ($table == 'users') {
        $username = $_SESSION['user_name'];
        $stmt = $mysqli->prepare("SELECT fname, lname, address, states.name, username FROM $table
                                 INNER JOIN states ON states.id = $table.state
                                 WHERE username = '$username'");
    }
    
    if (!$stmt->execute()) {
        echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    if ($table == 'states') {
        $res_id = NULL;
        $res_name = NULL;
        if (!$stmt->bind_result($res_id, $res_name)) {
            echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
            return;
        }
        echo '<table bgcolor="#0B2F3A" border="1" <tr>';
        $i = 0;
        for (; $i < 11; ++$i)
            echo '<td bgcolor="#3B170B">ID</td><td bgcolor="#3B170B">Name</td>';
        echo '</tr><tr>';
        $i = 0; // 5 rows X 11 cols
        while ($stmt->fetch()) {
            if ($i < 10) {
                if ($i % 2 == 0)
                    echo "<td>$res_id</td><td>$res_name</td>";
                else
                    echo "<td bgcolor='#0B4C5F'>$res_id</td><td bgcolor='#0B4C5F'>$res_name</td>";
                ++$i;
            }
            else {
                echo "<td>$res_id</td><td>$res_name</td></tr><tr>";
                $i = 0;
            }
        }
        echo '</tr></table><br>';
    }
    
    else if ($table == 'users') {
        $res_fname = NULL;
        $res_lname = NULL;
        $res_addr = NULL;
        $res_state = NULL;
        $res_username = NULL;
        
        if (!$stmt->bind_result($res_fname, $res_lname, $res_addr, $res_state, $res_username)) {
            echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
            return;
        }
        
        echo '<p>Customer Info</p>
            <table bgcolor="#0B2F3A" border="1" <tr>
            <td bgcolor="#3B170B">First Name</td>
            <td bgcolor="#3B170B">Last Name</td>
            <td bgcolor="#3B170B">Address</td>
            <td bgcolor="#3B170B">State</td>
            <td bgcolor="#3B170B">Username</td></tr>';
        
        while ($stmt->fetch()) {
            echo "<tr><td>$res_fname</td><td>$res_lname</td><td>$res_addr</td><td>$res_state</td><td>$res_username</td></tr>";
        }
        
        echo '</table><br>';
    }
    
     $stmt->close();
}


function isint_ref(&$val) {
    $isint = false;
    if (is_numeric($val)) {
        if (strpos($val, '.')) {
            $diff = floatval($val) - intval($val);
            if ($diff > 0)
                $isint = false;
            else {
                $val = intval($val);
                $isint = true;
            }
        }
        else
            $isint = true;
    }   
    return $isint;
}


echo '<!DOCTYPE html> 
    <html lang="en">
    <head>
        <meta charset="utf-8"/>
        <title>Register</title>
        <style type="text/css">
            body {
                color:#F5F6CE;
                background-color:#0B3B0B;
                font-family:Tahoma; }
            a:visited {
                color: #00FF00; }
            a:hover {
                color: #FF00FF; }
        </style>
    </head>
    <body>';


if (isset($_SESSION['user_name'])) {
    echo '<div style="float:right"><a href="logout.php" id="logout">Logout</a><br><br>
        <a href="order.php">Order</a></div>';
    displayTable($mysqli, 'users');
}   

else {
    echo '<div style="float:right"><a href="index.php" id="logout">Login</a></div>';
        
    echo "<form action='customer.php' method='post'>
            <fieldset style='width:80%'>
                <legend>Register Customer</legend>
                First Name: <input type='text' name='fname' style='width:6%'/>&nbsp;&nbsp;
                Last Name: <input type='text' name='lname' style='width:6%'/>&nbsp;&nbsp;&nbsp;&nbsp;
                Address: <input type='text' name='addr' style='width:18%'/>&nbsp;&nbsp;
                State#: <input type='number' name='stateid' style='width:4%' min='1' max='51'/>&nbsp;&nbsp;
                Username: <input type='text' name='username' style='width:6%'/>&nbsp;&nbsp;
                Password: <input type='text' name='password' style='width:6%'/>&nbsp;&nbsp;&nbsp;&nbsp;
                <input type='submit' name='btnAddCustomer' value='Register'/>
            </fieldset>
            <br>
        </form>";
        
    echo '<br><br>';
    displayTable($mysqli, 'states');
}


echo '</body>
    </html>';

mysqli_close($mysqli);
?>
