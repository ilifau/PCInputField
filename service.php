<?php
/**
 * Copyright (c) ...
 * GPLv3, see docs/LICENSE
 */

/**
 * PCInputField plugin: save/send input via ajax
 */

// Debug nur bei Bedarf aktivieren (während Entwicklung):
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
// ini_set('log_errors', '1');

chdir("../../../../../../../../"); // 8 Ebenen bis ILIAS 11 root (public/ + 7 Plugin-Ebenen)

// Client kontext setzen (wie in feed.php)
if (isset($_GET["client_id"])) {
    $cookie_domain = $_SERVER['SERVER_NAME'];
    $cookie_path = dirname($_SERVER['PHP_SELF']);
    $cookie_path .= (!preg_match("/[\/|\\\\]$/", $cookie_path)) ? "/" : "";
    if ($cookie_path == "\\") { $cookie_path = '/'; }
    $cookie_domain = '';
    setcookie("ilClientId", $_GET["client_id"], 0, $cookie_path, $cookie_domain);
    $_COOKIE["ilClientId"] = $_GET["client_id"];
}

// ILIAS 11: Bootstrap + Kontext vor initILIAS() (siehe feed.php / xapiproxy.php)
require_once("artifacts/bootstrap_default.php");
entry_point('ILIAS Legacy Initialisation Adapter');
ilContext::init(ilContext::CONTEXT_WEB);

ilInitialisation::initILIAS();

require_once __DIR__ . "/classes/class.ilPCInputFieldService.php";
$service = new ilPCInputFieldService();
$service->handleRequest();
