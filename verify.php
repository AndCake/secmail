#!/usr/bin/env php -q
<?php
include_once("init.php");

if (count($argv) <= 2) {
	echo "Not enough arguments provided.\n\n";
	echo "Syntax:\n";
	echo "\t".$argv[0]." <user> <fingerprint>\n";
	echo "\t<user> - the user's handle\n";
	echo "\t<fingerprint> - the user's fingerprint as provided by him/her.\n";
	die();
}

$id = sha1($argv[1]);
$user = $ADDRESSBOOK[$id];
$key = $KEYDIR . "/" . $id . ".public.pem";
if (!file_exists($key)) {
	die("Unable to verify fingerprint: user not found.");
}
$content = file_get_contents($key);
$publickey = trim(substr($content, 0, strpos($content, "-----END PUBLIC KEY-----") + strlen("-----END PUBLIC KEY-----")));
$fpCheck = substr(sha1($publickey), 0, 6);
if ($fpCheck == $argv[2]) {
	echo "Contact successfully verified.";
	$ADDRESSBOOK[$id]["fingerprint"] = $fpCheck;
	file_put_contents($KEYDIR."/addressbook", json_encode($ADDRESSBOOK));
} else {
	echo "Unable to verify fingerprint! Please re-issue the invitation.";
	unset($ADDRESSBOOK[$id]);
	unlink($key);
	exit(1);
}
?>