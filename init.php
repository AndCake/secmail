<?php
date_default_timezone_set("UTC");

$SERVLIST = file(dirname($argv[0]) . '/servers');
$SERVERS = Array();
foreach ($SERVLIST as $server) {
	list($name, $conn) = explode("=", $server);
	$SERVERS[$name] = trim($conn);
}

$KEYDIR = "./secmail";
$PRIVATEKEY = "$KEYDIR/id.private.pem";
$PUBLICKEY = "$KEYDIR/id.public.pem";
if (file_exists($KEYDIR."/addressbook")) {
	$ADDRESSBOOK = json_decode(file_get_contents("$KEYDIR/addressbook"), true);
} else $ADDRESSBOOK = Array();

$fp = fopen("php://stdin", "r");

if (!file_exists($KEYDIR)) {
	mkdir($KEYDIR, 0700, true);
	echo "This is the first time you use SecMail. Please enter your username:\n";
	$name = trim(fgets($fp));
	$id = sha1($name);

	$ADDRESSBOOK[$id] = Array("name" => trim($name), "fingerprint" => "");
	file_put_contents($KEYDIR."/addressbook", json_encode($ADDRESSBOOK));

	echo "Please choose your primary server:\n";
	$i = 0;
	foreach ($SERVERS as $key => $value) {
		$i++;
		echo "[$i] $key\n";
	}
	echo "[0] Other\n";
	echo "> ";
	$server = fgets($fp);
	if (intval($server) == 0) {
		echo "Enter global/public username:\n";
		$user = trim(fgets($fp));
		echo "Enter global/public password:\n";
		$pass = trim(fgets($fp));
		echo "Enter Hostname and path [myserver.org/that/webdav/path]:\n";
		$host = trim(fgets($fp));
		echo "Enter Alias:\n";
		$name = trim(fgets($fp));
		$conn = $user . ":" . $pass . ":" . $host;
		file_put_contents(dirname($argv[0])."/servers", $name."=".$conn);
		$SERVERS[$name] = $conn;
		$server = count($SERVERS) - 1;
	}
	$serverList = array_keys($SERVERS);
	$ME = $id . "@" . $serverList[intval($server) - 1];
	file_put_contents("$KEYDIR/id", $ME);
	chmod("$KEYDIR/id", 0700);
}
$ME = file_get_contents("$KEYDIR/id");
list($MEID, $MESERVER) = explode("@", $ME);

if (!file_exists($PRIVATEKEY)) {
	echo "Initializing security infrastructure...\n";

	# Create the keypair
	$res = openssl_pkey_new();
	# Get private key
	openssl_pkey_export($res, $priv);
	file_put_contents($PRIVATEKEY, $priv);
	# Get public key
	$pub=openssl_pkey_get_details($res);
	$pub=$pub["key"];
	file_put_contents($PUBLICKEY, $pub);

	chmod($PRIVATEKEY, 0700);
	chmod($PUBLICKEY, 0700);

	$ADDRESSBOOK[$MEID]["fingerprint"] = substr(sha1(trim($pub)), 0, 6);
	file_put_contents($KEYDIR."/addressbook", json_encode($ADDRESSBOOK));

	echo "Done.\n";
}

fclose($fp);

function upload($file, $target, $creds) {
	$fh = fopen($file, "r");
	$ch = curl_init($target);
 
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	curl_setopt($ch, CURLOPT_USERPWD, $creds);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
 
	curl_setopt($ch, CURLOPT_PUT, true);
	curl_setopt($ch, CURLOPT_INFILE, $fh);
	curl_setopt($ch, CURLOPT_INFILESIZE, filesize($file));
 
	$result = curl_exec($ch);

	$error = curl_error($ch);
	if ($error != "") {
		die("Unable to send message: " . $error);
	}
	fclose($fh);
}

function deleteMsg($target, $creds) {
	$ch = curl_init($target);
 
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	curl_setopt($ch, CURLOPT_USERPWD, $creds);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	$result = curl_exec($ch);
}

function download($file, $creds) {
	$ch = curl_init("https://" . $file);
 
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	curl_setopt($ch, CURLOPT_USERPWD, $creds);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec($ch);
	curl_close($ch);

	return $result;
}

function aesDecrypt($password, $edata) {
    $data = base64_decode($edata);
    $salt = substr($data, 8, 8);
    $ct = substr($data, 16);

    $rounds = 3;
    $data00 = $password.$salt;
    $md5_hash = array();
    $md5_hash[0] = md5($data00, true);
    $result = $md5_hash[0];
    for ($i = 1; $i < $rounds; $i++) {
		$md5_hash[$i] = md5($md5_hash[$i - 1].$data00, true);
		$result .= $md5_hash[$i];
    }
    $key = substr($result, 0, 32);
    $iv  = substr($result, 32,16);

    $result = openssl_decrypt($ct, 'aes-256-cbc', $key, true, $iv);
    return $result;
}

function aesEncrypt($password, $data) {
    // Set a random salt
    $salt = openssl_random_pseudo_bytes(8);

    $salted = '';
    $dx = '';
    // Salt the key(32) and iv(16) = 48
    while (strlen($salted) < 48) {
		$dx = md5($dx.$password.$salt, true);
		$salted .= $dx;
    }

    $key = substr($salted, 0, 32);
    $iv  = substr($salted, 32,16);

    $encrypted_data = openssl_encrypt($data, 'aes-256-cbc', $key, true, $iv);
    return base64_encode('Salted__' . $salt . $encrypted_data);
}
?>