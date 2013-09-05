<?php
include_once("class.AbstractInit.php");
class CLIInit extends AbstractInit {
	private $uname;
	private $server;
	private $host;
	private $alias;
	private $user;
	private $pass;

	function __construct($conn) {
		parent::__construct($conn);
	}

	function render() {
		$fp = fopen("php://stdin", "r");
		echo "This is the first time you use SecMail. Please enter your username:\n";
		$this->uname = fgets($fp);

		echo "Please choose your primary server:\n";
		$i = 0;
		foreach ($this->SERVERS as $key => $value) {
			$i++;
			echo "[$i] $key\n";
		}
		echo "[0] Other\n";
		echo "> ";
		$this->server = fgets($fp);

		if (intval($this->server) == 0) {
			echo "Enter global/public username:\n";
			$this->user = fgets($fp);
			echo "Enter global/public password:\n";
			$this->pass = fgets($fp);
			echo "Enter Hostname and path [myserver.org/that/webdav/path]:\n";
			$this->host = fgets($fp);
			echo "Enter Alias:\n";
			$this->alias = fgets($fp);
		}

		fclose($fp);

		$this->setInitData();
	}

	function renderContactRequest($to, $body) {
		echo "Unable to send message to $to. ";
		echo "No such user.\n";
	}

	function renderMessages($messages) {
		if (count($messages) > 0) {
			echo "Received new messages (" . count($messages) . "):\n";

			foreach ($messages as $id => $msg) {
				echo $this->ADDRESSBOOK[array_shift(explode("@", $msg["message"]["header"]["From"]))]["name"] . ": " . 
					 $msg["message"]["header"]["Subject"] . " (" . $id . ")\n";
			}
		} else {
			echo "No new messages received.";
		}
	}

	function renderMessage($msg) {
		foreach ($msg["header"] as $name => $value) {
			echo $name . ": " . $value . "\n";
		}
		echo "\n" . $msg['body'];
	}

	function setInitData() {
		$uid = $this->addAddress($this->uname);
		$this->setPrimaryServer($this->server, $uid, Array(
			"user" => $this->user,
			"pass" => $this->pass,
			"host" => $this->host,
			"name" => $this->alias
		));
	}
}
?>