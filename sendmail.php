#!/usr/bin/env php -q
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

	$ADDRESSBOOK[$id] = trim($name);
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
		$user = fgets($fp);
		echo "Enter global/public password:\n";
		$pass = fgets($fp);
		echo "Enter Hostname and path [myserver.org/that/webdav/path]:\n";
		$host = fgets($fp);
		echo "Enter Alias:\n";
		$name = fgets($fp);
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

if (count($argv) <= 1) {
	echo "No arguments provided.\n\n";
	echo "Syntax:\n";
	echo "\t".$argv[0]." <to> <subject>\n";
	echo "\t<to> - the recipient's handle\n";
	echo "\t<subject> - the subject you want to send the message with\n";
	die();
}
$TO = $argv[1];
$SUBJECT = $argv[2];
$HEADERS = $argv[3];
$BODY = "";
while (!feof($fp)) {
	$BODY .= fgets($fp);
}
fclose($fp);
$fp = fopen("php://stdin", "r");
$TOPARTS = explode("@", trim($TO));
var_dump($TOPARTS);
$TOID = sha1($TOPARTS[0]);
$TOKEY = "$KEYDIR/$TOID.public.pem";
$SERVER = $TOPARTS[1];

if (!file_exists($PRIVATEKEY)) {
	echo "Initializing security infrastructure...";

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
}

$MESSAGE = "From: $ME\nDate: ".date("Y-m-d H:i:s")."\nSubject: $SUBJECT\n$HEADERS\n\n$BODY";

$TMPFILE = dirname($argv[0])."/SECMAIL_".time();
$serv = explode(":", $SERVERS[$SERVER]);
$TARGET="https://".$serv[2]."/".$TOID."-".uniqid("".time(), true).".secmail";
$creds = $serv[0] . ":" . $serv[1];

if (!file_exists($TOKEY)) {
	echo "Unknown recipient! You did not yet connect to $TO\n";
	echo "Shall I send a contact invitation?\n";

	$invite = fgets($fp);
	if (strtolower($invite[0]) == "y") {
		echo "Please enter an invitation message:\n";
		while (!feof($fp)) {
			$msg .= fgets($fp);
		}
		fclose($fp);

		copy($PUBLICKEY, $TMPFILE);
		file_put_contents($TMPFILE, $ME . "\n" . $SERVERS[array_pop(explode("@", $ME))] . "\n".$ADDRESSBOOK[$MEID]."\n" . $msg, FILE_APPEND);
		upload($TMPFILE, $TARGET, $creds);
		unlink($TMPFILE);
		die("Invitation sent.");
	}
	exit(1);
}

$pubkey = openssl_get_publickey(file_get_contents($TOKEY));
openssl_seal($MESSAGE, $CRYPTMESSAGE, $ekeys, Array($pubkey));
if (empty($CRYPTMESSAGE)) {
	$error = openssl_error_string();
	if ($error != "") {
		var_dump($error);
		die();
	}
}
foreach ($ekeys as $id => $key) {
	$ekeys[$id] = base64_encode($key);
}

file_put_contents($TMPFILE, json_encode($ekeys) . $CRYPTMESSAGE);
upload($TMPFILE, $TARGET, $creds);
unlink($TMPFILE);

function upload($file, $target, $creds) {
	$fh = fopen($file, "r");
	$ch = curl_init($target);
 
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	curl_setopt($ch, CURLOPT_USERPWD, $creds);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
 
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

?>