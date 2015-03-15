<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'storedinfo.php';


session_start();
if (!isset($_SESSION['user_name'])) {
    header('Location: index.php');
    exit();
}


// ************************************ USER INPUT REQUIRED HERE ****************************

$mysqli = new mysqli('oniddb.cws.oregonstate.edu', 'choiy-db', $mypassword, 'choiy-db');

// ******************************************************************************************



if ($mysqli->connect_errno) {
    echo 'Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_errno . '<br>';
    exit();
}


$q_stmt = NULL;


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
                //$insert_failed = false;
                if (!$mysqli->query("INSERT INTO users (fname, lname, address, state, username) VALUES ('$fname', '$lname', '$addr', $stateid, '$username')")) {
                    echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
                    //$insert_failed = true;
                }
                // insert password into members table
                $password = trim($_POST['password']);
                // encode the password before inserting it
                $pwd_base64 = strtr(base64_encode($pw_addthis . $password), '+/=', '-_,');
                if (!$mysqli->query("INSERT INTO members (username, password) VALUES ('$username', '$pwd_base64')")) {
                    echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
                    //if (!$insert_failed) $insert_failed = true;
                }
                //if (!$insert_failed) {
                //    $_SESSION['user_name'] = $username;
                //}
            }
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnDeleteCustomer'])) {
    $id = $_POST['btnDeleteCustomer'];
    $query_stmt = "SELECT * FROM users WHERE id = $id";
    $result = mysqli_query($mysqli, $query_stmt) or die(mysqli_error());
    
    $num_row = mysqli_num_rows($result);
    if ($num_row == 1) {
        $row = mysqli_fetch_array($result);
        $usern = $row['username'];
        if (!$mysqli->query("DELETE FROM members WHERE username = '$usern'"))
            echo 'Delete failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        if (!$mysqli->query("DELETE FROM users WHERE id = $id"))
            echo 'Delete failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnSortCustomers'])) {
    $sel1 = $_POST['sort_customers1'];
    $sel2 = $_POST['sort_customers2'];
    $sortby = $_POST['sort_by'];
    
    $stmt = NULL;
    
    if ($sel1 == 'default') {
        if ($sortby == 'A-Z') 
            $stmt = "SELECT c.id, fname, lname, address, states.name, c.username, m.password FROM users AS c
                    INNER JOIN states ON states.id = c.state 
                    INNER JOIN members AS m ON m.username = c.username 
                    ORDER BY c.id ASC";
        else
             $stmt = "SELECT c.id, fname, lname, address, states.name, c.username, m.password FROM users AS c
                    INNER JOIN states ON states.id = c.state 
                    INNER JOIN members AS m ON m.username = c.username 
                    ORDER BY c.id DESC";
    }
    elseif ($sel1 == $sel2) {
        if ($sortby == 'A-Z')
            $stmt = "SELECT c.id, fname, lname, address, states.name, c.username, m.password FROM users AS c
                    INNER JOIN states ON states.id = c.state
                    INNER JOIN members AS m ON m.username = c.username
                    ORDER BY $sel1 ASC";
        else
            $stmt = "SELECT c.id, fname, lname, address, states.name, c.username, m.password FROM users AS c
                    INNER JOIN states ON states.id = c.state
                    INNER JOIN members AS m ON m.username = c.username
                    ORDER BY $sel1 DESC";
    }
    else {
        if ($sortby == 'A-Z')
            $stmt = "SELECT c.id, fname, lname, address, states.name, c.username, m.password FROM users AS c
                    INNER JOIN states ON states.id = c.state
                    INNER JOIN members AS m ON m.username = c.username
                    ORDER BY $sel1, $sel2 ASC";
        else
            $stmt = "SELECT c.id, fname, lname, address, states.name, c.username, m.password FROM users AS c
                    INNER JOIN states ON states.id = c.state
                    INNER JOIN members AS m ON m.username = c.username
                    ORDER BY $sel1, $sel2 DESC";
    }
    
    $q_stmt = $stmt;
}


function displayTable(&$mysqli, $table, $qstmt, &$pw_add_this) {
    if (!mysqli_num_rows($mysqli->query("SELECT * FROM $table"))) {
        //echo '<p>No data to display...</p>';
        return;
    }
    
    //echo $qstmt . '<br>';
    //return;

    
    $stmt = NULL;
    
    if ($qstmt == NULL) {
        if ($table == 'users')
            $stmt = $mysqli->prepare("SELECT c.id, fname, lname, address, states.name, c.username, m.password FROM users AS c
                                     INNER JOIN states ON states.id = c.state
                                     INNER JOIN members AS m ON m.username = c.username");
        elseif ($table == 'states')
            $stmt = $mysqli->prepare("SELECT id, name FROM states");
        //elseif ($table == 'things')
        //    $stmt = $mysqli->prepare("SELECT id, name, category, price FROM $table");
    }
    else // query statement
        $stmt = $mysqli->prepare($qstmt);
    
    if (!$stmt->execute()) {
        echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    if ($table == 'users') {
        $res_id = NULL;
        $res_fname = NULL;
        $res_lname = NULL;
        $res_addr = NULL;
        $res_state = NULL;
        $res_username = NULL;
        $res_password = NULL;
        
        if (!$stmt->bind_result($res_id, $res_fname, $res_lname, $res_addr, $res_state, $res_username, $res_password)) {
            echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cb_show_pw'])) {
            echo '<table bgcolor="#0B2F3A" border="1" <tr>
                <td bgcolor="#3B170B">ID</td>
                <td bgcolor="#3B170B">First Name</td>
                <td bgcolor="#3B170B">Last Name</td>
                <td bgcolor="#3B170B">Address</td>
                <td bgcolor="#3B170B">State</td>
                <td bgcolor="#3B170B">Username</td>
                <td bgcolor="#3B170B">Password</td>
                <td bgcolor="#3B170B">Delete</td></tr>';
                
            $pw_decoded;
            while ($stmt->fetch()) {
                // decode password first
                $pw_decoded = strtr(base64_decode($res_password), '-_,', '+/=');
                $pw_decoded = substr($pw_decoded, strlen($pw_add_this));
                
                echo "<tr><td>$res_id</td><td>$res_fname</td><td>$res_lname</td><td>$res_addr</td><td>$res_state</td><td>$res_username</td><td>$pw_decoded</td>";
        
                // $_POST['$res_name'] contains only the first part of the string if it has a space
                // need to prevent the string from being separated by a space in it
                //$res_name = str_replace(' ', '_', $res_name);
                echo "<td><form action='admin.php' method='post'>
                        <button name='btnDeleteCustomer' value=$res_id>Delete</button>
                    </form></td></tr>";
            }
            echo '</table><br>';
        }
        else {
            echo '<table bgcolor="#0B2F3A" border="1" <tr>
                <td bgcolor="#3B170B">ID</td>
                <td bgcolor="#3B170B">First Name</td>
                <td bgcolor="#3B170B">Last Name</td>
                <td bgcolor="#3B170B">Address</td>
                <td bgcolor="#3B170B">State</td>
                <td bgcolor="#3B170B">Username</td>
                <td bgcolor="#3B170B">Delete</td></tr>';
            while ($stmt->fetch()) {
                echo "<tr><td>$res_id</td><td>$res_fname</td><td>$res_lname</td><td>$res_addr</td><td>$res_state</td><td>$res_username</td>";
            
                // $_POST['$res_name'] contains only the first part of the string if it has a space
                // need to prevent the string from being separated by a space in it
                //$res_name = str_replace(' ', '_', $res_name);
                echo "<td><form action='admin.php' method='post'>
                        <button name='btnDeleteCustomer' value=$res_id>Delete</button>
                    </form></td></tr>";
            }
            echo '</table><br>';
        }
    }
    
    elseif ($table == 'states') {
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
        <title>Admin</title>
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

    
echo '<div style="float:right"><a href="logout.php" id="logout">Logout</a></div>';
  

echo "<form action='admin.php' method='post'>
        <fieldset style='width:80%'>
            <legend>Manage Customers</legend>
            First Name: <input type='text' name='fname' style='width:6%'/>&nbsp;&nbsp;
            Last Name: <input type='text' name='lname' style='width:6%'/>&nbsp;&nbsp;&nbsp;&nbsp;
            Address: <input type='text' name='addr' style='width:18%'/>&nbsp;&nbsp;
            State#: <input type='number' name='stateid' style='width:4%' min='1' max='51'/>&nbsp;&nbsp;
            Username: <input type='text' name='username' style='width:6%'/>&nbsp;&nbsp;
            Password: <input type='text' name='password' style='width:6%'/>&nbsp;&nbsp;&nbsp;&nbsp;
            <input type='submit' name='btnAddCustomer' value='Register'/><br><br>
            
            <select name='sort_customers1'>
                <option value='states.name'>State</option>
                <option value='lname'>Last Nm</option>
                <option value='default'>Default</option>
            </select>
            <select name='sort_customers2'>
                <option value='states.name'>State</option>
                <option value='lname'>Last Nm</option>
            </select>
            <select name='sort_by'>
                <option value='A-Z'>A-Z</option>
                <option value='Z-A'>Z-A</option>
            </select>
            &nbsp;<button name='btnSortCustomers' value='sortCustomers'>Sort</button>&nbsp;&nbsp;&nbsp;&nbsp;";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cb_show_pw']))
        echo "<input type='checkbox' name='cb_show_pw' value='cb_val_show_pw' checked>Show Password&nbsp;&nbsp;";
    else
        echo "<input type='checkbox' name='cb_show_pw' value='cb_val_show_pw'>Show Password&nbsp;&nbsp;";
        
    echo "<button name='btnPassword' value='val_password'>Pwd</button><br>
        </fieldset>
        <br>
    </form>";

    
displayTable($mysqli, 'users', $q_stmt, $pw_addthis);

if ($q_stmt != NULL) $q_stmt = NULL;

displayTable($mysqli, 'states', $q_stmt, $pw_addthis);


echo '</body>
    </html>';

mysqli_close($mysqli);
?>
