<?php
/*
 Reportico - PHP Reporting Tool
 Copyright (C) 2010-2013 Peter Deed

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

 * File:        configureproject.xml.php
 *
 * This is the core Reportico Reporting Engine. The main 
 * reportico class is responsible for coordinating
 * all the other functionality in reading, preparing and
 * executing Reportico reports as well as all the screen
 * handling.
 *
 * @link http://www.reportico.co.uk/
 * @copyright 2010-2013 Peter Deed
 * @author Peter Deed <info@reportico.org>
 * @package Reportico
 * @license - http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @version : reportico.php,v 1.58 2013/04/24 22:03:22 peter Exp $
 */

//global $_configure_mode;

// Extract Criteria Options
$configparams = array();

global $g_system_errors;

$g_system_errors = array();
global $g_debug_mode;
global $g_no_sql;
if ( $_configure_mode != "DELETE" )
{
    $configparams["SW_PROJECT_PASSWORD"] = $_criteria["projectpassword"]->get_criteria_value("VALUE", false);
    $configparams["SW_DB_TYPE"] = $_criteria["dbtype"]->get_criteria_value("VALUE", false);
    $configparams["SW_DB_DATABASE"] = $_criteria["database"]->get_criteria_value("VALUE", false);
    $configparams["SW_DB_HOST"] = $_criteria["host"]->get_criteria_value("VALUE", false);
    $configparams["SW_DB_SERVER"] = $_criteria["server"]->get_criteria_value("VALUE", false);
    $configparams["SW_DB_USER"] = $_criteria["user"]->get_criteria_value("VALUE", false);
    $configparams["SW_DB_PASSWORD"] = $_criteria["password"]->get_criteria_value("VALUE", false);
    $configparams["SW_DB_PROTOCOL"] = $_criteria["protocol"]->get_criteria_value("VALUE", false);
    $configparams["SW_HTTP_BASEDIR"] = $_criteria["baseurl"]->get_criteria_value("VALUE", false);
    $configparams["SW_PROJECT"] = $_criteria["project"]->get_criteria_value("VALUE", false);
    $configparams["SW_PROJECT_TITLE"] = $_criteria["projtitle"]->get_criteria_value("VALUE", false);
    $configparams["SW_STYLESHEET"] = $_criteria["stylesheet"]->get_criteria_value("VALUE", false);
    if ( $_configure_mode == "CREATE" )
        $configparams["SW_SAFE_DESIGN_MODE"] = true;
    else
        $configparams["SW_SAFE_DESIGN_MODE"] = $_criteria["safemode"]->get_criteria_value("VALUE", false);

    $configparams["SW_DB_DATEFORMAT"] = $_criteria["dbdateformat"]->get_criteria_value("VALUE", false);
    $configparams["SW_PREP_DATEFORMAT"] = $_criteria["displaydateformat"]->get_criteria_value("VALUE", false);
    $configparams["SW_DB_ENCODING"] = $_criteria["dbencoding"]->get_criteria_value("VALUE", false);
    $configparams["SW_OUTPUT_ENCODING"] = $_criteria["outputencoding"]->get_criteria_value("VALUE", false);
    $configparams["SW_LANGUAGE"] = $_criteria["language"]->get_criteria_value("VALUE", false);


    if ( !$configparams["SW_DB_TYPE"] ) { trigger_error ( "Specify Database Type" ); return; }

    $test = new reportico_datasource("array", "localhost");
    $test->driver = $configparams["SW_DB_TYPE"];

    if ( $test->driver != "framework" )
    {
        if ( !$configparams["SW_DB_DATABASE"] ) { trigger_error ( "Specify Database Name" ); return; }
        if ( !$configparams["SW_DB_USER"]  && $configparams["SW_DB_TYPE"] != "pdo_sqlite3" ) { trigger_error ( "Specify Database User" ); return; }
        if ( !$configparams["SW_DB_HOST"] ) { trigger_error ( "Specify Database Host" ); return; }
    }

    if ( !$configparams["SW_PROJECT"] ) { trigger_error ( "Specify Project Name" ); return; }
    if ( !$configparams["SW_PROJECT_TITLE"] ) { trigger_error ( "Specify Project Title" ); return; }
    if ( !$configparams["SW_HTTP_BASEDIR"] ) { trigger_error ( "Specify Base URL" ); return; }

    $g_debug_mode = true;
    $g_no_sql = true;



    $test->user_name = $configparams["SW_DB_USER"];
    $test->password = $configparams["SW_DB_PASSWORD"];
    $test->host_name = $configparams["SW_DB_HOST"];
    $test->database = $configparams["SW_DB_DATABASE"];
    $test->server = $configparams["SW_DB_SERVER"];
    $test->protocol = $configparams["SW_DB_PROTOCOL"];

    if ( $test->driver == "framework" )
    {
        $configparams["SW_DB_USER"] = "N/A";
        $configparams["SW_DB_PASSWORD"] = "N/A";
        $configparams["SW_DB_HOST"] = "N/A";
        $configparams["SW_DB_DATABASE"] = "N/A";
        $configparams["SW_DB_SERVER"] = "N/A";
        $configparams["SW_DB_PROTOCOL"] = "N/A";

    }
    else
    {
        $test->connect(true);
        if ( $test->connected )
            handle_debug("Connection to Database succeeded", 0);
        else
        {
            trigger_error("Connection to Database failed");
            return;
        }
    }

}
else
{
    $configparams["SW_PROJECT"] = SW_PROJECT;
    $configparams["SW_PROJECT_TITLE"] = SW_PROJECT_TITLE;
}

$proj_parent = find_best_location_in_include_path( "projects" );
$proj_dir = $proj_parent."/".$configparams["SW_PROJECT"];
$proj_conf = $proj_dir."/config.php";
$proj_menu = $proj_dir."/menu.php";
$proj_lang = $proj_dir."/lang.php";

$proj_template = $proj_parent."/admin/config.template";
$menu_template = $proj_parent."/admin/menu.template";
$lang_template = $proj_parent."/admin/lang.template";


if ( !file_exists ( $proj_parent ) )
{
    trigger_error ("Projects area $proj_parent does not exist - cannot write project");
    return;
}

if ( !is_writeable ( $proj_parent  ) )
{
    if ( $_configure_mode == "DELETE" )
        trigger_error ("Projects area $proj_parent is not writeable - cannot delete project");
    else
        trigger_error ("Projects area $proj_parent is not writeable - cannot write project");
    return;
}

if ( file_exists ( $proj_dir ) )
{
    if ( $_configure_mode == "CREATE" || $_configure_mode == "CREATETUTORIALS" )
    {
        trigger_error ("Projects area $proj_dir already exists - cannot write project - use Configure Project instead");
    	return;
    }
}
else 
if ( $_configure_mode != "CREATE" )
{
        trigger_error ("Unable to access project. Projects area $proj_dir does not exist - if you are trying to rename the project, then rename the project folder manually");
    	return;
}
else
    if ( !mkdir ( $proj_dir ) )
    {
        trigger_error ("Failed to create project directory $proj_dir");
        return;
    }

if ( !is_writeable ( $proj_dir ) )
{
   if ( ! chmod ( $proj_dir, "u+rwx") )
   {
        trigger_error ("Failed to make project directory $proj_dir writeable ");
   }
}

if ( !file_exists ( $proj_conf ) && $_configure_mode == "DELETE" )
{
    trigger_error ("Projects configuration file $proj_conf not found. Project already deleted/deactivated");
    return;
}

if ( file_exists ( $proj_conf ) && $_configure_mode == "DELETE" )
{
    if ( !($status = rename ( $proj_conf, $proj_conf.".deleted" )) )
        trigger_error ("Failed to disable $proj_conf file. Possible permission, configuration problem");
    else
	    handle_debug("Project Deleted Successfully", 0);
    $g_no_sql = true;
    
    return;
}

if ( file_exists ( $proj_conf ) && !is_writeable($proj_conf) )
{
    trigger_error ("Projects configuration file $proj_conf exists but is not writeble. Cannot continue");
    return;
}

if ( $_configure_mode == "CREATE" || $_configure_mode == "CREATETUTORIALS" )
{
	$txt = file_get_contents($proj_template);
}
else
{
    $conffound = false;
	if ( file_exists ( $proj_conf ) )
    {
		$txt = file_get_contents($proj_conf);

        // If the config file does not have a SW_DB_TYPE entry then we are running a pre-2.8
        // report with a post 2.8 reportico ... so generate a new one from the admin template
        if ( preg_match ( "/SW_DB_TYPE/", $txt ))
        {
            $conffound = true;
        }
        else
        {
            if ( $configparams["SW_DB_TYPE"] == "framework" )
            {
                handle_debug ("Warning - This project was created with an older version of reportico which cannot use the connection details of an application framework. In order to connect to a framework the project configuration file ".$configparams["SW_PROJECT"]."/config.php was updated. Any manually made modifications are saved as the original config.php was backed up to the file config.php.orig.", 0);
	            $retval = file_put_contents($proj_conf.".orig", $txt);
            }
        }
    }

    if ( !$conffound )
    {
		if ( file_exists ( $proj_template ) )
			$txt = file_get_contents($proj_template);
		else
		{
    			trigger_error ("Cannot find source $proj_conf or $proj_template to configure");
    			return;
		}
    }
}

$matches = array();


if ( $configparams["SW_DB_TYPE"] == "framework" )
{
        handle_debug("Connection to Database not checked as framework database connections have been used", 0);
}

// If this is a reportico pre 2.8 then it wont handle "framework" type
foreach ( $configparams as $paramkey => $paramval )
{
	if ( $paramkey == "SW_PROJECT" ) 
		continue;


    // Check if parameter exists in config file and if not add it (caters for new parameters in Reportico for existing projects )
	$match = preg_match ( "/(define.*?$paramkey',).*\);/", $txt);
    if ( !$match )
    {
	    $txt = preg_replace ( "/\?>/", "\n// Automatic addition of parameter $paramkey\ndefine('$paramkey', '$paramval');\n?>", $txt);
    }
    else
    {
        if ( $paramkey == "SW_SAFE_DESIGN_MODE" )
        {
            if ( $paramval )
                $paramval = "true";
            else
                $paramval = "false";
            $txt = preg_replace ( "/(define.*?$paramkey',).*\);/", "$1$paramval);", $txt);
        }
        else
        {
            $paramval = $paramval;
            $txt = preg_replace ( "/define\('$paramkey', *'.*'\);/", "define('$paramkey', '$paramval');", $txt);
        }
    }
}

$retval = file_put_contents($proj_conf, $txt);

if ( $_configure_mode == "CREATE"  || $_configure_mode == "CREATETUTORIALS" )
{
	$txt = file_get_contents($menu_template);
	$retval = file_put_contents($proj_menu, $txt);
	$txt = file_get_contents($lang_template);
	$retval = file_put_contents($proj_lang, $txt);
}

if ( !$configparams["SW_PROJECT_PASSWORD"] ) handle_debug ("Warning - Project password not set - any user will be able to run reports in this project", 0);

if ( $_configure_mode == "CREATETUTORIALS" )
	handle_debug("Tutorials Created Successfully", 0);
else if ( $_configure_mode == "CREATE" )
	handle_debug("Project Created Successfully", 0);
else
	handle_debug("Project Configuration Updated Successfully", 0);

$g_debug_mode = false;





?>
