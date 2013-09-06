<?php
class AbstractInit {

	public $SERVERS;
	public $ADDRESSBOOK;
	public $BOX;
	public $PUBLICKEY;
	public $PRIVATEKEY;
	public $KEYDIR;
	public $ME;
	public $MEID;
	public $MESERVER;

	private $conn;

	function __construct($conn, $ignoreSetupFailure = false) {
		$this->conn = $conn;

		# init list of servers
		$SERVLIST = file(__DIR__ . '/servers');
		$this->SERVERS = Array();
		foreach ($SERVLIST as $server) {
			if (strpos($server, "=") !== false) {
				list($name, $conn) = explode("=", $server);
				$this->SERVERS[$name] = trim($conn);
			}
		}

		$this->KEYDIR = __DIR__ . "/secmail";
		$this->PRIVATEKEY = "{$this->KEYDIR}/id.private.pem";
		$this->PUBLICKEY = "{$this->KEYDIR}/id.public.pem";

		# init addressbook
		if (file_exists($this->KEYDIR."/addressbook")) {
			$this->readAddressbook();
		} else $this->ADDRESSBOOK = Array();

		# init inbox
		if (!$ignoreSetupFailure) {
			$this->BOX["in"] = __DIR__ . "/inbox";
			if (!file_exists($this->BOX["in"])) { mkdir($this->BOX["in"], 0777, true); }
			$this->BOX["sent"] = __DIR__ . "/sent";
			if (!file_exists($this->BOX["sent"])) { mkdir($this->BOX["sent"], 0777, true); }
		}

		if (!$this->isSetup()) {
			if (!$ignoreSetupFailure) {
				@mkdir($this->KEYDIR, 0777, true);
				$this->render();
			}
		}

		if (!$ignoreSetupFailure) {

			$this->ME = file_get_contents("{$this->KEYDIR}/id");
			list($this->MEID, $this->MESERVER) = explode("@", $this->ME);

			$this->createKeypair();
		}
	}

	protected function createKeypair($addAddress = true) {
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

			chmod($this->PRIVATEKEY, 0744);
			chmod($this->PUBLICKEY, 0744);

			if ($addAddress) {
				$this->ADDRESSBOOK[$this->MEID]["fingerprint"] = substr(sha1(trim($pub)), 0, 6);
				$this->writeAddressbook();
			}
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

	public function isSetup() {
		return file_exists($this->KEYDIR) && file_exists("{$this->KEYDIR}/id");
	}

	protected function addAddress($id, $name, $fingerprint = "", $server = null, $publicKey = null) {
		$serverFileName = __DIR__ . '/servers';
		$serversFile = file_get_contents($serverFileName);
		$name = trim($name);
		$fingerprint = trim($fingerprint);
		$serv = "";

		if (!empty($publicKey)) {
			file_put_contents($this->KEYDIR . "/" . $id . ".public.pem", $publicKey);
		}
		if (!empty($server)) {
			if (strpos($serversFile, $server) === false) {
				# we don't have the server yet... add it
				file_put_contents($serverFileName, $server, FILE_APPEND);
				list($serv, $conn) = explode("=", $server);
			}
			list($serv, $conn) = explode("=", $server);
		}

		$this->ADDRESSBOOK[$id] = Array("name" => $name, "fingerprint" => $fingerprint, "server" => $serv);
		$this->writeAddressbook();

		return $id;
	}

	public function addPureContact($content) {
		$contact = $this->extractContact($content);
		$this->addAddress($contact["id"], $contact["name"], "", $contact["server"], $contact["publickey"]);
	}

	public function extractContact($contact) {
		$content = gzinflate(base64_decode($contact));
		if ($content === false || empty($content) || strpos($content, "-----END PUBLIC KEY-----") === false) {
			throw new Exception("Unable to read contact information.");
		}
		$pk = substr($content, 0, strpos($content, "-----END PUBLIC KEY-----") + strlen("-----END PUBLIC KEY-----"));
		$end = substr($content, strpos($content, "-----END PUBLIC KEY-----") + strlen("-----END PUBLIC KEY-----") + 1);

		list($server, $user, $id) = explode("\n", $end);
		if (empty($server) || empty($user)) {
			throw new Exception("Unable to read contact information: user extraction.");
		}

		return Array(
			"id" => $id,
			"name" => $user,
			"server" => $server,
			"publickey" => $pk
		);
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
		$this->ME = $uid . "@" . $serverList[$id - 1];
		file_put_contents("{$this->KEYDIR}/id", $this->ME);
		chmod("{$this->KEYDIR}/id", 0744);
	}

	protected function render() {
		throw new Exception("Render stub not implemented.");
	}	
}
?>