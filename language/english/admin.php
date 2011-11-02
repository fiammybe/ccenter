<?php
// $Id: admin.php,v 1.4 2011-03-14 13:59:16 nobu Exp $

define('_AM_FORM_EDIT', 'Edit contact form');
define('_AM_FORM_NEW', 'Neues Kontaktformular erstellen');
define('_AM_FORM_TITLE', 'Name des Formulares');
define('_AM_FORM_MTIME', 'Aktualisiert');
define('_AM_FORM_DESCRIPTION', 'Beschreibung');
define('_AM_INS_TEMPLATE', 'Adding template');
define('_AM_FORM_ACCEPT_GROUPS', 'Gruppenauswahl');
define('_AM_FORM_ACCEPT_GROUPS_DESC', 'Dieses Formular kann von folgenden Gruppen benutzt werden');
define('_AM_FORM_DEFS', 'Formular Definitionen');
define('_AM_FORM_DEFS_DESC', '<a href="help.php#form" target="_blank">Definitionen</a> <small>Typen: text checkbox radio textarea select const hidden mail file</small>');
define('_AM_FORM_PRIM_CONTACT', 'Kontaktperson');
define('_AM_FORM_PRIM_NONE', 'Kein');
define('_AM_FORM_PRIM_DESC', 'Wählen Sie die Mitglieder der Gruppe, The contact person need select by uid argument from the group');
define('_AM_FORM_CONTACT_GROUP', 'Kontaktgruppe');
define('_AM_FORM_CGROUP_NONE', 'Kein');
define('_AM_FORM_STORE', 'In der Datenbank speichern');
define('_AM_FORM_CUSTOM', 'Beschreibungstyp');
define('_AM_FORM_WEIGHT', 'Reihenfolge');
define('_AM_FORM_REDIRECT', 'Display page after sending');
define('_AM_FORM_OPTIONS', 'Optionelle Variablen');
define("_AM_FORM_OPTIONS_DESC","Voreinstellungen definieren und andere Eigenschaften <a href='help.php#attr'>Voreingestellte Optionen</a>. <br />Beispiel: <tt>size=60,rows=5,cols=50</tt>");
define('_AM_FORM_ACTIVE', 'Aktiv?');
define('_AM_DELETE_FORM', 'Formular löschen');
define('_AM_FORM_LAB', 'Name des Feldes');
define('_AM_FORM_LABREQ', 'Please input item name');
define('_AM_FORM_REQ','Erforderlich');
define('_AM_FORM_ADD', 'Hinzufügen');
define('_AM_FORM_OPTREQ', 'Need option argument');
define('_AM_CUSTOM_DESCRIPTION', '0=Normal[bb],4=HTML Beschreibung[bb],1=Part template,2=Overall template');
define('_AM_CHECK_NOEXIST', 'Variables not exist');
define('_AM_CHECK_DUPLICATE', 'Variable duplicates');
define('_AM_DETAIL', 'Details');
define('_AM_OPERATION', 'Aktionen');
define('_AM_CHANGE','Ädern');
define('_AM_SEARCH_USER', 'Suche Benutzer');

define('_AM_MSG_ADMIN', 'Contact Admin');
define('_AM_MSG_CHANGESTATUS', 'Status ädern');
define('_AM_SUBMIT', 'Aktualisieren');

define('_AM_MSG_COUNT', 'Anzahl');
define('_AM_MSG_STATUS', 'Status');
define('_AM_MSG_CHARGE', 'Verantwortlicher');
define('_AM_MSG_FROM', 'Von');
define('_AM_MSG_COMMS', 'Kommentare');

define('_AM_MSG_WAIT', 'Warten');
define('_AM_MSG_WORK', 'in Bearbeitung');
define('_AM_MSG_REPLY', 'Beantwortet');
define('_AM_MSG_CLOSE', 'Geschlossen');
define('_AM_MSG_DEL', 'Löschen');

define('_AM_MSG_CTIME', 'Registerd');
define('_AM_MSG_MTIME', 'Aktualisiert');

define('_AM_MSG_UPDATED', 'Status geändert');
define('_AM_MSG_UPDATE_FAIL', 'Aktualisierung fehlgeschlagen');

define('_AM_LOGGING','Historie');

define('_AM_FORM_UPDATED', 'Formular gespeichert');
define('_AM_FORM_DELETED', 'Formular gelöscht');
define('_AM_FORM_UPDATE_FAIL', 'Aktualisierung des Formulares fehlgeschlagen');
define('_AM_TIME_UNIT', '%d Min , %d Stunden , %d Tage , vor %s');
define('_AM_NODATA', 'Keine Daten');
define('_AM_SUBMIT_VIEW','Refresh');
define('_AM_OPTVARS_SHOW','Zeige mehr Einstellungen');
define('_AM_OPTVARS_LABEL','notify_with_email=Benachrichtigen Sie per E-Mail-Adresse angezeigt
redirect=Weiterleitung nach vorzulegen
reply_comment=Nachricht hinzufügen in automatische E-Mail
reply_use_comtpl=Hinzufügen Nachricht an e-Mail-Vorlage
others=Andere Variablen ("Name=Value"-Stil)
');

/**
*
* added in 1.0
*
*/

// Requirements
define("_AM_CCENTER_REQUIREMENTS", "'Contact Center' Voraussetzungen");
define("_AM_CCENTER_REQUIREMENTS_INFO", "Wir haben Ihr System überprüft und festgestellt, das nicht alle Anforderungen erfüllt sind um das Kontakt Modul benutzen zu können. Hier sind die benötigten Anforderungen:");
define("_AM_CCENTER_REQUIREMENTS_ICMS_BUILD", "'Contact Center' benötigt ImpressCMS 1.3 final oder höher.");
define("_AM_CCENTER_REQUIREMENTS_SUPPORT", "Sollten Sie Fragen oder Bedenken haben, besuchen Sie einfach unser Forum <a href='http://community.impresscms.org/modules/newbb/viewforum.php?forum=9'>impresscms.org</a>.");

// Select box for sprocket-module
define("_AM_CCENTER_ITEM_FILTER_BY_TAG", "Filtern nach Tag");
define("_AM_CCENTER_ITEM_ALL_ITEMS", "-- ALLE --");


define('_AM_CCENTER_FORM_ONLINE', 'online');
define('_AM_CCENTER_FORM_OFFLINE', 'offline');

define('_AM_CCENTER_INDEXPAGE_EDIT', 'Indexseite bearbeiten');
define('_AM_CCENTER_INDEXPAGE_MODIFIED', 'Indexseite wurde geändert');

define('_AM_CCENTER_FORM_CREATE', 'Neues Kontaktformular erstellen');
define('_AM_CCENTER_FORM_MODIFIED', 'Formular gespeichert');
define('_AM_CCENTER_FORM_CREATED', 'Formular erstellt');

define('_AM_CCENTER_PREVIEW', 'Vorschau');
define('_AM_CCENTER_FORM_VIEW', 'Anschauen');
define('_AM_CCENTER_FORM_CLONE', 'duplizieren');
