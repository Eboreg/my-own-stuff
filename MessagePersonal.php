<?php
require_once('DBSession.php');
require_once('util.php');
require_once('AdsysMailer.php');
require_once('SMS_GenericMobile.php');

/**
 * Implementeras av SMS- och e-postklasser eller vad man vill
 */
abstract class MessagePersonal 
{
	/**
	 * Gemensamt för sms o mejl: till_personal, fran_personal, text, timestamp, error_code, error_string, bekraftelse, DB, SQL-funktioner
	 */
	public $bekraftelse;   // boolean
	public $text;
	public $pass;          // array
	protected $db;
	protected $tillPersonal;  // resultat av showPersonal()
	protected $franPersonal;  // resultat av showPersonal()
	private $_errorCode;      // kanske ej behövs?
	private $_errorString;    // dito ^
	const DEBUG = false;
	const PASS_TABLE = 'log';
	const MESSAGE_TABLE = 'messages';
	const MESSAGE_PASS_TABLE = 'messages_pass';

	/**
	 * @uses MessagePersonal::setFranPersonal()
	 * @usedby SmsPersonal::__construct()
	 * @usedby MailPersonal::__construct()
	 */
	function __construct() {
		DBSession::sessionStart();
		$this->db = $_SESSION['dbSession'];
		$this->bekraftelse = false;
		$this->_errorCode = 0;
		$this->_errorString = '';
		$this->text = null;
		$this->setFranPersonal($this->db->dbUser());
		$this->tillPersonal = null;
		$this->pass = null;
	}

	/**
	 * Används för att sätta både franPersonal och tillPersonal
	 * 
	 * @usedby MessagePersonal::setFranPersonal()
	 * @usedby MessagePersonal::setTillPersonal()
	 * @throws Exception
	 */
	private function _setPersonal($personnr, $propName) {
		if (empty($personnr)) {
			throw new Exception('Inget personnummer angivet');
		} else {
			$personal = $this->db->call('showPersonal', $personnr)[0];
			if (empty($personal['personnummer'])) {  // personal finns ej
				throw new Exception("Kunde inte hitta personal med personnummer ".$personnr);
			} else {
				$this->$propName = $personal;
			}
		}
	}

	/**
	 * Kollar så att minimiinfo finns för att sända (text, franPersonal).
	 * 
	 * @usedby SmsPersonal::send()
	 * @usedby MailPersonal::send()
	 * @throws Exception
	 */
	protected function preSend() {
		if (null === $this->text) {
			throw new Exception('Ingen meddelandetext angiven');
		}
		if (null === $this->franPersonal) {
			throw new Exception('Avsändande personal saknas');
		}
		$this->text = trim($this->text);
	}

	/**
	 * $data == assoc. array (kolumn => värde)
	 * 
	 * @usedby MessagePersonal::logByPass()
	 * @usedby SmsPersonal::_log()
	 * @usedby MailPersonal::_log()
	 * @throws Exception
	 */
	protected function sqlInsert($table, $data, $ignore = false) {
		$escapedData = array();
		foreach ($data as $idx => $value) {
			$escapedData["`".$idx."`"] = (null === $value ? "NULL" : "'".$this->db->real_escape_string($value)."'");
		}
		$sql = "INSERT ".($ignore ? "IGNORE " : "")."INTO `".$table."` (".implode(', ', array_keys($escapedData)).") VALUES (".implode(', ', $escapedData).");";
		if (false === $this->db->execute($sql)) {  // Fel vid SQL-insert
			throw new Exception('[sqlInsert()] '.$this->db->dbErrorString());
		}
	}

	/**
	 * $pk == assoc. array för primärnyckel, t.ex. array('id' => '12842')
	 * $data == assoc. array (kolumn => värde)
	 *
	 * @usedby MessagePersonal::logByPass()
	 * @throws Exception
	 */
	protected function sqlUpdate($table, $pk, $data) {
		$escapedData = array();
		foreach ($data as $idx => $value) {
			$escapedData[] = "`".$idx."` = ".(null === $value ? "NULL" : "'".$this->db->real_escape_string($value)."'");
		}
		foreach ($pk as $idx => $value) {
			$escapedPk[] = "`".$idx."` = ".(null === $value ? "NULL" : "'".$this->db->real_escape_string($value)."'");
		}
		$sql = "UPDATE `".$table."` SET ".implode(', ', $escapedData)." WHERE ".implode(' AND ', $escapedPk);
		if (false === $this->db->execute($sql)) {  // Fel vid SQL-insert
			throw new Exception('[sqlInsert()] '.$this->db->dbErrorString());
		}
	}

	/**
	 * Loggar till messages_pass och kontaktad för varje ev. inblandat pass.
	 * Används av ärvande klassers loggmetoder efter att rad satts in i `messages`.  
	 * $message_id = autoincrement-ID i `messages`
	 *
	 * @uses MessagePersonal::sqlInsert()
	 * @uses MessagePersonal::sqlUpdate()
	 * @usedby SmsPersonal::_log()
	 * @usedby MailPersonal::_log()
	 * @throws Exception
	 */
	protected function logByPass($message_id) {
		$errors = array();
		foreach ($this->pass as $pass) {
			try {
				$this->sqlInsert(self::MESSAGE_PASS_TABLE, array('message_id' => $message_id, 'pass_id' => $pass));
				if ($this->bekraftelse)
					$this->sqlUpdate(self::PASS_TABLE, array('id' => $pass), array('bekraftat' => 1));
			} catch (Exception $e) {
				$errors[] = $e->getMessage();
			}
			if (null !== $this->tillPersonal) {
				$sqldata = array('personal' => $this->tillPersonal['personnummer'],
				                 'pass' => $pass,
				                 'kan_ej' => 0
				                 );
				try {
					$this->sqlInsert('kontaktad', $sqldata, true);
				} catch (Exception $e) {
					$errors[] = $e->getMessage();
				}
			}
		}
		if (!empty($errors)) {
			throw new Exception(implode('<br />', $errors));
		}
	}

	/**
	 * $personnr kan även vara användarnamn
	 *
	 * @uses MessagePersonal::_setPersonal()
	 * @usedby MessagePersonal::__construct()
	 * @throws MessagePersonalException
	 */
	public function setFranPersonal($personnr) {
		try {
			$this->_setPersonal($personnr, 'franPersonal');
		} catch (Exception $e) {
			throw new MessagePersonalException($e->getMessage());
		}
	}

	/**
	 * $personnr kan även vara användarnamn
	 *
	 * @uses MessagePersonal::_setPersonal()
	 * @throws MessagePersonalException
	 */
	public function setTillPersonal($personnr) {
		try {
			$this->_setPersonal($personnr, 'tillPersonal');
		} catch (Exception $e) {
			throw new MessagePersonalException($e->getMessage());
		}
	}

	/**
	 * Returnerar fullständigt namn på mottagare
	 */
	public function getTillNamn() {
		if (null !== $this->tillPersonal)
			return $this->tillPersonal['fornamn'].' '.$this->tillPersonal['efternamn'];
		return null;
	}

} // class MessagePersonal

class SmsPersonal extends MessagePersonal 
{
	private $_sms;         // SMS-objekt
	public $tillMobilNr;   // (alternativt) nummer
	private $_franMobilKey;      // token; sätts med setFranMobilNr() pga koll med databas m.m. måste göras
	private $_franMobilNr;       // nummer; sätts också med setFranMobilNr()
	private $_franMobilPersonnr; // personnummer för avsändande mobils ägare (behöver ej vara samma som avsändaren)

	/**
	 * @uses MessagePersonal::__construct()
	 */ 
	function __construct() { 
		$this->_franMobilNr = null;
		$this->_franMobilKey = null;
		$this->_franMobilPersonnr = null;
		$this->tillMobilNr = null;
		parent::__construct();
	}

	/**
	 * Sätt avsändarnummer.
	 * @throws MessagePersonalException
	 */
	public function setFranMobilNr($nr) {
		$fran = $this->db->execute("SELECT * FROM `mobiler` WHERE `nummer` = '".$nr."'");
		if (!isset($fran[0])) {
			throw new MessagePersonalException('SMS ej skickat; '.$nr.' är inte ett giltigt avsändarnummer');
		}
		$this->_franMobilNr = $nr;
		$this->_franMobilKey = $fran[0]['user_key'];
		$this->_franMobilPersonnr = $fran[0]['personnummer'];
	}

	/**
	 * Minimiinfo: parent::text, self::_franMobilNr, parent::franPersonal, (parent::tillPersonal || self::tillMobilNr)
	 * Vilken SMS-klass som används avgörs av vilken SMS_*.php som inkluderas överst i denna fil.
	 *
	 * @uses MessagePersonal::preSend()
	 * @uses SMS::__construct()
	 * @uses SMS::send()
	 * @uses SmsPersonal::_log()
	 * @throws MessagePersonalException
	 * @throws SQLException
	 */
	public function send() {
		try {
			parent::preSend();
		} catch (Exception $e) {
			throw new MessagePersonalException($e->getMessage());
		}
		/*
		  if (null === $this->_franMobilKey) {
		  throw new MessagePersonalException('SMS ej skickat; API-nyckel för avsändare ej angiven');
		  }
		*/
		if (null === $this->_franMobilNr) {
			throw new MessagePersonalException('SMS ej skickat; Nummer för avsändare ej angivet');
		}
		if (null === $this->tillMobilNr && null === $this->tillPersonal) {
			throw new MessagePersonalException('SMS ej skickat; Varken personnummer eller mobilnummer för mottagare angiven');
		}
		// Om vi kommer hit, har vi både meddelandetext, avsändarpersondata, avsändarmobildata
		// och antingen mottagarpersondata eller mottagarmobilnummer:
		$tillMobilNr = null === $this->tillMobilNr ? $this->tillPersonal['mobiltel'] : $this->tillMobilNr;
		//$this->_sms = new SMS($this->_franMobilNr, $this->_franMobilKey);
		$this->_sms = new SMS($this->_franMobilNr, $tillMobilNr, $this->text);
		try {
			$this->_sms->send();
		} catch (Exception $smsex) {
			try {
				$this->_log($tillMobilNr, $smsex->getCode(), $smsex->getMessage());
			} catch (Exception $e) {
				throw new MessagePersonalException('SMS ej skickat; '.$smsex->getMessage().' - Kunde dessutom ej logga: '.$e->getMessage());
			}
			throw new MessagePersonalException('SMS ej skickat; '.$smsex->getMessage());
		}
		// Om vi kommer hit, har SMS skickats framgångsrikt:
		try {
			$this->_log($tillMobilNr);
		} catch (Exception $e) {
			throw new SQLException('SMS skickat, men kunde inte logga: '.$e->getMessage());
		}
	}

	/**
	 * Skriv till logg efter skickat SMS, oavsett lyckat eller ej.
	 * @uses MessagePersonal::sqlInsert()
	 * @uses MessagePersonal::logByPass()
	 * @usedby SmsPersonal::send()
	 * @throws Exception
	 */
	private function _log($tillMobilNr, $errorCode = 0, $errorString = '') {
		// Alltid satta när vi kommer hit: franPersonal, _franMobilPersonnr, bekraftelse, text
		// Kan men måste ej vara satta: tillPersonal, pass
		$sqldata = array('fran_personal' => $this->franPersonal['personnummer'],
		                 'fran_nr' => $this->_franMobilNr,
		                 'till_nr' => $tillMobilNr,
		                 'messagetext' => $this->text,
		                 //		     'bekraftelse' => true === (boolean)$this->bekraftelse ? 1 : 0,
		                 'typ' => 'SMS'
		                 );
		if ($errorCode > 0)
			$sqldata['error_code'] = $errorCode;
		if ($errorString !== '')
			$sqldata['error_string'] = $errorString;
		if (null !== $this->tillPersonal)
			$sqldata['till_personal'] = $this->tillPersonal['personnummer'];
		try {
			$this->sqlInsert(self::MESSAGE_TABLE, $sqldata);
		} catch (Exception $e) {
			throw $e;
		}
		$insert_id = $this->db->dbInsertId();
		try {
			$this->logByPass($insert_id);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	 * Om särskilt mottagarnummer är angivet, returnera detta.
	 * Annars returnera mottagarens registrerade mobilnummer.
	 */
	public function getTillNr() {
		if (null !== $this->tillMobilNr)
			return $this->tillMobilNr;
		else if (null !== $this->tillPersonal['mobiltel'])
			return $this->tillPersonal['mobiltel'];
		return null;
	}
} // class SmsPersonal

class MailPersonal extends MessagePersonal 
{
	public $tillEpost;     // (alternativ) mottagaradress
	public $franEpost;     // (alternativ) avsändaradress
	public $subject;
	public $copyToMe;      // om evalueras som true: skicka kopia till avsändaren
	private $_mail;        // AdsysMailer-objekt

	/**
	 * @uses MessagePersonal::construct()
	 */
	function __construct() { 
		$this->tillEpost = null;
		$this->franEpost = null;
		$this->subject = '';
		$this->copyToMe = 0;
		$this->_mail = new AdsysMailer;
		parent::__construct();
	}

	/**
	 * Skickar meddelande via AdsysMailer.
	 * @uses AdsysMailer::setFrom()
	 * @uses AdsysMailer::addBCC()
	 * @uses AdsysMailer::addAddress()
	 * @uses AdsysMailer::send()
	 * @uses MailPersonal::_log()
	 * @uses MessagePersonal::preSend()
	 * @throws MessagePersonalException
	 * @throws SQLException
	 */
	public function send() {
		try {
			parent::preSend();
		} catch (Exception $e) {
			throw new MessagePersonalException($e->getMessage());
		}
		if (null === $this->tillEpost && null === $this->tillPersonal) {
			throw new MessagePersonalException('E-post ej skickat; Varken personnummer eller e-postadress för mottagare angiven');
		}
		// Nu är franPersonal satt, kanske franEpost samt antingen tillPersonal eller tillEpost
		$franEpost = null === $this->franEpost ? $this->franPersonal['epost'] : $this->franEpost;
		$tillEpost = null === $this->tillEpost ? $this->tillPersonal['epost'] : $this->tillEpost;
		$this->_mail->Subject = utf8_decode($this->subject);
		$this->_mail->Body = utf8_decode($this->text);
		$this->_mail->Body = str_replace("\r\n", "\n", $this->_mail->Body);
		$this->_mail->Body = str_replace("\r", "\n", $this->_mail->Body);
		$this->_mail->Body = str_replace("\n", "\t\n", $this->_mail->Body); // Lura Outlook att inte rensa "extra" radbrytningar
		try {
			$this->_mail->setFrom($franEpost, $this->franPersonal['fornamn'].' '.$this->franPersonal['efternamn']);
			if ($this->copyToMe)
				$this->_mail->addBCC($franEpost);
			$this->_mail->addAddress($tillEpost, null === $this->tillPersonal ? '' : $this->tillPersonal['fornamn'].' '.$this->tillPersonal['efternamn']);
			$this->_mail->send();
		} catch(phpmailerException $mailex) {
			try {
				$this->_log($franEpost, $tillEpost, $mailex->getCode(), $mailex->getMessage());
			} catch (Exception $e) {
				throw new MessagePersonalException('E-post ej skickat; '.$mailex->getMessage().' - Kunde dessutom ej logga: '.$e->getMessage());
			}
			throw new MessagePersonalException('E-post ej skickat; '.$mailex->getMessage());
		}
		// Om vi kommer hit, har mejl skickats framgångsrikt:
		try {
			$this->_log($franEpost, $tillEpost);
		} catch (Exception $e) {
			throw new SQLException('E-post skickat, men kunde inte logga: '.$e->getMessage());
		}
	}

	/**
	 * Skriver till MySQL-logg efter skickat meddelande (oavsett lyckat eller ej).
	 * @uses MessagePersonal::sqlInsert()
	 * @uses MessagePersonal::logByPass()
	 * @usedby MailPersonal::send()
	 * @throws Exception
	 */
	private function _log($franEpost, $tillEpost, $errorCode = 0, $errorString = '') {
		// Alltid satta när vi kommer hit: franPersonal, bekraftelse, text
		// Kan men måste ej vara satta: tillPersonal
		$sqldata = array('fran_personal' => $this->franPersonal['personnummer'],
		                 'fran_epost' => $franEpost,
		                 'till_epost' => $tillEpost,
		                 'messagetext' => $this->text,
		                 //		     'bekraftelse' => true === (boolean)$this->bekraftelse ? 1 : 0,
		                 'typ' => 'E-post'
		                 );
		if ($errorCode > 0)
			$sqldata['error_code'] = $errorCode;
		if ($errorString !== '')
			$sqldata['error_string'] = $errorString;
		if (null !== $this->tillPersonal)
			$sqldata['till_personal'] = $this->tillPersonal['personnummer'];
		try {
			$this->sqlInsert(self::MESSAGE_TABLE, $sqldata);
		} catch (Exception $e) {
			throw $e;
		}
		$insert_id = $this->db->dbInsertId();
		try {
			$this->logByPass($insert_id);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	 * Om särskild e-postadress är angiven, returnera den.
	 * Annars returnera mottagarens registrerade adress.
	 */
	public function getTillEpost() {
		if (null !== $this->tillEpost)
			return $this->tillEpost;
		else if (null !== $this->tillPersonal['epost'])
			return $this->tillPersonal['epost'];
		return null;
	}
}

class MessagePersonalException extends Exception
{
	public function __toString() {
		$trace = $this->getTrace();
		if (count($trace) > 0) {
			$idx = count($trace) - 1;
			return '[ '.$trace[$idx]['class'].'::'.$trace[$idx]['function'].'() ] '.$this->message;
		} else {
			return $this->message;
		}
	}
}

class SQLException extends MessagePersonalException
{
}
