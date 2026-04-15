<?php
$servername = "localhost";
$username = "root"; // डिफॉल्ट MySQL यूजर
$password = ""; // डिफॉल्ट पासवर्ड (XAMPP में खाली)
$dbname = "library_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>