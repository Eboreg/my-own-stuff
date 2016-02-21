<?php
require_once("util.php");

define('SMS_DEBUG', false);
define('SMS_MAX_LENGTH', 3060);

class SMS 
{
  const URI = 'https://www.minicall.se/messitgateway/messitgateway.asmx/SendMessages';
  const USER = 'ANVÄNDARNAMN';
  const PASS = 'LÖSENORD';
  private $from_number;
  private $to_number;
  private $text;

  function __construct($from_number = '', $to_number = '', $text = '') {
    $this->from_number = $this->formatNumber($from_number);
    $this->to_number = $this->formatNumber($to_number);
    $this->text = $this->formatText($text);
  }

  /**
   * Gör om godtyckligt nummer till +46...-format (förutsätter att vi 
   * bara skickar inom Sverige)
   */
  function formatNumber($nr) {
    $nr = trim($nr);
    $nr = preg_replace('#^\+46#', '', $nr);
    $nr = preg_replace('#[^0-9]#', '', $nr);
    $nr = preg_replace('#^0#', '', $nr);
    $nr = '+46'.$nr;
    return $nr;
  }

  function getFromNumber() {
    return $this->from_number;
  }

  /**
   * Konverterar text till GSM 7-bitarsformat
   * Källa: https://en.wikipedia.org/wiki/GSM_03.38#GSM_7-bit_default_alphabet_and_extension_table_of_3GPP_TS_23.038_.2F_GSM_03.38
   * Men utan \e (0x1B) då detta ej är giltig XML
   */
  function formatText($text) {
    $valid_chars_basic = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    $valid_chars_extended = "^{}\\[~]|€";
    $text_length = mb_strlen($text, "UTF-8");
    $newtext = '';
    for ($i = 0; $i < $text_length; $i++) {
      $text_char = mb_substr($text, $i, 1, "UTF-8");
      $newtext .= (false === strpos($valid_chars_basic.$valid_chars_extended, $text_char) ? '?' : $text_char);
    }
    return $newtext;
  }

  /**
   * Möjliga felmeddelanden i XML-svaret (via trial-and-error):
   * Tom avsändarsträng: NetworkFailure
   * Felaktigt mottagarnummer, namespace, XML-syntax eller receiverType: ValidationFailed
   * Felaktigt lösenord eller användarnamn: InvalidLogin
   */
  private function __send($text) {
    $text = htmlentities($text, ENT_COMPAT | ENT_XML1);
    $xml = '<Messages xmlns="http://genericmobile.se/MessItGateway/SendMessages_20"><Message><To receiverType="Sms">'.$this->to_number.'</To><From>'.$this->from_number.'</From><Text>'.$text.'</Text></Message></Messages>';
    $postfields = array('user' => self::USER,
			'password' => self::PASS,
			'xml' => $xml,
			'async' => 'false');
    $postfields = http_build_query($postfields);
    if (false === $ch = curl_init(self::URI)) {
      //echo "FAIL: ".curl_error($ch).PHP_EOL;
      throw new Exception('Fel vid curl_init(): '.curl_error($ch));
    }
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_PORT, 443);
    //curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true); 
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true); 
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, Array(
					       "Content-Type: application/x-www-form-urlencoded",
					       "Content-Length: ".strlen($postfields),
					       "Pragma: no-cache"
					       ));
    if (false === $result = curl_exec($ch)) {
      throw new Exception('Fel vid curl_exec(): '.curl_error($ch));
    }
    $result = simplexml_load_string($result);
    $result = simplexml_load_string((string) $result);
    if (!isset($result->Result->Code)) {
      throw new Exception('Servern returnerade ogiltigt svar; oklart vad som hänt');
    }
    switch ($result->Result->Code) {
    case 'Success':
      return true;
    case 'AccountLocked':
      throw new Exception('AccountLocked: Kontot är låst eller deaktiverat');
      break;
    case 'InvalidLogin':
      throw new Exception('InvalidLogin: Användarnamn och/eller lösenord är ogiltigt');
      break;
    case 'ValidationFailed':
      throw new Exception('ValidationFailed: Ogiltig XML skickad');
      break;
    case 'NetworkFailure':
      throw new Exception('NetworkFailure: Internt nätverksfel hos Generic Mobile');
      break;
    case 'IllegalCharacter':
      throw new Exception('IllegalCharacter: Ogiltigt tecken i meddelandet');
      break;
    case 'UnexpectedError':
      throw new Exception('UnexpectedError: Okänt/övrigt fel');
      break;
    default:
      throw new Exception('Okänt fel');
    }
  } // __send()
	
  function send() {
    // Om meddelandet måste splittas i flera SMS:
    if (strlen($this->text) > SMS_MAX_LENGTH) {
      $text_arr = str_split_at_newline($this->text, SMS_MAX_LENGTH);
      if (SMS_DEBUG)
	print_r($text_arr);
      foreach ($text_arr as $idx => $text) {
	if (count($text_arr) > 1) {
	  $text = '('.($idx+1).'/'.count($text_arr).")\n".$text;
	}
	try {
	  $this->__send($text);
	} catch (Exception $e) {
	  throw $e;
	}
	if ($idx < (count($text_arr) - 1))
	  sleep(1);
      }
    } else {  // Endast ett SMS
      if (SMS_DEBUG)
	print_r($this->text);
      try {
	$this->__send($this->text);
      } catch (Exception $e) {
	throw $e;
      }
    } // if...else
    return true;
  } // send()
}
