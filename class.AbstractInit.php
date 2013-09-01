<?php
class AbstractInit {

	public $SERVERS;
	public $ADDRESSBOOK;
	public $INBOX;
	public $PUBLICKEY;
	public $PRIVATEKEY;
	public $KEYDIR;
	public $ME;
	public $MEID;
	public $MESERVER;

	private $conn;

	function __construct($conn) {
		$this->conn = $conn;

		# init list of servers
		$SERVLIST = file(__DIR__ . '/servers');
		$this->SERVERS = Array();
		foreach ($SERVLIST as $server) {
			list($name, $conn) = explode("=", $server);
			$this->SERVERS[$name] = trim($conn);
		}

		$this->KEYDIR = __DIR__ . "/secmail";
		$this->PRIVATEKEY = "{$this->KEYDIR}/id.private.pem";
		$this->PUBLICKEY = "{$this->KEYDIR}/id.public.pem";

		# init addressbook
		if (file_exists($this->KEYDIR."/addressbook")) {
			$this->readAddressbook();
		} else $this->ADDRESSBOOK = Array();

		# init inbox
		$this->INBOX = __DIR__ . "/inbox";
		if (!file_exists($this->INBOX)) { mkdir($this->INBOX, 0700, true); }

		if (!$this->isSetup()) {
			$this->render();
		}

		$this->ME = file_get_contents("{$this->KEYDIR}/id");
		list($this->MEID, $this->MESERVER) = explode("@", $this->ME);

		$this->createKeypair();
	}

	protected function createKeypair() {
		if (!file_exists($this->PRIVATEKEY)) {
			# Create the keypair
			$res = openssl_pkey_new();
			# Get private key
			openssl_pkey_export($res, $priv);
			file_put_contents($this->PRIVATEKEY, $priv);
			# Get public key
			$pub = openssl_pkey_get_details($res);
			$pub = $pub["key"];
			file_put_contents($this->PUBLICKEY, $pub);

			chmod($this->PRIVATEKEY, 0700);
			chmod($this->PUBLICKEY, 0700);

			$this->ADDRESSBOOK[$this->MEID]["fingerprint"] = substr(sha1(trim($pub)), 0, 6);
			$this->writeAddressbook();
		}		
	}

	private function readAddressbook() {
		$pass = sha1(trim(file_get_contents($this->PRIVATEKEY)));
		if (!file_exists($this->PRIVATEKEY)) {
			$this->ADDRESSBOOK = Array();
			return false;
		}

		$data = file_get_contents("{$this->KEYDIR}/addressbook");
		$data = Crypto::decrypt($pass, $data);
		$this->ADDRESSBOOK = json_decode($data, true);
	}

	private function writeAddressbook() {
		$pass = sha1(trim(file_get_contents($this->PRIVATEKEY)));
		if (!file_exists($this->PRIVATEKEY)) {
			return false;
		}

		$data = Crypto::encrypt($pass, json_encode($this->ADDRESSBOOK));
		file_put_contents("{$this->KEYDIR}/addressbook", $data);
	}

	protected function isSetup() {
		if (!file_exists($this->KEYDIR)) {
			mkdir($this->KEYDIR, 0700, true);
			return false;
		}
		return true;
	}

	protected function addAddress($name, $fingerprint = "") {
		$name = trim($name);
		$fingerprint = trim($fingerprint);
		$id = sha1($name);

		$this->ADDRESSBOOK[$id] = Array("name" => $name, "fingerprint" => $fingerprint);
		$this->writeAddressbook();

		return $id;
	}

	protected function acceptContact($request) {
		$serverFileName = __DIR__ . '/servers';
		$serversFile = file_get_contents($serverFileName);

		if (strpos($serversFile, $request["server"]) === false) {
			# we don't have the server yet... add it
			file_put_contents($serverFileName, $request["serverName"] . "=" . $request["server"], FILE_APPEND);
		}
		file_put_contents($this->KEYDIR . "/" . $request["id"] . ".public.pem", $request["content"]);

		# answer with a contact response
		$TMPFILE = __DIR__."/SECMAIL_".time();
		copy($this->PUBLICKEY, $TMPFILE);
		$msg = Crypt::encrypt(sha1_file($this->PUBLICKEY), "Your contact request was accepted.");
		file_put_contents($TMPFILE, $this->ME . "\n" . $this->SERVERS[array_pop(explode("@", $this->ME))] . "\n" . $this->ADDRESSBOOK[$this->MEID]["name"] . "\n" . $msg, FILE_APPEND);
		
		$serv = explode(":", $this->SERVERS[$request["serverName"]]);
		$TOID = $request["id"];
		$TARGET = $serv[2]."/$TOID-" . uniqid("".time(), true) . ".secmail";
		$creds = $serv[0] . ":" . $serv[1];

		$this->conn->upload($TMPFILE, $TARGET, $creds);
		unlink($TMPFILE);

		$this->addAddress($request["name"], "");
	}

	protected function sendContact($to, $msg) {
		$toparts = explode("@", trim($to));
		$toid = sha1($toparts[0]);
		$tokey = "{$this->KEYDIR}/$toid.public.pem";
		$server = $toparts[1];
		$serv = explode(":", $this->SERVERS[$server]);
		$TARGET = $serv[2]."/".$toid."-".uniqid("".time(), true).".secmail";
		$TMPFILE = __DIR__."/SECMAIL_".time();

		copy($this->PUBLICKEY, $TMPFILE);

		$msg = Crypto::encrypt(sha1(trim(file_get_contents($this->PUBLICKEY))), $msg);
		file_put_contents($TMPFILE, $this->ME . "\n" . $this->SERVERS[$this->MESERVER] . "\n".$this->ADDRESSBOOK[$this->MEID]["name"]."\n" . $msg, FILE_APPEND);

		$this->conn->upload($TMPFILE, $TARGET, $creds);
		unlink($TMPFILE);
	}

	protected function verifyContact($id, $code) {
		$user = $this->ADDRESSBOOK[$id];
		$key = $this->KEYDIR . "/" . $id . ".public.pem";
		if (!file_exists($key)) {
			throw new Exception("Unable to verify fingerprint: user not found.");
		}
		$content = file_get_contents($key);
		$publickey = trim(substr($content, 0, strpos($content, "-----END PUBLIC KEY-----") + strlen("-----END PUBLIC KEY-----")));
		$fpCheck = substr(sha1($publickey), 0, 6);

		if ($fpCheck == $code) {
			$this->ADDRESSBOOK[$id]["fingerprint"] = $fpCheck;
			$this->writeAddressbook();
			return true;
		} else {
			unset($this->ADDRESSBOOK[$id]);
			$this->writeAddressbook();
			unlink($key);
			return false;
		}
	}

	public function setPrimaryServer($id, $uid, $server = null) {
		$id = intval($id);
		if (($id == null || $id === 0) && is_array($server)) {
			$conn = trim($server["user"]) . ":" . trim($server['pass']) . ":" . trim($server['host']);
			file_put_contents(__DIR__, trim($server['name']) . "=" . $conn);
			$this->SERVERS[trim($server['name'])] = $conn;
			$id = count($this->SERVERS) - 1;
		}

		$serverList = array_keys($this->SERVERS);
		$this->ME = $uid . "@" . $serverList[$uid - 1];
		file_put_contents("{$this->KEYDIR}/id", $this->ME);
		chmod("{$this->KEYDIR}/id", 0700);
	}

	protected function render() {
		throw new Exception("Render stub not implemented.");
	}	
}
?>