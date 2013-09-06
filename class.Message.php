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

	public function get($id, $box = "in", $toid = "") {
		if ($toid == "") {
			$toid = $this->base->MEID;
		}
		# unencrypt the message
		$content = file_get_contents($this->base->BOX[$box] . "/" . $toid . "-" . $id . ".secmail");

		if ($box == "sent") {
			$message = Crypto::decrypt(sha1(file_get_contents($this->base->PRIVATEKEY)), $content);
		} else {
			$ekeys = json_decode(substr($content, 0, strpos($content, ']') + 1), true);
			$content = substr($content, strpos($content, ']') + 1);

			$priv = openssl_get_privatekey(file_get_contents($this->base->PRIVATEKEY));
			openssl_open($content, $message, base64_decode($ekeys[0]), $priv);

			if (empty($message)) {
				throw new Exception("Unable to read message " . $id . ". This message might be fraud.\n" . openssl_error_string());
			}
		}

		$messageParts = explode("\n\n", $message);
		preg_match_all("#([\w_0-9-]+): ([^\n]*)#mi", $messageParts[0], $matches);

		foreach ($matches[1] as $key => $match) {
			$headers[$match] = $matches[2][$key];
		}

		$fromid = array_shift(explode("@", $headers["From"]));
		$headers["From"] = Array(
			"full" => $headers["From"],
			"id" => $fromid,
			"name" => $this->base->ADDRESSBOOK[$fromid]["name"]
		);
		$headers["To"]['id'] = $toid;
		$headers["To"]["name"] = $this->base->ADDRESSBOOK[$toid]["name"];

		return Array("to" => $toid, "header" => $headers, "body" => array_slice($messageParts, 1));
	}

	public function fetch($box = "in", $fetchFromServer = true) {
		$messages = Array();
		$inbox = Array();

		if ($this->base->isSetup()) {

			if ($box == "in" && $fetchFromServer) {
				$serv = explode(":", $this->base->SERVERS[$this->base->MESERVER]);
				$mycreds = $serv[0].":".$serv[1];

				$list = $this->conn->getList($serv[2] . "/", $mycreds);
				foreach ($list as $name) {
					if (strpos($name, $this->base->MEID . "-") >= 0 && array_pop(explode(".", $name)) == "secmail") {
						# get that file
						$newmsg = $this->base->BOX["in"] . "/" . $match;
						$content = $this->conn->download($serv[2]. '/' . $match, $mycreds);
						$this->conn->delete($serv[2] . '/' . $match, $mycreds);
						file_put_contents($newmsg, $content);

						$msgid = str_replace('.secmail', '', basename($newmsg));
						$msgid = str_replace($this->base->MEID . "-", '', $msgid);
						try {
							$msg = $this->get($msgid);
							$messages[$msgid] = Array(
								"to" => $this->base->MEID,
								"file" => $newmsg,
								"message" => $msg
							);
						} catch(Exception $e) {
							unlink($newmsg);
							throw $e;					
						}
					}
				}
			}

			$dp = opendir($this->base->BOX[$box]);
			while (($file = readdir($dp)) !== false) {
				if ($file[0] != "." && strpos($file, ".secmail") !== false) {
					$fileParts = explode("-", str_replace(".secmail", "", $file));
					$toid = $fileParts[0];
					$id = $fileParts[1];
					$msg = $this->get($id, $box, $toid);
					$inbox[$id] = Array(
						"id" => $id,
						"to" => $toid,
						"file" => $file,
						"message" => $msg
					);
				}
			}
			closedir($dp);
		}

		return Array(
			"new" => $messages,
			"messages" => $inbox,
		);
	}

	function send($to, $subject, $body, $headers = "") {
		if (strpos($to, "@") === false) {
			$to = $to . "@" . $this->base->ADDRESSBOOK[$to]["server"];
		}
		$toparts = explode("@", trim($to));
		$toid = $toparts[0];
		$tokey = "{$this->base->KEYDIR}/$toid.public.pem";
		$server = $toparts[1];
		$serv = explode(":", $this->base->SERVERS[$server]);
		$targetFile = $toid."-".uniqid("".time(), true).".secmail";
		$TARGET = $serv[2]."/".$targetFile;

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
		file_put_contents(__DIR__ . "/sent/" . $targetFile, Crypto::encrypt(sha1(file_get_contents($this->base->PRIVATEKEY)), $message));
		unlink($TMPFILE);
	}
}
?>