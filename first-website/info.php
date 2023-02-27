
	 <?php
$servername = "apps.lifeofpablo.com";
$username = "survey111";
$password = "survey11";
$dbname = "library_pabs";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "INSERT INTO users (role, Name, email, username, password)
VALUES ('USER', 'Name', 'email', 'username', 'password')";

if ($conn->query($sql) === TRUE) {
    echo "New record created successfully";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?> 
