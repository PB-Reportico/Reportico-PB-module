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
//  Path: /modules/reportico/pages/reports/template_main.php
//

echo "<form id='reporticoform' action='http://www.google.co.uk'>";
//echo html_form('reports', FILENAME_DEFAULT, gen_get_all_get_params(array('action')), 'post', 'enctype="multipart/form-data"', true) . chr(10);
// include hidden fields

require_once(DIR_FS_WORKING . 'pages/' . $page . '/js_include.php'); 

echo html_hidden_field('todo',   '')    . chr(10);
echo html_hidden_field('rowSeq', '')    . chr(10);
echo html_hidden_field('module_id', '') . chr(10);
// customize the toolbar actions
$toolbar->icon_list['cancel']['params'] = 'onclick="location.href = \'' . html_href_link(FILENAME_DEFAULT, '', 'SSL') . '\'"';
$toolbar->icon_list['open']['show']     = false;
$toolbar->icon_list['save']['show']     = false;
$toolbar->icon_list['delete']['show']   = false;
$toolbar->icon_list['print']['show']    = false;
if (count($extra_toolbar_buttons) > 0) foreach ($extra_toolbar_buttons as $key => $value) $toolbar->icon_list[$key] = $value;
$toolbar->add_help('09');
//echo $toolbar->build_toolbar($add_search = false, false, $cal_ship); 
// Build the page
?>
<div id="reporticotabs" style="padding: 0px">
<?php

    define('SW_FRAMEWORK_DB_DRIVER','pdo_mysql');
    define('SW_FRAMEWORK_DB_USER',DB_SERVER_USERNAME);
    define('SW_FRAMEWORK_DB_PASSWORD',DB_SERVER_PASSWORD);
    define('SW_FRAMEWORK_DB_HOST',DB_SERVER_HOST);
    define('SW_FRAMEWORK_DB_DATABASE',DB_DATABASE);

    //if ( substr(PHP_OS, 0, 3 ) == "WIN" )
	    //set_include_path(DIR_FS_MODULES . 'reportico/classes/reportico;'.get_include_path());
    //else
	    //set_include_path(DIR_FS_MODULES . 'reportico/classes/reportico:'.get_include_path());
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
	$a->forward_url_get_parameters = "module=reportico&page=ajax";
	$a->user_parameters["DB_PREFIX"] = DB_PREFIX;

    echo "<ul>";


    // Generate list of projects to choose from by finding all folders above the
    // current project area (i.e. the projects folder) and looking for any folder
    // that contains a config.php file (which means it proably is a project)
    $project_path = DIR_FS_MODULES . "reportico/classes/reportico/projects";
    if (is_dir($project_path))
    {  
?><h1 style="color: #ffffff; padding: 0px; margin: 0px 10px 0px 0px; clear: none; float: left"><?php echo MENU_HEADING_REPORTICO; ?></h1>

<?php
        $ct = 0;
        if ($dh = opendir($project_path))
        {  
            while (($file = readdir($dh)) !== false)
            {  
                if ( $file == "admin" )
                continue;
                if ( is_dir ( $project_path."/".$file ) )
                    if ( is_file ( $project_path."/".$file."/config.php" ) )
                    {  
                        $ct++;
                        echo "<li><a href=\"";
                        echo html_href_link(FILENAME_DEFAULT, 'module=reportico&page=ajax&template=phree&op=showmenu&execute_mode=MENU&project='.$file, 'SSL');
                        echo "\">$file</a></li>";
                    }
            }
            if ( $ct == 0 )
            {
                echo "<li><a href=\"";
                echo html_href_link(FILENAME_DEFAULT, 'module=reportico&page=ajax&template=phree&op=showmenu&execute_mode=MENU&project=admin', 'SSL');
                echo "\">Admin</a></li>";
            }
            closedir($dh);
        }
    }


    echo "</ul></div>";

	//$a->execute();
	ob_end_flush();
?>
</div>
<div id="reportico_dialog">&nbsp;</div>
</form>
