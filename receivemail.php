#!/usr/bin/env php -q
<?php
include_once("class.WebDAVServer.php");
include_once("class.CLIInit.php");
include_once("class.Message.php");

if (count($argv) > 1) { $showMsg = $argv[1]; }

$webdav = new WebDAVServer();
$base = new CLIInit($webdav);
$message = new Message($base, $webdav);

$list = $message->fetch();

foreach ($list["contactRequests"] as $request) {
	$base->handleContactRequest($request);
}

$base->renderMessages(array_merge($list["new"], $list["messages"]));

if (!empty($showMsg)) {
	$msg = $message->get($showMsg);
	$base->renderMessage($msg);
}
?>