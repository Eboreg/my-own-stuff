<?php
require_once("util.php");

define('SMS_DEBUG', false);
define('SMS_MAX_LENGTH', 800);

class SMS
{
  private $host;
  private $base_uri;
  private $from_number;
  private $token;

  function __construct($from_number = '', $token = '') {
    $this->host = 'smidig.unotelefoni.se';
    $this->base_uri = 'https://'.$this->host.'/api/user/sms/';
    $this->from_number = $this->formatNumber($from_number);
    $this->token = $token;
  }

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

  function formatText($text) {
    // Källa: https://en.wikipedia.org/wiki/GSM_03.38#GSM_7-bit_default_alphabet_and_extension_table_of_3GPP_TS_23.038_.2F_GSM_03.38
    $valid_chars_basic = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\eÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    $valid_chars_extended = "^{}\\[~]|€";
    $text_length = mb_strlen($text, "UTF-8");
    $newtext = '';
    for ($i = 0; $i < $text_length; $i++) {
      $text_char = mb_substr($text, $i, 1, "UTF-8");
      $newtext .= (false === strpos($valid_chars_basic.$valid_chars_extended, $text_char) ? '?' : $text_char);
    }
    return $newtext;
  }

  // Vi förutsätter att $text redan har korrekt teckenuppsättning
  private function __send($to_number, $text) {
    $to_number = $this->formatNumber($to_number);
    $uri = $this->base_uri.$to_number.'?from='.$this->from_number.'&t='.$this->token;
    //$uri = $this->base_uri.$to_number.'?t='.$this->token;
    if (SMS_DEBUG)
      echo $uri."\n";
    if (false === $ch = curl_init($uri))
      throw new Exception('Fel vid curl_init(): '.curl_error($ch));
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $text);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: text/plain', 'Content-length: '.strlen($text), 'Accept: application/json'/*, 'Expect:'*/));
    //curl_setopt($ch, CURLOPT_CRLF, true); // Om radbrytningar ej blir bra, testa denna
    if (false === $result = curl_exec($ch))
      throw new Exception('Fel vid curl_exec(): '.curl_error($ch));
    $result = str_replace("\r\n", "\n", $result);
    // Nu innehåller $result alla headers plus innehåll
    do {
      $p = preg_match('#^HTTP[^ ]* ([[:digit:]]{3}) (.*)$#m', $result, $matches);
      if (SMS_DEBUG) {
	echo "Skickat:\n";
	print_r(curl_getinfo($ch));
	echo "\n\n".'$result = '."\n";
	print_r($result);
	echo "\n\n".'$matches = '."\n";
	print_r($matches);
      }
      if (false === $p || 0 === $p)
	throw new Exception('Ogiltigt svar från server');
      $http_status_code = (int)$matches[1];
      $http_status_msg = $matches[2];
      // Om rad 1 är en 100 Continue-header bör där finnas två blankrader och sedan en till HTTP-header. Strippa därför de 2 första raderna:
      if (100 == $http_status_code)
	$result = preg_replace('#^.*\n\n#', '', $result);
    } while ($http_status_code == 100);
    preg_match('#\n\n(.*)$#ms', $result, $matches);
    $http_body_json = $matches[1]; // Bodyn börjar efter första dubbla radbrytningen
    $http_body_array = json_decode($http_body_json);
    curl_close($ch);
    if ($http_status_code !== 200) {
      switch ($http_status_code) {
      case 302:
	$errstr = $this->host." ville vidarebefordra, men vi vägrar";
	break;
      case 400:
	$errstr = "Ogiltigt mottagarnummer: ".$to_number;
	break;
      case 403:
	$errstr = "Ogiltig användar-token: ".$this->token;
	break;
      case 404:
	$errstr = "Ogiltig URI: ".$this->base_uri;
	break;
      case 405:
	$errstr = "Felaktig HTTP-metod";
	break;
      case 500:
	$errstr = "Internt serverfel på ".$this->host;
	break;
      default:
	$errstr = "Okänt fel";
      }
      $errstr .= " (".$http_status_code." ".$http_status_msg.")";
      throw new Exception($errstr, $http_status_code);
    }
    return true;
  }
	
  function send($to_number, $text) {
    $text = $this->formatText($text);
    if (strlen($text) > SMS_MAX_LENGTH) {
      $text_arr = str_split_at_newline($text, SMS_MAX_LENGTH);
      if (SMS_DEBUG)
	print_r($text_arr);
      foreach ($text_arr as $idx => $text) {
	if (count($text_arr) > 1) {
	  $text = '('.($idx+1).'/'.count($text_arr).")\n".$text;
	}
	try {
	  $this->__send($to_number, $text);
	} catch (Exception $e) {
	  throw $e;
	}
	if ($idx < (count($text_arr) - 1))
	  sleep(1);
      }
    } else {
      if (SMS_DEBUG)
	print_r($text);
      try {
	$this->__send($to_number, $text);
      } catch (Exception $e) {
	throw $e;
      }
    } // if...else
    return true;
  } // send()
}

?>