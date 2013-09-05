<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);

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

if (isset($_GET["getContact"])) {
	$contact = $json->extractContact($_POST["contact"]);
	die('{"name": ' . json_encode($contact["name"]) . '}');
}

if (isset($_GET["delete"])) {
	$dp = opendir($json->BOX[$_GET["mb"]]);
	while (($file = readdir($dp)) !== false) {
		if ($file[0] != ".") {
			list($toid, $msgid) = explode("-", str_replace(".secmail", "", $file));
			if ($msgid == $_GET["msgid"]) {
				unlink($json->BOX[$_GET["mb"]] . "/" . $file);
			}
		}
	}
	closedir($dp);
}

if (isset($_GET["send"])) {
	try {
		foreach ($_POST['send']['to'] as $to) {
			$message->send($to, $_POST['send']["subject"], $_POST['send']["body"]);
		}
		die('{"success": true}');
	} catch(Exception $e) {
		die('{"error": ' . json_encode($e->getMessage()) . '}');
	}	
}

?>[{
	"name": "Inbox",
	"id": "in",
	"messages": <?php
		$list = $message->fetch("in", isset($_GET["fetch"]));
		$json->renderMessages(array_merge($list["new"], $list["messages"]));
	?>
}, {
	"name": "Sent Items",
	"id": "sent",
	"messages": <?php
		$list = $message->fetch("sent", isset($_GET["fetch"]));
		$json->renderMessages($list["messages"]);
	?>
}]