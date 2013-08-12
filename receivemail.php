#!/usr/bin/env php -q
<?php
date_default_timezone_set("UTC");

$serverFileName = dirname($argv[0]) . '/servers';
$serversFile = file_get_contents(dirname($argv[0]) . '/servers');
$SERVLIST = explode("\n", $serversFile);
$SERVERS = Array();
foreach ($SERVLIST as $server) {
	list($name, $conn) = explode("=", $server);
	$SERVERS[$name] = trim($conn);
}

$KEYDIR = dirname($argv[0])."/secmail";
$INBOX = dirname($argv[0])."/inbox";
$PRIVATEKEY = "$KEYDIR/id.private.pem";
$PUBLICKEY = "$KEYDIR/id.public.pem";
if (file_exists($KEYDIR."/addressbook")) {
	$ADDRESSBOOK = json_decode(file_get_contents("$KEYDIR/addressbook"), true);
} else $ADDRESSBOOK = Array();

if (count($argv) > 1) { $showMsg = $argv[1]; }

$fp = fopen("php://stdin", "r");

if (!file_exists($INBOX)) { mkdir($INBOX, 0700, true); }
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
		file_put_contents(dirname($argv[0])."/servers", trim($name)."=".trim($conn));
		$SERVERS[trim($name)] = trim($conn);
		$server = count($SERVERS) - 1;
	}
	$serverList = array_keys($SERVERS);
	$ME = $id . "@" . $serverList[intval($server) - 1];
	file_put_contents("$KEYDIR/id", $ME);
	chmod("$KEYDIR/id", 0700);
}
$ME = file_get_contents("$KEYDIR/id");
list($MEID, $MYSERVER) = explode("@", $ME);
$serv = explode(":", $SERVERS[$MYSERVER]);
$SOURCE = "https://".$serv[0].":".$serv[1]."@".$serv[2]."/";

$result = file_get_contents($SOURCE);
preg_match_all('#href="(' . $MEID . '-[^"]+\\.secmail)"#m', $result, $matches);

foreach($matches[1] as $match) {
	# get that file
	$newmsg = $INBOX . "/" . $match;
	$result = file_get_contents($SOURCE . $match);
	file_put_contents($newmsg, $result);
	$content = file_get_contents($newmsg);
	if (substr($content, 0, 26) == '-----BEGIN PUBLIC KEY-----') {
		# we got a contact request
		$end = trim(substr($content, strpos($content, "-----END PUBLIC KEY-----") + strlen("-----END PUBLIC KEY-----")));
		list($user, $server, $name) = explode("\n", $end);
		$end = substr($end, strpos($end, $server) + strlen($server) + 1);
		list($id, $serverName) = explode("@", $user);
		if (!file_exists($KEYDIR . "/" . $id . ".public.pem")) {
			echo "You received a contact request: \n" . $end . "\n\nDo you want to accept it?\n";
			$accept = fgets($fp);
			if (strtolower($accept[0]) == 'y') {
				if (strpos($serversFile, $server) === false) {
					# we don't have the server yet... add it
					file_put_contents($serverFileName, $serverName . "=" . $server, FILE_APPEND);
				}
				copy($newmsg, $KEYDIR . "/" . $id . ".public.pem");

				# answer with a contact response
				$TMPFILE = dirname($argv[0])."/SECMAIL_".time();
				copy($PUBLICKEY, $TMPFILE);
				file_put_contents($TMPFILE, $ME . "\n" . $SERVERS[array_pop(explode("@", $ME))] . "\n" . $ADDRESSBOOK[$MEID] . "\nYour contact request was accepted.", FILE_APPEND);
				
				$serv = explode(":", $SERVERS[$serverName]);
				$TOID = $id;
				$TARGET="https://".$serv[2]."/$TOID-".uniqid("".time(), true).".secmail";
				$creds = $serv[0] . ":" . $serv[1];

				upload($TMPFILE, $TARGET, $creds);
				unlink($TMPFILE);

				# add that person to the address book
				$ADDRESSBOOK[$id] = $name;
				file_put_contents($KEYDIR."/addressbook", json_encode($ADDRESSBOOK));
			}
		}
		unlink($newmsg);
	} else {
		# unencrypt the message
		$ekeys = json_decode(substr($content, 0, strpos($content, ']') + 1), true);
		foreach ($ekeys as $id =>$key) {
			$ekeys[$id] = base64_decode($key);
		}
		$content = substr($content, strpos($content, ']') + 1);
		openssl_open($content, $message, $ekeys[0], openssl_get_privatekey(file_get_contents($PRIVATEKEY)));
		if (!empty($message)) {
			preg_match("#From: ([^@]+)@#mi", $message, $match);
			preg_match("#Subject: ([^\n]*)#mi", $message, $subject);
			echo basename($newmsg) . " From: " . $ADDRESSBOOK[$match[1]] . "; Subject: " . $subject[1] . "\n";
		} else {
			echo openssl_error_string();
			unlink($newmsg);
		}
	}
}

if (!empty($showMsg)) {
	# unencrypt the message
	openssl_private_decrypt(file_get_contents($INBOX."/".$showMsg), $message, file_get_contents($PRIVATEKEY));
	echo $message;
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
		die("Unable to send message.\n" . $error);
	}
	fclose($fh);
}
?>