<?php
// $Id: common.php,v 1.1 2009-07-02 01:57:48 nobu Exp $
// common user and admin

// message status
define('_CC_STATUS','Status');

define('_CC_STATUS_NONE','Kein');
define('_CC_STATUS_ACCEPT','Angenommen');
define('_CC_STATUS_REPLY','Geantwortet');
define('_CC_STATUS_CLOSE','Geschlossen');
define('_CC_STATUS_DEL','Gelöscht');

define('_CC_SORT_ORDER','Reihenfolge');
define('_CC_USER_NONE','Kein');

define('_CC_FORM_PRIM_GROUP', 'Mitglied [%s]');
define('_CC_LOG_STATUS','Status: von "%s" zu "%s"');
define('_CC_LOG_TOUSER','Verantwortlicher: von "%s" zu "%s"');
define('_CC_LOG_COMMENT','Kommentar schreiben');
define('_CC_LOG_BYCHARGE',': Verantwortlicher');
define('_CC_NOTIFY_SUBJ','{X_SITENAME}:{SUBJECT}');

define('_CC_EXPORT_THIS_MONTH','Diesen Monat');
define('_CC_EXPORT_LAST_MONTH','Letzten Monat');
define('_CC_EXPORT_THIS_YEAR','Dieses Jahr');
define('_CC_EXPORT_LAST_YEAR','Letztes Jahr');
define('_CC_EXPORT_ALL','Alle');
define('_CC_MARK_READIT','[x]');

define('_CC_STORE_MODE','Informationen speichern=1,Nur aufzeichnen=0,Nie speichern=2');

/**
*
* added in 1.0
*
**/

// Definitionen zur Formularerstellung im ACP
define("_CO_CCENTER_FORM_TITLE", "Titel");
define("_CO_CCENTER_FORM_TITLE_DSC", " Name des Formulars ");

define("_CO_CCENTER_FORM_DESCRIPTION", " Beschreibung ");
define("_CO_CCENTER_FORM_DESCRIPTION_DSC", " Hier können Sie das Formular erstellen und/oder eine Beschreibung hinzufügen. ");

define("_CO_CCENTER_FORM_DEFS", "Formularelemente");
define("_CO_CCENTER_FORM_DEFS_DSC", " Fügen Sie Formularelemente hinzu ");

define("_CO_CCENTER_FORM_GRPPERM", "Gruppenberechtigungen");
define("_CO_CCENTER_FORM_GRPPERM_DSC", " Welche Gruppen können das Formular benutzen? ");

define("_CO_CCENTER_FORM_PRIUID", "Benutzer");
define("_CO_CCENTER_FORM_PRIUID_DSC", " Welcher Benutzer erhält die Resultate des Formulars? ");

define("_CO_CCENTER_FORM_CGROUP", "Kontakt-Gruppe");
define("_CO_CCENTER_FORM_CGROUP_DSC", " Welche Gruppe erhält die Resultate des Formulars? ");

define("_CO_CCENTER_FORM_STORE", "Sichern");
define("_CO_CCENTER_FORM_STORE_DSC", " Wählen Sie, wie das Formular verarbeitet werden soll ");

define("_CO_CCENTER_FORM_ACTIVE", "Online-Status");
define("_CO_CCENTER_FORM_ACTIVE_DSC", " Wählen Sie <em>JA</em> um das Formular zu aktivieren. ");

define("_CO_CCENTER_FORM_WEIGHT", "Gewichtung");
define("_CO_CCENTER_FORM_WEIGHT_DSC", " Zur Sortierung des Formulars. ");

define("_CO_CCENTER_FORM_CUSTOM", "Verwendetes Template");
define("_CO_CCENTER_FORM_CUSTOM_DSC", " Wählen Sie, welches Template Sie zur Darstellung im Frontend nutzen möchten. ");

define("_CO_CCENTER_FORM_OPTVARS", "Optionale Variablen");
define("_CO_CCENTER_FORM_OPTVARS_DSC", " ");

define("_CO_CCENTER_FORM_NOTIFY_WITH_EMAIL", "bENACHRICHTIGEN PER EMAIL");
define("_CO_CCENTER_FORM_NOTIFY_WITH_EMAIL_DSC", " Zeigt im Formular die Option 'per E-Mail benachrichtigen' ");

define("_CO_CCENTER_FORM_REDIRECT", "weiterleiten");
define("_CO_CCENTER_FORM_REDIRECT_DSC", " Weiterleitung nach absenden des Formulars ");

define("_CO_CCENTER_FORM_REPLY_COMMENT", "Nachricht hinzufügen in automatische E-Mail");
define("_CO_CCENTER_FORM_REPLY_COMMENT_DSC", "   ");

define("_CO_CCENTER_FORM_REPLY_USE_COMTPL", "Hinzufügen Nachricht an e-Mail-Vorlage");
define("_CO_CCENTER_FORM_REPLY_USE_COMTPL_DSC", "  ");

define("_CO_CCENTER_FORM_OTHERS", "Andere Variablen ('Name=Value'-Stil)");
define("_CO_CCENTER_FORM_OTHERS_DSC", "  ");

define("_CO_CCENTER_FORM_TAG", "TAG's");
define("_CO_CCENTER_FORM_TAG_DSC", " Wenn das Sprockets-Modul installiert ist, lassen sich Formulare anhand der Tags kategorisieren ");


// Definitionen zur Erstellung der Indexseite im ACP
define("_CO_CCENTER_INDEXPAGE_INDEXHEADER", "Titel");
define("_CO_CCENTER_INDEXPAGE_INDEXHEADER_DSC", " Name des Formulars ");

define("_CO_CCENTER_INDEXPAGE_INDEXHEADING", "HEADING");
define("_CO_CCENTER_INDEXPAGE_INDEXHEADING_DSC", " Name des Formulars ");

define("_CO_CCENTER_INDEXPAGE_INDEXIMAGE", "IMAGE");
define("_CO_CCENTER_INDEXPAGE_INDEXIMAGE_DSC", " Name des Formulars ");

define("_CO_CCENTER_INDEXPAGE_INDEXFOOTER", "FOOTER");
define("_CO_CCENTER_INDEXPAGE_INDEXFOOTER_DSC", " Name des Formulars ");







