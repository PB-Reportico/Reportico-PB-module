<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright (c) 2008, 2009, 2010, 2011, 2012 PhreeSoft, LLC       |
// | http://www.PhreeSoft.com                                        |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/reportico/pages/reports/pre_process.php
//
$security_level = validate_user(SECURITY_ID_REPORTICO);
/**************  include page specific files    *********************/
require_once(DIR_FS_WORKING . 'defaults.php');
/**************   page specific initialization  *************************/
$date        = $_GET['search_date']       ? gen_db_date($_GET['search_date']) : date('Y-m-d');
$search_text = $_GET['search_text'] == TEXT_SEARCH ? ''         : db_input($_GET['search_text']);
$action      = isset($_GET['action'])     ? $_GET['action']     : $_POST['todo'];
$module_id   = isset($_POST['module_id']) ? $_POST['module_id'] : '';
$row_seq     = isset($_POST['rowSeq'])    ? $_POST['rowSeq']    : '';
// load methods
$installed_modules = load_all_methods('reportico');
/***************   hook for custom actions  ***************************/
$custom_path = DIR_FS_WORKING . 'custom/pages/reports/extra_actions.php';
if (file_exists($custom_path)) { include($custom_path); }
/***************   Act on the action request   *************************/
if ($module_id) {
  require_once (DIR_FS_WORKING.'methods/'.$module_id.'/'.$module_id.'.php');
  $reportico = new $module_id;
  switch ($action) {
    default:
      if (method_exists($reportico, $action)) $reportico->$action();
      break;
    case 'track':     $reportico->trackPackages($date, $row_seq);   break;
    case 'reconcile': $reportico->reconcileInvoice();               break;
    case 'search':
    case 'search_reset':
  }
}
/*****************   prepare to display templates  *************************/
$cal_ship = array(
  'name'      => 'cal',
  'form'      => 'repform',
  'fieldname' => 'search_date',
  'imagename' => 'btn_date_1',
  'default'   => gen_locale_date($date),
  'params'    => array('align'=>'left', 'onchange'=>'calendarPage();'),
);
$include_header   = true;
$include_footer   = true;
$include_template = 'template_main.php';
define('PAGE_TITLE', MENU_HEADING_REPORTICO);
?>
