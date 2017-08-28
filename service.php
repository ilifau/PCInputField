<?php
/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * PCInputField plugin: save input by ajax
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */

// optionally set error before initialisation
// error_reporting (E_ALL);
// ini_set("display_errors","on");

chdir("../../../../../../../");

// this should bring us all session data of the desired client
// @see feed.php
if (isset($_GET["client_id"]))
{
    $cookie_domain = $_SERVER['SERVER_NAME'];
    $cookie_path = dirname( $_SERVER['PHP_SELF'] );

    /* if ilias is called directly within the docroot $cookie_path
    is set to '/' expecting on servers running under windows..
    here it is set to '\'.
    in both cases a further '/' won't be appended due to the following regex
    */
    $cookie_path .= (!preg_match("/[\/|\\\\]$/", $cookie_path)) ? "/" : "";

    if($cookie_path == "\\") $cookie_path = '/';

    $cookie_domain = ''; // Temporary Fix

    setcookie("ilClientId", $_GET["client_id"], 0, $cookie_path, $cookie_domain);

    $_COOKIE["ilClientId"] = $_GET["client_id"];
}

require_once("Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

require_once (dirname(__FILE__)."/classes/class.ilPCInputFieldService.php");
$service = new ilPCInputFieldService;
$service->handleRequest();
