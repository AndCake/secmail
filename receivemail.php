#!/usr/bin/env php -q
<?php
include_once("init.php");

$INBOX = dirname($argv[0])."/inbox";
$serverFileName = dirname($argv[0]) . '/servers';
$serversFile = file_get_contents($serverFileName);
if (count($argv) > 1) { $showMsg = $argv[1]; }
if (!file_exists($INBOX)) { mkdir($INBOX, 0700, true); }

$fp = fopen("php://stdin", "r");
$serv = explode(":", $SERVERS[$MESERVER]);
$mycreds = $serv[0].":".$serv[1];

$result = download($serv[2], $mycreds);
preg_match_all('#href="(' . $MEID . '-[^"]+\\.secmail)"#m', $result, $matches);
$found = 0;

foreach($matches[1] as $match) {
	# get that file
	$newmsg = $INBOX . "/" . $match;
	$content = download($serv[2]. '/' . $match, $mycreds);
	deleteMsg($serv[2] . '/' . $match, $mycreds);
	file_put_contents($newmsg, $content);

	if (substr($content, 0, 26) == '-----BEGIN PUBLIC KEY-----') {
		# we got a contact request
		$pubkey = trim(substr($content, 0, strpos($content, "-----END PUBLIC KEY-----") + strlen("-----END PUBLIC KEY-----")));
		$end = trim(substr($content, strpos($content, "-----END PUBLIC KEY-----") + strlen("-----END PUBLIC KEY-----")));
		list($user, $server, $name,) = explode("\n", $end);
		$end = substr($end, strpos($end, $server."\n".$name) + strlen($server."\n".$name) + 1);
		$end = aesDecrypt(sha1(trim($pubkey)), $end);

		list($id, $serverName) = explode("@", $user);
		if (!file_exists($KEYDIR . "/" . $id . ".public.pem")) {
			echo "You received a contact request from $name: \n" . $end . "\n\nDo you want to accept it?\n";
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
				$msg = aesEncrypt(sha1_file($PUBLICKEY), "Your contact request was accepted.");
				file_put_contents($TMPFILE, $ME . "\n" . $SERVERS[array_pop(explode("@", $ME))] . "\n" . $ADDRESSBOOK[$MEID]["name"] . "\n" . $msg, FILE_APPEND);
				
				$serv = explode(":", $SERVERS[$serverName]);
				$TOID = $id;
				$TARGET="https://".$serv[2]."/$TOID-".uniqid("".time(), true).".secmail";
				$creds = $serv[0] . ":" . $serv[1];

				upload($TMPFILE, $TARGET, $creds);
				unlink($TMPFILE);

				echo "In order to verify the identity of $name, please contact him (p.e. via phone, eMail or otherwise) to get his fingerprint.";
				echo "You can enter this fingerprint into <verifymail.php>.\n\n";
				echo "Your own fingerprint is: \t\t" . $ADDRESSBOOK[$MEID]["fingerprint"] . "\n";

				# add that person to the address book
				$ADDRESSBOOK[$id] = Array("name" => $name, "fingerprint" => "");
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
			$msgid = str_replace('.secmail', '', basename($newmsg));
			$msgid = str_replace($MEID . "-", '', $msgid);
			echo "Received new message from " . $ADDRESSBOOK[$match[1]]["name"] . ": " . $subject[1] . " (" . $msgid . ")\n";
			$found++;
		} else {
			echo openssl_error_string();
			unlink($newmsg);
		}
	}
}

if ($found > 0) echo "Received ".$found." new messages.\n\n";

if (!empty($showMsg)) {
	# unencrypt the message
	$content = file_get_contents($INBOX . "/" . $MEID . "-" . $showMsg . ".secmail");
	$ekeys = json_decode(substr($content, 0, strpos($content, ']') + 1), true);
	$content = substr($content, strpos($content, ']') + 1);
	$priv = openssl_get_privatekey(file_get_contents($PRIVATEKEY));
	openssl_open($content, $message, base64_decode($ekeys[0]), $priv);
	if (empty($message)) {
		die("Unable to read message " . $showMsg . ". This message might be fraud.");
	}

	preg_match("#From: ([^@]+)@#mi", $message, $match);
	preg_match("#Subject: ([^\n]*)#mi", $message, $subject);
	echo "From: " . $ADDRESSBOOK[$match[1]]["name"] . "\n";
	echo "Subject: " . $subject[1] . "\n\n";
	echo substr($message, strpos($message, "\n\n") + 3);
}
?>