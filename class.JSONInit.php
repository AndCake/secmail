<?php
include_once("class.AbstractInit.php");
class JSONInit extends AbstractInit {
	private $uname;
	private $server;
	private $host;
	private $alias;
	private $user;
	private $pass;

	function __construct($conn, $ignoreSetupFailure = false) {
		parent::__construct($conn, $ignoreSetupFailure);
	}

	function render() {
		if (empty($_POST['setup'])) {
			echo '{"error": "setup required"}';
			die();
		} else {
			$this->setInitData($_POST['setup']);
		}
	}

	function renderServerList() {
		echo json_encode(array_keys($this->SERVERS));
	}

	function renderProfile() {
		$contact = file_get_contents($this->PUBLICKEY) . 
			 $this->MESERVER . "=" . $this->SERVERS[$this->MESERVER] . "\n" . 
			 $this->ADDRESSBOOK[$this->MEID]["name"] . "\n" . $this->MEID;

		$contact = base64_encode(gzdeflate($contact, 9));
		echo '{"me": ' . json_encode($contact) . ', "name": ' . json_encode($this->ADDRESSBOOK[$this->MEID]["name"]) . '}';
	}

	function renderMessages($messages) {
		if (count($messages) > 0) {
			echo "[";
			$i = 0;
			foreach ($messages as $id => $msg) {
				if ($i > 0) {
					echo ",\n";
				}
				$message = Array(
					"id" => $id,
					"from" => $msg["message"]["header"]["From"]["name"],
					"to" => $this->ADDRESSBOOK[$msg["to"]]["name"],
					"message" => $msg
				);
				echo json_encode($message);
				$i++;
			}
			echo "]";
		} else {
			echo "[]";
		}
	}

	function renderAddressBook() {
		$json = "[";
		$i = 0;
		if (is_array($this->ADDRESSBOOK)) {
			foreach ($this->ADDRESSBOOK as $id => $address) {
				if ($i++ > 0) $json .= ", ";
				$json .= '{"key": "'.$id.'", "value": "' . $address["name"] . '"}';
			}
		}
		$json .= "]";
		echo $json;
	}

	function setInitData($data) {
		$this->MEID = sha1($data["uname"] . uniqid() . microtime(true));
		$this->createKeypair();
		$uid = $this->addAddress($this->MEID, $data['uname'], "");
		$this->setPrimaryServer($data['server'], $this->MEID, Array(
			"user" => $data["user"],
			"pass" => $data["pass"],
			"host" => $data["host"],
			"name" => $data["alias"]
		));
	}
}
?>