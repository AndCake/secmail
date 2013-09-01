<?php

class Crypto {
	/**
	 * Crypto::decrypt($password, $data) -> String
	 * - $password (String) - the password that was originally used for encryption
	 * - $data (String) - the encrypted data to be decrypted
	 *
	 * This method decrypts an AES encrypted string. The decrypted, clear-text data
	 * is returned.
	 **/
	public static function decrypt($password, $edata) {
	    $data = base64_decode($edata);
	    $salt = substr($data, 8, 8);
	    $ct = substr($data, 16);

	    $rounds = 3;
	    $data00 = $password.$salt;
	    $md5_hash = array();
	    $md5_hash[0] = md5($data00, true);
	    $result = $md5_hash[0];
	    for ($i = 1; $i < $rounds; $i++) {
			$md5_hash[$i] = md5($md5_hash[$i - 1].$data00, true);
			$result .= $md5_hash[$i];
	    }
	    $key = substr($result, 0, 32);
	    $iv  = substr($result, 32,16);

	    $result = openssl_decrypt($ct, 'aes-256-cbc', $key, true, $iv);
	    return $result;
	}

	/**
	 * Crypto::encrypt($password, $data) -> String
	 * - $password (String) - the password to use for encryption
	 * - $data (String) - the data to be encrypted
	 *
	 * This method runs a AES encryption with the given password
	 * and data. The encrypted data is returned.
	 **/
	public static function encrypt($password, $data) {
	    # Set a random salt
	    $salt = openssl_random_pseudo_bytes(8);

	    $salted = '';
	    $dx = '';
	    # Salt the key(32) and iv(16) = 48
	    while (strlen($salted) < 48) {
			$dx = md5($dx.$password.$salt, true);
			$salted .= $dx;
	    }

	    $key = substr($salted, 0, 32);
	    $iv  = substr($salted, 32,16);

	    $encrypted_data = openssl_encrypt($data, 'aes-256-cbc', $key, true, $iv);
	    return base64_encode('Salted__' . $salt . $encrypted_data);
	}
}
?>