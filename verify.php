#!/usr/bin/env php -q
<?php
include_once("class.CLIInit.php");

if (count($argv) <= 2) {
	echo "Not enough arguments provided.\n\n";
	echo "Syntax:\n";
	echo "\t".$argv[0]." <user> <fingerprint>\n";
	echo "\t<user> - the user's handle\n";
	echo "\t<fingerprint> - the user's fingerprint as provided by him/her.\n";
	die();
}

$id = sha1($argv[1]);
$code = $argv[2];

$init = new CLIInit(null);
if ($init->verifyContact($id, $code)) {
	echo "Contact successfully verified.";
} else {
	echo "Unable to verify fingerprint! Please re-issue the invitation.";
	exit(1);
}
?>