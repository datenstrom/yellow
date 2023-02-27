<?php 
// input info
$username = filter_input(INPUT_POST, 'username');
$password = filter_input(INPUT_POST, 'password');
$Name = filter_input(INPUT_POST, 'Name');
$role = filter_input(INPUT_POST, 'user');
$email = filter_input(INPUT_POST, 'email');

// database info
$servername = "apps.lifeofpablo.com";
$dbusername = "survey111";
$dbpassword = "survey11";
$dbname = "library_pabs";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

$sql = "INSERT INTO users (role, Name, email, username, password)
VALUES ('USER', '$Name', '$email', '$username', '$password')";

if ($conn->query($sql) === TRUE) {
    echo "New record created successfully. Please visit: https://library.officialpablomorales.com/p/library/?-table=books&-action=forgot_password&-cursor=0&-skip=0&-limit=30&-mode=list to reset/create your password";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>