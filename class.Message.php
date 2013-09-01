<?php
include_once("class.Crypto.php");
date_default_timezone_set("UTC");

class Message {

	private $base;
	private $conn;

	function __construct($base, $conn) {
		$this->base = $base;
		$this->conn = $conn;
	}

	public function get($id) {
		# unencrypt the message
		$content = file_get_contents($this->base->INBOX . "/" . $this->base->MEID . "-" . $id . ".secmail");
		$ekeys = json_decode(substr($content, 0, strpos($content, ']') + 1), true);
		$content = substr($content, strpos($content, ']') + 1);

		$priv = openssl_get_privatekey(file_get_contents($this->base->PRIVATEKEY));
		openssl_open($content, $message, base64_decode($ekeys[0]), $priv);

		if (empty($message)) {
			throw new Exception("Unable to read message " . $id . ". This message might be fraud.\n" . openssl_error_string());
		}

		$messageParts = explode("\n\n", $message);
		preg_match_all("#([\w_0-9-]+): ([^\n]*)#mi", $messageParts[0], $matches);

		foreach ($matches[1] as $key => $match) {
			$headers[$match] = $matches[2][$key];
		}

		return Array("header" => $headers, "body" => array_slice($messageParts, 1));
	}

	public function fetch() {
		$contacts = Array();
		$messages = Array();

		$serv = explode(":", $this->base->SERVERS[$this->base->MESERVER]);
		$mycreds = $serv[0].":".$serv[1];

		$result = $this->conn->download($serv[2], $mycreds);
		preg_match_all('#href="(' . $this->base->MEID . '-[^"]+\\.secmail)"#m', $result, $matches);

		foreach($matches[1] as $match) {
			# get that file
			$newmsg = $this->base->INBOX . "/" . $match;
			$content = $this->conn->download($serv[2]. '/' . $match, $mycreds);
			$this->conn->delete($serv[2] . '/' . $match, $mycreds);
			file_put_contents($newmsg, $content);

			if (substr($content, 0, 26) == '-----BEGIN PUBLIC KEY-----') {
				# we got a contact request
				$pubkey = trim(substr($content, 0, strpos($content, "-----END PUBLIC KEY-----") + strlen("-----END PUBLIC KEY-----")));
				$end = trim(substr($content, strpos($content, "-----END PUBLIC KEY-----") + strlen("-----END PUBLIC KEY-----")));
				list($user, $server, $name,) = explode("\n", $end);
				$end = substr($end, strpos($end, $server."\n".$name) + strlen($server."\n".$name) + 1);
				$end = Crypto::decrypt(sha1(trim($pubkey)), $end);

				list($id, $serverName) = explode("@", $user);
				if (!file_exists($this->base->KEYDIR . "/" . $id . ".public.pem")) {
					$contacts[] = Array(
						"id" => $id,
						"serverName" => $serverName,
						"user" => $user,
						"name" => $name,
						"server" => $server,
						"content" => $content,
						"message" => $end
					);
				}
				unlink($newmsg);
			} else {
				$msgid = str_replace('.secmail', '', basename($newmsg));
				$msgid = str_replace($this->base->MEID . "-", '', $msgid);
				try {
					$messages[$msgid] = $this->get($msgid);
				} catch(Exception $e) {
					unlink($newmsg);
					throw $e;					
				}
			}
		}

		return Array(
			"contactRequests" => $contacts,
			"messages" => $messages
		);
	}

	function send($to, $subject, $body, $headers) {
		$toparts = explode("@", trim($to));
		$toid = sha1($toparts[0]);
		$tokey = "{$this->base->KEYDIR}/$toid.public.pem";
		$server = $toparts[1];
		$serv = explode(":", $this->base->SERVERS[$server]);
		$TARGET = $serv[2]."/".$toid."-".uniqid("".time(), true).".secmail";

		$message = "From: {$this->base->ME}\n" . 
				   "Date: " . date("Y-m-d H:i:s") . "\n" . 
				   "Subject: $subject\n" . 
				   $headers . "\n\n" .
				   $body;

		$TMPFILE = __DIR__."/SECMAIL_".time();
		$creds = $serv[0] . ":" . $serv[1];

		if (!file_exists($tokey)) {
			throw new Exception("Unknown recipient! You did not yet connect to $to");
		}

		$pubkey = openssl_get_publickey(file_get_contents($tokey));
		openssl_seal($message, $cryptmessage, $ekeys, Array($pubkey));
		if (empty($cryptmessage)) {
			throw new Exception(openssl_error_string());
		}

		foreach ($ekeys as $id => $key) {
			$ekeys[$id] = base64_encode($key);
		}

		file_put_contents($TMPFILE, json_encode($ekeys) . $cryptmessage);
		$this->conn->upload($TMPFILE, $TARGET, $creds);
		unlink($TMPFILE);
	}
}
?>