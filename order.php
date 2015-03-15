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
    header('Location: index.php');
    exit();
}


$show_order_tbl = false; // show the current order
$show_order_history = false; // show all the orders
$show_order_history_detail = false; // show an old order in detail


// get the customer id out of the stored username
$c_id; // customer id
$username = $_SESSION['user_name'];


//if ($username == 'admin') {
//    header('Location: admin.php');
//    exit();
//}


$q_stmt = "SELECT id, fname, lname FROM users WHERE username = '$username'";
$result = mysqli_query($mysqli, $q_stmt) or die(mysqli_error());
if (mysqli_num_rows($result) == 1) {
    $row = mysqli_fetch_array($result);
    $c_id = $row['id'];
    $q_stmt = NULL;
    
    echo "<p>Hello! " . $row['fname'] . " " . $row['lname'] . "</p>";
}
else {
    echo 'Wrong username. Exiting...';
    header('Location: index.php');
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnSortOrder'])) {
    $sel1 = $_POST['sort_order1'];
    $sel2 = $_POST['sort_order2'];
    $sortby = $_POST['sort_by'];
    
    $stmt = NULL;
    
    if ($sel1 == 'default') {
        if ($sortby == 'A-Z') 
            $stmt = "SELECT id, name, category, price FROM things ORDER BY id ASC";
        else
            $stmt = "SELECT id, name, category, price FROM things ORDER BY id DESC";
    }
    elseif ($sel1 == $sel2) {
        if ($sortby == 'A-Z')
            $stmt = "SELECT id, name, category, price FROM things ORDER BY $sel1 ASC";
        else
            $stmt = "SELECT id, name, category, price FROM things ORDER BY $sel1 DESC";
    }
    else {
        if ($sortby == 'A-Z')
            $stmt = "SELECT id, name, category, price FROM things ORDER BY $sel1, $sel2 ASC";
        else
            $stmt = "SELECT id, name, category, price FROM things ORDER BY $sel1, $sel2 DESC";
    }
    
    $q_stmt = $stmt;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnFilterItemsByCate'])) {
    $cate = $_POST['item_cate'];
    if ($cate != 'all_items') {
        $cate = str_replace('_', ' ', $cate);
        $q_stmt = "SELECT * FROM things WHERE category = '$cate' ORDER BY price";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnOrder'])) {
    if (isset($_POST['date']) && trim($_POST['date']) != '' && isValidDate($_POST['date'])) {
	// insert custom id and order date to make_orders table
	$odate = $_POST['date'];
	if (!$mysqli->query("INSERT INTO make_orders (cid, odate) VALUES ($c_id, '$odate')"))
            echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
	
	$stmt = $mysqli->prepare("SELECT id FROM make_orders WHERE cid = $c_id AND odate = '$odate' ORDER BY id DESC LIMIT 1;");
    
	if (!$stmt->execute()) {
	    echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
	    return;
	}
    
	$res_oid = NULL;
	if (!$stmt->bind_result($res_oid)) {
	    echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
	    return;
	}
        
        $o_id = NULL;
        while ($stmt->fetch()) $o_id = $res_oid;
    
	// collect item id and its quantity from shopping_cart table
	$stmt = $mysqli->prepare("SELECT tbl.iid, SUM(tbl.qnty) AS qnty FROM
				 (SELECT c.cid AS cid, c.iid AS iid, i.name AS name, i.category AS categ, c.qnty AS qnty, isordered AS iso FROM shopping_cart AS c
				 INNER JOIN things AS i ON i.id = c.iid) AS tbl
				 WHERE tbl.cid = $c_id AND tbl.iso = 0
				 GROUP BY tbl.iid;");
    
	if (!$stmt->execute()) {
	    echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
	    return;
	}
    
	$res_iid = NULL;
	$res_qnty = NULL;
	if (!$stmt->bind_result($res_iid, $res_qnty)) {
	    echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
	    return;
	}
	
	$arr_iid_qnty = array(); // data of item id and quantity
	while( $stmt->fetch()) $arr_iid_qnty[$res_iid] = $res_qnty;
	
	$stmt->close();
	
	
	// change isordered to true, 1 from shopping_cart table
	if (!$mysqli->query("UPDATE shopping_cart SET isordered = 1 WHERE cid = $c_id;"))
	    echo 'Update failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
	
        
	// add item id and quantity info to orders_things table
	foreach ($arr_iid_qnty as $key => $val)
            ///echo 'oid: ' . $res_oid . ' iid: ' . $key . ' qnty: ' . $arr_iid_qnty[$key] . '<br>'; // for testing
	    if (!$mysqli->query("INSERT INTO orders_things (oid, iid, qnty) VALUES ($o_id, $key, $arr_iid_qnty[$key]);"))
		echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
	
	$show_order_tbl = true;
    }
    else {
        echo 'Enter a correct order date.<br><br>';
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnOrderHistory'])) {
    if ($show_order_tbl) $show_order_tbl = false;
    $show_order_history = true;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnAddItems'])) {
    $input_valid = true;
    if (!isset($_POST['iid']) || !is_numeric($_POST['iid'])) {
        echo 'Enter an item ID.<br>';
        $input_valid = false;
    }
    if (!isset($_POST['qnty']) || !is_numeric($_POST['qnty'])) {
        echo 'Enter a quantity of the item.<br>';
        $input_valid = false;
    }
    if ($input_valid) {
        $iid = intval($_POST['iid']);
        $qnty = intval($_POST['qnty']);
        if (!$mysqli->query("INSERT INTO shopping_cart (cid, iid, qnty) VALUES ($c_id, $iid, $qnty)"))
            echo 'Insert failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnDeleteThisItem'])) {
    $item_id = intval($_POST['btnDeleteThisItem']);
	if (!$mysqli->query("DELETE FROM shopping_cart WHERE cid = $c_id AND iid = $item_id"))
	    echo 'Delete failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnOrderHistoryDetail'])) {
    if (!$show_order_history_detail) $show_order_history_detail = true;
}


function isValidDate($input_date) {
    $arr = explode('-', $input_date); // $input_date => '2015-3-9'
    
    if (count($arr) == 3 && is_numeric($arr[0]) && is_numeric($arr[1]) && is_numeric($arr[2])) {
        $yr = intval($arr[0]);
        if ($yr > 2000 && $yr < 2020) {
            $m = intval($arr[1]);
            if ($m > 0 && $m < 13) {
                $d = intval($arr[2]);
                if ($m == 1 || $m == 3 ||$m == 5 ||$m == 7 ||$m == 8 ||$m == 10 ||$m == 12) {
                    if ($d > 0 && $d <= 31) return true;
                    return false;
                }
                elseif ($m == 4 || $m == 6 ||$m == 9 ||$m == 11) {
                    if ($d > 0 && $d <= 30) return true;
                    return false;
                }
                else { // february
                    if ($yr == 2008 || $yr == 2012 || $yr == 2016) {
                        if ($d > 0 && $d <= 29) return true;
                        return false;    
                    }
                    else {
                        if ($d > 0 && $d <= 28) return true;
                        return false;
                    }
                }
            }
            return false;
        }
        return false;
    }
    return false;
}


function displayTable(&$mysqli, $table, $qstmt) {
    if (!mysqli_num_rows($mysqli->query("SELECT * FROM $table"))) {
        //echo '<p>No data to display...</p>';
        return;
    }
      
    $stmt = NULL;
    
    if ($qstmt == NULL) {
        if ($table == 'things')
            $stmt = $mysqli->prepare("SELECT id, name, category, price FROM $table");
    }
    else // query statement
        $stmt = $mysqli->prepare($qstmt);
    
    if (!$stmt->execute()) {
        echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    if ($table == 'things') {
        $res_id = NULL;
        $res_name = NULL;
        $res_cate = NULL;
        $res_price = NULL;
        
        if (!$stmt->bind_result($res_id, $res_name, $res_cate, $res_price)) {
            echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
            return;
        }
        
        echo '<table bgcolor="#0B2F3A" border="1" <tr>
            <td bgcolor="#3B170B">ID</td>
            <td bgcolor="#3B170B">Name</td>
            <td bgcolor="#3B170B">Category</td>
            <td bgcolor="#3B170B">Price</td></tr>';
        
        while ($stmt->fetch())
            echo "<tr><td>$res_id</td><td>$res_name</td><td>$res_cate</td><td align='right'>$$res_price</td></tr>";
        echo '</table><br>';
    }
    
    $stmt->close();
}


function displayCartTable(&$mysqli, &$cid) {
    if (!mysqli_num_rows($mysqli->query("SELECT * FROM shopping_cart WHERE cid = $cid AND isordered = 0"))) {
        echo 'No items in your shopping cart...<br>';
        return;
    }
    
    $stmt = NULL;
    
    if(!($stmt = $mysqli->prepare("SELECT tbl.iid, tbl.name, tbl.categ, SUM(tbl.qnty) AS qnty, SUM(tbl.total) AS total FROM
                                    (SELECT c.isordered AS isordered, c.cid AS cid, c.iid AS iid, i.name AS name, i.category AS categ, c.qnty AS qnty,
                                    (c.qnty * i.price) AS total FROM shopping_cart AS c INNER JOIN things AS i ON i.id = c.iid) AS tbl
                                    WHERE tbl.cid = $cid AND tbl.isordered = 0 GROUP BY tbl.iid ORDER BY tbl.name;")))
	echo "Prepare failed: "  . $stmt->errno . " " . $stmt->error;

    if(!$stmt->execute())
	echo "Execute failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;

    $res_iid = NULL;
    $res_name = NULL;
    $res_cate = NULL;
    $res_qnty = NULL;
    $res_total = NULL;
    if(!$stmt->bind_result($res_iid, $res_name, $res_cate, $res_qnty, $res_total))
	echo "Bind failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;

    echo '<p>In Your Cart</p>';
    echo '<table bgcolor="#0B2F3A" border="1" <tr>
            <td bgcolor="#3B170B">Name</td>
            <td bgcolor="#3B170B">Category</td>
            <td bgcolor="#3B170B">Qnty</td>
            <td bgcolor="#3B170B">Total</td>
            <td bgcolor="#3B170B">Delete</td></tr>';
    
    while( $stmt->fetch()) {
        echo "<tr><td>$res_name</td><td>$res_cate</td><td align='right'>$res_qnty</td><td align='right'>$$res_total</td>
                <td bgcolor='#3B170B'><form action='order.php' method='post'>
                <button name='btnDeleteThisItem' value=$res_iid>Delete</button>
                </form></td></tr>";
    }
    echo '</table><br>';
    
    $stmt->close();
}


function displayOrderTable(&$mysqli, &$cid) { 
    // first get the order id of a given customer
    $stmt = NULL;
    if (!($stmt = $mysqli->prepare("SELECT id FROM make_orders WHERE cid = ? ORDER BY id DESC LIMIT 1;")))
	echo "Prepare failed: "  . $stmt->errno . " " . $stmt->error;
    
    if (!($stmt->bind_param("i", $cid)))
	echo "Bind failed: "  . $stmt->errno . " " . $stmt->error;
    
    if (!$stmt->execute())
	echo "Execute failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;

    $res_oid = NULL;
    if (!$stmt->bind_result($res_oid))
        echo "Bind failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;
        
    $o_id = NULL;
    while ($stmt->fetch()) $o_id = $res_oid;

    // display ordered items
    $stmt = NULL;
    if (!($stmt = $mysqli->prepare("SELECT tbl2.iname, tbl2.icate, tbl2.iprice, tbl2.iqnty, tbl2.isub FROM
                                   (SELECT o.id AS orderid, o.cid AS customerid, o.odate, tbl1.item_name AS iname,
                                   tbl1.item_cate AS icate, tbl1.item_price AS iprice, tbl1.item_qnty AS iqnty, tbl1.item_sub AS isub FROM make_orders AS o
                                   INNER JOIN (SELECT oi.oid AS order_id, i.name AS item_name, i.category AS item_cate, i.price AS item_price,
                                   oi.qnty AS item_qnty, (i.price * oi.qnty) AS item_sub FROM orders_things AS oi
                                   INNER JOIN things AS i ON oi.iid = i.id) AS tbl1 ON tbl1.order_id = o.id) AS tbl2
                                   WHERE tbl2.customerid = ? AND tbl2.orderid = ?
                                   ORDER BY tbl2.iname;")))
	echo "Prepare failed: "  . $stmt->errno . " " . $stmt->error;
        
    if (!($stmt->bind_param("ii", $cid, $o_id)))
        echo "Bind failed: "  . $stmt->errno . " " . $stmt->error;
    
    if (!$stmt->execute())
	echo "Execute failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;
    
    $res_iname = NULL;
    $res_icate = NULL;
    $res_iprice = NULL;
    $res_iqnty = NULL;
    $res_isub = NULL;
    
    if (!$stmt->bind_result($res_iname, $res_icate, $res_iprice, $res_iqnty, $res_isub))
	echo "Bind failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;

    echo '<p>Ordered Items</p>';
    echo '<table bgcolor="#0B2F3A" border="1" <tr>
            <td bgcolor="#3B170B">Name</td>
            <td bgcolor="#3B170B">Category</td>
            <td bgcolor="#3B170B">Price</td>
            <td bgcolor="#3B170B">Quantity</td>
            <td bgcolor="#3B170B">Subtotal</td></tr>';
    while( $stmt->fetch())
        echo "<tr><td>$res_iname</td><td>$res_icate</td><td align='right'>$$res_iprice</td><td align='right'>$res_iqnty</td><td align='right'>$$res_isub</td></tr>";
    echo '</table><br>';
    
    // display order receipt
    $stmt = NULL;
    if (!($stmt = $mysqli->prepare("SELECT c.fname, c.lname, c.address, s.name AS state, grp.odate AS order_date, grp.amount FROM users AS c
                                   INNER JOIN states AS s ON s.id = c.state
                                   INNER JOIN (SELECT o.id AS oid, o.cid AS cid, o.odate AS odate, SUM(tbl1.item_sub) AS amount FROM make_orders AS o
                                   INNER JOIN (SELECT oi.oid AS order_id, i.name AS item_name, i.category AS item_cate, i.price AS item_price,
                                   oi.qnty AS item_qnty, (i.price * oi.qnty) AS item_sub FROM orders_things AS oi
                                   INNER JOIN things AS i ON oi.iid = i.id) AS tbl1
                                   WHERE tbl1.order_id = o.id
                                   GROUP BY o.id) AS grp ON grp.cid = c.id
                                   WHERE c.id = ?
                                   ORDER BY grp.oid DESC LIMIT 1;")))
	echo "Prepare failed: "  . $stmt->errno . " " . $stmt->error;
        
    if (!($stmt->bind_param("i", $cid)))
	echo "Bind failed: "  . $stmt->errno . " " . $stmt->error;
        
    if (!$stmt->execute())
	echo "Execute failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;
    
    $res_fname = NULL;
    $res_lname = NULL;
    $res_address = NULL;
    $res_state = NULL;
    $res_order_date = NULL;
    $res_amount = NULL;
    if(!$stmt->bind_result($res_fname, $res_lname, $res_address, $res_state, $res_order_date, $res_amount))
	echo "Bind failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;

    echo '<p>Order Receipt</p>';
    echo '<table bgcolor="#0B2F3A" border="1" <tr>
            <td bgcolor="#3B170B">First Name</td>
            <td bgcolor="#3B170B">Last Name</td>
            <td bgcolor="#3B170B">Address</td>
            <td bgcolor="#3B170B">State</td>
            <td bgcolor="#3B170B">Order Date</td>
	    <td bgcolor="#3B170B">Amount</td></tr>';
    
    while( $stmt->fetch())
        echo "<tr><td>$res_fname</td><td>$res_lname</td><td>$res_address</td><td>$res_state</td><td>$res_order_date</td><td align='right'>$$res_amount</td></tr>";
    echo '</table><br>';
    
    $stmt->close();
}


function displayOrderHistory(&$mysqli, &$cid) {
    $stmt = NULL;
    if (!($stmt = $mysqli->prepare("SELECT c.address, s.name AS state, grp.odate AS order_date, grp.amount, grp.o_id FROM users AS c
                                   INNER JOIN states AS s ON s.id = c.state
                                   INNER JOIN (SELECT o.id AS o_id, o.cid AS cid, o.odate AS odate, SUM(tbl1.item_sub) AS amount FROM make_orders AS o
                                   INNER JOIN (SELECT oi.oid AS order_id, i.name AS item_name, i.category AS item_cate, i.price AS item_price,
                                   oi.qnty AS item_qnty, (i.price * oi.qnty) AS item_sub
                                   FROM orders_things AS oi INNER JOIN things AS i ON oi.iid = i.id) AS tbl1
                                   WHERE tbl1.order_id = o.id
                                   GROUP BY o.id) AS grp ON grp.cid = c.id
                                   WHERE c.id = ?
                                   ORDER BY order_date, grp.amount;")))
	echo "Prepare failed: "  . $stmt->errno . " " . $stmt->error;
        
    if (!($stmt->bind_param("i", $cid)))
	echo "Bind failed: "  . $stmt->errno . " " . $stmt->error;
        
    if (!$stmt->execute())
	echo "Execute failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;
    
    $stmt->store_result();
    $numOfRows = $stmt->num_rows;
    
    if ($numOfRows < 1) return;
  
    $res_address = NULL;
    $res_state = NULL;
    $res_order_date = NULL;
    $res_amount = NULL;
    $res_order_id = NULL;
    
    if(!$stmt->bind_result($res_address, $res_state, $res_order_date, $res_amount, $res_order_id))
	echo "Bind failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;
    
    $arr1 = array();
    $arr2 = array();
    $arr3 = array();
    $info = '';
    while($stmt->fetch()) {
        if ($info == '') $info = 'Address:  ' . $res_address . ', ' . $res_state . '<br><br>';
        array_push($arr1, $res_order_date);
        array_push($arr2, $res_amount);
        array_push($arr3, $res_order_id);
    }
    
    echo '<br><p>All Your Order Receipts</p>';
    echo "$info";
    echo '<table bgcolor="#0B2F3A" border="1" <tr>
            <td bgcolor="#3B170B">Order Date</td>
	    <td bgcolor="#3B170B">Amount</td>
        <td bgcolor="#3B170B">Detail</td></tr>';
    for ($i = 0; $i < $numOfRows; ++$i) {
        echo "<tr><td>$arr1[$i]</td><td align='right'>$$arr2[$i]</td>";
        $detail_val = $arr1[$i] . '*$' . $arr2[$i] . '*' . $arr3[$i]; // i.e. 2015-03-14*$261.84*10
        echo "<td><form action='order.php' method='post'>
                    <button name='btnOrderHistoryDetail' value=$detail_val>Detail</button>
                </form></td></tr>";
    }
    echo '</table><br>';
    
    $stmt->close();
}


function displayOrderHistoryDetail(&$mysqli, &$cid) {
    $arr = explode('*', $_POST['btnOrderHistoryDetail']); // $_POST['btnOrderHistoryDetail'] => '2015-03-14*$261.84*10'
    // $arr[0] = order date
    // $arr[1] = order total amount
    // $arr[2] = order id
    $oid = intval($arr[2]);
    
    $stmt = NULL;
    if (!($stmt = $mysqli->prepare("SELECT tbl2.iname, tbl2.icate, tbl2.iprice, tbl2.iqnty, tbl2.isub FROM
                                   (SELECT o.id AS orderid, o.cid AS customerid, o.odate, tbl1.item_name AS iname, tbl1.item_cate AS icate,
                                   tbl1.item_price AS iprice, tbl1.item_qnty AS iqnty, tbl1.item_sub AS isub FROM make_orders AS o
                                   INNER JOIN (SELECT oi.oid AS order_id, i.name AS item_name, i.category AS item_cate,
                                   i.price AS item_price, oi.qnty AS item_qnty, (i.price * oi.qnty) AS item_sub FROM orders_things AS oi
                                   INNER JOIN things AS i ON oi.iid = i.id) AS tbl1 ON tbl1.order_id = o.id) AS tbl2
                                   WHERE tbl2.customerid = ? AND tbl2.orderid = ?
                                   ORDER BY tbl2.iname;")))
        echo "Prepare failed: "  . $stmt->errno . " " . $stmt->error;
        
    if (!($stmt->bind_param("ii", $cid, $oid)))
        echo "Bind failed: "  . $stmt->errno . " " . $stmt->error;
        
    if (!$stmt->execute())
        echo "Execute failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;
    
    $stmt->store_result();
    
    if ($stmt->num_rows < 1) return;
  
    $res_iname = NULL;
    $res_icate = NULL;
    $res_iprice = NULL;
    $res_iqnty = NULL;
    $res_isub = NULL;
    
    if(!$stmt->bind_result($res_iname, $res_icate, $res_iprice, $res_iqnty, $res_isub))
        echo "Bind failed: "  . $mysqli->connect_errno . " " . $mysqli->connect_error;
    
    echo '<br><p>Your Order History Detail (';
    echo "Date: " . $arr[0] . "&nbsp;&nbsp;&nbsp;&nbsp;Total: " . $arr[1] . ")</p>";
    echo '<table bgcolor="#0B2F3A" border="1" <tr>
        <td bgcolor="#3B170B">Name</td>
        <td bgcolor="#3B170B">Category</td>
        <td bgcolor="#3B170B">Price</td>
        <td bgcolor="#3B170B">Qnty</td>
	    <td bgcolor="#3B170B">Sub Total</td></tr>';
    
    while($stmt->fetch()) {
        echo "<tr><td>$res_iname</td><td>$res_icate</td><td align='right'>$$res_iprice</td>
            <td align='right'>$res_iqnty</td><td align='right'>$$res_isub</td></tr>";
    }
    
    echo '</table><br>';
    
    $stmt->close();
}


function displayItemCategory(&$mysqli, $table) {
    if (!mysqli_num_rows($mysqli->query("SELECT * FROM $table"))) {
        //echo '<p>No movie categories to display...</p>';
        return;
    }
    
    $stmt = $mysqli->prepare("SELECT category FROM $table GROUP BY category ORDER BY category");
    if (!$stmt->execute()) {
        echo 'Query failed: (' . $mysqli->errno . ') ' . $mysqli->error . '<br>';
        return;
    }
    
    $res_cate = NULL;
    
    if (!$stmt->bind_result($res_cate)) {
        echo 'Binding output parameters failed: (' . $stmt->errno . ') ' . $stmt->error . '<br>';
        return;
    }
    
    echo '&nbsp&nbsp<select name="item_cate">';
    $tmp;
    while ($stmt->fetch()) {
        // $_POST['$res_name'] contains only the first part of the string if it has a space
        // need to prevent the string from being separated by a space in it
        $tmp = str_replace(' ', '_', $res_cate);
        echo "<option value='$tmp'>$res_cate</option>";
    }
    echo "<option value='all_items'>All Items</option>";
    echo '</select>';
    echo "&nbsp&nbsp<button name='btnFilterItemsByCate' value='filterItems'>Filter Category</button>";
}


echo '<!DOCTYPE html> 
    <html lang="en">
    <head>
        <meta charset="utf-8"/>
        <title>Order</title>
        <style type="text/css">
            body {
                color:#F5F6CE;
                background-color:#0B3B0B;
                font-family:Tahoma; }
            a:visited { color: #00FF00; }
            a:hover { color: #FF00FF; }
            #c1 { float:left; }
            #c2 { float:right; }
        </style>
    </head>
    <body>';

echo '<div style="float:right"><a href="logout.php" id="logout">Logout</a><br><br>';
if ($_SESSION['user_name'] == 'admin')
    echo '<a href="admin.php">Admin</a></div>';
else
    echo '<a href="customer.php">Customer</a></div>';


echo "<form action='order.php' method='post'>
        <fieldset style='width:80%'>
            <legend>Order</legend>";

echo "Item ID: <input type='number' name='iid' style='width:5%' min='1'/>&nbsp;&nbsp;
            Quantity: <input type='number' name='qnty' style='width:4%' min='1'/>&nbsp;&nbsp;
            <button name='btnAddItems' value='addItems'>Add to Cart</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            Date (yyyy-m-d): <input type='text' name='date' style='width:8%'/>&nbsp;&nbsp;
            <input type='submit' name='btnOrder' value='Order'/><br><br>
            
            <select name='sort_order1'>
                <option value='price'>Price</option>
                <option value='category'>Categ</option>
                <option value='default'>Default</option>
            </select>
            <select name='sort_order2'>
                <option value='price'>Price</option>
                <option value='category'>Categ</option>
            </select>
            <select name='sort_by'>
                <option value='A-Z'>A-Z</option>
                <option value='Z-A'>Z-A</option>
            </select>
            &nbsp;<button name='btnSortOrder' value='sortOrder'>Sort</button>&nbsp;&nbsp;&nbsp;&nbsp;";
    
displayItemCategory($mysqli, 'things');

echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<button name='btnShowCart'>My Cart</button>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<button name='btnOrderHistory'>Order History</button>
    </fieldset><br>
    </form>";


echo '<div id="tables" style="width:80%">
    <div id="c1">';

echo '<p>Items</p>';
displayTable($mysqli, 'things', $q_stmt);
if ($q_stmt != NULL) $q_stmt = NULL;


echo '</div>
    <div id="c2">';

if ($show_order_tbl) displayOrderTable($mysqli, $c_id);
else {
    displayCartTable($mysqli, $c_id);
    if ($show_order_history) displayOrderHistory($mysqli, $c_id);
    if ($show_order_history_detail) displayOrderHistoryDetail($mysqli, $c_id);
}

echo '</div>
    </div>';


echo '</body>
    </html>';

mysqli_close($mysqli);
?>
