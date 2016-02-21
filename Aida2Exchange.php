<?php
/**
 * För att lägga till eller uppdatera personal på bemanning@mixmedicare.se-kontot:
 * 1. Skapa instans av Aida2Bemanning
 * 2. Anropa Aida2Bemanning::createOrUpdateContactPersonal($data), där $data är en 
 *    array från förslagsvis showPersonal(), med följande värden:
 *      * personnummer, (fornamn || efternamn) - obligatoriska
 *      * epost, arbtel, hemtel, mobiltel, auxtel, yrke, enhetlist, status - valfria
 * 3. Om status == 'aktiv' eller 'vilande' och kontakt ej finns: Kontakt skapas
 *    Om status == 'aktiv' eller 'vilande' och kontakt finns: Kontakt uppdateras
 *    Annars: Kontakt raderas
 * 4. `exchange_personal` uppdateras efter behov, Aida2ExchangeException kastas 
 *    vid fel
 * 
 * För att radera personal på bemanning@mixmedicare.se:
 * 1. Skapa instans av Aida2Bemanning
 * 2. Anropa Aida2Bemanning::deleteContactPersonal($personnummer)
 * 3. Kontakt raderas, rad raderas i `exchange_personal`, Aida2ExchangeException 
 *    kastas vid fel
 *
 * För att lägga till eller uppdatera kund på bemanning@mixmedicare.se-kontot:
 * 1. Skapa instans av Aida2Bemanning
 * 2. Anropa Aida2Bemanning::createOrUpdateContactKund($data), där $data är en 
 *    array från förslagsvis showKund(), med följande värden:
 *      * kundnummer, namn - obligatoriska
 *      * epost, tel, fax, enhet, status, kundtyp - valfria
 * 3. Om status == 'Aktiv' eller 'Vilande' och kontakt ej finns: Kontakt skapas
 *    Om status == 'Aktiv' eller 'Vilande' och kontakt finns: Kontakt uppdateras
 *    Annars: Kontakt raderas
 * 4. `exchange_kund` uppdateras efter behov, Aida2ExchangeException kastas 
 *    vid fel
 * 
 * För att radera kund på bemanning@mixmedicare.se:
 * 1. Skapa instans av Aida2Bemanning
 * 2. Anropa Aida2Bemanning::deleteContactKund($kundnummer)
 * 3. Kontakt raderas, rad raderas i `exchange_kund`, Aida2ExchangeException 
 *    vid fel
 */

require_once 'DBSession.php';
require_once 'ChromePhp.php';
require_once 'AdsysMailer.php';

function ews_autoload($class) {
	// Start from the base path and determine the location from the class name,
	$base_path = 'php-ews';
	$include_file = $base_path . '/' . str_replace('_', '/', $class) . '.php';
	//  require_once $include_file;
	include_once $include_file;
}
spl_autoload_register('ews_autoload');

// Vi subclassar Aida2Exchange för varje Exchange-konto
abstract class Aida2Exchange extends ExchangeWebServices
{
	protected $db;  // DBSession-objekt
	protected $debug = false;
	const EXCHANGE_HOST = 'webmail.fsdata.se';
	const EXCHANGE_VERSION = parent::VERSION_2013;
	const TABLE_PERSONAL = 'exchange_personal';
	const TABLE_KUND = 'exchange_kund';
	const TABLE_KONTAKTPERSON = 'exchange_kontaktperson';

	function __construct($user = null, $pass = null) {
		DBSession::sessionStart();
		$this->db = $_SESSION['dbSession'];
		if ('cli' === php_sapi_name())
			$this->db->forceSysad();
		parent::__construct(self::EXCHANGE_HOST, $user, $pass, self::EXCHANGE_VERSION);
	}

	/**
	 * @param $contact EWSType_ContactItemType
	 * @param $folderId String
	 * @return String ID för ny kontakt
	 * @throws Aida2ExchangeException
	 * @usedby Aida2Exchange::createContactPersonal()
	 * @usedby Aida2Exchange::createContactKund()
	 * @usedby Aida2Exchange::createContactKontaktperson()
	 */
	protected function _newContact($contact, $folderId) {
		$request = new EWSType_CreateItemType();
		$request->SavedItemFolderId = new EWSType_TargetFolderIdType();
		$request->SavedItemFolderId->FolderId = new EWSType_FolderIdType();
		$request->SavedItemFolderId->FolderId->Id = $folderId;
		$request->Items->Contact[] = $contact;
		try {
			$response = parent::CreateItem($request);
		} catch (Exception $e) {
			throw new Aida2ExchangeException($e->getMessage());
		}

		if ($this->debug)
			print_r($response);

		if ($response->ResponseMessages->CreateItemResponseMessage->ResponseClass == EWSType_ResponseClassType::SUCCESS) {
			return $response->ResponseMessages->CreateItemResponseMessage->Items->Contact->ItemId->Id;
		} else { // Fel har uppstått
			throw new Aida2ExchangeException($response->ResponseMessages->CreateItemResponseMessage->MessageText);
		}
	}

	/**
	 * @param $id String
	 * @param $updates EWSType_NonEmptyArrayOfItemChangeDescriptionsType 
	 * @param $folderId String Behöver bara anges om kontakten ska flyttas till annan folder
	 * @return String ID om allt gått väl (OBS, nytt ID om kontakt flyttats till annan folder)
	 * @throws Aida2ExchangeException
	 * @uses Aida2Exchange::_moveContact()
	 * @usedby Aida2Exchange::updateContactPersonal()
	 * @usedby Aida2Exchange::updateContactKund()
	 * @usedby Aida2Exchange::updateContactKontaktperson()
	 */
	protected function _updateContact($id, $updates, $folderId = '') {
		$request = new EWSType_UpdateItemType();
		$request->ConflictResolution = new EWSType_ConflictResolutionType();
		$request->ConflictResolution->_ = EWSType_ConflictResolutionType::ALWAYS_OVERWRITE;
		$request->ItemChanges = new EWSType_NonEmptyArrayOfItemChangesType();
		$request->ItemChanges->ItemChange = new EWSType_ItemChangeType();
		$request->ItemChanges->ItemChange->ItemId = new EWSType_ItemIdType();
		$request->ItemChanges->ItemChange->ItemId->Id = $id;
		$request->ItemChanges->ItemChange->Updates = $updates;
		if ($this->debug)
			print_r($request);
		try {
			$response = parent::UpdateItem($request);
		} catch (Exception $e) {
			throw new Aida2ExchangeException($e->getMessage());
		}

		if ($this->debug)
			print_r($response);

		if ($response->ResponseMessages->UpdateItemResponseMessage->ResponseClass == EWSType_ResponseClassType::SUCCESS) {
			if (!empty($folderId)) {
				try {
					// Kontakt får nytt ID vid flytt; returnera detta
					return $this->_moveContact($id, $folderId);
				} catch (Exception $e) {
					throw $e;
				}
			} else {
				// Returnera original-ID, kontakt har ej flyttats
				return $id;
			}
		} else { // Fel har uppstått
			throw new Aida2ExchangeException($response->ResponseMessages->UpdateItemResponseMessage->MessageText);
		}
	}

	/**
	 * @return Nytt ID för kontakt
	 * @usedby Aida2Exchange::_updateContact()
	 */
	protected function _moveContact($id, $folderId) {
		if (empty($folderId))
			return $id;
		$request = new EWSType_MoveItemType();
		$request->ItemIds->ItemId->Id = $id;
		$request->ReturnNewItemIds = "true";
		$request->ToFolderId->FolderId->Id = $folderId;
		if ($this->debug)
			print_r($request);
		try {
			$response = parent::MoveItem($request);
			if ($this->debug)
				print_r($response);
		} catch (Exception $e) {
			throw new Aida2ExchangeException($e->getMessage());
		}
		if ($response->ResponseMessages->MoveItemResponseMessage->ResponseClass == EWSType_ResponseClassType::SUCCESS) {
			if (!empty($response->ResponseMessages->MoveItemResponseMessage->Items->Contact->ItemId->Id))
				return $response->ResponseMessages->MoveItemResponseMessage->Items->Contact->ItemId->Id;
			else
				return $id;
		} else { // Fel har uppstått
			throw new Aida2ExchangeException($response->ResponseMessages->MoveItemResponseMessage->MessageText);
		}
	}

	/**
	 * @return Boolean true om allt går väl
	 * @usedby Aida2Exchange::deleteContactPersonal()
	 * @usedby Aida2Exchange::deleteContactKund()
	 * @usedby Aida2Exchange::deleteContactKontaktperson()
	 * @throws Aida2ExchangeException
	 */
	protected function _deleteContact($id) {
		$request = new EWSType_DeleteItemType();
		$request->DeleteType = new EWSType_DisposalType();
		$request->DeleteType->_ = EWSType_DisposalType::HARD_DELETE;
		$request->ItemIds = new EWSType_NonEmptyArrayOfBaseItemIdsType();
		$request->ItemIds->ItemId = new EWSType_ItemIdType();
		$request->ItemIds->ItemId->Id = $id;
		try {
			$response = parent::DeleteItem($request);
		} catch (Exception $e) {
			throw new Aida2ExchangeException($e->getMessage());
		}

		if ($this->debug)
			print_r($response);

		if ($response->ResponseMessages->DeleteItemResponseMessage->ResponseClass == EWSType_ResponseClassType::SUCCESS) {
			return true;
		} else { // Fel har uppstått
			throw new Aida2ExchangeException($response->ResponseMessages->DeleteItemResponseMessage->MessageText);
		}
	}

	/**
	 * Skapar EWSType_EmailAddressDictionaryType() från enstaka e-postadress (våra kontakter har i nuläget inte flera).
	 * @return EWSType_EmailAddressDictionaryType
	 * @usedby Aida2Exchange::_createEmailAddressUpdateField()
	 * @usedby Aida2Exchange::createContactPersonal()
	 * @usedby Aida2Exchange::createContactKund()
	 * @usedby Aida2Exchange::createContactKontaktperson()
	 */
	protected function _createEmailAddressDictionary($epost) {
		$ead = new EWSType_EmailAddressDictionaryType();
		$ead->Entry = new EWSType_EmailAddressDictionaryEntryType();
		$ead->Entry->_ = trim($epost);
		$ead->Entry->Key = new EWSType_EmailAddressKeyType();
		$ead->Entry->Key->_ = EWSType_EmailAddressKeyType::EMAIL_ADDRESS_1;
		return $ead;
	}

	/**
	 * Skapar EWSType_SetItemFieldType() från e-postadress för uppdatering av existerande kontakt.
	 * @return EWSType_SetItemFieldType
	 * @uses Aida2Exchange::_createEmailAddressDictionary()
	 * @usedby Aida2Exchange::updateContactPersonal()
	 * @usedby Aida2Exchange::updateContactKund()
	 * @usedby Aida2Exchange::updateContactKontaktperson()
	 */
	protected function _createEmailAddressUpdateField($epost) {
		$field = new EWSType_SetItemFieldType();
		$field->IndexedFieldURI->FieldURI = EWSType_DictionaryURIType::CONTACTS_EMAIL_ADDRESS;
		$field->IndexedFieldURI->FieldIndex = EWSType_EmailAddressKeyType::EMAIL_ADDRESS_1;
		$field->Contact = new EWSType_ContactItemType();
		$field->Contact->EmailAddresses = $this->_createEmailAddressDictionary($epost);
		return $field;
	}

	/**
	 * Skapar EWSType_DeleteItemFieldType() för radering av valfritt fält.
	 * @return EWSType_DeleteItemFieldType
	 * @param $fielduri String Konstant från EWSType_DictionaryURIType eller EWSType_UnindexedFieldURIType
	 * @param $fieldindex String Konstant från EWSType_EmailAddressKeyType, EWSType_PhoneNumberKeyType m.fl. Bara för indexerade fält.
	 * @usedby Aida2Exchange::updateContactPersonal()
	 * @usedby Aida2Exchange::updateContactKund()
	 * @usedby Aida2Exchange::updateContactKontaktperson()
	 */
	protected function _createDeleteItemField($fielduri, $fieldindex = null) {
		$field = new EWSType_DeleteItemFieldType();
		if ($fieldindex) {
			$field->IndexedFieldURI->FieldURI = $fielduri;
			$field->IndexedFieldURI->FieldIndex = $fieldindex;
		} else {
			$field->FieldURI->FieldURI = $fielduri;
		}
		return $field;
	}

	/**
	 * @param $keyType Konstant från EWSType_PhoneNumberKeyType
	 * @return EWSType_PhoneNumberDictionaryEntryType
	 * @usedby Aida2Exchange::_createPhoneNumberUpdateField()
	 * @usedby Aida2Exchange::createContactPersonal()
	 * @usedby Aida2Exchange::createContactKund()
	 * @usedby Aida2Exchange::createContactKontaktperson()
	 */
	protected function _createPhoneNumberDictionaryEntry($nr, $keyType) {
		$phone = new EWSType_PhoneNumberDictionaryEntryType();
		$phone->Key = new EWSType_PhoneNumberKeyType();
		$phone->Key->_ = $keyType;
		$phone->_ = $nr;
		return $phone;
	}

	/**
	 * För update-operationer på kontakters telefonnummer.
	 * @param $keyType Konstant från EWSType_PhoneNumberKeyType
	 * @return EWSType_SetItemFieldType
	 * @uses Aida2Exchange::_createPhoneNumberDictionaryEntry()
	 * @usedby Aida2Exchange::updateContactPersonal()
	 * @usedby Aida2Exchange::updateContactKund()
	 * @usedby Aida2Exchange::updateContactKontaktperson()
	 */
	protected function _createPhoneNumberUpdateField($nr, $keyType) {
		$field = new EWSType_SetItemFieldType();
		$field->IndexedFieldURI->FieldURI = EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER;
		$field->IndexedFieldURI->FieldIndex = $keyType;
		$field->Contact = new EWSType_ContactItemType();
		$field->Contact->PhoneNumbers = new EWSType_PhoneNumberDictionaryType();
		$field->Contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($nr, $keyType);
		return $field;
	}

	/**
	 * @param $data Array med data från t.ex. showPersonal(). 
	 * Obligatoriska: personnummer, (fornamn || efternamn)
	 * Valfria: epost, arbtel, hemtel, mobiltel, auxtel, yrke
	 *
	 * @param $folderId String
	 * @return String ID för nya kontakten om lyckat
	 * @uses Aida2Exchange::_createEmailAddressDictionary()
	 * @uses Aida2Exchange::_createPhoneNumberDictionaryEntry()
	 * @uses Aida2Exchange::_newContact()
	 * @usedby Aida2Exchange::updateContactPersonal()
	 * @throws Aida2ExchangeException
	 */
	public function createContactPersonal($data, $folderId) {
		ChromePhp::log('createContactPersonal', $data, $folderId);
		if (!is_array($data))
			throw new Aida2ExchangeException('Inga personaldata angivna');
		if (empty($folderId))
			throw new Aida2ExchangeException('FolderId måste anges');
		array_walk($data, function(&$value) {
				$value = trim($value);
			});

		if (empty($data['personnummer']))
			throw new Aida2ExchangeException('Personnummer måste anges');

		if (empty($data['fornamn']) && empty($data['efternamn']))
			throw new Aida2ExchangeException('Antingen för- eller efternamn måste anges');

		$contact = new EWSType_ContactItemType();
		if (!empty($data['fornamn']))
			$contact->GivenName = $data['fornamn'];
		if (!empty($data['efternamn']))
			$contact->Surname = $data['efternamn'];
		$contact->DisplayName = trim($data['fornamn'].' '.$data['efternamn']);
		if (!empty($data['yrke']))
			$contact->Profession = $data['yrke'];

		if (!empty($data['epost']))
			$contact->EmailAddresses = $this->_createEmailAddressDictionary($data['epost']);

		if (!empty($data['arbtel']) || !empty($data['hemtel']) || !empty($data['mobiltel']) || !empty($data['auxtel'])) {
			$contact->PhoneNumbers = new EWSType_PhoneNumberDictionaryType();
			if (!empty($data['arbtel']))
				$contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($data['arbtel'], EWSType_PhoneNumberKeyType::BUSINESS_PHONE);
			if (!empty($data['hemtel']))
				$contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($data['hemtel'], EWSType_PhoneNumberKeyType::HOME_PHONE);
			if (!empty($data['mobiltel']))
				$contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($data['mobiltel'], EWSType_PhoneNumberKeyType::MOBILE_PHONE);
			if (!empty($data['auxtel']))
				$contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($data['auxtel'], EWSType_PhoneNumberKeyType::OTHER_PHONE);
		}

		$contact->FileAsMapping = new EWSType_FileAsMappingType();
		$contact->FileAsMapping->_ = EWSType_FileAsMappingType::DISPLAY_NAME;

		try {
			$id = $this->_newContact($contact, $folderId);
		} catch (Exception $e) {
			throw $e;
		}

		if (false === $this->db->execute("INSERT INTO `".self::TABLE_PERSONAL."` (konto, personal, exchange_id) VALUES ('".$this->username."', '".$data['personnummer']."', '".$id."')")) {
			throw new Aida2ExchangeException('Kunde inte lägga till i databasen: '.$this->db->dbErrorString());
		}
		return $id;
	}

	/**
	 * Obligatoriska: personnummer
	 * Valfria: fornamn, efternamn, epost, arbtel, hemtel, mobiltel, auxtel, yrke, oldpnr (om personnummer ändrats)
	 *
	 * @param $folderId String Anges om kontakt ska flyttas till annan folder och/eller om man vill safe:a ifall den ej redan finns
	 * @return Boolean true om allt går väl
	 * @throws Aida2ExchangeException
	 * @uses Aida2Exchange::_createDeleteItemField()
	 * @uses Aida2Exchange::_createEmailAddressUpdateField()
	 * @uses Aida2Exchange::_createPhoneNumberUpdateField()
	 * @uses Aida2Exchange::_updateContact()
	 * @uses Aida2Exchange::createContactPersonal()
	 */
	public function updateContactPersonal($data, $folderId = '') {
		if (empty($data['personnummer']) || !is_array($data))
			throw new Aida2ExchangeException('Personnummer måste anges');

		$oldpnr = (!empty($data['oldpnr']) ? $data['oldpnr'] : $data['personnummer']);
		$sqldata = $this->db->execute("SELECT * FROM `".self::TABLE_PERSONAL."` WHERE konto = '".$this->username."' AND personal = '".$oldpnr."'");
		// Om exchange-id ej finns i DB utgår vi från att kontakten ej är skapad och gör detta istället:
		if (false === $sqldata)
			throw new Aida2ExchangeException('Databasfel: '.$this->db->dbErrorString());
		else if (0 === count($sqldata)) {
			try {
				return $this->createContactPersonal($data, $folderId);
			} catch (Aida2ExchangeException $e) {
				throw $e;
			}
		}
		$id = $sqldata[0]['exchange_id'];
		ChromePhp::log('updateContactPersonal', $data, $folderId, $id);

		array_walk($data, function(&$value) {
				$value = trim($value);
			});

		$updates = new EWSType_NonEmptyArrayOfItemChangeDescriptionsType();
		$updates->SetItemField = array();
		$updates->DeleteItemField = array();

		// Om både för- och efternamn saknas uppdaterar vi inget av dem utan går bara vidare
		if (!(empty($data['fornamn']) && empty($data['efternamn']))) {
			if (!empty($data['fornamn'])) {
				$field = new EWSType_SetItemFieldType();
				$field->FieldURI->FieldURI = EWSType_UnindexedFieldURIType::CONTACTS_GIVEN_NAME;
				$field->Contact = new EWSType_ContactItemType();
				$field->Contact->GivenName = $data['fornamn'];
				$updates->SetItemField[] = $field;
			} else
				$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_UnindexedFieldURIType::CONTACTS_GIVEN_NAME);

			if (!empty($data['efternamn'])) {
				$field = new EWSType_SetItemFieldType();
				$field->FieldURI->FieldURI = EWSType_UnindexedFieldURIType::CONTACTS_SURNAME;
				$field->Contact = new EWSType_ContactItemType();
				$field->Contact->Surname = $data['efternamn'];
				$updates->SetItemField[] = $field;
			} else
				$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_UnindexedFieldURIType::CONTACTS_SURNAME);

			$field = new EWSType_SetItemFieldType();
			$field->FieldURI->FieldURI = EWSType_UnindexedFieldURIType::CONTACTS_DISPLAY_NAME;
			$field->Contact = new EWSType_ContactItemType();
			$field->Contact->DisplayName = trim($data['fornamn'].' '.$data['efternamn']);
			$updates->SetItemField[] = $field;
		}

		if (!empty($data['yrke'])) {
			$field = new EWSType_SetItemFieldType();
			$field->FieldURI->FieldURI = EWSType_UnindexedFieldURIType::CONTACTS_PROFESSION;
			$field->Contact = new EWSType_ContactItemType();
			$field->Contact->Profession = $data['yrke'];
			$updates->SetItemField[] = $field;
		} else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_UnindexedFieldURIType::CONTACTS_PROFESSION);

		if (!empty($data['epost']))
			$updates->SetItemField[] = $this->_createEmailAddressUpdateField($data['epost']);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_EMAIL_ADDRESS, EWSType_EmailAddressKeyType::EMAIL_ADDRESS_1);

		if (!empty($data['arbtel']))
			$updates->SetItemField[] = $this->_createPhoneNumberUpdateField($data['arbtel'], EWSType_PhoneNumberKeyType::BUSINESS_PHONE);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER, EWSType_PhoneNumberKeyType::BUSINESS_PHONE);
		if (!empty($data['hemtel']))
			$updates->SetItemField[] = $this->_createPhoneNumberUpdateField($data['hemtel'], EWSType_PhoneNumberKeyType::HOME_PHONE);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER, EWSType_PhoneNumberKeyType::HOME_PHONE);
		if (!empty($data['mobiltel']))
			$updates->SetItemField[] = $this->_createPhoneNumberUpdateField($data['mobiltel'], EWSType_PhoneNumberKeyType::MOBILE_PHONE);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER, EWSType_PhoneNumberKeyType::MOBILE_PHONE);
		if (!empty($data['auxtel']))
			$updates->SetItemField[] = $this->_createPhoneNumberUpdateField($data['auxtel'], EWSType_PhoneNumberKeyType::OTHER_PHONE);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER, EWSType_PhoneNumberKeyType::OTHER_PHONE);
    
		try {
			$newId = $this->_updateContact($id, $updates, $folderId);
		} catch (Exception $e) {
			throw $e;
		}
		if (false === $this->db->execute("UPDATE `".self::TABLE_PERSONAL."` SET exchange_id = '".$newId."', personal = '".$data['personnummer']."' WHERE konto = '".$this->username."' AND personal = '".$oldpnr."'")) {
			throw new Aida2ExchangeException('Kunde inte uppdatera databasen: '.$this->db->dbErrorString());
		}
		return true;
	}

	/**
	 * Om personen ej finns i TABLE_PERSONAL, avslutas det hela bara tyst.
	 * @uses Aida2Exchange::_deleteContact()
	 * @throws Aida2ExchangeException
	 */
	public function deleteContactPersonal($personnummer) {
		$sqldata = $this->db->execute("SELECT * FROM `".self::TABLE_PERSONAL."` WHERE konto = '".$this->username."' AND personal = '".$personnummer."'");
		if (false === $sqldata)
			throw new Aida2ExchangeException('Databasfel: '.$this->db->dbErrorString());
		else if (count($sqldata) > 0) {
			$id = $sqldata[0]['exchange_id'];
			try {
				$this->_deleteContact($id);
			} catch (Exception $e) {
				throw $e;
			}
			if (false === $this->db->execute("DELETE FROM `".self::TABLE_PERSONAL."` WHERE konto = '".$this->username."' AND personal = '".$personnummer."'"))
				throw new Aida2ExchangeException('Databasfel: '.$this->db->dbErrorString());
		}
	}

	/**
	 * @param $data Array av data från t.ex. showKund()
	 * Obligatoriska: kundnummer, namn
	 * Valfria: epost, tel, fax
	 *
	 * @param $folderId String
	 * @return String ID för nya kontakten om lyckat
	 * @uses Aida2Exchange::_createEmailAddressDictionary()
	 * @uses Aida2Exchange::_createPhoneNumberDictionaryEntry()
	 * @uses Aida2Exchange::_newContact()
	 * @throws Aida2ExchangeException
	 */
	public function createContactKund($data, $folderId) {
		if (!is_array($data))
			throw new Aida2ExchangeException('Inga kunddata angivna');
		if (empty($folderId))
			throw new Aida2ExchangeException('FolderId måste anges');
		array_walk($data, function(&$value) {
				$value = trim($value);
			});

		if (empty($data['kundnummer']))
			throw new Aida2ExchangeException('Kundnummer måste anges');

		if (empty($data['namn']))
			throw new Aida2ExchangeException('Kundens namn måste anges');

		$contact = new EWSType_ContactItemType();
		$contact->CompanyName = $data['namn'];
		$contact->DisplayName = $data['namn'];

		if (!empty($data['epost']))
			$contact->EmailAddresses = $this->_createEmailAddressDictionary($data['epost']);

		if (!empty($data['tel']) || !empty($data['fax'])) {
			$contact->PhoneNumbers = new EWSType_PhoneNumberDictionaryType();
			if (!empty($data['tel']))
				$contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($data['tel'], EWSType_PhoneNumberKeyType::COMPANY_MAIN_PHONE);
			if (!empty($data['fax']))
				$contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($data['fax'], EWSType_PhoneNumberKeyType::BUSINESS_FAX);
		}

		$contact->FileAsMapping = new EWSType_FileAsMappingType();
		$contact->FileAsMapping->_ = EWSType_FileAsMappingType::DISPLAY_NAME;

		try {
			$id = $this->_newContact($contact, $folderId);
		} catch (Exception $e) {
			throw $e;
		}

		if (false === $this->db->execute("INSERT INTO `".self::TABLE_KUND."` (konto, kund, exchange_id) VALUES ('".$this->username."', '".$data['kundnummer']."', '".$id."')")) {
			throw new Aida2ExchangeException('Kunde inte lägga till i databasen: '.$this->db->dbErrorString());
		}
		return $id;
	}

	/**
	 * Obligatoriska: kundnummer
	 * Valfria: namn, epost, tel, fax
	 *
	 * @param $folderId String Anges om kontakt ska flyttas till annan folder och/eller om man vill safe:a ifall den ej redan finns
	 * @return Boolean true om allt går väl
	 * @throws Aida2ExchangeException
	 * @uses Aida2Exchange::_createEmailAddressUpdateField()
	 * @uses Aida2Exchange::_createDeleteItemField()
	 * @uses Aida2Exchange::_createPhoneNumberUpdateField()
	 * @uses Aida2Exchange::_updateContact()
	 * @todo $plats används inte
	 */
	public function updateContactKund($data, $folderId = '') {
		if (empty($data['kundnummer']) || !is_array($data))
			throw new Aida2ExchangeException('Kundnummer måste anges');

		$sqldata = $this->db->execute("SELECT * FROM `".self::TABLE_KUND."` WHERE konto = '".$this->username."' AND kund = '".$data['kundnummer']."'");
		// Om exchange-id ej finns i DB utgår vi från att kontakten ej är skapad och gör detta istället:
		if (false === $sqldata)
			throw new Aida2ExchangeException('Databasfel: '.$this->db->dbErrorString());
		else if (0 === count($sqldata)) {
			try {
				return $this->createContactKund($data, $folderId);
			} catch (Exception $e) {
				throw $e;
			}
		}
		$id = $sqldata[0]['exchange_id'];

		array_walk($data, function(&$value) {
				$value = trim($value);
			});

		$updates = new EWSType_NonEmptyArrayOfItemChangeDescriptionsType();
		$updates->SetItemField = array();
		$updates->DeleteItemField = array();

		// Om namn ej angivet uppdaterar vi det inte
		if (!empty($data['namn'])) {
			$field = new EWSType_SetItemFieldType();
			$field->FieldURI->FieldURI = EWSType_UnindexedFieldURIType::CONTACTS_COMPANY_NAME;
			$field->Contact = new EWSType_ContactItemType();
			$field->Contact->CompanyName = $data['namn'];
			$updates->SetItemField[] = $field;
			$field = new EWSType_SetItemFieldType();
			$field->FieldURI->FieldURI = EWSType_UnindexedFieldURIType::CONTACTS_DISPLAY_NAME;
			$field->Contact = new EWSType_ContactItemType();
			$field->Contact->DisplayName = $data['namn'];
			$updates->SetItemField[] = $field;
		}

		if (!empty($data['epost']))
			$updates->SetItemField[] = $this->_createEmailAddressUpdateField($data['epost']);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_EMAIL_ADDRESS, EWSType_EmailAddressKeyType::EMAIL_ADDRESS_1);

		if (!empty($data['tel']))
			$updates->SetItemField[] = $this->_createPhoneNumberUpdateField($data['tel'], EWSType_PhoneNumberKeyType::COMPANY_MAIN_PHONE);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER, EWSType_PhoneNumberKeyType::COMPANY_MAIN_PHONE);
		if (!empty($data['fax']))
			$updates->SetItemField[] = $this->_createPhoneNumberUpdateField($data['fax'], EWSType_PhoneNumberKeyType::BUSINESS_FAX);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER, EWSType_PhoneNumberKeyType::BUSINESS_FAX);
    
		try {
			$newId = $this->_updateContact($id, $updates, $folderId);
		} catch (Exception $e) {
			throw $e;
		}
		if (false === $this->db->execute("UPDATE `".self::TABLE_KUND."` SET exchange_id = '".$newId."' WHERE konto = '".$this->username."' AND kund = '".$data['kundnummer']."'")) {
			throw new Aida2ExchangeException('Kunde inte uppdatera databasen: '.$this->db->dbErrorString());
		}
		return true;
	}

	/**
	 * @uses Aida2Exchange::_deleteContact()
	 * @throws Aida2ExchangeException
	 */
	public function deleteContactKund($kundnummer) {
		$sqldata = $this->db->execute("SELECT * FROM `".self::TABLE_KUND."` WHERE konto = '".$this->username."' AND kund = '".$kundnummer."'");
		if (false === $sqldata)
			throw new Aida2ExchangeException('Databasfel: '.$this->db->dbErrorString());
		else if (count($sqldata) > 0) {
			$id = $sqldata[0]['exchange_id'];
			try {
				$this->_deleteContact($id);
			} catch (Exception $e) {
				throw $e;
			}
			if (false === $this->db->execute("DELETE FROM `".self::TABLE_KUND."` WHERE konto = '".$this->username."' AND kund = '".$kundnummer."'"))
				throw new Aida2ExchangeException('Databasfel: '.$this->db->dbErrorString());
		}
	}

	/**
	 * @param $folderId String
	 * Obligatoriska: namn, kund
	 * Valfria: kundnamn, befattning, epost, tel, fax, mobil
	 *
	 * @return String ID för nya kontakten om lyckat
	 * @throws Aida2ExchangeException
	 * @uses Aida2Exchange::_createEmailAddressDictionary()
	 * @uses Aida2Exchange::_createPhoneNumberDictionaryEntry()
	 * @uses Aida2Exchange::_newContact()
	 * @return String ID för ny kontakt
	 */
	public function createContactKontaktperson($data, $folderId) {
		if (!is_array($data))
			throw new Aida2ExchangeException('Inga kontaktpersondata angivna');
		if (empty($folderId))
			throw new Aida2ExchangeException('FolderId måste anges');
		if (empty($data['namn']) || empty(trim($data['namn'])))
			throw new Aida2ExchangeException('Kontaktpersonens namn måste anges');
		if (empty($data['kund']))
			throw new Aida2ExchangeException('Kontaktpersonens kundnummer måste anges');
		$namn = $data['namn']; // I databasen är namn PK och måste anges exakt, men i Exchange vill vi trim:a det
		array_walk($data, function(&$value) {
				$value = trim($value);
			});

		$contact = new EWSType_ContactItemType();
		$contact->DisplayName = $data['namn'];
		if (!empty($data['kundnamn']))
			$contact->CompanyName = $data['kundnamn'];
		if (!empty($data['befattning']))
			$contact->JobTitle = $data['befattning'];

		if (!empty($data['epost']))
			$contact->EmailAddresses = $this->_createEmailAddressDictionary($data['epost']);

		if (!empty($data['tel']) || !empty($data['fax']) || !empty($data['mobil'])) {
			$contact->PhoneNumbers = new EWSType_PhoneNumberDictionaryType();
			if (!empty($data['tel']))
				$contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($data['tel'], EWSType_PhoneNumberKeyType::BUSINESS_PHONE);
			if (!empty($data['fax']))
				$contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($data['fax'], EWSType_PhoneNumberKeyType::BUSINESS_FAX);
			if (!empty($data['mobil']))
				$contact->PhoneNumbers->Entry[] = $this->_createPhoneNumberDictionaryEntry($data['mobil'], EWSType_PhoneNumberKeyType::MOBILE_PHONE);
		}

		$contact->FileAsMapping = new EWSType_FileAsMappingType();
		$contact->FileAsMapping->_ = EWSType_FileAsMappingType::DISPLAY_NAME;

		try {
			$id = $this->_newContact($contact, $folderId);
		} catch (Exception $e) {
			throw $e;
		}

		if (false === $this->db->execute("INSERT INTO `".self::TABLE_KONTAKTPERSON."` (konto, kund, kontaktperson, exchange_id) VALUES ('".$this->username."', '".$data['kund']."', '".$namn."', '".$id."')")) {
			throw new Aida2ExchangeException('Kunde inte lägga till i databasen: '.$this->db->dbErrorString());
		}
		return $id;
	}

	/**
	 * Obligatoriska: namn, kundnummer
	 * Valfria: kundnamn, befattning, epost, tel, fax, mobil
	 *
	 * @param $folderId String Anges om kontakt ska flyttas till annan folder och/eller om man vill safe:a ifall den ej redan finns
	 * @return Boolean true om allt går väl
	 * @throws Aida2ExchangeException
	 * @uses Aida2Exchange::_createDeleteItemField()
	 * @uses Aida2Exchange::_createEmailAddressUpdateField()
	 * @uses Aida2Exchange::_createPhoneNumberUpdateField()
	 * @uses Aida2Exchange::_updateContact()
	 */
	public function updateContactKontaktperson($data, $folderId = '') {
		if (empty($data['kundnummer']) || empty($data['namn']) || !is_array($data))
			throw new Aida2ExchangeException('Kundnummer och kontaktpersons namn måste anges');

		$sqldata = $this->db->execute("SELECT * FROM `".self::TABLE_KONTAKTPERSON."` WHERE konto = '".$this->username."' AND kund = '".$data['kundnummer']."' AND kontaktperson = '".$data['namn']."'");
		// Om exchange-id ej finns i DB utgår vi från att kontakten ej är skapad och gör detta istället:
		if (false === $sqldata)
			throw new Aida2ExchangeException('Databasfel: '.$this->db->dbErrorString());
		else if (0 === count($sqldata))
			return $this->createContactKontaktperson($data, $folderId);
		$id = $sqldata[0]['exchange_id'];

		array_walk($data, function(&$value) {
				$value = trim($value);
			});

		$updates = new EWSType_NonEmptyArrayOfItemChangeDescriptionsType();
		$updates->SetItemField = array();
		$updates->DeleteItemField = array();

		if (!empty($data['namn'])) {
			$field = new EWSType_SetItemFieldType();
			$field->FieldURI->FieldURI = EWSType_UnindexedFieldURIType::CONTACTS_DISPLAY_NAME;
			$field->Contact = new EWSType_ContactItemType();
			$field->Contact->DisplayName = $data['namn'];
			$updates->SetItemField[] = $field;
		}

		if (!empty($data['kundnamn'])) {
			$field = new EWSType_SetItemFieldType();
			$field->FieldURI->FieldURI = EWSType_UnindexedFieldURIType::CONTACTS_COMPANY_NAME;
			$field->Contact = new EWSType_ContactItemType();
			$field->Contact->CompanyName = $data['kundnamn'];
			$updates->SetItemField[] = $field;
		} else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_UnindexedFieldURIType::CONTACTS_COMPANY_NAME);
      
		if (!empty($data['befattning'])) {
			$field = new EWSType_SetItemFieldType();
			$field->FieldURI->FieldURI = EWSType_UnindexedFieldURIType::CONTACTS_JOB_TITLE;
			$field->Contact = new EWSType_ContactItemType();
			$field->Contact->JobTitle = $data['befattning'];
			$updates->SetItemField[] = $field;
		} else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_UnindexedFieldURIType::CONTACTS_JOB_TITLE);
      
		if (!empty($data['epost']))
			$updates->SetItemField[] = $this->_createEmailAddressUpdateField($data['epost']);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_EMAIL_ADDRESS, EWSType_EmailAddressKeyType::EMAIL_ADDRESS_1);

		if (!empty($data['tel']))
			$updates->SetItemField[] = $this->_createPhoneNumberUpdateField($data['tel'], EWSType_PhoneNumberKeyType::BUSINESS_PHONE);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER, EWSType_PhoneNumberKeyType::BUSINESS_PHONE);
		if (!empty($data['fax']))
			$updates->SetItemField[] = $this->_createPhoneNumberUpdateField($data['fax'], EWSType_PhoneNumberKeyType::BUSINESS_FAX);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER, EWSType_PhoneNumberKeyType::BUSINESS_FAX);
		if (!empty($data['mobil']))
			$updates->SetItemField[] = $this->_createPhoneNumberUpdateField($data['mobil'], EWSType_PhoneNumberKeyType::MOBILE_PHONE);
		else
			$updates->DeleteItemField[] = $this->_createDeleteItemField(EWSType_DictionaryURIType::CONTACTS_PHONE_NUMBER, EWSType_PhoneNumberKeyType::MOBILE_PHONE);
    
		try {
			return $this->_updateContact($id, $updates, $folderId);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	 * @uses Aida2Exchange::_deleteContact()
	 * @throws Aida2ExchangeException
	 */
	public function deleteContactKontaktperson($kundnummer, $namn) {
		$sqldata = $this->db->execute("SELECT * FROM `".self::TABLE_KONTAKTPERSON."` WHERE konto = '".$this->username."' AND kund = '".$kundnummer."' AND kontaktperson = '".$namn."'");
		if (false === $sqldata)
			throw new Aida2ExchangeException('Databasfel: '.$this->db->dbErrorString());
		else if (count($sqldata) > 0) {
			$id = $sqldata[0]['exchange_id'];
			try {
				$this->_deleteContact($id);
			} catch (Exception $e) {
				throw $e;
			}
			if (false === $this->db->execute("DELETE FROM `".self::TABLE_KONTAKTPERSON."` WHERE konto = '".$this->username."' AND kund = '".$kundnummer."' AND kontaktperson = '".$namn."'"))
				throw new Aida2ExchangeException('Databasfel: '.$this->db->dbErrorString());
		}
	}

	/**
	 * @param $parent String FolderId eller tom för att skapa direkt i kontaktfolder-roten
	 * @return String ID för skapad folder om lyckat
	 */
	function createContactFolder($name, $parent = '') {
		$request = new EWSType_CreateFolderType();
		$request->Folders = new EWSType_NonEmptyArrayOfBaseFolderIdsType();
		$request->Folders->ContactsFolder = new EWSType_ContactsFolderType();
		$request->Folders->ContactsFolder->DisplayName = $name;
		$request->ParentFolderId = new EWSType_TargetFolderIdType();
		//new EWSType_NonEmptyArrayOfBaseFolderIdsType();
		if (!empty($parent)) {
			$request->ParentFolderId->FolderId->Id = $parent;
		} else {
			$request->ParentFolderId->DistinguishedFolderId->Id = EWSType_DistinguishedFolderIdNameType::CONTACTS;
		}
		$response = parent::CreateFolder($request);
		if ($response->ResponseMessages->CreateFolderResponseMessage->ResponseClass == EWSType_ResponseClassType::SUCCESS) {
			return $response->ResponseMessages->CreateFolderResponseMessage->Folders->ContactsFolder->FolderId->Id;
		} else { // Fel har uppstått 
			throw new Aida2ExchangeException($response->ResponseMessages->CreateFolderResponseMessage->MessageText);
		}
	}

	/**
	 * @todo Felkontroll
	 */
	function listContactFolders() {
		$request = new EWSType_FindFolderType();
		$request->FolderShape = new EWSType_FolderResponseShapeType();
		$request->FolderShape->BaseShape = EWSType_DefaultShapeNamesType::ALL_PROPERTIES;
		$request->ParentFolderIds->DistinguishedFolderId->Id = EWSType_DistinguishedFolderIdNameType::CONTACTS;
		$request->Traversal = new EWSType_FolderQueryTraversalType();
		$request->Traversal->_ = EWSType_FolderQueryTraversalType::DEEP;
		$response = parent::FindFolder($request);
		return $response;
	}

	/**
	 * @todo Felkontroll
	 */
	function deleteFolder($folderId) {
		$request = new EWSType_DeleteFolderType();
		$request->DeleteType = new EWSType_DisposalType();
		$request->DeleteType->_ = EWSType_DisposalType::HARD_DELETE;
		$request->FolderIds->FolderId->Id = $folderId;
		$response = parent::DeleteFolder($request);
		return $response;
	}
} // abstract class Aida2Exchange extends ExchangeWebServices



// Vi subclassar Aida2Exchange för varje Exchange-konto
class Aida2Bemanning extends Aida2Exchange
{
	// Struktur: $folderIds['kund' || 'personal'][status][yrke/kundtyp], med 'default' som frivillig catchall-nyckel om inget annat matchar
	public $folderIds;
	const ACCOUNT="bemanning@mixmedicare.se";
	const PASSWORD="PASSWORD";

	function __construct($debug = false) {
		$this->folderIds['personal']['aktiv'] = 
			array(
			      'Sjuksköterska' => 'AAMkADUxMTEyMGZiLTEyNzQtNGU1My04ZTA2LWZkMmE1MDJmNDI3MwAuAAAAAAB0JBjdDviUT4/SIf2wsiT9AQA1QZ0cRAS2TKSh0yX/ev7PAAAlB70/AAA=',
			      'Undersköterska' => 'AAMkADUxMTEyMGZiLTEyNzQtNGU1My04ZTA2LWZkMmE1MDJmNDI3MwAuAAAAAAB0JBjdDviUT4/SIf2wsiT9AQA1QZ0cRAS2TKSh0yX/ev7PAAAlB71AAAA=',
			      'default' => 'AAMkADUxMTEyMGZiLTEyNzQtNGU1My04ZTA2LWZkMmE1MDJmNDI3MwAuAAAAAAB0JBjdDviUT4/SIf2wsiT9AQA1QZ0cRAS2TKSh0yX/ev7PAAAlB71BAAA='
			      );
		$this->folderIds['personal']['vilande'] = 
			array('default' => 'AAMkADUxMTEyMGZiLTEyNzQtNGU1My04ZTA2LWZkMmE1MDJmNDI3MwAuAAAAAAB0JBjdDviUT4/SIf2wsiT9AQA1QZ0cRAS2TKSh0yX/ev7PAAAlB71CAAA=');
		$this->folderIds['kund']['Aktiv'] = 
			array(
			      'Privat' => 'AAMkADUxMTEyMGZiLTEyNzQtNGU1My04ZTA2LWZkMmE1MDJmNDI3MwAuAAAAAAB0JBjdDviUT4/SIf2wsiT9AQA1QZ0cRAS2TKSh0yX/ev7PAAAlB71DAAA=',
			      'Kommun' => 'AAMkADUxMTEyMGZiLTEyNzQtNGU1My04ZTA2LWZkMmE1MDJmNDI3MwAuAAAAAAB0JBjdDviUT4/SIf2wsiT9AQA1QZ0cRAS2TKSh0yX/ev7PAAAlB71EAAA=',
			      'Landsting' => 'AAMkADUxMTEyMGZiLTEyNzQtNGU1My04ZTA2LWZkMmE1MDJmNDI3MwAuAAAAAAB0JBjdDviUT4/SIf2wsiT9AQA1QZ0cRAS2TKSh0yX/ev7PAAAlB71FAAA=',
			      'Statlig' => 'AAMkADUxMTEyMGZiLTEyNzQtNGU1My04ZTA2LWZkMmE1MDJmNDI3MwAuAAAAAAB0JBjdDviUT4/SIf2wsiT9AQA1QZ0cRAS2TKSh0yX/ev7PAAAlB71GAAA='
			      );
		$this->folderIds['kund']['Vilande'] = 
			array('default' => 'AAMkADUxMTEyMGZiLTEyNzQtNGU1My04ZTA2LWZkMmE1MDJmNDI3MwAuAAAAAAB0JBjdDviUT4/SIf2wsiT9AQA1QZ0cRAS2TKSh0yX/ev7PAAAlB71HAAA=');
		parent::__construct(self::ACCOUNT, self::PASSWORD);
		$this->debug = $debug;
	}

	/**
	 * Kan ej anropas med POST-data från addCustomerF eftersom vi då missar kundnummer, som genereras först i MySQL.
	 * Anropa istället först showKund() på den nya kunden och skicka denna data hit.
	 *
	 * @param $data Array av kunddata, se Aida2Exchange::updateContactKund()
	 * @uses Aida2Exchange::updateContactKund()
	 * @uses Aida2Exchange::deleteContactKund()
	 * @throws Aida2ExchangeException
	 */
	function createOrUpdateContactKund($data) {
		try {
			/*
			  if (empty($data['enhet']) || 'Huvudkontor' != $data['enhet']) {
			  parent::deleteContactKund($data['kundnummer']);
			  } else {
			*/
			// updateContactKund() anropar automatiskt createContactKund() om kontakt ej finns än
			if (!empty($this->folderIds['kund'][$data['status']][$data['kundtyp']]))
				parent::updateContactKund($data, $this->folderIds['kund'][$data['status']][$data['kundtyp']]);
			elseif (!empty($this->folderIds['kund'][$data['status']]['default']))
				parent::updateContactKund($data, $this->folderIds['kund'][$data['status']]['default']);
			else
				parent::deleteContactKund($data['kundnummer']);
			//      } 
		} catch (Aida2ExchangeException $e) {
			ChromePhp::log((string)$e);
			throw $e;
		}
	}

	/**
	 * Bör ej anropas med POST-data från addPersonalF eftersom vi då missar yrke.
	 * Anropa istället först showPersonal() på den nya anställda och skicka denna data hit.
	 *
	 * @param $data Array av personaldata, se Aida2Exchange::updateContactPersonal()
	 * @uses Aida2Exchange::updateContactPersonal()
	 * @uses Aida2Exchange::deleteContactPersonal()
	 * @throws Aida2ExchangeException
	 */
	function createOrUpdateContactPersonal($data) {
		try {
			$personnummer = (!empty($data['oldpnr']) ? $data['oldpnr'] : $data['personnummer']);
			/*
			  if (empty($data['enhetlist']) || false === strpos($data['enhetlist'], 'Huvudkontor')) {
			  parent::deleteContactPersonal($personnummer);
			  } else {
			*/
			if (false !== strpos($data['yshort'], 'Sjuksköterska'))
				$data['yrke'] = 'Sjuksköterska';
			elseif (false !== strpos($data['yshort'], 'Undersköterska'))
				$data['yrke'] = 'Undersköterska';
			else
				$data['yrke'] = str_replace("\n", ', ', $data['yshort']);

			if (!empty($this->folderIds['personal'][$data['status']][$data['yrke']]))
				parent::updateContactPersonal($data, $this->folderIds['personal'][$data['status']][$data['yrke']]);
			elseif (!empty($this->folderIds['personal'][$data['status']]['default']))
				parent::updateContactPersonal($data, $this->folderIds['personal'][$data['status']]['default']);
			else
				parent::deleteContactPersonal($personnummer);
			//      }
		} catch (Aida2ExchangeException $e) {
			throw $e;
		}
	}

	/**
	 * Använd bara interaktivt, ej som cronjobb el. dyl.
	 */
	function createAllContactsPersonal() {
		foreach (array('aktiv', 'vilande') as $status) {
			//      $sql = "CALL listPersonalByCriteria('', '%', '".$status."', '', '', '', '', '', '', '', '1000', 'Huvudkontor')";
			//      $sql = "CALL listPersonalByCriteria('', '%', '".$status."', '', '', '', '', '', '', '', '1000', 'Uppsala')";
			$sql = "CALL listPersonalByCriteria('', '%', '".$status."', '', '', '', '', '', '', '', '1000', '')";
			$rows = $this->db->execute($sql);
			foreach ($rows as $row) {
				/*
				  if (false !== strpos($row['enhetlist'], 'Huvudkontor'))
				  continue;
				*/
				$personalinfo = $this->db->call('showPersonal', $row['personnummer'])[0];
				if (!empty($this->folderIds['personal'][$personalinfo['status']][$personalinfo['yshort']]))
					$folderId = $this->folderIds['personal'][$personalinfo['status']][$personalinfo['yshort']];
				elseif (!empty($this->folderIds['personal'][$personalinfo['status']]['default']))
					$folderId = $this->folderIds['personal'][$personalinfo['status']]['default'];
				else
					continue;
				try {
					echo $personalinfo['personnummer'].' '.$personalinfo['fornamn'].' '.$personalinfo['efternamn'];
					if (false !== strpos($row['yshort'], 'Sjuksköterska')) {
						$personalinfo['yrke'] = 'Sjuksköterska';
					} elseif (false !== strpos($row['yshort'], 'Undersköterska')) {
						$personalinfo['yrke'] = 'Undersköterska';
					} else {
						$personalinfo['yrke'] = str_replace("\n", ', ', $row['yshort']);
					}
					$this->createContactPersonal($personalinfo, $folderId);
				} catch (Aida2ExchangeException $e) {
					echo $e;
				} finally {
					echo PHP_EOL;
				}
			}
		}
	}

	/**
	 * Använd bara interaktivt, ej som cronjobb el. dyl.
	 */
	function deleteAllContactsPersonal() {
		$sql = "SELECT * FROM exchange_personal";
		$rows = $this->db->execute($sql);
		$latestPercent = -1;
		foreach ($rows as $idx => $row) {
			$this->_deleteContact($row['exchange_id']);
			$this->db->execute("DELETE FROM exchange_personal WHERE konto = '".$row['konto']."' AND personal = '".$row['personal']."'");
			$percent = ceil(100 * ($idx / count($rows)));
			if ($percent % 10 == 0 && $percent > $latestPercent) {
				echo $percent." % ... ";
				$latestPercent = $percent;
			}
		}
		echo PHP_EOL;
	}

	/**
	 * Använd bara interaktivt, ej som cronjobb el. dyl.
	 */
	function createAllContactsKund() {
		//    $sql = "SELECT kundnummer, namn, epost, tel, fax, status, kundtyp FROM kund WHERE enhet = 'Huvudkontor' AND (status = 'Aktiv' OR status = 'Vilande') ORDER BY status, kundtyp, namn";
		//    $sql = "SELECT kundnummer, namn, epost, tel, fax, status, kundtyp FROM kund WHERE enhet = 'Uppsala' AND (status = 'Aktiv' OR status = 'Vilande') ORDER BY status, kundtyp, namn";
		$sql = "SELECT kundnummer, namn, epost, tel, fax, status, kundtyp FROM kund WHERE status = 'Aktiv' OR status = 'Vilande' ORDER BY status, kundtyp, namn";
		$kunder = $this->db->execute($sql);
		foreach ($kunder as $kund) {
			if (!empty($this->folderIds['kund'][$kund['status']][$kund['kundtyp']]))
				$folderId = $this->folderIds['kund'][$kund['status']][$kund['kundtyp']];
			elseif (!empty($this->folderIds['kund'][$kund['status']]['default']))
				$folderId = $this->folderIds['kund'][$kund['status']]['default'];
			else
				continue;
			$sql = "SELECT namn, befattning, epost, tel, fax, mobil FROM kontaktperson WHERE kund = '".$kund['kundnummer']."'";
			$kontaktpersoner = $this->db->execute($sql);
			try {
				echo '['.$kund['status'].'] ['.$kund['kundtyp'].'] '.$kund['namn'].' ... ';
				$this->createContactKund($kund, $folderId);
				/*
				  foreach ($kontaktpersoner as $kontaktperson) {
				  echo "\n\t".$kontaktperson['namn'].' ... ';
				  $kontaktperson['kund'] = $kund['kundnummer'];
				  $kontaktperson['kundnamn'] = $kund['namn'];
				  $this->createContactKontaktperson($kontaktperson, $folderId);
				  }
				*/
			} catch (Aida2ExchangeException $e) {
				echo $e;
			} finally {
				echo PHP_EOL;
			}
		}
	}

	/**
	 * Använd bara interaktivt, ej som cronjobb el. dyl.
	 */
	function deleteAllContactsKund() {
		$sql = "SELECT * FROM exchange_kund";
		$rows = $this->db->execute($sql);
		$latestPercent = -1;
		foreach ($rows as $idx => $row) {
			$this->_deleteContact($row['exchange_id']);
			$this->db->execute("DELETE FROM exchange_kund WHERE konto = '".$row['konto']."' AND kund = '".$row['kund']."'");
			$percent = ceil(100 * ($idx / count($rows)));
			if ($percent % 10 == 0 && $percent > $latestPercent) {
				echo $percent." % ... ";
				$latestPercent = $percent;
			}
		}
		echo PHP_EOL;
	}

	/**
	 * Använd bara interaktivt, ej som cronjobb el. dyl.
	 */
	function deleteAllContactsKontaktperson() {
		$sql = "SELECT * FROM exchange_kontaktperson";
		$rows = $this->db->execute($sql);
		$latestPercent = -1;
		foreach ($rows as $idx => $row) {
			$this->_deleteContact($row['exchange_id']);
			$this->db->execute("DELETE FROM exchange_kund WHERE konto = '".$row['konto']."' AND kund = '".$row['kund']."' AND kontaktperson = '".$row['kontaktperson']."'");
			$percent = ceil(100 * ($idx / count($rows)));
			if ($percent % 10 == 0 && $percent > $latestPercent) {
				echo $percent." % ... ";
				$latestPercent = $percent;
			}
		}
		echo PHP_EOL;
	}
} // class Aida2Bemanning extends Aida2Exchange

class Aida2ExchangeException extends Exception
{
	public function __construct($message = null, $code = 0, $previous = null) {
		parent::__construct($message, $code, $previous);
		ChromePhp::log($this->getTrace(), $this->message);
		adsysSimpleMailer("it@mixmedicare.se", "Aida2Exchange-fel", $this->message."\n".var_export($this->getTrace(), true));
	}

	public function __toString() {
		$trace = $this->getTrace();
		if (count($trace) > 0) {
			$idx = count($trace) - 1;
			return '[ '.$trace[$idx]['class'].'::'.$trace[$idx]['function'].'() ] '.$this->message;
		} else {
			return $this->message;
		}
	}
} // class Aida2ExchangeException extends Exception

