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
//  Path: /modules/reportico/classes/install.php
//

class reportico_admin {
  function reportico_admin() {
	$this->notes = array(); // placeholder for any operational notes
	$this->prerequisites = array( // modules required and rev level for this module to work properly
	  'contacts'   => '3.6',
	  'phreedom'   => '3.4',
	  'phreebooks' => '3.4',
	);
	// Load configuration constants for this module, must match entries in admin tabs
    $this->keys = array();
	// add new directories to store images and data
	$this->dirlist = array(
	);
	// Load tables
	$this->tables = array(
    );
  }

  function install($module) {
	$error = false;
    return $error;
  }

  function initialize($module) {
  }

  function update($module) {
    global $db, $messageStack;
	$error = false;
	if (!$error) {
	  write_configure('MODULE_' . strtoupper($module) . '_STATUS', constant('MODULE_' . strtoupper($module) . '_VERSION'));
   	  $messageStack->add(sprintf(GEN_MODULE_UPDATE_SUCCESS, $module, constant('MODULE_' . strtoupper($module) . '_VERSION')), 'success');
	}
	return $error;
  }

  function remove($module) {
	$error = false;
	// load and remove all modules
	$method_dir = DIR_FS_ADMIN . 'modules/' . $module . '/methods/';
	$methods = array();
	if ($dir = @dir($method_dir)) {
	  while ($choice = $dir->read()) {
		if (file_exists($method_dir . $choice . '/' . $choice . '.php') && $choice <> '.' && $choice <> '..') {
		  $methods[] = $choice;
		}
	  }
	  $dir->close();
	  foreach ($methods as $method) {
	    require_once(DIR_FS_ADMIN . 'modules/' . $module . '/methods/' . $method . '/' . $method . '.php');
	    $properties = new $method();
	    remove_configure('MODULE_' . strtoupper($module) . '_' . strtoupper($method) . '_STATUS');
	    foreach ($properties->keys() as $key) remove_configure($key['key']);
	    if (method_exists($properties, 'remove')) $properties->remove();
	  }
	}
	return $error;
  }

  function load_reports($module) {
  }

  function load_demo() {
  }

}
?>
