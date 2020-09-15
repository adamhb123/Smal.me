<?php

function get_db_conn(){
	$servername = "localhost";
	$dbname = "slinky";
	$port = 3306;
	$username = "root";
	$pfilestr = "../sql-password.txt";
	$pfile = fopen($pfilestr, "r") or die("DB load failure!");
	$password = trim(fgets($pfile));
	$conn = new MySQLi($servername, $username, $password, $dbname , $port);
	if($conn->connect_error){
		echo "Sorry, we are experiencing problems! Try later!";
		exit;
	}
	return $conn;
}
function get_real_link_given_short_link($shortened_link, $conn){
	$command = "SELECT OriginalLink FROM shortened_links WHERE ShortenedLink=?";
	$stmt = $conn->prepare($command);
	$stmt->bind_param("s",$shortened_link);
	$stmt->execute();
	$result = $stmt->get_result();
	if(mysqli_num_rows($result) == 0){
		return FALSE;
	}
	else {
		return $result->fetch_row()[0];
	}

}
///////////////////////////////////////////////////////////////////////////////
//	Request handling
$request = $_SERVER['REQUEST_URI'];
if ($request !== "/index.php" && $request !== "/"){
	//	Check if shortened link was requested
	$conn = get_db_conn();
	$link = get_real_link_given_short_link("smal.me$request",$conn);
	if ($link !== FALSE) {
		echo "<script>window.open('$link');</script>";
		//header('Location: '.strval($link),true);
		exit();
	}
	else{
		//	Otherwise return 404
		http_response_code(404);
		include('error.php');
		die();
	}
}
///////////////////////////////////////////////////////////////////////////////

function link_validity_checks($linkinput){
	$strlen = strlen($linkinput);
	$substring = substr($linkinput, 0, 8);
	$validity_a = strpos($substring,"https://") !== false || strpos($substring, "http://") !== false;
	return $validity_a;
}

function random_ascii(){
	$set_choice = rand(0,2);
	switch($set_choice){
		case 0:
			return rand(48,57);
		case 1:
			return rand(65,90);
		case 2:
			return rand(97,122);
	}
}

function generate_link_name(){
	$array = array(random_ascii(), random_ascii(), random_ascii(), random_ascii());
	for($i = 0; $i < count($array); $i++){
		$array[$i] = chr($array[$i]);
	}
	return 'smal.me/'.implode("",$array);
}

function add_link_to_database($original_link, $shortened_link, $conn){
	$command = "INSERT INTO shortened_links (OriginalLink,ShortenedLink) VALUES (?,?)";
	$query_statement = $conn->prepare($command);
	$query_statement->bind_param("ss",$original_link,$shortened_link);
	$query_statement->execute();
	return $query_statement;
}

function shorten_link_with_db_checks($link, $conn){
	$tries = 0;
	$short_link = generate_link_name();
	//	Check if link in db
	$stmt = "SELECT * FROM shortened_links WHERE ShortenedLink=?";
	$stmt = $conn->prepare($stmt);
	$stmt->bind_param("s",$short_link);
	$stmt->execute();
	$fetchres = $stmt->fetch();
	if($fetchres && $tries < 5){
		shorten_link_with_db_checks($link, $conn);
	}
	elseif($tries >= 5){
		echo "There was an error shortening your link! Try again maybe?";
		$conn->close();
		exit;
	}
	else{
		return $short_link;
	}
}

function run_shortener(){

	$conn = get_db_conn();
	if(array_key_exists("linkinput", $_POST) && !empty($_POST['linkinput'])){
		//	Check link validity
		$linkinput = $_POST['linkinput'];
		if(!link_validity_checks($linkinput)){
			echo "Invalid link!";
			return;
		}
		//	Convert link to shortened url
		$shortened = shorten_link_with_db_checks($linkinput,$conn);
		//	Plant link in database
		add_link_to_database($linkinput,$shortened, $conn);
		//	Echo link
		echo "http://".$shortened;
	}
	$conn->close();
}

?>

<!DOCTYPE HTML>
<html lang="en">
	<head>
		<link rel="stylesheet" href="style.css">
		<title>bruh</title>
	</head>
	<body>
		<div class="content-wrapper">
			<h1>Smal.me</h1>
			<form action='index.php' method='POST'>
				<div style="padding-bottom: 6vh">
					<input type="text" name="linkinput" class="linkinput" id="linkinput" placeholder="Link to shorten:">
				</div>
				<div style="padding-bottom: 6vh">
					<button type="submit" class="buttonboi" id="buttonboi" name="buttonboi">MAKE ME SMALL!</button>
				</div>
			</form>
			<div style="padding-bottom: 6vh">
				<input type="text" class="linkoutput linkinput" id="linkoutput" name="linkoutput" placeholder="Press SHORTEN ME to convert!" value="<?php run_shortener() ?>">
			</div>
		</div>
	</body>
</html>
