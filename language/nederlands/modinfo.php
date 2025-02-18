<?php
// Module Informatie

// De naam van de module
define("_MI_CCENTER_NAME","Contact Center");

// A brief description of this module
define("_MI_CCENTER_DESC","Contact formulier met opslag en beheer van de antwoorden");

// Sub Menu
define("_MI_CCENTER_MYCONTACT", "Mijn boodschappen");
define("_MI_CCENTER_MYCHARGE", "Mijn taken");
define("_MI_CCENTER_STAFFDESK", "Dashboard");

// Admin Menu
define("_MI_CCENTER_FORMADMIN", "Formulieren");
define("_MI_CCENTER_MSGADMIN", "Boodschappen");
define("_MI_CCENTER_HELP", "Over ccenter");

// A brief template of this module
define("_MI_CCENTER_INDEX_TPL", "Lijst van formulieren");
define("_MI_CCENTER_FORM_TPL", "Contact formulier");
define("_MI_CCENTER_CUST_TPL", "Contact formulier (aangepast)");
define("_MI_CCENTER_CONF_TPL", "Bevestig formulier ingave");
define("_MI_CCENTER_LIST_TPL", "Lijst van mijn vragen");
define("_MI_CCENTER_CHARGE_TPL", "Lijst van mijn taken");
define("_MI_CCENTER_MSGS_TPL", "Toon contact boodschap");
define("_MI_CCENTER_RECEPT_TPL", "Team Dashboard");
define("_MI_CCENTER_WIDGET_TPL", "Formulier widgets");

// A brief blocks of this module
define("_MI_CCENTER_BLOCK_RECEIPT","Mijn taken");
define("_MI_CCENTER_BLOCK_FORM","Contact formulier");

// Configs
define("_MI_CCENTER_LISTS","Aantal lijst items");
define("_MI_CCENTER_LISTS_DESC","Het aantal lijst items om te tonen");
define("_MI_CCENTER_DEF_ATTRS","Standaard opties");
define("_MI_CCENTER_DEF_ATTRS_DESC","Setting form definition and other attribute <a href='help.php#attr'>default options</a>. Example: <tt>size=60,rows=5,cols=50</tt>");
define("_MI_CCENTER_STATUS_COMBO", "Statussen");
define("_MI_CCENTER_STATUS_COMBO_DESC","Het formaat: <tt>Display-label: [status1[,status2...]]</tt>, kan meerdere lijnen bevatten. De status is een letter van (-,a,b,c). Voorbeeld: <tt>Open: - a</tt>");
define("_MI_CCENTER_STATUS_COMBO_DEF","Alles: - a b c\nOpen: - a\nGesloten: b c\n--------:\nWachten: -\nWorking: a\nBeantwoord: b\nKlaar: c\n");

// Notifications
define("_MI_CCENTER_GLOBAL_NOTIFY","Alle formulieren");
define("_MI_CCENTER_FORM_NOTIFY","Dit formulier");
define("_MI_CCENTER_MESSAGE_NOTIFY","Deze boodschap");

define("_MI_CCENTER_NEWPOST_NOTIFY","Contact boodschap");
define("_MI_CCENTER_NEWPOST_NOTIFY_CAP","Notify contact message");
define("_MI_CCENTER_NEWPOST_SUBJECT","Verstuur bericht");

define("_MI_CCENTER_STATUS_NOTIFY","Update status");
define("_MI_CCENTER_STATUS_NOTIFY_CAP","Notify status changes");
define("_MI_CCENTER_STATUS_SUBJECT","Status:[{X_MODULE}]{FORM_NAME}");

define("_MI_SAMPLE_FORM","Maak voorbeeldformulier");
define("_MI_SAMPLE_TITLE","Contacteer ons");
define("_MI_SAMPLE_DESC","Gebruik dit formulier om ons te contacteren.");
define("_MI_SAMPLE_DEFS","Uw naam*,size=40\nEmail*,mail,size=60\nOnderwerp*,radio,Site inhoud,Vraag over ons,Andere\nMessage,textarea,cols=50,rows=5");
