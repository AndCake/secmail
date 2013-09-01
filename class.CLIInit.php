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

	function handleContactRequest($request) {
		$fp = fopen("php://stdin", "r");

		echo "You received a contact request from {$request["name"]}: \n" . $request["message"] . "\n\nDo you want to accept it?\n";
		$accept = fgets($fp);
		if (strtolower($accept[0]) == 'y') {
			$this->acceptContact($request);

			echo "In order to verify the identity of {$request['name']}, please contact him (p.e. via phone, eMail or otherwise) to get his fingerprint.";
			echo "You can enter this fingerprint into <verifymail.php>.\n\n";
			echo "Your own fingerprint is: \t\t" . $this->ADDRESSBOOK[$this->MEID]["fingerprint"] . "\n";
		}
		fclose($fp);
	}

	function renderContactRequest($to, $body) {
		echo "Unable to send message to $to. ";
		echo "Shall I send a contact invitation?\n";

		$fp = fopen("php://stdin", "r");
		$invite = fgets($fp);
		fclose($fp);

		if (strtolower($invite[0]) == "y") {
			$this->sendContact($to, $body);
			echo "Invitation sent.";
		}
	}

	function renderMessages($messages) {
		if (count($messages) > 0) {
			echo "Received new messages (" . count($messages) . "):\n";

			foreach ($messages as $id => $msg) {
				echo $this->ADDRESSBOOK[array_shift(explode("@", $msg["From"]))]["name"] . ": " . 
					 $msg["Subject"] . " (" . $id . ")\n";
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