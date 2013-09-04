<?php
include_once("../class.WebDAVServer.php");
include_once("../class.JSONInit.php");
include_once("../class.Message.php");

$webdav = new WebDAVServer();

if (isset($_GET['serverlist'])) {
	$json = new JSONInit($webdav, true);
	$json->renderServerList();
	die();
}

$json = new JSONInit($webdav);
$message = new Message($json, $webdav);

if (isset($_GET["me"])) {
	$json->renderProfile();
	die();
}

if (isset($_GET["import"])) {
	$contacts = $_POST["contact"];
	foreach ($contacts as $contact) {
		$json->addPureContact($contact);
	}
	die('{"success": true}');
}

if (isset($_GET['addressbook'])) {
	$json->renderAddressBook();
	die();
}

if (isset($_GET["send"])) {
	try {
		$message->send($_POST["to"], $_POST["subject"], $_POST["body"]);
		die('{"success": true}');
	} catch(Exception $e) {
		die('{"error": ' . json_encode($e->getMessage()) . '}');
	}	
}

?>[{
	"name": "Inbox",
	"id": "in",
	"messages": <?php
		$list = $message->fetch("in");
		$json->renderMessages(array_merge($list["new"], $list["messages"]));
	?>
}, {
	"name": "Sent Items",
	"id": "sent",
	"messages": <?php
		$list = $message->fetch("sent");
		$json->renderMessages($list["messages"]);
	?>
}]