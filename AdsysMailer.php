<?php
require_once('PHPMailer/PHPMailerAutoload.php');
require_once('util.php');

/**
 * Wrapper för PHPMailer med våra lokala inställningar
 * De publika metoderna kastar phpmailerException vid fel
 */
class AdsysMailer extends PHPMailer
{
	private $provider;
	private $_db;

	/**
	 * Snitsigast vore förstås att göra detta till en abstrakt klass
	 * och skapa en implementering för varje e-postleverantör.
	 * Men skit samma.
	 */
	function __construct($provider = 'fsdata') {
		parent::__construct(true);
		$this->isSMTP();
		$this->provider = $provider;
		switch ($this->provider) {
		case 'fsdata':
			$this->Host = 'smtp.fsdata.se';
			$this->Port = 587;
			$this->SMTPAuth = true;
			break;
		default: // fallback på crossnet om värdet är något annat
			$this->provider = 'crossnet';
			$this->Host = 'mailrelay.crossnet.net';
			$this->Port = 2525;
			$this->SMTPAuth = true;
			$this->Username = '--- USERNAME ---';
			$this->Password = '--- PASSWORD ---';
		}
	}

	public function setFrom($email, $name = '') {
		if ('fsdata' === $this->provider) {
			$this->Username = $email;
			try {
				$this->Password = $this->_getSMTPPassword($email);
			} catch (Exception $e) {
				throw new phpmailerException($e->getMessage());
			}
		}
		try {
			parent::setFrom($email, $name);
		} catch (phpmailerException $e) {
			throw $e;
		}
	}

	private function _getSMTPPassword($franEpost) {
		require_once('util.php');
		$mysqli = new mysqli('localhost', MYSQL_USER, MYSQL_PASS, 'adsys');
		if ($mysqli->connect_error) {
			throw new Exception($mysqli->connect_error);
		}
		$sql = "SELECT pass FROM mail_senders WHERE user = '".$mysqli->real_escape_string($franEpost)."';";
		if (false === $result = $mysqli->query($sql)) {
			throw new Exception($mysqli->error);
		}
		if (0 === $result->num_rows) {
			throw new Exception("Ogiltig avsändaradress: ".$franEpost);
		}
		$row = $result->fetch_assoc();
		$mysqli->close();
		return $row['pass'];
	}
}

function adsysSimpleMailer($to, $subject, $message) {
	try {
		$mail = new AdsysMailer;
		$mail->AltBody = mb_convert_encoding($message, "ISO-8859-15", 'utf-8');
		$mail->Body = '<html><body>'.mb_convert_encoding(str_replace("\n", "<br />", $message), "ISO-8859-15", 'utf-8').'</body></html>';
		$mail->setFrom('info@mixmedicare.se', 'Mix Medicare');
		$mail->Subject = mb_convert_encoding($subject, "ISO-8859-15", 'utf-8');
		$mail->Encoding = "quoted-printable";
		if (is_array($to)) {
			foreach ($to as $addr) {
				$mail->addAddress($addr);
			}
		} else {
			$mail->addAddress($to);
		}
		$mail->send();
	} catch (phpmailerException $e) {
		return $e->getMessage();
	}
	return "Skickat!";
}

function adsysSimplePdfFileMailer($to, $subject, $message, $file, $fran_mail = 'bemanning@mixmedicare.se') {
	try {
		$mail = new AdsysMailer;
		$mail->AltBody = mb_convert_encoding($message, "ISO-8859-15", 'utf-8');
		$mail->Body = '<html><body>'.mb_convert_encoding(str_replace("\n", "<br />", $message), "ISO-8859-15", 'utf-8').'</body></html>';
		$mail->setFrom($fran_mail, 'Mix Medicare');
		$mail->Subject = mb_convert_encoding($subject, "ISO-8859-15", 'utf-8');
		$mail->Encoding = "quoted-printable";
		if (is_array($to)) {
			foreach ($to as $addr) {
				$mail->addAddress($addr);
			}
		} else {
			$mail->addAddress($to);
		}
		$mail->addAttachment($file);
		$mail->send();
	} catch (phpmailerException $e) {
		return $e->getMessage();
	}
	return "Skickat!";
}
