<?php
class WebDAVServer {

	protected $request;
	protected $conn;

	protected function open($url, $method, $creds) {
		// parsing the given URL
        $URL_Info = parse_url($url);

        // Find out which port is needed - if not given use standard (=80) 
        if (!isset($URL_Info["port"])) 
          $URL_Info["port"] = 443;

        $this->conn = fsockopen("ssl://" . $URL_Info["host"],$URL_Info["port"]); 

        $this->request = $method . " " . $URL_Info["path"] . " HTTP/1.1\n"; 
        $this->request .= "Host: " . $URL_Info["host"] . "\n"; 
        $this->request .= "Authorization: Basic " . base64_encode($creds) . "\n";
	}

	protected function addHeader($name, $value) {
		$this->request .= $name . ": " . $value . "\n";
	}

	protected function send($mixed) {
		$this->addHeader("Connection", "close");

		if (gettype($mixed) == "string") {
			$this->request .= "\n" . $mixed;
			fputs($this->conn, $this->request);
		} else {
			$this->request .= "\n";
			fputs($this->conn, $this->request);
			$bytes = 0;
	        while (!feof($mixed)) {
	        	# send file
	        	$res = fread($mixed, 1024);
	        	fputs($this->conn, $res);
	        	$bytes += strlen($res);
	        }
		}

        # read response
        $result = "";
        while(!feof($this->conn)) { 
            $result .= fgets($this->conn, 128); 
        } 
        fclose($this->conn);

        return $result;
	}

	public function upload($file, $target, $creds) {
		$fh = fopen($file, "r");

        # send header
        $this->open("https://" . $target, "PUT", $creds);
        $this->addHeader("Content-length", filesize($file));
        $result = $this->send($fh);
        fclose($fh);

        $result = trim($result);
        $status = preg_replace("#HTTP/1\.[01] #", "", substr($result, 0, strpos($result, "\n")));
        $status = explode(" ", $status);
        $statusCode = intval($status[0]);
        $statusMsg = implode(" ", array_splice($status, 1));

        if ($statusCode >= 300) {
        	throw new Exception("Unable to send message: " . $statusCode . " - " . $statusMsg);
        }
	}

	public function delete($target, $creds) {
		$ch = curl_init("https://" . $target);
	 
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ch, CURLOPT_USERPWD, $creds);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		$result = curl_exec($ch);
	}

	public function getList($target, $creds) {
		$this->open("https://" . $target, "PROPFIND", $creds);
		$this->addHeader("Content-Type", "text/xml");
		$this->addHeader("Depth", 1);

		$xml  = "<?xml version=\"1.0\"?>\r\n";
        $xml .= "<A:propfind xmlns:A=\"DAV:\">\r\n";
        $xml .= "    <A:allprop/>\r\n";
        $xml .= "</A:propfind>\r\n";

        $this->addHeader("Content-length", strlen($xml));
		$result = $this->send($xml);

		$list = Array();
		preg_match_all('#<(?:[a-zA-Z]+:)?displayname>([^<]+)</(?:[a-zA-Z]+:)?displayname>#m', $result, $matches);
		foreach($matches[1] as $match) {
			$list[] = $match;
		}

		return $list;
	}

	public function download($file, $creds) {
		$ch = curl_init("https://" . $file);
	 
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ch, CURLOPT_USERPWD, $creds);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}	
}
?>