<?php
//define koneksi variable
$host = "localhost";//36.67.157.195:33306 lama
$base = "walisongo_spmb";//
$user = "walisongo";
$pawd = "walisongo_spmb1q2w3e";//

// $dbhandle = mysql_connect($host, $user, $pawd) or die("Couldn't connect to MySQL Server on $host"); 
  
// //select a database to work with
// $selected = mysql_select_db($base, $dbhandle)
//   or die("Couldn't open database $dbhandle"); 
$dbhandle = mysqli_connect($host, $user, $pawd,$base) or die("Couldn't connect to SQL Server on $host"); 

?>
