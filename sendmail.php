#!/usr/bin/env php -q
<?php
include_once("class.WebDAVServer.php");
include_once("class.CLIInit.php");
include_once("class.Message.php");

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

$webdav = new WebDAVServer();
$init = new CLIInit($webdav);
$message = new Message($init, $webdav);

try {
	$message->send($TO, $SUBJECT, $BODY, $HEADERS);
} catch(Exception $e) {
	if (strpos($e->getMessage(), "Unknown recipient!") !== false) {
		$init->renderContactRequest($TO, $BODY);
	}
}
?>