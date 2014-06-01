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
//  Path: /modules/reportico/config.php
//
// Release History
// 3.0 => 2011-01-15 - Converted from stand-alone PhreeBooks release
// 3.1 => 2011-04-15 - Bug fixes
// 3.2 => 2011-08-01 - Bug fixes
// 3.3 => 2011-11-15 - bug fixes, themeroller changes
// 3.4 => 2012-02-15 - bug fixes
// Module software version information
define('MODULE_REPORTICO_VERSION', '3.2');
// Menu Sort Positions
// Menu Security id's (refer to master doc to avoid security setting overlap)
define('SECURITY_ID_REPORTICO', 3);
// New Database Tables
// Set the title menu
// Menu Locations
if (defined('MODULE_REPORTICO_STATUS')) {
  $mainmenu["tools"]['submenu']['reportico'] = array(
    'text'        => MENU_HEADING_REPORTICO,
    'heading'     => MENU_HEADING_TOOLS,
    'order'       => 40,
    'security_id' => SECURITY_ID_REPORTICO,
    'link'        => html_href_link(FILENAME_DEFAULT, 'module=reportico&amp;page=reports', 'SSL'),
    'show_in_users_settings' => true,
  );
}
?>
