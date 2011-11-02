<?php
// $Id: modinfo.php,v 1.1 2009-07-02 01:57:48 nobu Exp $
// Module Info

// The name of this module
define("_MI_CCENTER_NAME","Contact Center");

// A brief description of this module
define("_MI_CCENTER_DESC","Contact form with message store and management");

// Sub Menu
define("_MI_CCENTER_MYCONTACT", "Meine Nachrichten");
define("_MI_CCENTER_MYCHARGE", "Kontakte an mich");
define("_MI_CCENTER_STAFFDESK", "Staff Desk");

// Admin Menu
define("_MI_CCENTER_FORMADMIN", "Formulare");
define("_MI_CCENTER_MSGADMIN", "Nachrichten");
define("_MI_CCENTER_HELP", "Anleitung");


// A brief template of this module
define("_MI_CCENTER_INDEX_TPL", "Formularliste");
define("_MI_CCENTER_FORM_TPL", "Kontaktformular");
define("_MI_CCENTER_CUST_TPL", "Kontaktformular (custom)");
define("_MI_CCENTER_CONF_TPL", "Confirm form inputs");
define("_MI_CCENTER_LIST_TPL", "List of my queries");
define("_MI_CCENTER_CHARGE_TPL", "Liste von Kontakten an mich");
define("_MI_CCENTER_MSGS_TPL", "Zeige Kontaktnachrichten");
define("_MI_CCENTER_RECEPT_TPL", "Zeige staff desk");
define("_MI_CCENTER_WIDGET_TPL", "Form widgets");

// A brief blocks of this module
define("_MI_CCENTER_BLOCK_RECEIPT","Kontakte an mich");
define("_MI_CCENTER_BLOCK_FORM","Kontaktformular");

// Configs
define ("_MI_CCENTER_USE_CATS","Benutze Kategorien");
define ("_MI_CCENTER_USE_CATS_DESC","Wählen Sie 'ja' um das Modul 'sprockets' zum Kategorisieren von Formularen zu nutzen");
define("_MI_CCENTER_LISTS","Number of list items");
define("_MI_CCENTER_LISTS_DESC","Set number of list show a display");
define("_MI_CCENTER_DEF_ATTRS","Standardeinstellungen");
define("_MI_CCENTER_DEF_ATTRS_DESC","Setting form definition and other attribute <a href='help.php#attr'>default options</a>. Example: <tt>size=60,rows=5,cols=50</tt>");
define("_MI_CCENTER_STATUS_COMBO", "Statusauswahl");
define("_MI_CCENTER_STATUS_COMBO_DESC","the Format as: <tt>Display-label: [status1[,status2...]]</tt>, include multipule lines. the status is a character from (-,a,b,c). Example: <tt>Open: - a</tt>");
define("_MI_CCENTER_STATUS_COMBO_DEF","Alle: - a b c\nOffen: - a\nGeschlossen: b c\n--------:\nWartend: -\nin Bearbeitung: a\nGeantwortet: b\nFertig: c\n");

// Notifications
define("_MI_CCENTER_GLOBAL_NOTIFY","Alle Formulare");
define("_MI_CCENTER_FORM_NOTIFY","Dieses Formular");
define("_MI_CCENTER_MESSAGE_NOTIFY","Diese Nachricht");

define("_MI_CCENTER_NEWPOST_NOTIFY","Kontakt Nachricht");
define("_MI_CCENTER_NEWPOST_NOTIFY_CAP","Notify contact message");
define("_MI_CCENTER_NEWPOST_SUBJECT","Post contact message");

define("_MI_CCENTER_STATUS_NOTIFY","Update status");
define("_MI_CCENTER_STATUS_NOTIFY_CAP","Benachrichtigung bei Änderungen");
define("_MI_CCENTER_STATUS_SUBJECT","Status:[{X_MODULE}]{FORM_NAME}");

define("_MI_SAMPLE_FORM","Create sample form");
define("_MI_SAMPLE_TITLE","Kontaktformular");
define("_MI_SAMPLE_DESC","Füllen Sie bitte einfach das Formular aus, wenn Sie uns etwas mitteilen möchten");
define("_MI_SAMPLE_DEFS","Ihr Name*,size=40\nEmail*,mail,size=60\nInfos zu*,radio,Information,Produkte,Sonstiges\nMeine Auswahl,checkbox,Rot,Grün,Blau\nGröße auswählen,select,Groß,Normal,Klein\nNachricht*,textarea,cols=50,rows=5\nSie können uns auch Dateien senden\nDatei anhängen,file,size=60\nBewertung: Ihre Seite finde ich,radio,sehr gut+,gut<br/>,*=andere");


/**
*
* added in 1.0
*
*/

// Added to adminmenu
define("_MI_CCENTER_TEMPLATES", "Templates");
define("_MI_CCENTER_INDEXPAGE", "Indexseite");

// Added to icms_version
define("_MI_CCENTER_HEADER_TPL", "Header-Template");
define("_MI_CCENTER_ADMIN_FORM_TPL", "ACP-Template");
define("_MI_CCENTER_BLOCK_RECEIPT_DSC", "Zeigt die erhaltenen Nachrichten");
define("_MI_CCENTER_BLOCK_FORM_DSC", "Zeigt ein ausgewähltes Formular im Block");
define("_MI_CCENTER_SHOW_BREADCRUMBS", "Zeige breadcrumbs");
define("_MI_CCENTER_SHOW_BREADCRUMBS_DESC", "Wähle <em>JA</em>, um die Breadcrumb anzuzeigen.");
define("_MI_CCENTER_SHOW_FORMS", "Anzahl an Formularen");
define("_MI_CCENTER_SHOW_FORMS_DESC", "Wie viele Formulare sollen auf der Indexseite in der Liste auftauchen?");


define('_AM_CCENTER_ADD_FORM','Neues Formular');

