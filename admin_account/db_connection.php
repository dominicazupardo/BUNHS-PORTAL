<?php
$host     = getenv("DB_HOST");
$db_user  = getenv("DB_USER");
$db_pass  = getenv("DB_PASSWORD");
$db_name  = getenv("DB_NAME");
$db_port  = getenv("DB_PORT"); // important for Railway

$conn = mysqli_connect($host, $db_user, $db_pass, $db_name, $db_port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
