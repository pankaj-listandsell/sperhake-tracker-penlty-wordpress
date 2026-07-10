<?php
/**
 * One-off build script: generates sperhake-tracker-de_DE.po and .mo from the
 * translation map below. Run with: php languages/build-de.php
 *
 * Kept in the repo so the German catalogue has a single, editable source.
 */

declare(strict_types=1);

// CLI-only: never run via a web request (it writes files in this directory).
if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$meta = "Project-Id-Version: Sperhake Vehicle Tracking & Penalty Payment 1.3.0\n"
	. "Report-Msgid-Bugs-To: https://abschleppdienst-sperhake.de/\n"
	. "Language: de_DE\n"
	. "MIME-Version: 1.0\n"
	. "Content-Type: text/plain; charset=UTF-8\n"
	. "Content-Transfer-Encoding: 8bit\n"
	. "Plural-Forms: nplurals=2; plural=(n != 1);\n"
	. "X-Domain: sperhake-tracker\n";

$t = [
	'%s record(s) found.' => '%s Datensätze gefunden.',
	'A reference number is required.' => 'Eine Referenznummer ist erforderlich.',
	'Add this URL as a Stripe webhook endpoint and subscribe to checkout.session.completed.' => 'Fügen Sie diese URL als Stripe-Webhook-Endpunkt hinzu und abonnieren Sie checkout.session.completed.',
	'All levels' => 'Alle Stufen',
	'All statuses' => 'Alle Status',
	'Amount' => 'Betrag',
	'Amount Paid' => 'Gezahlter Betrag',
	'API Key' => 'API-Schlüssel',
	'API Secret' => 'API-Secret',
	'API Settings' => 'API-Einstellungen',
	'API Sync' => 'API-Synchronisierung',
	'API URL' => 'API-URL',
	'Attach PDF receipt' => 'PDF-Beleg anhängen',
	'Case Number' => 'Fallnummer',
	'Channel' => 'Kanal',
	'City' => 'Stadt',
	'Country' => 'Land',
	'Google reCAPTCHA v2 ("I\'m not a robot") site key. Leave blank to disable the CAPTCHA.' => 'Google-reCAPTCHA-v2-Site-Key („Ich bin kein Roboter“). Leer lassen, um das CAPTCHA zu deaktivieren.',
	'Contact' => 'Kontakt',
	'Context' => 'Kontext',
	'Could not reach the payment provider. Please try again.' => 'Der Zahlungsanbieter konnte nicht erreicht werden. Bitte versuchen Sie es erneut.',
	'Could not request the invoice.' => 'Die Rechnung konnte nicht angefordert werden.',
	'Could not start the payment. Please try again.' => 'Die Zahlung konnte nicht gestartet werden. Bitte versuchen Sie es erneut.',
	'Currency' => 'Währung',
	'Current Location' => 'Aktueller Standort',
	'Custom Request Headers' => 'Benutzerdefinierte Request-Header',
	'Customer' => 'Kunde',
	'Customer Email' => 'Kunden-E-Mail',
	'Customer Name' => 'Kundenname',
	'Dashboard' => 'Übersicht',
	'Date' => 'Datum',
	'Debug' => 'Debug',
	'Download Receipt' => 'Beleg herunterladen',
	'e.g. "Case Number" or "Reference Number". Sent to the API as "reference".' => 'z. B. „Fallnummer“ oder „Referenznummer“. Wird als „reference“ an die API gesendet.',
	'Email' => 'E-Mail',
	'Email Settings' => 'E-Mail-Einstellungen',
	'Email Subject' => 'E-Mail-Betreff',
	'Enable SEPA Direct Debit' => 'SEPA-Lastschrift aktivieren',
	'Enabled' => 'Aktiviert',
	'Enter your license plate to check your vehicle status and pay any outstanding penalty.' => 'Geben Sie Ihr Kennzeichen ein, um den Fahrzeugstatus zu prüfen und offene Gebühren zu bezahlen.',
	'Error' => 'Fehler',
	'Every 5 minutes (Sperhake)' => 'Alle 5 Minuten (Sperhake)',
	'Export CSV' => 'CSV exportieren',
	'Failed' => 'Fehlgeschlagen',
	'Failed API Syncs' => 'Fehlgeschlagene API-Synchronisierungen',
	'Filter' => 'Filtern',
	'Find Your Vehicle' => 'Finden Sie Ihr Fahrzeug',
	'Forces a second identifier alongside the plate to prevent enumeration.' => 'Erzwingt eine zweite Kennung zusätzlich zum Kennzeichen, um Enumeration zu verhindern.',
	'Forward Webhook URL' => 'Webhook-URL weiterleiten',
	'From Email' => 'Absender-E-Mail',
	'From Name' => 'Absendername',
	'Frontend Search Security' => 'Sicherheit der Frontend-Suche',
	'If you have any questions about this payment, please contact %s.' => 'Bei Fragen zu dieser Zahlung wenden Sie sich bitte an %s.',
	'Info' => 'Info',
	'Insufficient permissions.' => 'Unzureichende Berechtigungen.',
	'Invalid license plate.' => 'Ungültiges Kennzeichen.',
	'Invoice Details' => 'Rechnungsdetails',
	'Invoice requested.' => 'Rechnung angefordert.',
	'Invoice requested. It will be emailed to you shortly.' => 'Rechnung angefordert. Sie wird Ihnen in Kürze per E-Mail zugesandt.',
	'Invoice requested. Please check your email.' => 'Rechnung angefordert. Bitte prüfen Sie Ihre E-Mails.',
	'Invoice requests are temporarily unavailable. Please contact us.' => 'Rechnungsanforderungen sind vorübergehend nicht verfügbar. Bitte kontaktieren Sie uns.',
	'IP Address' => 'IP-Adresse',
	'Level' => 'Stufe',
	'License plate' => 'Kennzeichen',
	'License Plate' => 'Kennzeichen',
	'Link expired' => 'Link abgelaufen',
	'Live' => 'Live',
	'Logo URL' => 'Logo-URL',
	'Message' => 'Nachricht',
	'Missing case reference.' => 'Fehlende Fallreferenz.',
	'Mode' => 'Modus',
	'Name' => 'Name',
	'No log entries.' => 'Keine Protokolleinträge.',
	'No Outstanding Penalties' => 'Keine offenen Gebühren',
	'No searches recorded.' => 'Keine Suchanfragen aufgezeichnet.',
	'No transactions found.' => 'Keine Transaktionen gefunden.',
	'No vehicle matched that plate and reference.' => 'Kein Fahrzeug stimmte mit diesem Kennzeichen und dieser Referenz überein.',
	'No vehicle was found for that license plate.' => 'Für dieses Kennzeichen wurde kein Fahrzeug gefunden.',
	'Not set' => 'Nicht festgelegt',
	'One per line, e.g. X-Tenant: sperhake' => 'Eine pro Zeile, z. B. X-Tenant: sperhake',
	'Outstanding Penalty' => 'Offene Gebühr',
	'Outstanding penalty payment to Abschleppdienst Sperhake.' => 'Zahlung offener Gebühren an Abschleppdienst Sperhake.',
	'Owner' => 'Halter',
	'Partner' => 'Partner',
	'Paid' => 'Bezahlt',
	'Pay Now – %s' => 'Jetzt bezahlen – %s',
	'Payment' => 'Zahlung',
	'Payment is being processed' => 'Zahlung wird verarbeitet',
	'Payment Receipt' => 'Zahlungsbeleg',
	'Payment Status' => 'Zahlungsstatus',
	'Payment Successful' => 'Zahlung erfolgreich',
	'Payments are temporarily unavailable.' => 'Zahlungen sind vorübergehend nicht verfügbar.',
	'Payments Received' => 'Erhaltene Zahlungen',
	'Penalty Paid' => 'Gebühr bezahlt',
	'Pending' => 'Ausstehend',
	'Personal data in towing penalty records was anonymised. Financial fields are retained for legal/accounting obligations.' => 'Personenbezogene Daten in den Abschlepp-Gebührendatensätzen wurden anonymisiert. Finanzdaten werden aufgrund gesetzlicher/buchhalterischer Pflichten aufbewahrt.',
	'Please complete the verification first.' => 'Bitte schließen Sie zuerst die Verifizierung ab.',
	'Please confirm or update your billing details below. The invoice will be issued to this recipient and address.' => 'Bitte bestätigen oder aktualisieren Sie unten Ihre Rechnungsdaten. Die Rechnung wird an diesen Empfänger und diese Adresse ausgestellt.',
	'Please enter a license plate.' => 'Bitte geben Sie ein Kennzeichen ein.',
	'Please enter a valid email address.' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
	'Please enter a valid license plate.' => 'Bitte geben Sie ein gültiges Kennzeichen ein.',
	'Please enter the invoice recipient name.' => 'Bitte geben Sie den Namen des Rechnungsempfängers ein.',
	'Please enter the required reference.' => 'Bitte geben Sie die erforderliche Referenz ein.',
	'Please enter your %s to look up this vehicle.' => 'Bitte geben Sie Ihre %s ein, um dieses Fahrzeug zu suchen.',
	'Plugin activation error' => 'Fehler bei der Plugin-Aktivierung',
	'Protect the public search form against bots and plate enumeration.' => 'Schützt das öffentliche Suchformular vor Bots und Kennzeichen-Enumeration.',
	'Postal Code' => 'Postleitzahl',
	'Publishable Key' => 'Publishable Key',
	'Receipt' => 'Beleg',
	'Receipt file is unavailable.' => 'Belegdatei ist nicht verfügbar.',
	'Receipt not found.' => 'Beleg nicht gefunden.',
	'Recent vehicle searches, for abuse/enumeration detection. IPs are shown for the retained window only.' => 'Aktuelle Fahrzeugsuchen zur Erkennung von Missbrauch/Enumeration. IP-Adressen werden nur für den Aufbewahrungszeitraum angezeigt.',
	'Redirecting to secure checkout…' => 'Weiterleitung zur sicheren Kasse…',
	'Reference field label' => 'Bezeichnung des Referenzfelds',
	'Reference?' => 'Referenz?',
	'Relocated To' => 'Verbracht nach',
	'Request Invoice' => 'Rechnung anfordern',
	'Request Timeout (seconds)' => 'Anfrage-Timeout (Sekunden)',
	'Requesting…' => 'Wird angefordert…',
	'Require reference number' => 'Referenznummer erforderlich',
	'Result' => 'Ergebnis',
	'Search Audit Log' => 'Such-Audit-Protokoll',
	'Search shortcode' => 'Such-Shortcode',
	'Search Vehicle' => 'Fahrzeug suchen',
	'Searching…' => 'Suche läuft…',
	'Secret Key' => 'Geheimer Schlüssel',
	'Secure payment powered by Stripe. Cards, Apple Pay & Google Pay accepted.' => 'Sichere Zahlung über Stripe. Karten, Apple Pay & Google Pay akzeptiert.',
	'Send Invoice Request' => 'Rechnungsanfrage senden',
	'Send invoice to a different email (optional)' => 'Rechnung an eine andere E-Mail senden (optional)',
	'Setup Checklist' => 'Einrichtungs-Checkliste',
	'Something went wrong. Please try again.' => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.',
	'Sperhake Tracker' => 'Sperhake Tracker',
	'Sperhake Tracker – Dashboard' => 'Sperhake Tracker – Übersicht',
	'Sperhake Vehicle Tracker' => 'Sperhake Vehicle Tracker',
	'Sperhake Vehicle Tracker requires PHP 8.2 or higher.' => 'Sperhake Vehicle Tracker erfordert PHP 8.2 oder höher.',
	'Status' => 'Status',
	'Storage Address' => 'Adresse der Verwahrstelle',
	'Storage Yard' => 'Verwahrstelle',
	'Stored encrypted. Leave blank to keep the current value.' => 'Verschlüsselt gespeichert. Leer lassen, um den aktuellen Wert beizubehalten.',
	'Street & House Number' => 'Straße & Hausnummer',
	'Stripe Configuration' => 'Stripe-Konfiguration',
	'Stripe is not configured. Visit Stripe Settings to enable payments.' => 'Stripe ist nicht konfiguriert. Öffnen Sie die Stripe-Einstellungen, um Zahlungen zu aktivieren.',
	'Stripe mode' => 'Stripe-Modus',
	'Stripe Settings' => 'Stripe-Einstellungen',
	'Stripe webhook endpoint' => 'Stripe-Webhook-Endpunkt',
	'System Logs' => 'Systemprotokolle',
	'Test' => 'Test',
	'Thank you for your payment' => 'Vielen Dank für Ihre Zahlung',
	'Thank you for your payment.' => 'Vielen Dank für Ihre Zahlung.',
	'The invoice could not be requested right now. Please try again later.' => 'Die Rechnung konnte derzeit nicht angefordert werden. Bitte versuchen Sie es später erneut.',
	'The Vehicle Tracking API URL is not configured yet. Visit API Settings to get started.' => 'Die API-URL für die Fahrzeugverfolgung ist noch nicht konfiguriert. Öffnen Sie die API-Einstellungen, um zu beginnen.',
	'There is no outstanding penalty to pay for this vehicle.' => 'Für dieses Fahrzeug ist keine offene Gebühr zu zahlen.',
	'This vehicle is not available for online payment.' => 'Dieses Fahrzeug ist nicht für die Online-Zahlung verfügbar.',
	'This download link is valid for 24 hours. The receipt is also attached as a PDF.' => 'Dieser Download-Link ist 24 Stunden gültig. Der Beleg ist außerdem als PDF angehängt.',
	'This is a computer-generated receipt issued by %s. No signature is required.' => 'Dies ist ein computergenerierter Beleg, ausgestellt von %s. Eine Unterschrift ist nicht erforderlich.',
	'This penalty has already been paid.' => 'Diese Gebühr wurde bereits bezahlt.',
	'This receipt link has expired. Please contact us to request a new copy.' => 'Dieser Beleg-Link ist abgelaufen. Bitte kontaktieren Sie uns, um eine neue Kopie anzufordern.',
	'Time (UTC)' => 'Zeit (UTC)',
	'Too many requests. Please wait a moment and try again.' => 'Zu viele Anfragen. Bitte warten Sie einen Moment und versuchen Sie es erneut.',
	'Total Collected (EUR)' => 'Gesamt eingenommen (EUR)',
	'Towed Date' => 'Abschleppdatum',
	'Towed From' => 'Abgeschleppt von',
	'Towed Time' => 'Abschleppzeit',
	'Towing Location' => 'Abschlepport',
	'Towing Vehicle' => 'Abschleppfahrzeug',
	'Towing penalty – %s' => 'Abschleppgebühr – %s',
	'Towing penalty payments' => 'Abschleppgebühren-Zahlungen',
	'Track on Map' => 'Auf Karte verfolgen',
	'Transaction ID' => 'Transaktions-ID',
	'Transaction Logs' => 'Transaktionsprotokolle',
	'reCAPTCHA Secret Key' => 'reCAPTCHA-Secret-Key',
	'reCAPTCHA Site Key' => 'reCAPTCHA-Site-Key',
	'Unexpected response from the vehicle database.' => 'Unerwartete Antwort von der Fahrzeugdatenbank.',
	'Vehicle Brand' => 'Fahrzeugmarke',
	'Vehicle Found' => 'Fahrzeug gefunden',
	'Vehicle ID' => 'Fahrzeug-ID',
	'Vehicle lookup is temporarily unavailable. Please contact us.' => 'Die Fahrzeugsuche ist vorübergehend nicht verfügbar. Bitte kontaktieren Sie uns.',
	'Vehicle not found.' => 'Fahrzeug nicht gefunden.',
	'Vehicle Release Instructions' => 'Hinweise zur Fahrzeugherausgabe',
	'Vehicle Type' => 'Fahrzeugtyp',
	'Vehicle Relocated To' => 'Fahrzeug verbracht nach',
	'Vehicle Tracking API' => 'Fahrzeugverfolgungs-API',
	'Verification failed. Please complete the challenge and try again.' => 'Verifizierung fehlgeschlagen. Bitte schließen Sie die Prüfung ab und versuchen Sie es erneut.',
	'View not found.' => 'Ansicht nicht gefunden.',
	'View Route on Map' => 'Route auf Karte anzeigen',
	'Warning' => 'Warnung',
	'We could not reach the invoice service. Please try again shortly.' => 'Wir konnten den Rechnungsdienst nicht erreichen. Bitte versuchen Sie es in Kürze erneut.',
	'We could not reach the vehicle database. Please try again shortly.' => 'Wir konnten die Fahrzeugdatenbank nicht erreichen. Bitte versuchen Sie es in Kürze erneut.',
	'We have received your penalty payment. A copy of your receipt is attached to this email for your records.' => 'Wir haben Ihre Gebührenzahlung erhalten. Eine Kopie Ihres Belegs ist dieser E-Mail für Ihre Unterlagen beigefügt.',
	'We have received your request and will confirm shortly. You will receive an email once the payment is complete.' => 'Wir haben Ihre Anfrage erhalten und bestätigen sie in Kürze. Sie erhalten eine E-Mail, sobald die Zahlung abgeschlossen ist.',
	'Webhook Signing Secret' => 'Webhook-Signing-Secret',
	'Where completed payments are posted after success.' => 'Wohin abgeschlossene Zahlungen nach erfolgreicher Zahlung gesendet werden.',
	'Yes' => 'Ja',
	'You do not have permission to access this page.' => 'Sie haben keine Berechtigung, auf diese Seite zuzugreifen.',
	'Your %s is printed on the notice left on or near your vehicle.' => 'Ihre %s steht auf dem Hinweis, der an oder in der Nähe Ihres Fahrzeugs hinterlassen wurde.',
	'Your payment receipt – Abschleppdienst Sperhake' => 'Ihr Zahlungsbeleg – Abschleppdienst Sperhake',
	'Your receipt is being generated and has been emailed to you.' => 'Ihr Beleg wird erstellt und wurde Ihnen per E-Mail zugesandt.',
	'Your Webhook Endpoint' => 'Ihr Webhook-Endpunkt',
];

/* --------------------------------------------------------------------- */

/** Escape a string for the PO format. */
function po_escape( string $s ): string {
	$s = str_replace( [ '\\', '"', "\n", "\t" ], [ '\\\\', '\\"', '\\n', '\\t' ], $s );
	return $s;
}

/** Build a .po document. */
function build_po( array $t, string $meta ): string {
	$out  = "# German translation for Sperhake Vehicle Tracking & Penalty Payment.\n";
	$out .= "# This file is distributed under the GPL-2.0-or-later license.\n";
	$out .= "msgid \"\"\nmsgstr \"\"\n";
	foreach ( explode( "\n", rtrim( $meta, "\n" ) ) as $line ) {
		$out .= '"' . po_escape( $line ) . '\n"' . "\n";
	}
	$out .= "\n";

	ksort( $t, SORT_STRING );
	foreach ( $t as $id => $str ) {
		$out .= 'msgid "' . po_escape( (string) $id ) . "\"\n";
		$out .= 'msgstr "' . po_escape( (string) $str ) . "\"\n\n";
	}

	return $out;
}

/** Build a binary .mo from id => str (header passed as ""). */
function build_mo( array $t, string $meta ): string {
	$entries = [ '' => $meta ] + $t;
	uksort( $entries, static fn( $a, $b ) => strcmp( (string) $a, (string) $b ) );

	$ids  = array_keys( $entries );
	$strs = array_values( $entries );
	$n    = count( $entries );

	$o_offsets = '';
	$t_offsets = '';
	$ids_blob  = '';
	$strs_blob = '';

	$base = 28 + $n * 8 + $n * 8; // header + two offset tables (hash table omitted).

	$offset = $base;
	foreach ( $ids as $id ) {
		$id        = (string) $id;
		$o_offsets .= pack( 'VV', strlen( $id ), $offset );
		$ids_blob  .= $id . "\0";
		$offset    += strlen( $id ) + 1;
	}
	foreach ( $strs as $str ) {
		$str       = (string) $str;
		$t_offsets .= pack( 'VV', strlen( $str ), $offset );
		$strs_blob .= $str . "\0";
		$offset    += strlen( $str ) + 1;
	}

	$header = pack(
		'VVVVVVV',
		0x950412de, // magic
		0,          // revision
		$n,         // number of strings
		28,         // offset of originals table
		28 + $n * 8, // offset of translations table
		0,          // hash table size
		$base       // hash table offset (unused)
	);

	return $header . $o_offsets . $t_offsets . $ids_blob . $strs_blob;
}

$dir = __DIR__;
file_put_contents( $dir . '/sperhake-tracker-de_DE.po', build_po( $t, $meta ) );
file_put_contents( $dir . '/sperhake-tracker-de_DE.mo', build_mo( $t, $meta ) );

printf( "Wrote %d strings to .po and .mo%s", count( $t ), PHP_EOL );
