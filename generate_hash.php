<?php
$password = 'Zetech123'; // your desired admin password
$hash = password_hash($password, PASSWORD_DEFAULT);
echo $hash;
?>
