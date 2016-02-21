Lite PHP-exempel från Robert Huselius (robert@huseli.us). Dessa kommer från mitt  nuvarande jobb på
bemanningsföretaget Mix Medicare.

Aida2Exchange.php
-----------------
Anropas av vårt verksamhetssystem Aida när personal eller kunder läggs till, tas bort eller uppdateras.
Den uppdaterar kontakterna på våra Exchange-konton därefter.
Aida2Exchange är abstrakt och måste implementeras för varje Exchange-konto. I nuläget finns bara
implementationen Bemanning2Exchange för kontot bemanning@mixmedicare.se.
Använder php-ews: https://github.com/jamesiarmes/php-ews

MessagePersonal.php
-------------------
Skickar och loggar meddelanden till personal. Innehåller implementationer för e-post och SMS.

AdsysMailer.php
---------------
Implementation av PHPMailer: https://github.com/PHPMailer/PHPMailer
Används av MessagePersonal.

SMS_*.php
---------
Vi har prövat flera olika SMS-API:n. För att enkelt kunna byta mellan dem skriver jag olika
implementationer av klassen SMS. Denna används i MessagePersonal.