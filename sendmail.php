#!/usr/bin/env php -q
<?php
include_once("init.php");

if (count($argv) <= 1) {
	echo "No arguments provided.\n\n";
	echo "Syntax:\n";
	echo "\t".$argv[0]." <to> <subject>\n";
	echo "\t<to> - the recipient's handle\n";
	echo "\t<subject> - the subject you want to send the message with\n";
	die();
}
$fp = fopen("php://stdin", "r");
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
$TOID = sha1($TOPARTS[0]);
$TOKEY = "$KEYDIR/$TOID.public.pem";
$SERVER = $TOPARTS[1];

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
		$msg = $MESSAGE;

		copy($PUBLICKEY, $TMPFILE);
		$msg = aesEncrypt(sha1(trim(file_get_contents($PUBLICKEY))), $msg);
		file_put_contents($TMPFILE, $ME . "\n" . $SERVERS[array_pop(explode("@", $ME))] . "\n".$ADDRESSBOOK[$MEID]["name"]."\n" . $msg, FILE_APPEND);
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
?>