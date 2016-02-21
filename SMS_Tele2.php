<?php
require_once('ChromePhp.php');

/**
 * Gräns för SMS-längd via Tele2:s API: 3667 tecken
 * (Framforskat via trial-and-error mars 2015)
 */

define('SMS_MAXLENGTH', 3667);

class SMS
{
	const STATUS_SUCCESS="100";
	const STATUS_NOT_ALLOWED = "200"; 		// MSISDN is not accepted by operator
	const STATUS_WRONG_FORMAT = "201";		// MSISDN is in wrong format
	const STATUS_INVALID_MSISDN = "202";	// Errors with account
	const STATUS_WRONG_CODE = "203";		// Wrong registration code 
	const STATUS_NO_MSISDN_SPEC = "204";	// Invalid MSISDN
	const STATUS_APPID_ERROR = "206";		// Application ID errors
	const STATUS_FAULTY_XML = "300";		// Faulty XML request

	private $app_id = '1919209850714612876';
	private $url = "http://83.138.172.112/mmpNG/ws_xml.html";  // gardera för DNS-strul
	//  private $url = "http://tele2se.msgsend.com/mmpNG/ws_xml.html";
	public $curl_status;
	public $curl_error;
	public $curl_errno;

	/*	
		Register response:
	
		<result code="100"></result>
	*/	
	function register($number, $email) {
		$xml="<register appId=\"".$this->app_id."\" phoneNumber=\"$number\" email=\"$email\"/>";
		$data = $this->send_xml($xml);
		if ($this->curl_errno != 0) return false;
		$result=simplexml_load_string($data);
		return (($result['code'] == self::STATUS_SUCCESS)?true:(string)$result['code']);
	}

	function error_string($code) {
		switch($code) {
		case self::STATUS_NOT_ALLOWED:
			return "MSISDN is not accepted by operator";
		case self::STATUS_WRONG_FORMAT:
			return "MSISDN is in wrong format";
		case self::STATUS_INVALID_MSISDN:
			return "Errors with account";
		case self::STATUS_WRONG_CODE:
			return "Wrong registration code";
		case self::STATUS_NO_MSISDN_SPEC:
			return "Invalid MSISDN";
		case self::STATUS_APPID_ERROR:
			return "Application ID errors";
		case self::STATUS_FAULTY_XML:
			return "Faulty XML request";
		default:
			return "Okänt SMS-fel";
		}
	}

	/*
	  Activation response:

	  <result code="100"><message><![CDATA[10000 151b17765f112962364b4a1a2c6a6633736c2f7e]]></message></result>
	*/	
	function activate($number, $code) {
		$xml="<sendRegistrationCode appId=\"".$this->app_id."\" phoneNumber=\"$number\" code=\"$code\" />";
		$data = $this->send_xml($xml);
		if ($this->curl_errno != 0) return false;
		$result=simplexml_load_string($data);
		if ($result['code'] == self::STATUS_SUCCESS) {
			$user_key=substr($data,strpos($data,"[CDATA[",0)+7);
			$user_key=substr($user_key,0,strpos($user_key,"]",0)-1);
			return $user_key;
		}
		else return (string)$result['code'];
	}

	/*
	  Send SMS response:

	  <result code="100"><message><![CDATA[2326514]]></message></result>

	  Om funktionen returnerar sträng med mer än 3 tecken, är det ett SMS-id och sändningen lyckad
	*/	
	function sendSMS($user_key, $recipient, $text) {
		//$recipient = $this->format_number($recipient);
		$text = htmlspecialchars($text, ENT_COMPAT | ENT_XML1);
		$xml="<sendSMS appId=\"".$this->app_id."\">\n<key>$user_key</key>\n<text>$text</text>\n<phoneNumber>$recipient</phoneNumber>\n</sendSMS>";
		$data = $this->send_xml($xml);
		ChromePhp::log('sendSMS() skickar data', $xml);
		ChromePhp::log('sendSMS() returnerar', $data);
		if ($this->curl_errno != 0) return false;
		$result = simplexml_load_string($data);
		if ($result['code'] == self::STATUS_SUCCESS) {
			$sms_id = substr($data, strpos($data, "[CDATA[", 0) + 7);
			$sms_id = substr($sms_id, 0, strpos($sms_id, "]", 0));
			return $sms_id;
		}
		else return (string)$result['code'];
	}

	function format_number($nr) {
		$nr = trim($nr);
		$nr = preg_replace('#^\+46#', '', $nr);
		$nr = preg_replace('#[^0-9]#', '', $nr);
		$nr = preg_replace('#^0#', '', $nr);
		$nr = '+46'.$nr;
		return $nr;
	}
	
	// used for sending XML request to the server. add common wrapping xml code 
	function send_xml($code) {
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n$code";
		$ch = curl_init($this->url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		$data = curl_exec($ch);
		$this->curl_status = curl_getinfo($ch);
		$this->curl_errno = curl_errno($ch);
		$this->curl_error = curl_error($ch);
		curl_close($ch);
		return $data;
	}
}
