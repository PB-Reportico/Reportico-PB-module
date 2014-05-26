<?php
    $security_level = validate_ajax_user();
    define('SW_FRAMEWORK_DB_DRIVER','pdo_mysql');
    define('SW_FRAMEWORK_DB_USER',DB_SERVER_USERNAME);
    define('SW_FRAMEWORK_DB_PASSWORD',DB_SERVER_PASSWORD);
    define('SW_FRAMEWORK_DB_HOST',DB_SERVER_HOST);
    define('SW_FRAMEWORK_DB_DATABASE',DB_DATABASE);


	ob_start();
    date_default_timezone_set(@date_default_timezone_get());
	error_reporting(E_ALL);
	require_once('modules/reportico/classes/reportico/reportico.php');
	$a = new reportico();
	$a->allow_maintain = "FULL";
	$a->allow_debug = true;
	$a->embedded_report = true;
	$a->reportico_ajax_script_url = "index.php";
	$a->reportico_ajax_mode = true;
    $a->reportico_ajax_preloaded = true;
    $a->user_template = 'phree';
    $a->user_parameters["DB_PREFIX"] = DB_PREFIX;
	//$a->forward_url_get_parameters = "module=reportico&page=ajax";

    $_SESSION["template"] = "phree";
    $_SESSION["linkbaseurl"] = "index.php";
    session_request_item('linkbaseurl', "modules/reportico/classes/reportico/embedded.php");
	$a->execute();
	ob_end_flush();
?>
