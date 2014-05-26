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

 * File:        reportico.php
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
 * @version $Id: reportico.php,v 1.48 2013/07/30 21:12:28 peter Exp $
 */

// Include files
include_once('smarty/libs/Smarty.class.php');
require_once('swdb.php');
require_once('swsql.php');
require_once('swutil.php');
require_once('swpanel.php');

// Set up globals
$g_project = false;
$g_language = "en_gb";
$g_menu = false;
$g_menu_title = false;
$g_dropdown_menu = false;
$g_translations = false;
$g_locale = false;
$g_report_desc = false;

// Defines external plugin parameters
global $g_no_sql;
global $g_external_param1;   // Values passed form calling framworks
global $g_external_param2;   
global $g_external_param3;   

// Until next release can only include a config file from a single
// project, so use this variable to ensure only a single config file
// is included
global $g_included_config;
$g_included_config = false;

$g_no_sql = false;

// Session namespace for allowing multiple reporticos on a single 
// page when called from a framework. In name space in operation the
// session array index to find reportico variables can be found in "reportico"
// otherwise it's reportic_<namespace>
global $g_session_namespace;
global $g_session_namespace_key;
$g_session_namespace = false;
$g_session_namespace_key = "reportico";


/**
 * Class reportico_object
 *
 * Base class for other reportico classes. 
 */
class reportico_object
{

	var $debug = false;
	var $formats = array();
	var $attributes = array();
	var $default_attr = array();

	function reportico_object()
	{
		$this->default_attr = $this->attributes;
	}

	function debug($val)
	{
		if ( $this->debug )
			printf("<br>(X".get_class($this)."): $val\n");
	}

	function error($in_text)
	{
		trigger_error($in_text, E_USER_ERROR);
	}


	function & get_attribute ( $attrib_name )
		{
			if ( $this->attributes[$attrib_name] )
			{
				$val = check_for_default($attrib_name, $this->attributes[$attrib_name]);
				return $val;
			}
			else
			{
				$val = check_for_default($attrib_name, $this->attributes[$attrib_name]);
				return $val;
			}
		}
		
	// Parses a Reportico value ( e.g. criteria default, criteria value )
	// and if it indicates some kind of metavalue surrounded by {} then
	// convert it
	// Current syntax :-
	// {constant,<VALUE>} - returns defined PHP constants
	function & derive_meta_value ( $to_parse )
	{
		global $g_project;

		$parsed  = $to_parse;
        if ( preg_match ( "/{constant,SW_PROJECT}/", $parsed ) )
        {
            $parsed = $g_project;
            return $parsed;
        }
		else
        if ( preg_match ( "/{constant,SW_DB_DRIVER}/", $parsed ) )
        {
            if ( defined("SW_DB_TYPE") && SW_DB_TYPE == "framework" )
                $parsed = "framework";
            else
            {
                $parsed = preg_replace('/{constant,([^}]*)}/',
                        	'\1',
                        	$parsed);
			    if ( defined ( $parsed ) )
				    $parsed = constant($parsed);
			    else
				    $parsed = "";
            }
			return $parsed;
        }
		else
        if ( 
            preg_match ( "/{constant,SW_DB_PASSWORD}/", $parsed )  ||
            preg_match ( "/{constant,SW_DB_USER}/", $parsed )  ||
            preg_match ( "/{constant,SW_DB_DATABASE}/", $parsed ) 
        )
        {
            if ( defined("SW_DB_TYPE") && SW_DB_TYPE == "framework" )
                $parsed = "";
            else
            {
                $parsed = preg_replace('/{constant,([^}]*)}/',
                        	'\1',
                        	$parsed);
			    if ( defined ( $parsed ) )
				    $parsed = constant($parsed);
			    else
				    $parsed = "";
            }
			return $parsed;
        }
		else
		if ( preg_match ( "/{constant,.*}/", $parsed ) )
		{
            $parsed = preg_replace('/{constant,([^}]*)}/',
                        	'\1',
                        	$parsed);
			if ( defined ( $parsed ) )
				$parsed = constant($parsed);
			else
				$parsed = "";
			return $parsed;
		}
		else
			return $parsed;
	}

	function & derive_attribute ( $attrib_name, $default )
		{
			if ( $this->attributes[$attrib_name] )
			{
				return $this->attributes[$attrib_name];
			}
			else
			{
				return $default;
			}
		}


	function set_format ( $format_type, $format_value )
	{
		if ( !array_key_exists($format_type, $this->formats) )
			handle_error("Format Type ".$format_type." Unknown.");

		$this->formats[$format_type] = $format_value;
	}

	function get_format ( $format_type )
	{
		if ( !array_key_exists($format_type, $this->formats) )
			return;

		return $this->formats[$format_type];
	}

	function set_attribute ( $attrib_name, $attrib_value )
	{
		if ( !array_key_exists($attrib_name, $this->attributes ) )
			return;

		if ( $attrib_value )
			$this->attributes[$attrib_name] = $attrib_value;
		else
			$this->attributes[$attrib_name] = $this->default_attr[$attrib_name];
	}

	function & get_value ( $value_name )
	{
		return $this->values[$value_name];
	}

	function set_value ( $value_name, $value_value )
	{
		$this->values[$value_name] = $value_value;
	}

	function submitted ( $value_name )
	{
		if ( array_key_exists($value_name, $_REQUEST) )
			return true;
		else
			return false;
	}
}

/**
 * Class reportico
 *
 * Core functionality that plugs in the database handling,
 * screen handling, XML report definition handling.
 */
class reportico extends reportico_object
{
	var $name;
	var $rowselection="all";
	var $parent_query=false;
	var $allow_maintain = "FULL";
	var $embedded_report = false;
	var $allow_debug = false;
	var $user_template=false;
	var $xmlin=false;
	var $xmlout=false;
	var $xmloutfile=false;
	var $xmlintext=false;
	var $xmlinput=false;
	var $datasource;
	var $progress_text = "Ready";
	var $progress_status = "Ready"; // One of READY, WORKING, FINISHED, ERROR
	var $query_statement;
	var $maintain_sql = false;
	var $columns = array();
	var $tables = array();
	var $where_text;
	var $group_text;
	var $table_text;
	var $sql_raw = false;
	var $sql_limit_first = false;
	var $sql_skip_offset = false;
	var $display_order_set = array();
	var $order_set = array();
	var $group_set = array();
	var $groups = array();
	var $page_headers = array();
	var $page_footers = array();
	var $query_count = 0;
	var $expand_col = false;
	var $execute_mode;
	var $match_column = "";
 	var $lookup_return_col = false;
	var $lookup_queries = array();
	var $source_type = "database";
	var $source_datasource = false;
	var $source_xml = false;
	var $top_level_query = true;
	var $clone_columns = array();
	var $pre_sql = array();
	var $graphs = array();
	var $clearform = false;
	var $first_criteria_selection = true;
	var $menuitems = array();
	var $dropdown_menu = array();
	var $projectitems = array();
	var $target_style = false;
	var $target_format = false;
	var $lineno = 0;
	var $groupvals = array();
	var $email_recipients = false;
	var $drilldown_report = false;
	var $forward_url_get_parameters="";
    var $reportico_ajax_script_url=false;
    var $reportico_ajax_called=false;
    var $reportico_ajax_mode=false;
    var $reportico_ajax_preloaded=false;

	var $target_show_graph = false;
	var $target_show_detail = false;
	var $target_show_group_headers = false;
	var $target_show_group_trailers = false;
	var $target_show_column_headers = false;
	var $target_show_criteria = false;

	var $show_form_panel = false;
	var $status_message = "";
	var $reports_path = "projects/reports";
	var $admin_path = "projects/admin";

	var $framework_parent = false;
	var $framework_type = false;

	var $attributes = array (
			"ReportTitle" => "Set Report Title",
			"ReportDescription" => false,
			"PageSize" => ".DEFAULT",
			"PageOrientation" => ".DEFAULT",
			"TopMargin" => "",
			"BottomMargin" => "",
			"RightMargin" => "",
			"LeftMargin" => "",
			"pdfFont" => "",
			"pdfFontSize" => "",
			"PreExecuteCode" =>  "NONE",
			"formBetweenRows" => "solidline",
			"bodyDisplay" => "show",
			"graphDisplay" => "show"
			);

	var $panels = array();
	var $targets = array();
	var $assignment = array();
	var $criteria_links = array();

    // Output control 
    var $output_skipline = false;
    var $output_allcell_styles = false;
    var $output_header_styles = false;
    var $output_hyperlinks = false;
    var $output_images = false;
    var $output_row_styles = false;
    var $output_page_styles = false;
    var $output_group_header_label_styles = false;
    var $output_group_header_value_styles = false;
    var $output_group_trailer_styles = false;
    var $output_reportbody_styles = false;
	var $admin_accessible = true;

    // Charsets for in and output
    var $db_charset = false;
    var $output_charset = false;

    // Currently edited links to other reports
    var $reportlink_report = false;
    var $reportlink_report_item = false;
    var $reportlink_or_import = false;

    // Three parameters which can be set from a calling script
    // which can be incorporated into reportic queries
    // For example a calling framework username can
    // be passed so that data can be returned for that
    // user
	var $external_user = false;
	var $external_param1 = false;
	var $external_param2 = false;
	var $external_param3 = false;

    // Initial settings to set default project, report, execute mode. Set by
    // application frameworks embedding reportico 
    var $initial_project = false;
    var $initial_execute_mode = false;
    var $initial_report = false;
    var $initial_project_password = false;
    var $initial_output_format = false;
    var $initial_output_style = false;
    var $initial_show_detail = false;
    var $initial_show_graph = false;
    var $initial_show_group_headers = false;
    var $initial_show_group_trailers = false;
    var $initial_show_column_headers = false;
    var $initial_show_criteria = false;
    var $initial_execution_parameters = false;
    // Access mode - one of FULL, ALLPROJECTS, ONEPROJECT, REPORTOUTPUT
    var $access_mode = "FULL";

    // Whether to show refresh button on report output
    var $show_refresh_button = false;

    // Whether to show print button on report output
    var $show_print_button = true;

    // Session namespace to use
    var $session_namespace = false;

    // Whether to perform drill downs in their own namespace (normally from embedding in frameworks
    // where reportico namespaces are used within the framework session
    var $drilldown_namespace = false;

    // URL Path to Reportico folder
    var $reportico_url_path = false;

    // Path to Reportico runner for AJAX use or standalone mode
    var $url_path_to_reportico_runner = false;

    // Path to calling script for form actions
    // In standalone mode will be the reportico runner, otherwise the
    // script in which reportico is embedded
    var $url_path_to_calling_script = false;

    // external user parameters as specified in sql as {USER_PARAM,your_parameter_name}
    // set with $q->user_parameters["your_parameter_name"] = "value";
	var $user_parameters = array();

	function reportico()
	{ 	
		reportico_object::reportico_object();

		$this->parent_query =& $this;
	}

	function &create_graph()
	{
        if ( defined("SW_GRAPH_ENGINE") && SW_GRAPH_ENGINE == "PCHART" )
        {
		    require_once('swgraph_pchart.php');
        }
        else
        {
		    require_once('swgraph.php');
        }
        
		$graph = new reportico_graph($this, "internal");
		$this->graphs[] =& $graph;
		return $graph;
	}

    /*
    ** In AJAX mode, all links, buttons etc will be served by ajax call to 
    ** to runner script or specified ajax script, otherwise they will
    ** call the initial calling script
    */
    function get_action_url()
    {
        $calling_script = $this->url_path_to_calling_script;
        if ( $this->reportico_ajax_mode )
            $calling_script = $this->reportico_ajax_script_url;
        return $calling_script;
    }

	function &get_graph_by_name($in_query)
	{
		$graphs = array();
		foreach ( $this->graphs as $k => $v )
		{
			if ( $v->graph_column == $in_query )
			{
                $graphs[] =& $this->graphs[$k];
			}
		}
		return $graphs;
	}


	function query_display()
	{

			foreach ( $this->columns as $col )
			{
					echo $col->query_name;
					echo " ".$col->table_name.".".$col->column_name;
					echo " ".$col->column_type;
					echo " ".$col->column_length;
					echo " ".$col->in_select;
					echo "<br>\n";
			}

	}

	function request_display()
	{
			while ( list($id, $val) = each($_REQUEST) )
			{
					echo "<b>$id</b><br>";
					var_dump($val);
					echo "<br>";
			}
	}


	function set_datasource(&$datasource)
	{ 	
		$this->datasource =& $datasource;
		foreach ( $this->columns as $k => $col )
		{
			$this->columns[$k]->set_datasource($this->datasource);
		}
	}

	function display_columns()
	{
		foreach ( $this->columns as $k => $col )
		{
			echo "$k Data: $col->datasource  Name: $col->query_name<br>";
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_lookup_return
	// -----------------------------------------------------------------------------
	function set_lookup_return($query_name)
	{

		foreach($this->columns as $k => $v )
		{
			$this->columns[$k]->lookup_return_flag = false;
		}
		if ( $cl = get_query_column($query_name, $this->columns ) )
		{
			$col =& $cl ;
			$col->lookup_return_flag = true;
			$this->lookup_return_col =& $col;
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_column_format
	// -----------------------------------------------------------------------------
	function set_column_format($query_name, $format_type, $format_value )
	{

		$this->check_column_name("set_column_format", $query_name);
		if ( $cl =&get_query_column($query_name, $this->columns ) )
		{
			$col =& $cl ;
			$col->set_format($format_type, $format_value);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_group_format
	// -----------------------------------------------------------------------------
	function set_group_format($query_name, $format_type, $format_value )
	{

		$this->check_group_name("set_group_format", $query_name);
		if ( array_key_exists($query_name, $this->group) )
		{
			$col =& $this->group[$query_name] ;
			$col->set_format($format_type, $format_value);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : add_pre_sql
	// -----------------------------------------------------------------------------
	function add_pre_sql($in_sql)
	{
		$this->pre_sql[] = $in_sql;
	}

	// -----------------------------------------------------------------------------
	// Function : set_lookup_display
	// -----------------------------------------------------------------------------
	function set_lookup_display($query_name, $abbrev_name = false)
	{

		if ( !$query_name )
			return;

		if ( !$this->check_column_name_r("set_lookup_display", $query_name) )
		{	
			handle_error(  "Failure in Lookup Display: Unknown Column Name $query_name");
			return;
		}

		if ( $cl = get_query_column($query_name, $this->columns ) )
		{
			foreach ( $this->columns as $k => $v )
			{
					$this->columns[$k]->lookup_display_flag = false;
					$this->columns[$k]->lookup_abbrev_flag = false;
			}
		
			$cl->lookup_display_flag = true;

			if ( $abbrev_name )
			{
				$col2 = get_query_column($abbrev_name, $this->columns ) ;
				$col2->lookup_abbrev_flag = true;
			}
			else
				$cl->lookup_abbrev_flag = true;
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_lookup_expand_match
	// -----------------------------------------------------------------------------
	function set_lookup_expand_match($match_column)
	{
		$this->match_column = $match_column;
	}

	// -----------------------------------------------------------------------------
	// Function : check_page_header_name
	// -----------------------------------------------------------------------------
	function check_page_header_name($in_scope, $in_name)
	{
		if ( !array_key_exists($in_name, $this->page_headers) )
		{
			handle_error("$in_scope: Group $in_name unknown");
		}
	}

	// -----------------------------------------------------------------------------
	// Function : check_page_footer_name
	// -----------------------------------------------------------------------------
	function check_page_footer_name($in_scope, $in_name)
	{
		if ( !array_key_exists($in_name, $this->page_footers) )
		{
			handle_error("$in_scope: Group $in_name unknown");
		}
	}

	// -----------------------------------------------------------------------------
	// Function : check_group_name_r
	// -----------------------------------------------------------------------------
	function check_group_name_r($in_scope, $in_column_name)
	{
		if ( !($qc = get_group_column($in_column_name, $this->groups ) ) )
		{
			handle_error( "$in_scope: Group $in_column_name unknown");
			return(false);
		}
		else
			return true;
	}

	// -----------------------------------------------------------------------------
	// Function : check_group_name
	// -----------------------------------------------------------------------------
	function check_group_name($in_scope, $in_column_name)
	{
		if ( !($qc = get_group_column($in_column_name, $this->groups ) ) )
		{
			handle_error("$in_scope: Group $in_column_name unknown");
		}
	}

	// -----------------------------------------------------------------------------
	// Function : check_column_name_r
	// -----------------------------------------------------------------------------
	function check_column_name_r($in_scope, $in_column_name)
	{
		if ( ! ($cl = get_query_column($in_column_name, $this->columns )) )
		{
			handle_error( "$in_scope: Column $in_column_name unknown");
			return false;
		}
		else
			return true;
	}

	// -----------------------------------------------------------------------------
	// Function : check_column_name
	// -----------------------------------------------------------------------------
	function check_column_name($in_scope, $in_column_name)
	{
		if ( ! ($cl = get_query_column($in_column_name, $this->columns )) )
		{
			handle_error("$in_scope: Column $in_column_name unknown");
		}
	}
	// -----------------------------------------------------------------------------
	// Function : check_criteria_name
	// -----------------------------------------------------------------------------
	function check_criteria_name($in_scope, $in_column_name)
	{
		if ( !array_key_exists($in_column_name, $this->lookup_queries) )
		{
			handle_error("$in_scope: Column $in_column_name unknown");
		}
	}
	// -----------------------------------------------------------------------------
	// Function : check_criteria_name_r
	// -----------------------------------------------------------------------------
	function check_criteria_name_r($in_scope, $in_column_name)
	{
		if ( !array_key_exists($in_column_name, $this->lookup_queries) )
		{
			//handle_error("$in_scope: Column $in_column_name unknown");
			return false;
		}
		return true;
	}
	// -----------------------------------------------------------------------------
	// Function : set_criteria_link
	// -----------------------------------------------------------------------------
	function set_criteria_link($link_from, $link_to, $clause, $link_number=-1)
	{

		if ( !$this->check_criteria_name_r("set_criteria_link", $link_from))
		{	
			handle_error(  "Failure in Criteria Link: Unknown Lookup Name $link_from");
			return;
		}
		if ( !$this->check_criteria_name_r("set_criteria_link", $link_to))
		{	
			handle_error(  "Failure in Criteria Link: Unknown Lookup Name $link_to");
			return;
		}

		//$lf =& $this->columns[$link_from];
		//$lt =& $this->columns[$link_to];

		//$lfq =& $lf->lookup_query;
		//$ltq =& $lt->lookup_query;

		$lfq =& $this->lookup_queries[$link_from]->lookup_query;
		$ltq =& $this->lookup_queries[$link_to]->lookup_query;

		if ( !$lfq )
			handle_error("set_criteria_link: No Lookup For $link_from");

		$this->lookup_queries[$link_from]->lookup_query->add_criteria_link($clause, $link_from, $link_to, $ltq, $link_number);
	}

	// -----------------------------------------------------------------------------
	// Function : add_criteria_link
	// -----------------------------------------------------------------------------
	function add_criteria_link($clause, $link_from, $link_to, &$query, $link_number = -1)
	{
		if ( $link_number != -1 )
			$this->criteria_links[$link_number] =
				array(
						"clause" => $clause,
						"link_from" => $link_from,
						"tag" => $link_to,
						"query" => &$query
					);
		else
			$this->criteria_links[] =
				array(
						"clause" => $clause,
						"link_from" => $link_from,
						"tag" => $link_to,
						"query" => &$query
					);
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_type
	// -----------------------------------------------------------------------------
	function set_criteria_type($query_name, $criteria_type)
	{

		$this->check_column_name("set_criteria_ltype", $query_name);
		if ( ($cl =&get_query_column($query_name, $this->columns )) )
		{
			$cl->set_criteria_type($criteria_type);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_help
	// -----------------------------------------------------------------------------
	function set_criteria_help($query_name, $criteria_help)
	{
		$this->check_criteria_name("set_criteria_defaults", $query_name);
		if ( array_key_exists($query_name, $this->lookup_queries) )
		{
			$col =& $this->lookup_queries[$query_name] ;
			$col->set_criteria_help($criteria_help);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_link_report
	// -----------------------------------------------------------------------------
	function set_criteria_link_report($in_query, $in_report, $in_report_item)
	{
		if ( array_key_exists($in_query, $this->lookup_queries) )
		{
			$col =& $this->lookup_queries[$in_query] ;
			$col->set_criteria_link_report($in_report, $in_report_item);
			$col->set_datasource($this->datasource);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_input
	// -----------------------------------------------------------------------------
	function set_criteria_input($in_query, $in_source, $in_display, $in_expand_display = false, $_use = "")
	{
		if ( array_key_exists($in_query, $this->lookup_queries) )
		{
			$col =& $this->lookup_queries[$in_query] ;
			$col->set_criteria_input($in_source, $in_display, $in_expand_display, $_use);
			$col->set_datasource($this->datasource);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_lookup
	// -----------------------------------------------------------------------------
	function set_criteria_lookup($query_name, &$lookup_query, $in_table, $in_column)
	{
		if ( array_key_exists ( $query_name, $this->lookup_queries ) )
		{
		}
		else
		{
			$this->lookup_queries[$query_name] = new reportico_criteria_column(
						$query_name,
						$in_table,
						$in_column,
						"CHAR",
						0,
						"###.##",
						0
					);
			$this->set_criteria_attribute($query_name, "column_title", $query_name);
			$lookup_query->set_datasource($this->datasource);
		}

		$this->parent_query =& $this;
		$this->lookup_queries[$query_name]->set_criteria_lookup($lookup_query);
		$this->lookup_queries[$query_name]->first_criteria_selection = $this->first_criteria_selection;
		$this->lookup_queries[$query_name]->lookup_query->parent_query =& $this;
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_list
	// -----------------------------------------------------------------------------
	function set_criteria_list($query_name, $in_list)
	{
		$this->check_criteria_name("set_criteria_list", $query_name);
		if ( array_key_exists($query_name, $this->lookup_queries) )
		{
			$col =& $this->lookup_queries[$query_name] ;
			$col->set_criteria_list($in_list);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_defaults
	// -----------------------------------------------------------------------------
	function set_criteria_defaults($query_name, $in_default, $in_delimiter = false)
	{

		if ( $in_default ) // && $this->get_execute_mode() != "MAINTAIN" )
		{
			$this->check_criteria_name("set_criteria_defaults", $query_name);
			if ( array_key_exists($query_name, $this->lookup_queries) )
			{
				$col =& $this->lookup_queries[$query_name] ;
				$col->set_criteria_defaults($in_default, $in_delimiter);
			}
		}
	}

	// -----------------------------------------------------------------------------
	// Function : report_progress
	// -----------------------------------------------------------------------------
	function report_progress($in_text, $in_status )
	{
		$this->progress_text = $in_text;
		$this->progress_status = $in_status;

		set_reportico_session_param("progress_text",$this->progress_text);
		set_reportico_session_param("progress_status",$this->progress_status);
	}

	// -----------------------------------------------------------------------------
	// Function : set_page_header_attribute
	// -----------------------------------------------------------------------------
	function set_page_header_attribute($query_name, $attrib_name, $attrib_value)
	{

		$this->check_page_header_name("set_page_header_attribute", $query_name);
		if ( array_key_exists($query_name, $this->page_headers) )
		{
			$col =& $this->page_headers[$query_name] ;
			$col->set_attribute($attrib_name, $attrib_value);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_page_footer_attribute
	// -----------------------------------------------------------------------------
	function set_page_footer_attribute($query_name, $attrib_name, $attrib_value)
	{

		$this->check_page_footer_name("set_page_footer_attribute", $query_name);
		if ( array_key_exists($query_name, $this->page_footers) )
		{
			$col =& $this->page_footers[$query_name] ;
			$col->set_attribute($attrib_name, $attrib_value);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_attribute
	// -----------------------------------------------------------------------------
	function set_criteria_attribute($query_name, $attrib_name, $attrib_value)
	{

		if ( array_key_exists($query_name, $this->lookup_queries) )
		{
			$col =& $this->lookup_queries[$query_name] ;
			$col->set_attribute($attrib_name, $attrib_value);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_column_attribute
	// -----------------------------------------------------------------------------
	function set_column_attribute($query_name, $attrib_name, $attrib_value)
	{

		$this->check_column_name("set_column_attribute", $query_name);
		if ( ($cl = get_query_column($query_name, $this->columns )) )
		{
			$cl->set_attribute($attrib_name, $attrib_value);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : add_target
	// -----------------------------------------------------------------------------
	function add_target(&$target)
	{
		$this->targets[] =& $target;
	}

	// -----------------------------------------------------------------------------
	// Function : store_column_results
	// -----------------------------------------------------------------------------
	function store_column_results()
	{
		// Ensure that values returned from database query are placed
		// in the appropriate query column value
		foreach ( $this->columns as $k => $col )
		{
			$this->columns[$k]->old_column_value = 
				$this->columns[$k]->column_value;
			$this->columns[$k]->reset_flag = false;
		}
	}

	// -----------------------------------------------------------------------------
	// Function : build_column_results
	// -----------------------------------------------------------------------------
	function build_column_results($result_line)
	{
		// Ensure that values returned from database query are placed
		// in the appropriate query column value
		$ct = 0;
		foreach ( $this->columns as $k => $col )
		{
			if ( $col->in_select )
			{
				$this->debug("selecting $col->query_name in");

                // Oracle returns associated array keys in upper case
                $assoc_key = $col->query_name;
				if ( array_key_exists($assoc_key, $result_line ) )
				{
					$this->debug("exists");
					$colval = $result_line[$assoc_key];

					if ( is_string($colval) )
						$colval = trim($colval);

					$this->debug("$colval");
				}
				else
				if ( array_key_exists(strtoupper($assoc_key), $result_line ) )
				{
					$this->debug("exists");
					$colval = $result_line[strtoupper($assoc_key)];

					if ( is_string($colval) )
						$colval = trim($colval);

					$this->debug("$colval");
				}
				else
					$colval = "NULL";
				$this->columns[$k]->column_value = $colval;
			}
			else
			{
				$this->columns[$k]->column_value = $col->query_name;
			}

			$ct++;

		}
	}

	// -----------------------------------------------------------------------------
	// Function : get_execute_mode()
	// -----------------------------------------------------------------------------
	function get_execute_mode()
	{
		// User clicked Report Dropdown + Go Button
		if ( array_key_exists('submit_execute_mode', $_REQUEST) )
			if ( array_key_exists('execute_mode', $_REQUEST) )
			{
				$this->execute_mode = $_REQUEST['execute_mode'];
			}

		// User clicked Design Mode Button
		if ( array_key_exists('submit_design_mode', $_REQUEST) )
		{
			$this->execute_mode = "MAINTAIN";
		}

		// User clicked Design Mode Button
		if ( array_key_exists('submit_genws_mode', $_REQUEST) )
		{
			$this->execute_mode = "SOAPSAVE";
		}

		// User clicked Design Mode Button
		if ( array_key_exists('submit_prepare_mode', $_REQUEST) )
		{
			$this->execute_mode = "PREPARE";
		}

		if ( array_key_exists('execute_mode', $_REQUEST) )
		{
			if ( $_REQUEST["execute_mode"] == "MAINTAIN" && $this->allow_maintain != "SAFE" 
				&& $this->allow_maintain != "FULL" && $this->allow_maintain != "DEMO" )
			{}
			else
			{
				$this->execute_mode = $_REQUEST["execute_mode"];
			}
		}


		if ( !$this->execute_mode  && array_key_exists('submit', $_REQUEST) )
			$this->execute_mode = "EXECUTE";
		if ( !$this->execute_mode  && array_key_exists('submitPrepare', $_REQUEST) )
			$this->execute_mode = "EXECUTE";


		if ( !$this->execute_mode && isset($_SESSION[reportico_namespace()]) && array_key_exists("execute_mode", $_SESSION[reportico_namespace()] ) )
		{
			$this->execute_mode = get_reportico_session_param("execute_mode");
		}

		// If user has pressed expand then we want to staty in PREPARE mode
		foreach ( $_REQUEST AS $key => $value )
		{
			if ( preg_match ( "/^EXPAND_/", $key ) )
			{
				$this->execute_mode = "PREPARE";
				break;
			}
		}

		if ( !$this->execute_mode )
			$this->execute_mode = "MENU";

		if ( $this->execute_mode == "MAINTAIN" && 
			$this->allow_maintain != "SAFE"  &&
			$this->allow_maintain != "DEMO"  &&
			$this->allow_maintain != "FULL"  &&
			!$this->parent_query )
		{
			handle_error("Report Maintenance Mode Disallowed");
			$this->execute_mode = "PREPARE";
		}
		if ( array_key_exists('execute_mode', $_REQUEST) )
		{
			if ( $_REQUEST["execute_mode"] == "MAINTAIN" && $this->allow_maintain != "SAFE" 
				&& $this->allow_maintain != "DEMO" 
				&& $this->allow_maintain != "FULL" )
			{}
			else
			{
				$this->execute_mode = $_REQUEST["execute_mode"];
			}
		}

	 	if ( !$this->execute_mode )
			$this->execute_mode = "MENU";

		// Override mode if specified from ADMIN page
        if ( get_request_item("jump_to_delete_project", "") && array_key_exists("submit_delete_project", $_REQUEST) )
            $this->execute_mode = "PREPARE";

        if ( get_request_item("jump_to_configure_project", "") && array_key_exists("submit_configure_project", $_REQUEST) )
            $this->execute_mode = "PREPARE";

        if ( get_request_item("jump_to_menu_project", "") && array_key_exists("submit_menu_project", $_REQUEST) )
            $this->execute_mode = "MENU";

        if ( get_request_item("jump_to_design_project", "") && array_key_exists("submit_design_project", $_REQUEST) )
			$this->execute_mode = "MAINTAIN";

		// If Reset pressed force to Prepare mode
		if ( array_key_exists("clearform", $_REQUEST) )
        {
			set_reportico_session_param("firstTimeIn",true);
			$this->execute_mode = "PREPARE";
        }

		// If logout pressed then force to MENU mode
		if ( array_key_exists("logout", $_REQUEST) )
			$this->execute_mode = "MENU";

        // If initialised from framework then set mode from there
        if ( $this->initial_execute_mode && get_reportico_session_param("awaiting_initial_defaults") )
            $this->execute_mode = $this->initial_execute_mode;

		set_reportico_session_param("execute_mode",$this->execute_mode);
		return($this->execute_mode);
	}
	
	// -----------------------------------------------------------------------------
	// Function : set_request_columns()
	// -----------------------------------------------------------------------------
	function set_request_columns()
	{
		if ( array_key_exists("clearform", $_REQUEST) )
		{
			$this->clearform = true;
			$this->first_criteria_selection = true;
		}

        // If an initial set of parameter values has been set then parameters are being
        // set probably from a framework. In this case we need clear any MANUAL and HIDDEN requests
        // and set MANUAL ones from the external ones
        if ( $this->initial_execution_parameters )
        {
            foreach ( $_REQUEST as $k => $v )
                if ( preg_match ("/^MANUAL_/", $k ) || preg_match ("/^HIDDEN_/", $k ) )
                    unset($_REQUEST[$k]);
        }

        $execute_mode = $this->get_execute_mode();
		foreach ( $this->lookup_queries as $col )
		{
			// If this is first time into screen and we have defaults then
			// use these instead
			if ( get_reportico_session_param("firstTimeIn") )
			{
				$this->lookup_queries[$col->query_name]->column_value =
					$this->lookup_queries[$col->query_name]->defaults;
				if ( is_array($this->lookup_queries[$col->query_name]->column_value) )
					$this->lookup_queries[$col->query_name]->column_value =
						implode(",", $this->lookup_queries[$col->query_name]->column_value);
                // Daterange defaults needs to  eb converted to 2 values
                if ( $this->lookup_queries[$col->query_name]->criteria_type == "DATERANGE" && !$this->lookup_queries[$col->query_name]->defaults)
                {
                    $this->lookup_queries[$col->query_name]->defaults = array();
                    $this->lookup_queries[$col->query_name]->defaults[0] = "TODAY-TODAY";
                    $this->lookup_queries[$col->query_name]->defaults[1] = "TODAY";
                    $this->lookup_queries[$col->query_name]->column_value = "TODAY-TODAY";
                }
                if ( $this->lookup_queries[$col->query_name]->criteria_type == "DATE" && !$this->lookup_queries[$col->query_name]->defaults)
                {
                    $this->lookup_queries[$col->query_name]->defaults = array();
                    $this->lookup_queries[$col->query_name]->defaults[0] = "TODAY";
                    $this->lookup_queries[$col->query_name]->defaults[1] = "TODAY";
                    $this->lookup_queries[$col->query_name]->column_value = "TODAY";
                }
                $this->defaults = $this->lookup_queries[$col->query_name]->defaults;
                if ( isset($this->defaults) )
                {
                    if ( $this->lookup_queries[$col->query_name]->criteria_type == "DATERANGE" )
                    {
                        if ( !convert_date_range_defaults_to_dates("DATERANGE", 
                            $this->lookup_queries[$col->query_name]->column_value, 
                            $this->lookup_queries[$col->query_name]->column_value,
                            $this->lookup_queries[$col->query_name]->column_value2) )
                            trigger_error( "Date default '".$this->defaults[0]."' is not a valid date range. Should be 2 values separated by '-'. Each one should be in date format (e.g. yyyy-mm-dd, dd/mm/yyyy) or a date type (TODAY, TOMMORROW etc", E_USER_ERROR );
                    }
                    if ( $this->lookup_queries[$col->query_name]->criteria_type == "DATE" )
                    {
                        $dummy="";
                        if ( !convert_date_range_defaults_to_dates("DATE", $this->defaults[0], $this->range_start, $dummy) )
                        if ( !convert_date_range_defaults_to_dates("DATE", 
                            $this->lookup_queries[$col->query_name]->column_value, 
                            $this->lookup_queries[$col->query_name]->column_value,
                            $this->lookup_queries[$col->query_name]->column_value2) )
                        trigger_error( "Date default '".$this->defaults[0]."' is not a valid date. Should be in date format (e.g. yyyy-mm-dd, dd/mm/yyyy) or a date type (TODAY, TOMMORROW etc", E_USER_ERROR );
                    }
                }
			}
		}


		if ( array_key_exists("clearform", $_REQUEST) )
		{
			set_reportico_session_param("firstTimeIn",true);
		}

        // Set up show option check box settings

        // If initial form style specified use it
        if ( $this->initial_output_style ) set_reportico_session_param("target_style", $this->initial_output_style );

        // If default starting "show" setting provided by calling framework then use them
        if ( $this->show_print_button ) set_reportico_session_param("show_print_button", ( $this->show_print_button == "show" ));
        if ( $this->show_refresh_button ) set_reportico_session_param("show_refresh_button", ( $this->show_refresh_button == "show" ));
        if ( $this->initial_show_detail ) set_reportico_session_param("target_show_detail",( $this->initial_show_detail == "show" ));
        if ( $this->initial_show_graph ) set_reportico_session_param("target_show_graph",( $this->initial_show_graph == "show" ));
        if ( $this->initial_show_group_headers ) set_reportico_session_param("target_show_group_headers",( $this->initial_show_group_headers == "show" ));
        if ( $this->initial_show_group_trailers ) set_reportico_session_param("target_show_group_trailers",( $this->initial_show_group_trailers == "show" ));
        if ( $this->initial_show_column_headers ) set_reportico_session_param("target_show_column_headers",( $this->initial_show_column_headers == "show" ));
        if ( $this->initial_show_criteria ) set_reportico_session_param("target_show_criteria",( $this->initial_show_criteria == "show" ));

	    $this->target_show_detail = session_request_item("target_show_detail", true, !isset_reportico_session_param("target_show_detail"));
	    $this->target_show_graph = session_request_item("target_show_graph", true, !isset_reportico_session_param("target_show_graph"));
	    $this->target_show_group_headers = session_request_item("target_show_group_headers", true, !isset_reportico_session_param("target_show_group_headers"));
	    $this->target_show_group_trailers = session_request_item("target_show_group_trailers", true, !isset_reportico_session_param("target_show_group_trailers"));
	    $this->target_show_column_headers = session_request_item("target_show_column_headers", true, !isset_reportico_session_param("target_show_column_headers"));
	    $this->target_show_criteria = session_request_item("target_show_criteria", true, !isset_reportico_session_param("target_show_criteria"));

		if ( get_reportico_session_param("firstTimeIn") 
                && !$this->initial_show_detail && !$this->initial_show_graph && !$this->initial_show_group_headers 
                && !$this->initial_show_group_trailers && !$this->initial_show_column_headers && !$this->initial_show_criteria 
        )
        {
            // If first time in default output hide/show elements to what is passed in URL params .. if none supplied show all
            if ( $this->execute_mode == "EXECUTE" )
            {
	                $this->target_show_detail = get_request_item("target_show_detail", false);
	                $this->target_show_graph = get_request_item("target_show_graph", false);
	                $this->target_show_group_headers = get_request_item("target_show_group_headers", false);
	                $this->target_show_group_trailers = get_request_item("target_show_group_trailers", false);
	                $this->target_show_column_headers = get_request_item("target_show_column_headers", false);
	                $this->target_show_criteria = get_request_item("target_show_criteria", false);
                    if ( !$this->target_show_detail && !$this->target_show_graph && !$this->target_show_group_headers
                        && !$this->target_show_group_trailers && !$this->target_show_column_headers && !$this->target_show_criteria )
                    {
                            $this->target_show_detail = true;
                            $this->target_show_graph = true;
                            $this->target_show_group_headers = true;
                            $this->target_show_group_trailers = true;
                            $this->target_show_column_headers = true;
                            $this->target_show_criteria = true;
                    }
	                set_reportico_session_param("target_show_detail",$this->target_show_detail);
	                set_reportico_session_param("target_show_graph",$this->target_show_graph);
	                set_reportico_session_param("target_show_group_headers",$this->target_show_group_headers);
	                set_reportico_session_param("target_show_group_trailers",$this->target_show_group_trailers);
	                set_reportico_session_param("target_show_column_headers",$this->target_show_column_headers);
	                set_reportico_session_param("target_show_criteria",$this->target_show_criteria);
            }
            else
            {
                    $this->target_show_detail = true;
                    $this->target_show_graph = true;
                    $this->target_show_group_headers = true;
                    $this->target_show_group_trailers = true;
                    $this->target_show_column_headers = true;
                    $this->target_show_criteria = true;
                    set_reportico_session_param("target_show_detail",true);
                    set_reportico_session_param("target_show_graph",true);
                    set_reportico_session_param("target_show_group_headers",true);
                    set_reportico_session_param("target_show_group_trailers",true);
                    set_reportico_session_param("target_show_column_headers",true);
                    set_reportico_session_param("target_show_criteria",true);
            }
        }
        else
        {
            // If not first time in, then running report would have come from
            // prepare screen which provides details of what report elements to include
            if ( $this->execute_mode == "EXECUTE" )
            {
	            $runfromcriteriascreen = get_request_item("user_criteria_entered", false);
                if ( $runfromcriteriascreen )
                {
	                $this->target_show_detail = get_request_item("target_show_detail", false);
	                $this->target_show_graph = get_request_item("target_show_graph", false);
	                $this->target_show_group_headers = get_request_item("target_show_group_headers", false);
	                $this->target_show_group_trailers = get_request_item("target_show_group_trailers", false);
	                $this->target_show_column_headers = get_request_item("target_show_column_headers", false);
	                $this->target_show_criteria = get_request_item("target_show_criteria", false);
                    if ( !$this->target_show_detail && !$this->target_show_graph && !$this->target_show_group_headers
                        && !$this->target_show_group_trailers && !$this->target_show_column_headers && !$this->target_show_criteria )
                    {
                            $this->target_show_detail = true;
                            $this->target_show_graph = true;
                            $this->target_show_group_headers = true;
                            $this->target_show_group_trailers = true;
                            $this->target_show_column_headers = true;
                            $this->target_show_criteria = true;
                    }
	                set_reportico_session_param("target_show_detail",$this->target_show_detail);
	                set_reportico_session_param("target_show_graph",$this->target_show_graph);
	                set_reportico_session_param("target_show_group_headers",$this->target_show_group_headers);
	                set_reportico_session_param("target_show_group_trailers",$this->target_show_group_trailers);
	                set_reportico_session_param("target_show_column_headers",$this->target_show_column_headers);
	                set_reportico_session_param("target_show_criteria",$this->target_show_criteria);
                }
            }
        }
        if ( isset ( $_REQUEST["target_show_detail"] ))  set_reportico_session_param("target_show_detail",$_REQUEST["target_show_detail"]);
        if ( isset ( $_REQUEST["target_show_graph"] ))  set_reportico_session_param("target_show_graph",$_REQUEST["target_show_graph"]);
        if ( isset ( $_REQUEST["target_show_group_headers"] ))  set_reportico_session_param("target_show_group_headers",$_REQUEST["target_show_group_headers"]);
        if ( isset ( $_REQUEST["target_show_group_trailers"] ))  set_reportico_session_param("target_show_group_trailers",$_REQUEST["target_show_group_trailers"]);
        if ( isset ( $_REQUEST["target_show_column_headers"] ))  set_reportico_session_param("target_show_column_headers",$_REQUEST["target_show_column_headers"]);
        if ( isset ( $_REQUEST["target_show_criteria"] ))  set_reportico_session_param("target_show_criteria",$_REQUEST["target_show_criteria"]);

		if ( array_key_exists("clearform", $_REQUEST) )
		{
			return;
		}


		// Fetch current criteria choices from HIDDEN_ section
		foreach ( $this->lookup_queries as $col )
		{
			// criteria name could be a field name or could be "groupby" or the like
			$crit_name   = $col->query_name;
			$crit_value  = null;

			if ( array_key_exists($crit_name, $_REQUEST) )
			{
				$crit_value = $_REQUEST[$crit_name];
			}

			if ( array_key_exists("HIDDEN_" . $crit_name, $_REQUEST) )
			{
                                $crit_value = $_REQUEST["HIDDEN_" . $crit_name];
                        }

			// applying multi-column values
			if ( array_key_exists("HIDDEN_" . $crit_name . "_FROMDATE", $_REQUEST) )
			{
				$crit_value_1 = $_REQUEST["HIDDEN_" . $crit_name . "_FROMDATE"];
				$this->lookup_queries[$crit_name]->column_value1 = $crit_value_1;
			}

			if ( array_key_exists("HIDDEN_" . $crit_name . "_TODATE", $_REQUEST) )
			{
				$crit_value_2 = $_REQUEST["HIDDEN_" . $crit_name . "_TODATE"];
				$this->lookup_queries[$crit_name]->column_value2 = $crit_value_2;
			}
			// end applying multi-column values

			if ( array_key_exists("EXPANDED_" . $crit_name, $_REQUEST) )
			{
				$crit_value = $_REQUEST["EXPANDED_" . $crit_name];
			}


			// in case of single column value, we apply it now
			if ( !is_null( $crit_value ) )
			{
				$this->lookup_queries[$crit_name]->column_value = $crit_value;

				 // for groupby criteria, we need to show and hide columns accordingly
                                 if ($crit_name == 'showfields' || $crit_name == 'groupby')
                                 {
					foreach ( $this->columns as $q_col)
					{
						//show the column if it matches a groupby value
						if  ( in_array ( $q_col->column_name, $crit_value ) )
						{
                                                        $q_col->attributes['column_display'] = "show";
						}
						// if it doesn't match, hide it if this is the first 
						// groupby column we are going through; otherwise
						// leave it as it is
						elseif ( !isset ( $not_first_pass ) )
						{
							$q_col->attributes['column_display'] = "hide";
						}
					}
					$not_first_pass = true;
                                }
			}
		}

		// Fetch current criteria choices from MANUAL_ section
		foreach ( $this->lookup_queries as $col )
		{
            $identified_criteria = false;

            // If an initial set of parameter values has been set then parameters are being
            // set probably from a framework. Use these for setting criteria
            if ( $this->initial_execution_parameters )
            {
                if ( isset($this->initial_execution_parameters[$col->query_name]) )
                {
                    $val1 = false;
                    $val2 = false;
                    $criteriaval = $this->initial_execution_parameters[$col->query_name];
                    if ( $col->criteria_type == "DATERANGE" )
                    {
                        if ( !convert_date_range_defaults_to_dates("DATERANGE", 
                            $criteriaval,
                            $val1,
                            $val2) )
                            trigger_error( "Date default '".$criteriaval."' is not a valid date range. Should be 2 values separated by '-'. Each one should be in date format (e.g. yyyy-mm-dd, dd/mm/yyyy) or a date type (TODAY, TOMMORROW etc", E_USER_ERROR );
                        else
                        {
                            $_REQUEST["MANUAL_".$col->query_name."_FROMDATE"] = $val1;
                            $_REQUEST["MANUAL_".$col->query_name."_TODATE"] = $val2;
		                    if ( get_reportico_session_param('latestRequest') )
                            {
                                $_SESSION[reportico_namespace()]["latestRequest"]["MANUAL_".$col->query_name."_FROMDATE"] = $val1;
                                $_SESSION[reportico_namespace()]["latestRequest"]["MANUAL_".$col->query_name."_TODATE"] = $val2;
                            }
                        }
                    }
                    else if ( $col->criteria_type == "DATE" )
                    {
                        if ( !convert_date_range_defaults_to_dates("DATERANGE", 
                            $criteriaval,
                            $val1,
                            $val2) )
                            trigger_error( "Date default '".$criteriaval."' is not a valid date. Should be in date format (e.g. yyyy-mm-dd, dd/mm/yyyy) or a date type (TODAY, TOMMORROW etc", E_USER_ERROR );
                        else
                        {
                            $_REQUEST["MANUAL_".$col->query_name."_FROMDATE"] = $val1;
                            $_REQUEST["MANUAL_".$col->query_name."_TODATE"] = $val1;
                            $_REQUEST["MANUAL_".$col->query_name] = $val1;
			                if ( get_reportico_session_param('latestRequest') )
                            {
                                $_SESSION[reportico_namespace()]["latestRequest"]["MANUAL_".$col->query_name."_FROMDATE"] = $val1;
                                $_SESSION[reportico_namespace()]["latestRequest"]["MANUAL_".$col->query_name."_TODATE"] = $val1;
                                $_SESSION[reportico_namespace()]["latestRequest"]["MANUAL_".$col->query_name] = $val1;
                            }
                        }
                    }
                    else
                    {
                        $_REQUEST["MANUAL_".$col->query_name] = $criteriaval;
		                if ( get_reportico_session_param('latestRequest') )
                        {
                            $_SESSION[reportico_namespace()]["latestRequest"]["MANUAL_".$col->query_name] = $criteriaval;
                        }
                    }
                }
            }

            // Fetch the criteria value summary if required for displaying
            // the criteria entry summary at top of report
			if ( $execute_mode && $this->target_show_criteria &&
                    ( array_key_exists($col->query_name, $_REQUEST) 
			        || array_key_exists("MANUAL_".$col->query_name, $_REQUEST) 
			        || array_key_exists("HIDDEN_".$col->query_name, $_REQUEST) 
                    ) )
			{
				$lq =&	$this->lookup_queries[$col->query_name] ;
                if ( $lq->criteria_type == "LOOKUP" )
				    $lq->execute_criteria_lookup();
	            $lq->criteria_summary_display();
                $identified_criteria = true;
            }

			if ( array_key_exists($col->query_name, $_REQUEST) )
			{
				$this->lookup_queries[$col->query_name]->column_value =
					$_REQUEST[$col->query_name];
			}

			if ( array_key_exists("MANUAL_".$col->query_name, $_REQUEST) )
			{
				$this->lookup_queries[$col->query_name]->column_value =
				$_REQUEST["MANUAL_".$col->query_name];

				$lq =&	$this->lookup_queries[$col->query_name] ;
				if ( $lq->criteria_type == "LOOKUP" && $_REQUEST["MANUAL_".$col->query_name])
				{
					foreach ( $lq->lookup_query->columns as $k => $col1 )
					{
						if ( $col1->lookup_display_flag )
							$lab =& $lq->lookup_query->columns[$k];
						if ( $col1->lookup_return_flag )
							$ret =& $lq->lookup_query->columns[$k];
						if ( $col1->lookup_abbrev_flag )
							$abb =& $lq->lookup_query->columns[$k];
					}

					if ( $abb && $ret && $abb->query_name != $ret->query_name )
					{
                        if ( !$identified_criteria )
						    $lq->execute_criteria_lookup();
						$res =& $lq->lookup_query->targets[0]->results;
						$choices = $lq->column_value;
						if ( !is_array($choices) )
							$choices = explode(',', $choices);
						$lq->column_value;
						$choices = array_unique($choices);
						$target_choices = array();
						foreach ( $choices as $k => $v )
					   	{
                                if ( isset ( $res[$abb->query_name] ) )
								foreach ( $res[$abb->query_name] as $k1 => $v1 )
								{
									//echo "$v1 / $v<br>";
									if ( $v1 == $v )
									{
										$target_choices[] = $res[$ret->query_name][$k1];
										//echo "$k -> ".$choices[$k]."<BR>";
									}
								}
						}	
						$choices = $target_choices;
						$lq->column_value = implode(",", $choices);

						if ( !$choices )
						{
							// Need to set the column value to a arbitrary value when no data found
							// matching users MANUAL entry .. if left blank then would not bother 
							// creating where clause entry
							$lq->column_value = "(NOTFOUND)";
						}
						$_REQUEST["HIDDEN_".$col->query_name] = $choices;
					}
					else
					{
						if ( !is_array($_REQUEST["MANUAL_".$col->query_name]))
							$_REQUEST["HIDDEN_".$col->query_name] = explode(",", $_REQUEST["MANUAL_".$col->query_name]);
						else
							$_REQUEST["HIDDEN_".$col->query_name] = $_REQUEST["MANUAL_".$col->query_name];
					}
				}
			}

			if ( array_key_exists($col->query_name."_FROMDATE_DAY", $_REQUEST) )
			{
				$this->lookup_queries[$col->query_name]->column_value =
					$this->lookup_queries[$col->query_name]->collate_request_date(
						$col->query_name, "FROMDATE",
						$this->lookup_queries[$col->query_name]->column_value,
						SW_PREP_DATEFORMAT);
			}

			if ( array_key_exists($col->query_name."_TODATE_DAY", $_REQUEST) )
			{
				$this->lookup_queries[$col->query_name]->column_value2 =
					$this->lookup_queries[$col->query_name]->collate_request_date(
						$col->query_name, "TODATE", 
						$this->lookup_queries[$col->query_name]->column_value2,
						SW_PREP_DATEFORMAT);
			}

			if ( array_key_exists("MANUAL_".$col->query_name."_FROMDATE", $_REQUEST) )
			{
				$this->lookup_queries[$col->query_name]->column_value =
					$_REQUEST["MANUAL_".$col->query_name."_FROMDATE"];

			}

			if ( array_key_exists("MANUAL_".$col->query_name."_TODATE", $_REQUEST) )
            {
				$this->lookup_queries[$col->query_name]->column_value2 =
					$_REQUEST["MANUAL_".$col->query_name."_TODATE"];
            }

			if ( array_key_exists("EXPANDED_".$col->query_name, $_REQUEST) )
				$this->lookup_queries[$col->query_name]->column_value =
					$_REQUEST["EXPANDED_".$col->query_name];
		}


        // If external page has supplied an initial output format then use it
        if ( $this->initial_output_format )
            $_REQUEST["target_format"] = $this->initial_output_format;

        // If printable HTML requested force output type to HTML
        if ( get_request_item("printable_html") )
        {
            $_REQUEST["target_format"] = "HTML";
        }

		// Prompt user for report destination if target not already set - default to HTML if not set
		if ( !array_key_exists("target_format", $_REQUEST) && $execute_mode == "EXECUTE" )
			$_REQUEST["target_format"] = "HTML";
			
		if ( array_key_exists("target_format", $_REQUEST) && $execute_mode == "EXECUTE" && count($this->targets) == 0)
		{	
		
			require_once("swoutput.php");
			$tf = $_REQUEST["target_format"];
			$this->target_format = strtoupper($tf);
			switch ( $tf )
			{
				case "CSV" :
				case "csv" :
				case "Microsoft Excel" :
				case "EXCEL" :
					$rep = new reportico_report_csv();
					$this->add_target($rep);
					$rep->set_query($this);
					break;

				case "soap" :
				case "SOAP" :
					$rep = new reportico_report_soap_template();
					$this->add_target($rep);
					$rep->set_query($this);
					break;

				case "html" :
				case "HTML" :
					$rep = new reportico_report_html_template();
					$this->add_target($rep);
					$rep->set_query($this);
					break;

				case "pdf" :
				case "PDF" :
					$rep = new reportico_report_pdf();
					$rep->page_length = 80;
					$this->add_target($rep);
					$rep->set_query($this);
					break;

				case "json" :
				case "JSON" :
					$rep = new reportico_report_json();
					$rep->page_length = 80;
					$this->add_target($rep);
					$rep->set_query($this);
					break;

               case "jquerygrid" :
               case "JQUERYGRID" :
                       $rep = new reportico_report_jquerygrid();
                       $rep->page_length = 80;
                       $this->add_target($rep);
                       $rep->set_query($this);
                    break;

				case "xml" :
				case "XML" :
					$rep = new reportico_report_xml();
					$rep->page_length = 80;
					$this->add_target($rep);
					$rep->set_query($this);
					break;

				//case "array" :
				case "ARRAY" :
					$rep = new reportico_report_array();
					$rep->page_length = 80;
					$this->add_target($rep);
					$rep->set_query($this);
					break;

				default:
					// Should not get here
			}
		}

		if ( array_key_exists("mailto", $_REQUEST) )
		{
			$this->email_recipients = $_REQUEST["mailto"];
		}

	}


	// -----------------------------------------------------------------------------
	// Function : login_check
	// -----------------------------------------------------------------------------
	function login_check($smarty)
	{

		global $g_project;
		
		if ( !$this->datasource )
		{
			$this->datasource = new reportico_datasource("none", "localhost", "");
		}

		$loggedon = false;

		//if ( $g_project == "admin" && $mode == "MENU" )
		if ( $g_project == "admin" )
		{
			// Allow access to Admin Page if already logged as admin user, or configuration does not contain
			// an Admin Password (older version of reportico) or Password is blank implying site congired with
			// No Admin Password security or user has just reset password to blank (ie open access )
			if (isset_reportico_session_param('admin_password') || !defined ('SW_ADMIN_PASSWORD') || ( defined ('SW_ADMIN_PASSWORD_RESET' ) && SW_ADMIN_PASSWORD_RESET == '' ) )
			{
				$loggedon = "ADMIN";
			}
			else
            {
				if (array_key_exists("login", $_REQUEST) && isset($_REQUEST['admin_password']))
				{
                    // User has supplied an admin password and pressed login
					if ( $_REQUEST['admin_password'] == SW_ADMIN_PASSWORD )
					{
						set_reportico_session_param('admin_password',"1");
						$loggedon = "ADMIN";
					}
					else
                    {
						$smarty->assign('ADMIN_PASSWORD_ERROR', template_xlate("PASSWORD_ERROR"));
                    }
				}
            }

			if ( array_key_exists("adminlogout", $_REQUEST) )
			{
				unset_reportico_session_param('admin_password');
				$loggedon = false;
			}

			// If Admin Password is set to blank then force logged on state to true
			if ( SW_ADMIN_PASSWORD == "" )
			{
				set_reportico_session_param('admin_password',"1");
				$loggedon = true;
			}
			return $loggedon;
		}

		if ( 
			( !defined ('SW_PROJECT_PASSWORD') ) || 
			( SW_PROJECT_PASSWORD == '' ) ||
			( isset_reportico_session_param('admin_password') ) ||
			( $this->execute_mode != "MAINTAIN" && isset_reportico_session_param('project_password') && 
					get_reportico_session_param('project_password') == SW_PROJECT_PASSWORD )  ||
			( isset_reportico_session_param('project_password') && get_reportico_session_param('project_password') == SW_PROJECT_PASSWORD && $this->allow_maintain == "DEMO" )
            
		)
		{
            // After logging on to project allow user access to design mode if user is admin or if we
            // are running in "DEMO" mode
			if ( isset_reportico_session_param('admin_password') || $this->allow_maintain == "DEMO" )
				$loggedon = "DESIGN";
			else
				$loggedon = "NORMAL";
		}
		else
		{
			// User has attempted to login .. allow access to report PREPARE and MENU modes if user has entered either project
			// or design password or project password is set to blank. Allow access to Design mode if design password is entered	
			// or design mode password is blank
			if (isset($_REQUEST['project_password']) || $this->initial_project_password  )
			{
                if ( $this->initial_project_password )
                    $testpassword = $this->initial_project_password;
                else
                    $testpassword = $_REQUEST['project_password'];

				if ( isset_reportico_session_param('admin_password') ||
					( $this->execute_mode != "MAINTAIN" && $testpassword == SW_PROJECT_PASSWORD  )
                    )
				{
					set_reportico_session_param('project_password',$testpassword);
					$loggedon = true;
					if (isset_reportico_session_param('admin_password'))
						$loggedon = "DESIGN";
					else
						$loggedon = "NORMAL";
				}
				else
                {
                    if ( isset($_REQUEST["login"]) )
					    $smarty->assign('PROJ_PASSWORD_ERROR', "Error");
                }
			}
		}

		// User has pressed logout button, default then to MENU mode
		if ( array_key_exists("logout", $_REQUEST) )
		{
			if ( array_key_exists("admin_password", $_SESSION[reportico_namespace()]) )
			{
				unset_reportico_session_param('admin_password');
			}
			unset_reportico_session_param('project_password');
			set_reportico_session_param("execute_mode","MENU");
			$loggedon = false;
			if ( SW_PROJECT_PASSWORD == '' ) 
			{
				$loggedon = "NORMAL";
			}
		}

		return $loggedon;
	}

	// -----------------------------------------------------------------------------
	// Function : build_column_list
	// -----------------------------------------------------------------------------
	function & build_column_list()
	{

		$str = "";
		$ct = 0;

		// Build Select Column List
		foreach ( $this->columns as $k => $col )
		{
			if ( $col->in_select )
			{
				if ( $ct > 0 )
					$str .= ",";

				$str .= " ";

				if ( $col->table_name )
					$str .= $col->table_name.".";
			
				$str .= $col->column_name;

				if ( ($col->query_name) )
					$str .= " ".$col->query_name;

				$ct++;
			}
		}
	//die;	
		return $str;
	}

	// -----------------------------------------------------------------------------
	// Function : build_order_list
	// -----------------------------------------------------------------------------
	function & build_order_list($in_criteria_name)
	{
		$ct = 0;
		$str = "";

		foreach ( $this->order_set as $col )
		{
			if ( $ct > 0 )
				$str .= ",";
			else
				$ct++;

			$str .= " ";

			if ( $col->table_name )
			{
				$str .= $col->table_name.".";
				$str .= $col->column_name." ";
			}
			else
			{
				$str .= $col->query_name." ";
			}
			$str .= $col->order_type;
		}

		// May need to use users custom sort :-
		if ( ! $in_criteria_name && $orderby = get_request_item("sidx", "" ) )
		{
            if ( $orddir = get_request_item("sord", "" ) )
			    $str = $orderby." ".$orddir;
            else
			    $str = $orderby;
		}

		if ( $str )
			$str = " \nORDER BY ".$str;

		return $str;
	}

	// -----------------------------------------------------------------------------------
	function  & build_limit_offset ()
	{
		$str = "";

		// Handle any user specified FIRST, SKIP ROWS functions
        // Set in the following order :-
        // User specified a limit and offset parameter else
		$limit = get_request_item("report_limit", "");	
		$offset = get_request_item("report_offset", "");	
        // User specified a page and row parameter  which else
        if ( !$limit && ! $offset )
        {
		    $page = get_request_item("page", "");	
		    $rows = get_request_item("rows", "");	
		    if ( $page && $page > 0 && $rows )
            {
			    $offset = ( $page - 1 ) * $rows;
                $limit = $rows;
            }
        }

        // report contains a default skip and offset definition
        if ( !$limit && ! $offset )
        {
            $limit = $this->sql_limit_first;
            $offset = $this->sql_skip_offset;
        }
        
        if ( !$limit && !$offset )
            return $str;


	    if ( !$offset )
            $offset = "0";

        if ( $this->datasource->_conn_driver != "pdo_informix" && $this->datasource->_conn_driver != "informix" )
        {
            // Offset without limit doesnt work in Mysql
            if ( $this->datasource->_conn_driver == "pdo_mysql" )
            {
	            if ( !$limit )
                    $limit = "18446744073709551615";
            }

	        $str .= " LIMIT $limit";
        
	        if ( $offset )
		        $str .= " OFFSET $offset";
        } 
        else
        {
            if ( $rows )
                $str .= " FIRST $limit";
       
	        if ( $offset ) 
                $str = " SKIP $offset".$str;
        }

		return $str;
	}

	// -----------------------------------------------------------------------------
	// Function : build_where_list
	// -----------------------------------------------------------------------------
	function & build_where_list($include_lookups = true)
	{
		// Parse the where text to replace withcriteria values specified
		// with {}
		if ( $this->where_text != "AND " )
			$str = " \nWHERE 1 = 1 ".$this->where_text;
		else
			$str = " \nWHERE 1 = 1 ";

		$x = array_keys($this->lookup_queries);

		$parsing = true;

		if ( $include_lookups )
			foreach ( $this->lookup_queries as $k => $col )
			{
				if ( $col->column_name )
				{
					$str .= $col->get_criteria_clause();
				}
			}

		$str .= " ".$this->group_text;
		return $str;
	}

	// -----------------------------------------------------------------------------
	// Function : build_where_extra_list
	// -----------------------------------------------------------------------------
	function & build_where_extra_list($in_is_expanding = false, $criteria_name)
	{
		$str = "";
		$expval = false;
		if ( $in_is_expanding )
		{
			if ( array_key_exists("expand_value", $_REQUEST) )
			{
				if ( $_REQUEST["expand_value"] && $this->match_column )
				{
					$expval = $_REQUEST["expand_value"];
				}
			}
			if ( array_key_exists("MANUAL_".$criteria_name, $_REQUEST) )
			{
					$tmpval=$_REQUEST['MANUAL_'.$criteria_name];
					if ( strlen($tmpval) > 1 && substr($tmpval, 0, 1) == "?" )
							$expval = substr($tmpval, 1);
			}

			if ( $expval ) 
			{
				$str = ' AND '.$this->match_column.' LIKE "%'.$expval.'%"';
			}
		}

		return $str;
	}

	// -----------------------------------------------------------------------------
	// Function : build_where_criteria_link
	// -----------------------------------------------------------------------------
	function & build_where_criteria_link($in_is_expanding = false)
	{
		$retval = "";
		foreach ($this->criteria_links as $criteria_link )
		{
			$clause = $criteria_link["clause"];
			$link = $criteria_link["tag"];
			$query = $criteria_link["query"];

			$params = array();

			if ( ! array_key_exists("EXPANDED_".$link, $_REQUEST) )
			{
				if ( array_key_exists($link, $_REQUEST) )
				{
					$params = $_REQUEST[$link];
						if ( !is_array($params) )
							$params = array ( $params );
				}
			}

			$hidden_params = array();
			if ( ! array_key_exists("EXPANDED_".$link, $_REQUEST) )
				if ( array_key_exists("HIDDEN_".$link, $_REQUEST) )
				{
					$hidden_params = $_REQUEST["HIDDEN_".$link];
					if ( !is_array($hidden_params) )
						$hidden_params = array ( $hidden_params );
				}

			$manual_params = array();
			if ( ! array_key_exists("EXPANDED_".$link, $_REQUEST) )
			{
				if ( array_key_exists("MANUAL_".$link, $_REQUEST) )
				{
					$manual_params = explode(',',$_REQUEST["MANUAL_".$link]);
					if ( !is_array($manual_params) )
						$manual_params = array ( $manual_params );
				}
			}
	
			$expanded_params = array();
			if ( array_key_exists("EXPANDED_".$link, $_REQUEST) )
			{
				$expanded_params = $_REQUEST["EXPANDED_".$link];
				if ( !is_array($expanded_params) )
					$expanded_params = array ( $expanded_params );
			}

			$del = "";
			$cls ="";

			// quotedness for in clause is based on return value column
			if ( $query )
			{
				if ( $query->lookup_return_col )
				{
					$del = $query->lookup_return_col->get_value_delimiter();
				}
			}


			foreach ( $hidden_params as $col )
			{

				if ( $col == "(ALL)" )
					continue; 

				if ( !$cls )
					$cls = $del.$col.$del;
				else
					$cls .= ",".$del.$col.$del;
			}
			foreach ( $expanded_params as $col )
			{
				if ( $col == "(ALL)" )
					continue; 

				if ( !$cls )
					$cls = $del.$col.$del;
				else
					$cls .= ",".$del.$col.$del;
			}

			if ( $cls )
				$retval = " AND $clause IN ( $cls )";
		}

		return ( $retval );
				
	}

	// -----------------------------------------------------------------------------
	// Function : build_table_list
	// -----------------------------------------------------------------------------
	function & build_table_list()
	{
		$str = " \nFROM ".$this->table_text;
		return $str;
	}

	// -----------------------------------------------------------------------------
	// Function : build_query
	// -----------------------------------------------------------------------------
	function build_query($in_is_expanding = false, $criteria_name = "", $in_design_mode = false )
	{
		$this->set_request_columns();
		$execute_mode = $this->get_execute_mode();

        // Use raw user query in >= Version 2.5
        if ( $this->sql_raw )
        {
		    $this->query_statement = $this->sql_raw;

            // Build in criteria items
            $critwhere = "";
		    if ( $execute_mode != "MAINTAIN" )
            {
			    foreach ( $this->lookup_queries as $k => $col )
			    {
				    if ( $col->column_name )
				    {
					    $critwhere .= $col->get_criteria_clause();
				    }
			    }
            }

			// Add in any expand criteria
		    $critwhere .= $this->build_where_extra_list($in_is_expanding, $criteria_name);

            // If user has "Main query column" criteria then parse sql to find
            // where to insert them
            if ( $critwhere )
            {
                $p = new reportico_sql_parser($this->query_statement);
                if ( $p->parse() )
                {
                    if ( $p->haswhere )
                    {
                        $this->query_statement = 
                            substr( $this->query_statement, 0, $p->whereoffset ). 
                            " 1 = 1".
                            $critwhere.
                            " AND".
                            substr( $this->query_statement, $p->whereoffset );
                    }
                    else
                    {
                        $this->query_statement = 
                            substr( $this->query_statement, 0, $p->whereoffset ). 
                            " WHERE 1 = 1 ".
                            $critwhere.
                            substr( $this->query_statement, $p->whereoffset );
                    }
                }
            }

            // Dont add limits/offset if crtieria query of entering SQL in design mode
            if ( !$criteria_name  && !$in_design_mode)
                if ( $this->datasource->_conn_driver != "pdo_informix" && $this->datasource->_conn_driver != "informix" )
		            $this->query_statement .= $this->build_limit_offset();
        }
        else
        {
            // Pre Version 2.5 - parts of SQL specified in XML
		    $this->query_statement = "SELECT";

            // Dont add limits/offset if crtieria query of entering SQL in design mode
            if ( !$criteria_name  && !$in_design_mode)
                if ( $this->datasource->_conn_driver == "pdo_informix" || $this->datasource->_conn_driver == "informix" )
		                $this->query_statement .= $this->build_limit_offset();

		    if ( $this->rowselection == "unique"  )
                if ( $this->datasource->_conn_driver == "pdo_informix" || $this->datasource->_conn_driver == "informix" )
				    $this->query_statement .= " UNIQUE";
			    else
				    $this->query_statement .= " DISTINCT";

		    $this->query_statement .= $this->build_column_list();
		    $this->query_statement .= $this->build_table_list();

		    if ( $execute_mode == "MAINTAIN" )
			    $this->query_statement .= $this->build_where_list(false);
		    else
			    $this->query_statement .= $this->build_where_list(true);

		    $this->query_statement .= $this->build_where_extra_list($in_is_expanding, $criteria_name);
		    $this->query_statement .= $this->build_where_criteria_link($in_is_expanding);
		    $this->query_statement .= $this->build_order_list($criteria_name);

            // Dont add limits/offset if crtieria query of entering SQL in design mode
            if ( !$criteria_name  && !$in_design_mode)
                if ( $this->datasource->_conn_driver != "pdo_informix" && $this->datasource->_conn_driver != "informix" )
		            $this->query_statement .= $this->build_limit_offset();

        }

	    if ( $execute_mode != "MAINTAIN" )
	    {
		    $this->query_statement = reportico_assignment::reportico_meta_sql_criteria($this->parent_query, $this->query_statement);
	    }
			
	}			

	// -----------------------------------------------------------------------------
	// Function : create_page_header
	// -----------------------------------------------------------------------------
	function create_page_header( 	
			$page_header_name = "",
			$line,
			$page_header_text
			)
	{
		$this->page_headers[$page_header_name] = new reportico_page_end($line, $page_header_text);
	}			

	// -----------------------------------------------------------------------------
	// Function : create_page_footer
	// -----------------------------------------------------------------------------
	function create_page_footer( 	
			$page_footer_name = "",
			$line,
			$page_footer_text
			)
	{
		$this->page_footers[$page_footer_name] = new reportico_page_end($line, $page_footer_text);
	}			

	// -----------------------------------------------------------------------------
	// Function : create_group
	// -----------------------------------------------------------------------------
	function create_group( 	
			$query_name = "",
			$in_group = false
			)
	{
		$this->groups[] = new reportico_group($query_name, $this);
		end($this->groups);
		$ky = key($this->groups);
		return ( $this->groups[$ky] );
	}			

	// -----------------------------------------------------------------------------
	// Function : create_group_trailer
	// -----------------------------------------------------------------------------
	function create_group_trailer( $query_name, $trailer_column, $value_column )
	{
		$this->check_group_name("create_group_trailer", $query_name);
		//$this->check_column_name("create_group_trailer", $trailer_column);
		$this->check_column_name("create_group_trailer", $value_column);

		$grp = get_group_column($query_name, $this->groups );
		$qc = get_query_column($value_column, $this->columns );
		//$trl = get_query_column($trailer_column, $this->columns )) )
		$grp->add_trailer($trailer_column, $qc);
	}

	// -----------------------------------------------------------------------------
	// Function : delete_group_trailer_by_number
	// -----------------------------------------------------------------------------
	function delete_group_trailer_by_number( $query_name, $trailer_number )
	{
		$tn = (int)$trailer_number;
		if ( !$this->check_group_name_r("create_group_trailer", $query_name) )
		{	
			handle_error(  "Failure in Group Column Trailer: Unknown Group Name $query_name");
			return;
		}

		$grp = get_group_column($query_name, $this->groups );

		$ct = 0;
		$k = false;
		$updtr = false;
		foreach ( $grp->trailers as $k => $v )
		{
			foreach ( $v as $k2 => $v2 )
			{
				if ( $ct == $tn )
				{
					array_splice($grp->trailers[$k], $k2, 1 );
					break;
				}
				$ct++;
			}
			if ( $ct == $tn )
				break;
		}
				
	}
	// -----------------------------------------------------------------------------
	// Function : set_group_trailer_by_number
	// -----------------------------------------------------------------------------
	function set_group_trailer_by_number( $query_name, $trailer_number, $trailer_column, $value_column )
	{
		$tn = (int)$trailer_number;
		if ( !$this->check_group_name_r("create_group_trailer", $query_name) )
		{	
			handle_error(  "Failure in Group Column Trailer: Unknown Group Name $query_name");
			return;
		}

		if ( !$this->check_column_name_r("create_group_trailer", $trailer_column) )
		{
			handle_error(  "Failure in Group Column Trailer: Unknown Column $trailer_column");
			return;
		}

		if ( !$this->check_column_name_r("create_group_trailer", $value_column) )
		{
			handle_error(  "Failure in Group Column Trailer: Unknown Column $value_column");
			return;
		}

		//$grp =& $this->groups[$query_name] ;
		$grp = get_group_column($query_name, $this->groups );
		$col = get_query_column($value_column, $this->columns );

		$ct = 0;
		$k = false;
		$updtr = false;
		$looping = true;

		foreach ( $grp->trailers as $k => $v )
		{
			foreach ( $v as $k2 => $v2 )
			{
				if ( $ct == $tn )
				{
					array_splice ( $grp->trailers[$k], $k2, 1 );
					$grp->trailers[$trailer_column][] =& $col;
					$looping = false;
					break;
				}
				$ct++;
			}
			if ( !$looping )
				break;
		}
				
	}

	// -----------------------------------------------------------------------------
	// Function : create_group_header
	// -----------------------------------------------------------------------------
	function create_group_header ( $query_name, $header_column )
	{
		$this->check_group_name("create_group_header", $query_name);
		$this->check_column_name("create_group_header", $header_column);

		$grp = get_group_column($query_name, $this->groups );
		$col = get_query_column($header_column, $this->columns );
		//$trl = get_query_column($trailer_column, $this->columns );
		//$trl =& $this->columns[$trailer_column] ;
		$grp->add_header($col);
	}

	// -----------------------------------------------------------------------------
	// Function : set_group_header_by_number
	// -----------------------------------------------------------------------------
	function set_group_header_by_number ( $query_name, $header_number, $header_column )
	{

		$hn = (int)$header_number;
		if ( !$this->check_group_name_r("create_group_header", $query_name) )
		{	
			handle_error(  "Failure in Group Column Header: Unknown Group Name $query_name");
			return;
		}

		if ( !$this->check_column_name_r("create_group_header", $header_column) )
		{
			handle_error(  "Failure in Group Column Header: Unknown Column $header_column");
			return;
		}

		$grp = get_group_column($query_name, $this->groups );
		$col = get_query_column($header_column, $this->columns );
		$grp->headers[$hn] =& $col;
	}

	// -----------------------------------------------------------------------------
	// Function : delete_group_header_by_number
	// -----------------------------------------------------------------------------
	function delete_group_header_by_number ( $query_name, $header_number )
	{

		$hn = (int)$header_number;
		if ( !$this->check_group_name_r("delete_group_header", $query_name) )
		{	
			handle_error(  "Failure in Group Column Header: Unknown Group Name $query_name");
			return;
		}

		$grp = get_group_column($query_name, $this->groups );
		array_splice($grp->headers, $hn, 1 );
	}

	// -----------------------------------------------------------------------------
	// Function : create_group_column
	// -----------------------------------------------------------------------------
	function create_group_column( 	
			$query_name = "",
			$assoc_column = "",
			$summary_columns = "",
			$header_columns = ""
			)
	{
		$col =& $this->get_column($query_name);
		$col->assoc_column =$assoc_column;
		$col->header_columns = explode(',',$header_columns);
		$col->summary_columns = explode(',',$summary_columns);

		$this->group_set[] =& $col ;
	}			

	// -----------------------------------------------------------------------------
	// Function : create_order_column
	// -----------------------------------------------------------------------------
	function create_order_column( 	
			$query_name = "",
			$order_type = "ASC"
			)
	{
		$col =& $this->get_column($query_name);

		$order_type = strtoupper($order_type); 
		if ( $order_type ==         "UP" ) $order_type = "ASC";
		if ( $order_type ==  "ASCENDING" ) $order_type = "ASC";
		if ( $order_type ==       "DOWN" ) $order_type = "DESC";
		if ( $order_type == "DESCENDING" ) $order_type = "DESC";

		$col->order_type = $order_type;

		$this->order_set[] =& $col ;
		
	}			


	// -----------------------------------------------------------------------------
	// Function : remove_group
	// -----------------------------------------------------------------------------
	function remove_group( 	
			$query_name = "" 
			)
	{
		if ( !($grp = get_group_column($query_name, $this->groups ) ) )
		{
				return;
		}

		$cn = 0;
		$ct = 0;
		foreach ( $this->groups as $k => $v )
		{
			if ( $k->group_name == $query_name )
			{
				$cn = $ct;
				break;
			}

			$ct ++;
		}


		// finally remove the column
		array_splice($this->groups, $cn, 1 );
	}			
	// -----------------------------------------------------------------------------
	// Function : remove_column
	// -----------------------------------------------------------------------------
	function remove_column( 	
			$query_name = "" 
			)
	{
		$col = get_query_column($query_name, $this->columns );
		if ( ! $col )
				return;

		$ct = 0;
		$cn = 0;
		foreach ( $this->columns as $k => $v )
		{
			if ( $v->query_name == $query_name )
			{
				$cn = $ct;
				break;
			}
			$ct ++;
		}

		// Remove all order bys to this column
		$deleting = true;
		while ( $deleting )
		{
			$deleting = false;
			foreach ( $this->order_set as $k => $v )
			{
				if ( $v->query_name == $query_name )
				{
					array_splice ( $this->order_set, $k, 1 );
					$deleting = true;
					break;
				}
			}
		}


		// Remove all assignments to this column
		$deleting = true;
		while ( $deleting )
		{
			$deleting = false;
			foreach ( $this->assignment as $k => $v )
			{
				if ( $v->query_name == $query_name )
				{
					array_splice ( $this->assignment, $k, 1 );
					$deleting = true;
					break;
				}
			}
		}

		// Remove all group headers for this column
		$deleting = true;
		while ( $deleting )
		{
			$deleting = false;
			foreach ( $this->groups as $k => $v )
			{
				foreach ( $v->headers as $k1 => $v1 )
				{
					if ( $v1->query_name == $query_name )
					{
						array_splice ( $this->groups[$k]->headers, $k1, 1 );
						$deleting = true;
						break;
					}
				}

				$cn1 = 0;
				foreach ( $v->trailers as $k1 => $v1 )
				{
					if ( $k1 == $query_name )
					{
						array_splice ( $this->groups[$k]->trailers, $cn1, 1 );
						$deleting = true;
						break;
					}

					foreach ( $v->trailers[$k1] as $k2 => $v2 )
					{
						if ( $v2->query_name == $query_name )
						{
							array_splice ( $this->groups[$k]->trailers[$k1], $k2, 1 );
							$deleting = true;
							break;
						}
					}
					$cn1++;

					if ( $deleting )
						break;
				}

			}
		}

		// finally remove the column
		array_splice($this->columns, $cn, 1 );
	}			
	
	// -----------------------------------------------------------------------------
	// Function : create_criteria_column
	// -----------------------------------------------------------------------------
	function create_criteria_column( 	
			$query_name = "",
			$table_name = "table_name",
			$column_name = "column_name", 
			$column_type = "string",
			$column_length = 0,
			$column_mask = "MASK",
			$in_select = true
			)
	{
			// Default Query Column Name to Datbase Column Name ( if not set )
			
			// If the column already exists we are probably importing over the
			// top of an existing query, so just update it
			if ( $cl = get_query_column($query_name, $this->columns ) )
			{
				$cl->table_name = $table_name;
				$cl->column_name = $column_name;
				$cl->column_type = $column_type;
				$cl->column_length = $column_length;
				$cl->column_mask = $column_mask;
			}
			else
			{
				$this->columns[] = new reportico_criteria_column
					(
						$query_name,
						$table_name,
						$column_name, 
						$column_type,
						$column_length,
						$column_mask,
						$in_select
					);
				end($this->columns);
				$ky = key($this->columns);
				$this->display_order_set["itemno"][] = count($this->columns);
				$this->display_order_set["column"][] = &$this->columns[$ky];
			}
	}			
	
	// -----------------------------------------------------------------------------
	// Function : create_query_column
	// -----------------------------------------------------------------------------
	function create_query_column( 	
			$query_name = "",
			$table_name = "table_name",
			$column_name = "column_name", 
			$column_type = "string",
			$column_length = 0,
			$column_mask = "MASK",
			$in_select = true
			)
	{
			// Default Query Column Name to Datbase Column Name ( if not set )
			
			$this->columns[] = new reportico_query_column
					(
						$query_name,
						$table_name,
						$column_name, 
						$column_type,
						$column_length,
						$column_mask,
						$in_select
					);
			end($this->columns);
			$ky = key($this->columns);
			$this->display_order_set["itemno"][] = count($this->columns);
			$this->display_order_set["column"][] = &$this->columns[$ky];
	}			

	// -----------------------------------------------------------------------------
	// Function : set_column_order
	// -----------------------------------------------------------------------------
	function set_column_order( 	
			$query_name = "",
			$order,
			$insert_before = true
			)
	{
		//echo "=========================================<br>";
		//echo "set order $query_name - $order<br>";
			// Changes the display order of the column
			// by resetting display_order_set
			reset($this->display_order_set);

			$ct = count($this->display_order_set["itemno"]);
			$c =& $this->display_order_set;
			for ($i = 0; $i < $ct; $i++ )
			{
				if ( $c["column"][$i]->query_name == $query_name )
				{
					if ( $c["itemno"][$i] < $order  )
					{
						//echo $c["itemno"][$i]." up1  ".$c["column"][$i]->query_name." $i<br>";
						$c["itemno"][$i] = $order + 1;
					}
					else
					{
						//echo $c["itemno"][$i]." set  ".$c["column"][$i]->query_name." $i<br>";
						$c["itemno"][$i] = $order;
					}
				}
				else
					if ( ( $c["itemno"][$i] >= $order && $insert_before) 
						|| 
					     ( $c["itemno"][$i] > $order && !$insert_before)  )
					{
						//echo $c["itemno"][$i]." up5  ".$c["column"][$i]->query_name." $i<br>";
						$c["itemno"][$i] += 500;
					}
					//else
						//echo $c["itemno"][$i]." leave ".$c["column"][$i]->query_name." $i<br>";
			}

			// Now resort the list
			$n = array_multisort(
				$this->display_order_set["itemno"], SORT_ASC, SORT_NUMERIC,
				$this->display_order_set["column"]
			);

			for ($i = 0; $i < $ct; $i++ )
			{
				$c["itemno"][$i]  = $i + 1;
			}
			foreach ( $this->display_order_set["itemno"] as $val )
			{
				$vv=$val  - 1;
				//echo " SET $val ",  $this->display_order_set["column"][$vv]->query_name. " - ".$val."/". $this->display_order_set["itemno"][$vv]."<BR>";
				$ct++;
			}


	}			

    // Work out whether we are in ajax mode. This is so if either 
    // ajax mode has been specified or there is an ajax_script_url specified
    // or reportico has been called by an ajax script using the reportico_ajax_called=1
    // url request parameter
    function derive_ajax_operation() 
    {
        // Fetch URL path to reportico and set URL path to the runner
        $this->reportico_url_path = get_reportico_url_path();
        if ( !$this->url_path_to_reportico_runner )
            $this->url_path_to_reportico_runner = $this->reportico_url_path."run.php";

        // If full ajax mode is requested but no ajax url is passed then defalt the ajax url to the default reportico runner
        if ( $this->reportico_ajax_script_url )
            $this->reportico_ajax_mode = true;
        if ( !$this->reportico_ajax_script_url )
            $this->reportico_ajax_script_url = $this->url_path_to_reportico_runner;
        if ( $this->reportico_ajax_called )
            $this->reportico_ajax_mode = true;
		$this->reportico_ajax_preloaded = get_request_item("reportico_ajax_called", $this->reportico_ajax_preloaded);
		if ( get_reportico_session_param("reportico_ajax_called" ) )
            $this->reportico_ajax_mode = true;

        if ( $this->reportico_ajax_mode )
        {
            $this->embedded_report = true;
        }
    }

	// -----------------------------------------------------------------------------
	// Function : initialize_panels
	//
	// Based on whether Reportico is in criteria entry, report run or other mode
	// Flag what browser panels should be displayed
	// -----------------------------------------------------------------------------
	function initialize_panels($mode)
	{
		global $g_project;
		global $g_dropdown_menu;

		$smarty = new smarty();
		$smarty->template_dir = find_best_location_in_include_path( "templates" );
		$smarty->compile_dir = find_best_location_in_include_path( "templates_c" );

		$dummy="";
		$version = "\$Name: reportico-3_2 $";
		$version = preg_replace("/.*reportico-/", "", $version);
		$version = preg_replace("/ \\$/", "", $version);
		$version = preg_replace("/_/", ".", $version);

		$forward_url_params = session_request_item('forward_url_get_parameters', $this->forward_url_get_parameters);

		$smarty->assign('REPORTICO_VERSION', $version);

        // Assign user parameters to template
        if ( $this->user_parameters && is_array($this->user_parameters) )
	        foreach ( $this->user_parameters as $k => $v )
            {
                $param = preg_replace("/ /", "_", $k);
                $smarty->assign('USER_'.$param, $v);
            }

		// Smarty needs to include Javascript if AJAX enabled
		if ( !defined ('AJAX_ENABLED') )
			define('AJAX_ENABLED', true);
		$smarty->assign('AJAX_ENABLED', AJAX_ENABLED);

		// Date format for ui Datepicker
        global $g_language;
		$smarty->assign('AJAX_DATEPICKER_LANGUAGE', get_datepicker_language($g_language));
		$smarty->assign('AJAX_DATEPICKER_FORMAT', get_datepicker_format(SW_PREP_DATEFORMAT));
		

		$smarty->assign('DB_LOGGEDON', false);
		$smarty->assign('ADMIN_MENU_URL', false);
		$smarty->assign('CONFIGURE_MENU_URL', false);
		$smarty->assign('CREATE_REPORT_URL', false);
		$smarty->assign('SESSION_ID', reportico_session_name());

        // Set smarty variables
		$smarty->assign('SCRIPT_SELF',  $this->url_path_to_calling_script);

		$smarty->assign('REPORTICO_AJAX_MODE',  $this->reportico_ajax_mode);
		$smarty->assign('REPORTICO_AJAX_CALLED',  $this->reportico_ajax_called);
		$smarty->assign('REPORTICO_URL_DIR',  $this->reportico_url_path);

		$smarty->assign('REPORTICO_AJAX_RUNNER',  $this->reportico_ajax_script_url);

		$smarty->assign('PRINTABLE_HTML', false);
        if ( get_request_item("printable_html") )
        {
		    $smarty->assign('PRINTABLE_HTML', true);
        }

        // In frameworks we dont want to load jquery when its intalled once when the module load
        // so flag this unless specified in new_reportico_window
		$smarty->assign('REPORTICO_AJAX_PRELOADED',  $this->reportico_ajax_preloaded);
        if ( get_request_item("new_reportico_window",  false ) )
		    $smarty->assign('REPORTICO_AJAX_PRELOADED',  false);
    
		$smarty->assign('SHOW_LOGOUT', false);
		$smarty->assign('SHOW_LOGIN', false);
		$smarty->assign('SHOW_REPORT_MENU', false);
		$smarty->assign('SHOW_SET_ADMIN_PASSWORD', false);
		$smarty->assign('SHOW_OUTPUT', false);
		$smarty->assign('SHOW_DESIGN_BUTTON', false);
		$smarty->assign('SHOW_ADMIN_BUTTON', true);
        $smarty->assign('SHOW_PROJECT_MENU_BUTTON', true);
        if ( $this->access_mode && ( $this->access_mode != "DEMO" && $this->access_mode != "FULL" && $this->access_mode != "ALLPROJECTS" && $this->access_mode != "ONEPROJECT" )  )
        {
            $smarty->assign('SHOW_PROJECT_MENU_BUTTON', false);
        }
		$smarty->assign('SHOW_EXPAND', false);
		$smarty->assign('SHOW_CRITERIA', false);
		$smarty->assign('SHOW_EXPANDED', false);
		$smarty->assign('SHOW_MODE_MAINTAIN_BOX', false);
		$smarty->assign('STATUSMSG', '');
		$smarty->assign('ERRORMSG', false);
		$smarty->assign('SET_ADMIN_PASSWORD_INFO', '');
		$smarty->assign('SET_ADMIN_PASSWORD_ERROR', '');
		$smarty->assign('ADMIN_PASSWORD_ERROR', '');
		$smarty->assign('PASSWORD_ERROR', '');
		$smarty->assign('DEMO_MODE', false);
		$smarty->assign('DROPDOWN_MENU_ITEMS', false);

        // Dont allow admin menu buttons to show in demo mode
        if ( $this->allow_maintain == "DEMO" )
        {
            $smarty->assign('DEMO_MODE', true);
            $smarty->assign('SHOW_ADMIN_BUTTON', false);
        }

        if ( !$this->admin_accessible )
        {
            $smarty->assign('SHOW_ADMIN_BUTTON', false);
        }

        // Dont show admin button 
        if ( $this->access_mode && ( $this->access_mode != "DEMO" && $this->access_mode != "FULL" && $this->access_mode != "ALLPROJECTS" )  )
        {
            $smarty->assign('SHOW_ADMIN_BUTTON', false);
        }

		
        $partialajaxpath = find_best_location_in_include_path( "partial.php" );
		$smarty->assign('AJAX_PARTIAL_RUNNER', $this->reportico_url_path.$partialajaxpath );

        $csspath = $this->reportico_url_path."/".find_best_url_in_include_path( SW_STYLESHEET );

		$smarty->assign('STYLESHEET', $csspath);

        $jspath = find_best_url_in_include_path( "js/reportico.js" );
		if ( $jspath ) $jspath = dirname($jspath);

		$smarty->assign('JSPATH', $this->reportico_url_path.$jspath);

		$this->panels["MAIN"] = new reportico_panel($this, "MAIN");
		$this->panels["MAIN"]->set_smarty($smarty);
		$this->panels["BODY"] = new reportico_panel($this, "BODY");
		$this->panels["TITLE"] = new reportico_panel($this, "TITLE");
		$this->panels["TOPMENU"] = new reportico_panel($this, "TOPMENU");
		$this->panels["MENUBUTTON"] = new reportico_panel($this, "MENUBUTTON");
		$this->panels["LOGIN"] = new reportico_panel($this, "LOGIN");
		$this->panels["SET_ADMIN_PASSWORD"] = new reportico_panel($this, "SET_ADMIN_PASSWORD");
		$this->panels["LOGOUT"] = new reportico_panel($this, "LOGOUT");
		$this->panels["FORM"] = new reportico_panel($this, "FORM");
		$this->panels["MENU"] = new reportico_panel($this, "MENU");
		$this->panels["ADMIN"] = new reportico_panel($this, "ADMIN");
		$this->panels["USERINFO"] = new reportico_panel($this, "USERINFO");
		$this->panels["RUNMODE"] = new reportico_panel($this, "RUNMODE");
		$this->panels["PREPARE"] = new reportico_panel($this, "PREPARE");
		$this->panels["CRITERIA"] = new reportico_panel($this, "CRITERIA");
		$this->panels["CRITERIA_FORM"] = new reportico_panel($this, "CRITERIA_FORM");
		$this->panels["CRITERIA_EXPAND"] = new reportico_panel($this, "CRITERIA_EXPAND");
		$this->panels["MAINTAIN"] = new reportico_panel($this, "MAINTAIN");
		$this->panels["REPORT"] = new reportico_panel($this, "REPORT");
		$this->panels["DESTINATION"] = new reportico_panel($this, "DESTINATION");
		$this->panels["EXECUTE"] = new reportico_panel($this, "EXECUTE");
		$this->panels["STATUS"] = new reportico_panel($this, "STATUS");
		$this->panels["ERROR"] = new reportico_panel($this, "ERROR");

		// Identify which panels are visible by default
		$this->panels["MAIN"]->set_visibility(true);
		$this->panels["BODY"]->set_visibility(true);
		$this->panels["TITLE"]->set_visibility(true);
		$this->panels["TOPMENU"]->set_visibility(true);
		$this->panels["STATUS"]->set_visibility(true);
		$this->panels["ERROR"]->set_visibility(true);

		// Set up a default panel hierarchy
		$this->panels["MAIN"]->add_panel($this->panels["BODY"]);
		$this->panels["BODY"]->add_panel($this->panels["TITLE"]);
		$this->panels["BODY"]->add_panel($this->panels["TOPMENU"]);
		$this->panels["BODY"]->add_panel($this->panels["FORM"]);
		$this->panels["BODY"]->add_panel($this->panels["STATUS"]);
		$this->panels["BODY"]->add_panel($this->panels["ERROR"]);
		$this->panels["FORM"]->add_panel($this->panels["CRITERIA"]);
		$this->panels["FORM"]->add_panel($this->panels["MAINTAIN"]);
		$this->panels["FORM"]->add_panel($this->panels["REPORT"]);
		$this->panels["FORM"]->add_panel($this->panels["MENU"]);
		$this->panels["FORM"]->add_panel($this->panels["ADMIN"]);
		$this->panels["CRITERIA"]->add_panel($this->panels["CRITERIA_FORM"]);
		$this->panels["CRITERIA"]->add_panel($this->panels["CRITERIA_EXPAND"]);
		$this->panels["CRITERIA"]->add_panel($this->panels["DESTINATION"]);
		$this->panels["BODY"]->add_panel($this->panels["REPORT"]);
		$this->panels["TOPMENU"]->add_panel($this->panels["LOGIN"]);
		$this->panels["TOPMENU"]->add_panel($this->panels["SET_ADMIN_PASSWORD"]);
		$this->panels["TOPMENU"]->add_panel($this->panels["USERINFO"]);
		$this->panels["TOPMENU"]->add_panel($this->panels["MENUBUTTON"]);
		$this->panels["TOPMENU"]->add_panel($this->panels["RUNMODE"]);
		$this->panels["TOPMENU"]->add_panel($this->panels["LOGOUT"]);

		// Store any menu page URL, in ajax mode links go through the general ajax link, otherwise go through calling script
        $calling_script = $this->get_action_url();
		$this->prepare_url =  $calling_script."?execute_mode=PREPARE&amp;session_name=".reportico_session_name();
		$this->menu_url =  $calling_script."?execute_mode=MENU&amp;session_name=".reportico_session_name();
		$this->admin_menu_url =  $calling_script."?project=admin&amp;execute_mode=MENU&amp;session_name=".reportico_session_name();
		$this->configure_project_url =  $calling_script."?execute_mode=PREPARE&amp;xmlin=configureproject.xml&amp;session_name=".reportico_session_name();
		$this->delete_project_url =  $calling_script."?execute_mode=PREPARE&amp;xmlin=deleteproject.xml&amp;session_name=".reportico_session_name();
		$this->create_report_url =  $calling_script."?execute_mode=MAINTAIN&amp;xmlin=&amp;session_name=".reportico_session_name();

		if ( $forward_url_params )
		{
				$this->prepare_url .= "&".$forward_url_params;
				$this->menu_url .= "&".$forward_url_params;
				$this->admin_menu_url .= "&".$forward_url_params;
				$this->configure_project_url .= "&".$forward_url_params;
				$this->delete_project_url .= "&".$forward_url_params;
				$this->create_report_url .= "&".$forward_url_params;
		}
		// ***MENUURL ***if (array_key_exists("menu_url", $_SESSION[reportico_namespace()]))
		// ***MENUURL ***{
			// ***MENUURL ***$this->menu_url = get_reportico_session_param("menu_url");
		// ***MENUURL ***}

        // Generate dropdown menu strip in menu or prepare mode
        if ( $g_dropdown_menu )
            $this->dropdown_menu = $g_dropdown_menu;

		if ( $this->dropdown_menu && ( $mode == "MENU" || $mode == "PREPARE" ) )
        {
		    $this->generate_dropdown_menu ( $this->dropdown_menu );
		    $smarty->assign('DROPDOWN_MENU_ITEMS', $this->dropdown_menu);
        }

		if ( $mode == "MENU" )
		{
			// Store the URL of thi smenu so it can be referred to 
			// in later screens
			// ***MENUURL ***$this->menu_url = $_SERVER["PHP_SELF"];
			// ***MENUURL ***set_reportico_session_param("menu_url",$this->menu_url);
			$this->panels["MENU"]->set_visibility(true);
			//$this->panels["FORM"]->add_panel($this->panels["MENU"]);
		}

		if ( $mode == "EXECUTE" )
		{
			$this->panels["REPORT"]->set_visibility(true);
			//$this->panels["FORM"]->add_panel($this->panels["REPORT"]);
		}

		if ( $mode == "MAINTAIN" )
		{
			$this->panels["MAINTAIN"]->set_visibility(true);
			//$this->panels["FORM"]->add_panel($this->panels["MAINTAIN"]);
		}

		if ( $mode == "ADMIN" )
		{
			$this->panels["ADMIN"]->set_visibility(true);
			$this->panels["MENU"]->set_visibility(true);
			//$this->panels["FORM"]->add_panel($this->panels["MAINTAIN"]);
		}

		if ( $mode == "PREPARE" )
		{
			$this->panels["CRITERIA"]->set_visibility(true);
			$this->panels["CRITERIA_FORM"]->set_visibility(true);
			$this->panels["CRITERIA_EXPAND"]->set_visibility(true);
			$this->panels["DESTINATION"]->set_visibility(true);
			//$this->panels["FORM"]->add_panel($this->panels["CRITERIA"]);
		}

		// Visibility of Login details depends on whether user has provided login
		// details and also whether those details are valid, so set user name
		// and password to use for connection and then attempt to connect
		$this->panels["MENUBUTTON"]->set_visibility(true);
		$this->panels["LOGIN"]->set_visibility(false);
		$this->panels["SET_ADMIN_PASSWORD"]->set_visibility(false);
		$this->panels["LOGOUT"]->set_visibility(true);
		$this->panels["USERINFO"]->set_visibility(true);
		$this->panels["RUNMODE"]->set_visibility(true);

		// If no admin password then force user to enter one and  a language
		if ( $g_project == "admin" && SW_ADMIN_PASSWORD == "PROMPT" )
		{
			$smarty->assign('LANGUAGES', available_languages());
			// New Admin password submitted, attempt to set password and go to MENU option
			if ( array_key_exists("submit_admin_password", $_REQUEST) )
			{
				$smarty->assign('SET_ADMIN_PASSWORD_ERROR', 
					save_admin_password($_REQUEST["new_admin_password"], $_REQUEST["new_admin_password2"], $_REQUEST["jump_to_language"]  ) );
			}

			$this->panels["SET_ADMIN_PASSWORD"]->set_visibility(true);
			$smarty->assign('SHOW_SET_ADMIN_PASSWORD', true);
			$this->panels["LOGOUT"]->set_visibility(false);
			$this->panels["MENU"]->set_visibility(false);
			$smarty->assign('SHOW_REPORT_MENU', false);
			if ( !defined('SW_ADMIN_PASSWORD_RESET') )
				return;
			else
				$smarty->assign('SHOW_SET_ADMIN_PASSWORD', false);
		} 

		{
			set_reportico_session_param("loggedin",true);
			if ( $login_type = $this->login_check($smarty) )
			{
				// User has supplied details ( user and password ), so assume that login box should
				// not occur ( user details
				$this->panels["MENUBUTTON"]->set_visibility(true);
				$this->panels["LOGIN"]->set_visibility(false);
				$this->panels["SET_ADMIN_PASSWORD"]->set_visibility(false);
				$this->panels["LOGOUT"]->set_visibility(true);
				$this->panels["USERINFO"]->set_visibility(true);
				$this->panels["FORM"]->set_visibility(true);

				if ( $login_type == "DESIGN" )
				{
					$this->panels["RUNMODE"]->set_visibility(true);
				}
				else
					$this->panels["RUNMODE"]->set_visibility(false);
				$smarty->assign('SHOW_REPORT_MENU', true);

				// Only show a logout button if a password is in effect
				if ( $login_type == "DESIGN" || $login_type == "ADMIN" || ( defined ('SW_PROJECT_PASSWORD') && SW_PROJECT_PASSWORD != '' ) )
					$smarty->assign('SHOW_LOGOUT', true);

                // Dont show logout button in ALLPROJECTS, ONE PROJECT
                if ( $this->access_mode && ( $this->access_mode != "DEMO" && $this->access_mode != "FULL" && $this->access_mode != "ALLPROJECTS" ) )
					$smarty->assign('SHOW_LOGOUT', false);

                if ( $mode == "PREPARE" && ( $this->xmlinput == "deleteproject.xml" || $this->xmlinput == "configureproject.xml" || $this->xmlinput == "createtutorials.xml" ) )
                {
                    // Dont show database errors if displaying Configure Project prepare page as database connectivity could be wrong
                    // and user will correct it 
                }
                else
				if ( $this->datasource->connect() || $mode != "MAINTAIN" )
				{
					// Store connection session details
					set_reportico_session_param("database",$this->datasource->database);
					set_reportico_session_param("hostname",$this->datasource->host_name);
					set_reportico_session_param("driver",$this->datasource->driver);
					set_reportico_session_param("server",$this->datasource->server);
					set_reportico_session_param("protocol",$this->datasource->protocol);
				}
				else
				{
					//echo "not connected okay<br>";
					$this->panels["LOGIN"]->set_visibility(true);
					$this->panels["SET_ADMIN_PASSWORD"]->set_visibility(false);
					$this->panels["MENUBUTTON"]->set_visibility(false);
					$this->panels["LOGOUT"]->set_visibility(false);
					$this->panels["USERINFO"]->set_visibility(false);
					$this->panels["RUNMODE"]->set_visibility(true);
					$this->panels["FORM"]->set_visibility(false);
					$this->panels["STATUS"]->set_visibility(true);
					$this->panels["ERROR"]->set_visibility(true);
				}
				//echo "done connecting";
			}
			else
			{
				// If not logged in then set first criteria entry to true
				// So when we do get into criteria it will work
				set_reportico_session_param("firstTimeIn",true);
				set_reportico_session_param("loggedin",false);
				
				$this->panels["LOGIN"]->set_visibility(true);
				$this->panels["MENUBUTTON"]->set_visibility(true);
				$this->panels["LOGOUT"]->set_visibility(false);
				$this->panels["USERINFO"]->set_visibility(false);
				$this->panels["RUNMODE"]->set_visibility(false);

                // Dont allow admin design access if access mode is set and not FULL access
		        if ( $g_project == "admin" )
                if ( $this->access_mode && ( $this->access_mode != "FULL" )  )
                {
			        $this->panels["LOGIN"]->set_visibility(false);
                }
				
				// We do want to show the "run project" list in admin mode if not logged in
				if ( $g_project == "admin" )
					$this->panels["FORM"]->set_visibility(true);
				else
					$this->panels["FORM"]->set_visibility(false);


			}
		}

		
	}

	// -----------------------------------------------------------------------------
	// If initial starting parameters are given (initial project, access_mode then
    // only use them if this is the first use of the session, other wise clear them
	// -----------------------------------------------------------------------------
	function handle_initial_settings()
	{
		if ( !$this->framework_parent && !get_reportico_session_param("awaiting_initial_defaults") )
        {
            $this->initial_project = false;
            $this->initial_execute_mode = false;
            $this->initial_report = false;
            $this->initial_project_password = false;
            $this->initial_output_style = false;
            $this->initial_output_format = false;
            $this->initial_show_detail = false;
            $this->initial_show_graph = false;
            $this->initial_show_group_headers = false;
            $this->initial_show_group_trailers = false;
            $this->initial_show_column_headers = false;
            $this->initial_show_criteria = false;
            $this->initial_execution_parameters = false;
            $this->access_mode = false;
        }
	}

	// -----------------------------------------------------------------------------
	// If initial starting parameters are given (initial project, access_mode then
    // only use them if this is the first use of the session, other wise clear them
	// -----------------------------------------------------------------------------
	function handled_initial_settings()
	{
		if ( get_reportico_session_param("awaiting_initial_defaults") )
        {
		    set_reportico_session_param("awaiting_initial_defaults", false);
        }
	}

	// -----------------------------------------------------------------------------
	// Function : initialize_connection
	// -----------------------------------------------------------------------------
	function initialize_connection()
	{
		return;
	}

	// -----------------------------------------------------------------------------
	// Function : handle_xml_input
	// -----------------------------------------------------------------------------
	function handle_xml_query_input($mode=false)
	{
		if ( ! $this->top_level_query )
			return;

		if ( $mode == "MENU" && array_key_exists("xmlin", $_SESSION[reportico_namespace()]) )
		{
			unset_reportico_session_param("xmlin");
		}

		if ( $mode == "ADMIN" && array_key_exists("xmlin", $_SESSION[reportico_namespace()]) )
		{
			unset_reportico_session_param("xmlin");
		}

		// See if XML needs to be read in
		$this->xmlinput = false;

		if ( array_key_exists("xmlin", $_SESSION[reportico_namespace()]) )
		{
			$this->xmlinput = get_reportico_session_param("xmlin");
			set_reportico_session_param("xmlout",$this->xmlinput);
		}

		if ( array_key_exists("xmlin", $_REQUEST) )
		{
			set_reportico_session_param("firstTimeIn",true);
			$this->xmlinput =  $_REQUEST["xmlin"];

			unset_reportico_session_param("xmlintext");
			set_reportico_session_param("xmlin",$this->xmlinput);
			set_reportico_session_param("xmlout",$this->xmlinput);
		}

        if ( $this->initial_report )
        {
            $this->xmlinput = $this->initial_report;
			set_reportico_session_param("xmlin",$this->xmlinput);
			set_reportico_session_param("xmlout",$this->xmlinput);
        }

		if ( $this->user_template == "_DEFAULT" )
		{
			$this->user_template = false;
			$_SESSION['reportico_template'] = $this->user_template;
		}
		else if ( !$this->user_template )
		{
			$this->user_template = session_request_item('reportico_template', $this->user_template);
		}
		if ( array_key_exists("partial_template", $_REQUEST) )
		{
			$this->user_template = $_REQUEST["partial_template"];
		}

        // Set template from request if specified
		//if ( array_key_exists("template", $_SESSION[reportico_namespace()]) )
		//{
			//$this->user_template = get_reportico_session_param("template");
			//set_reportico_session_param("template",$this->user_template);
		//}

		//if ( array_key_exists("template", $_REQUEST) )
		//{
			//$this->user_template =  $_REQUEST["template"];
			//set_reportico_session_param("template",$this->user_template);
		//}


		// Now work out out file...
		if ( !$this->xmloutfile )
		{
				$this->xmloutfile = $this->xmlinput;
		}

		if ( array_key_exists("xmlout", $_SESSION[reportico_namespace()]) )
		{
			$this->xmloutfile = get_reportico_session_param("xmlout");
		}

		if ( array_key_exists("xmlout", $_REQUEST) )
		{
			$this->xmloutfile =  $_REQUEST["xmlout"];
			set_reportico_session_param("xmlout",$this->xmloutfile);
		}
			
		$this->xmlintext =  false;
		if ( $this->top_level_query && array_key_exists("xmlintext", $_SESSION[reportico_namespace()]) )
		{
			if ( ( $this->xmlintext = get_reportico_session_param("xmlintext") ) )
			{
				$this->xmlinput =  false;
			}
		}

		// Has new report been pressed ? If so clear any existing report
		// definitions
		if ( array_key_exists("submit_maintain_NEW", $_REQUEST) || 
	             array_key_exists("new_report", $_REQUEST))
		{
			$this->xmlinput =  false;
			$this->xmlintext =  false;
			$this->xmloutfile =  false;
			set_reportico_session_param("xmlin",$this->xmlinput);
			set_reportico_session_param("xmlout",$this->xmlinput);
		}

		if ( $this->xmlinput || $this->xmlintext )
		{
			$this->xmlin = new reportico_xml_reader($this, $this->xmlinput, $this->xmlintext);
			$this->xmlin->xml2query();
		}
		else
		{
			$this->xmlin = new reportico_xml_reader($this, false, "");
			$this->xmlin->xml2query();
		}


	}

	// -----------------------------------------------------------------------------
	// Function : get_panel
	// -----------------------------------------------------------------------------
	function & get_panel($panel=false, $section = "ALL")
	{
			$txt = "";

			switch ( $section )
			{
				case "PRE":
					$txt = $this->panels[$panel]->pre_text;					
					break;
				case "POST":
					$txt = $this->panels[$panel]->post_text;					
					break;
				default:
					$txt = $this->panels[$panel]->full_text;					
					break;
			}
			return $txt;
	}

	// -----------------------------------------------------------------------------
	// Function : execute
	// -----------------------------------------------------------------------------
	function execute($mode=false, $draw=true)
	{
		global $g_system_errors;
		global $g_system_debug;
		global $g_code_area;
		global $g_code_source;
		global $g_debug_mode;
		global $g_language;
		global $g_project;
        global $g_session_namespace;
        global $g_session_namespace_key;

        if ( $this->session_namespace )
            $g_session_namespace = $this->session_namespace;

        if ( $g_session_namespace )
            $g_session_namespace_key = "reportico_".$g_session_namespace;

        // If a session namespace doesnt exist create one
        if ( !isset($_SESSION[$g_session_namespace_key]) || isset($_REQUEST['clear_session']) )
            initialize_reportico_namespace($g_session_namespace_key);

        // Work out the mode (ADMIN, PREPARE, MENU, EXECUTE, MAINTAIN based on all parameters )
        if ( !$mode )
            $mode = $this->get_execute_mode();

		$old_error_handler = set_error_handler("ErrorHandler");
        set_exception_handler("ExceptionHandler");

        // If new session, we need to use initial project, report etc, otherwise ignore them
	    $this->handle_initial_settings();

        // Fetch project config
		set_project_environment($this->initial_project);

        // Set globally parameters set from application framework
        global $g_external_param1;
        global $g_external_param2;
        global $g_external_param3;
        global $g_external_user;
        $g_external_param1 = $this->external_param1;
        $g_external_param2 = $this->external_param2;
        $g_external_param3 = $this->external_param3;
        $g_external_user = $this->external_user;

        // We are in AJAX mode if it is passed throuh
        if ( isset($_REQUEST["reportico_ajax_called"]) )
            $this->reportico_ajax_called = $_REQUEST["reportico_ajax_called"];

        //set_reportico_session_param("reportico_ajax_called", $_REQUEST["reportico_ajax_called"] );

        // Store whether in framework
        set_reportico_session_param("framework_parent",$this->framework_parent);

        // Set access mode to decide whether to allow user to access Design Mode, Menus, Criteria or just run a single report
        $this->access_mode = session_item("access_mode", $this->access_mode );
        if ( $this->access_mode == "DEMO" )
            $this->allow_maintain = "DEMO";

		$this->reports_path = "projects/".$g_project;

        // Convert input and out charsets into their PHP versions
        // for later iconv use
        $this->db_charset = db_charset_to_php_charset(SW_DB_ENCODING);
        $this->output_charset = output_charset_to_php_charset(SW_OUTPUT_ENCODING);

		// Ensure Smarty Template folder exists and is writeable
		$include_template_dir="templates_c";
		if ( !( is_dir ("templates_c")) )
		{
			find_file_to_include("templates_c", $include_template_dir, $include_template_dir);
		}

		if ( !( is_dir ($include_template_dir)) )
		{
			echo "Unable to generate output. The <b>$include_template_dir</b> folder does not exist within the main reportico area. Please create this folder and ensure it has read, write and execute permissions and then retry.";
			die;
		}

		if ( !sw_path_executable( $include_template_dir ) )
		{
			echo "Unable to generate output. The <b>$include_template_dir</b> folder does not have read, write and execute permissions. Please correct and retry.";
			die;
		}

		$g_debug_mode = get_request_item("debug_mode", "0", $this->first_criteria_selection );

		if ( !$mode )
		{
			$mode=$this->get_execute_mode();
		}

		// If the project is the ADMIN project then the MAin Menu will be the Admin Page
		if ( $g_project == "admin" && $mode == "MENU" )
		{
			$mode = "ADMIN";
		}

		// If this is PREPARE mode then we want to identify whether user has entered prepare
		// screen for first time so we know whether to set defaults or not
		switch ( $mode )
		{
				case "PREPARE":
					$this->report_progress("Ready", "READY" );
					$this->first_criteria_selection = true;
					// Must find ALternative to THIs for first time in testing!!!
					if ( array_key_exists("target_format", $_REQUEST))
					{
						$this->first_criteria_selection = false;
						set_reportico_session_param("firstTimeIn",false);
					}

                    if ( !isset_reportico_session_param("firstTimeIn")  )
                        set_reportico_session_param("firstTimeIn",true);

                    // Default output to HTML in PREPARE mode first time in
                    if ( get_reportico_session_param("firstTimeIn") && !isset($_REQUEST["target_format"]))
                    {
                        $this->target_format = "HTML";
                        set_reportico_session_param("target_format","HTML");
                    }
                        
                    // Default style to TABLE in PREPARE mode first time in
                    //if ( get_reportico_session_param("firstTimeIn") && !isset($_REQUEST["target_style"]))
                    //{
                        //$this->target_format = "TABLE";
                        //set_reportico_session_param("target_style","TABLE");
//echo "set table ";
                    //}
                        
					break;

				case "EXECUTE":
					if ( array_key_exists("submit", $_REQUEST))
						$this->first_criteria_selection = false;
					else
						$this->first_criteria_selection = true;

                    if  ( get_reportico_session_param("awaiting_initial_defaults") )
					    set_reportico_session_param("firstTimeIn",true);
                    else
                        if ( get_reportico_session_param("firstTimeIn") && get_request_item("refreshReport", false) )
					        set_reportico_session_param("firstTimeIn",true);
                        else
					        set_reportico_session_param("firstTimeIn",false);

					break;

				case "MAINTAIN":
					$this->report_progress("Ready", "READY" );
					$this->first_criteria_selection = true;
					set_reportico_session_param("firstTimeIn",true);
					break;

				default:
					//$this->report_progress("Ready", "READY" );
					$this->first_criteria_selection = true;
					set_reportico_session_param("firstTimeIn",true);
					break;
		}

		// If xml file is used to genearate the reportico_query, either by the xmlin session variable
		// or the xmlin request variable then process this before executing
		if ( $mode == "EXECUTE" )
		{
			$_REQUEST['execute_mode'] = "$mode";

            // If executing report then stored the REQUEST parameters unless this
            // is a refresh of the report in which case we want to keep the ones already there
            $runfromcriteriascreen = get_request_item("user_criteria_entered", false);
            $refreshmode = get_request_item("refreshReport", false);

            if ( !get_request_item("printable_html") && ( $runfromcriteriascreen || ( !isset_reportico_session_param('latestRequest') || !get_reportico_session_param('latestRequest'))  ) )
            {
			    set_reportico_session_param('latestRequest',$_REQUEST);
            }
            else
            {
                if ( !$runfromcriteriascreen && $refreshmode )
                {
			        $_REQUEST = get_reportico_session_param('latestRequest');
                }
            }
		}
		else
		{
			if ( $mode != "MODIFY" && array_key_exists('latestRequest', $_SESSION[reportico_namespace()] ))
			{
				if ( get_reportico_session_param('latestRequest') )
				{
					$OLD_REQUEST = $_REQUEST;

                    // If a new report is being run dont bother trying to restore previous
                    // run crtieria
                    if ( !get_request_item("xmlin") )
					    $_REQUEST = get_reportico_session_param('latestRequest');

					foreach ( $OLD_REQUEST as $k => $v )
					{
						if ( $k == 'partial_template' ) $_REQUEST[$k] = $v;
						if ( preg_match ( "/^EXPAND_/", $k ) ) $_REQUEST[$k] = $v;
					}
					$_REQUEST['execute_mode'] = "$mode";
				}
			}
			set_reportico_session_param('latestRequest',"");
		}
        // Derive URL call of the calling script so it can be recalled in form actions when not running in AJAX mode
        if ( !$this->url_path_to_calling_script )
            $this->url_path_to_calling_script = $_SERVER["PHP_SELF"];


        // Work out we are in AJAX mode
        $this->derive_ajax_operation();

		switch ($mode) 
		{

			case "MODIFY":
                require_once("swmodify.php");
				$this->initialize_panels($mode);
                $engine = new reportico_db_engine($this->datasource->ado_connection->_connectionID);
                $status = $engine->perform_project_modifications($g_project);
                if ( $status["errstat"] != 0 )
                { 
                    header("HTTP/1.0 404 Not Found", true);
                }
                echo json_encode($status);
                die;
                    
			case "ADMIN":
				$txt = "";
				$this->handle_xml_query_input($mode);
				$this->build_admin_screen();
				$text = $this->panels["BODY"]->draw_smarty();
				$this->panels["MAIN"]->smarty->debugging =false;
				$this->panels["MAIN"]->smarty->assign('LANGUAGES', available_languages());
				$this->panels["MAIN"]->smarty->assign('CONTENT', $txt);
                restore_error_handler();

				if ( $this->user_template )
				    $this->panels["MAIN"]->smarty->display($this->user_template.'_admin.tpl');
				else
				    $this->panels["MAIN"]->smarty->display('admin.tpl');
		        $old_error_handler = set_error_handler("ErrorHandler");
				break;

			case "MENU":
				$this->handle_xml_query_input($mode);
				$this->build_menu();
	            load_mode_language_pack("languages", $this->output_charset);
				load_mode_language_pack("menu", $this->output_charset);
                localise_template_strings($this->panels["MAIN"]->smarty);

				$text = $this->panels["BODY"]->draw_smarty();
				$this->panels["MAIN"]->smarty->debugging =false;
				$this->panels["MAIN"]->smarty->assign('CONTENT', $text);
			    $this->panels["MAIN"]->smarty->assign('LANGUAGES', available_languages());
                restore_error_handler();
				if ( $this->user_template )
				    $this->panels["MAIN"]->smarty->display($this->user_template.'_menu.tpl');
				else
				    $this->panels["MAIN"]->smarty->display('menu.tpl');
		        $old_error_handler = set_error_handler("ErrorHandler");
				break;

			case "PREPARE":
				load_mode_language_pack("languages", $this->output_charset);
				$this->handle_xml_query_input($mode);
				$this->initialize_panels($mode);
				$this->set_request_columns();

                global $g_translations;
                global $g_report_desc;

                if ( $this->xmlinput == "deleteproject.xml" || $this->xmlinput == "configureproject.xml" || $this->xmlinput == "createtutorials.xml" || $this->xmlinput == "createproject.xml" )
                {
                    // If configuring project then use project language strings from admin project
                    // found in projects/admin/lang.php
                    load_project_language_pack("admin", $this->output_charset);
                }
				load_mode_language_pack("prepare", $this->output_charset);
                localise_template_strings($this->panels["MAIN"]->smarty);

				$text = $this->panels["BODY"]->draw_smarty();
				$this->panels["MAIN"]->smarty->debugging =false;
				$this->panels["MAIN"]->smarty->assign('CONTENT', $text);

				$reportname = preg_replace("/.xml/", "", $this->xmloutfile.'_prepare.tpl');
                restore_error_handler();
				if (preg_match("/$reportname/", find_best_location_in_include_path( "templates/". $reportname )))
				{
					$this->panels["MAIN"]->smarty->display($reportname);
				}
				else
				if ( $this->user_template )
				        $this->panels["MAIN"]->smarty->display($this->user_template.'_prepare.tpl');
				else
				        $this->panels["MAIN"]->smarty->display('prepare.tpl');
		        $old_error_handler = set_error_handler("ErrorHandler");
				break;
				 
			
			case "EXECUTE":
				load_mode_language_pack("languages", $this->output_charset);
				$this->handle_xml_query_input($mode);
				$this->initialize_panels($mode);
				$g_code_area = "Main Query";
				$this->build_query(false, "");
				$g_code_area = false;
				load_mode_language_pack("execute", $this->output_charset);
                localise_template_strings($this->panels["MAIN"]->smarty);

                if ( $this->xmlinput == "deleteproject.xml" || $this->xmlinput == "configureproject.xml" || $this->xmlinput == "createtutorials.xml" || $this->xmlinput == "createproject.xml" )
                {
                    // If configuring project then use project language strings from admin project
                    // found in projects/admin/lang.php
                    load_project_language_pack("admin", $this->output_charset);
                }

                if ( !get_reportico_session_param("loggedin",false) )
                    $text = "you are not logged in ";
                else
				    $text = $this->execute_query(false);

				if ( $this->target_format == "SOAP" )
				{
                    close_reportico_session();
					return;
				}

                // Situtations where we dont want to swithc results page - no data found, debug mode, not logged in
				if ( ( count($g_system_errors) > 0 || $g_debug_mode || count($g_system_debug) > 0 || !get_reportico_session_param("loggedin") ) )
				{
                    // If errors and this is an ajax request return json ajax response for first message
                    $runfromcriteriascreen = get_request_item("user_criteria_entered", false);
                    global $g_no_data;
                    if ( $g_no_data && get_request_item("new_reportico_window",  false ) && !$g_debug_mode && $this->target_format == "HTML" && $runfromcriteriascreen && $this->reportico_ajax_mode && count($g_system_errors) == 1 )
                        
                    {
                        header("HTTP/1.0 404 Not Found", true);
                        $response_array = array();
                        $response_array["errno"] = $g_system_errors[0]["errno"];
                        $response_array["errmsg"] = $g_system_errors[0]["errstr"];
                        echo json_encode($response_array);
                        die;
                    }
					$this->initialize_panels("PREPARE");
					$this->set_request_columns();
					$text = $this->panels["BODY"]->draw_smarty();
					$this->panels["MAIN"]->smarty->debugging =false;
					$title = sw_translate($this->derive_attribute("ReportTitle", "Unknown"));
					$this->panels["MAIN"]->smarty->assign('TITLE', $title);
					$this->panels["MAIN"]->smarty->assign('CONTENT', $text);
				    load_mode_language_pack("languages", $this->output_charset, true);
					load_mode_language_pack("prepare", $this->output_charset);
                    localise_template_strings($this->panels["MAIN"]->smarty);
					$reportname = preg_replace("/.xml/", "", $this->xmloutfile.'_prepare.tpl');
                    restore_error_handler();
					if (preg_match("/$reportname/", find_best_location_in_include_path( "templates/". $reportname )))
					{
						$this->panels["MAIN"]->smarty->display($reportname);
					}
					else
						if ( $this->user_template )
							$this->panels["MAIN"]->smarty->display($this->user_template.'_prepare.tpl');
						else
							$this->panels["MAIN"]->smarty->display('prepare.tpl');
	                $old_error_handler = set_error_handler("ErrorHandler");
                }
                else
                {	
                    if ( $this->target_format != "HTML" )
                    {
                            if ( $draw )
                                echo $text;
                    }
                    else
				    {
						$title = sw_translate($this->derive_attribute("ReportTitle", "Unknown"));
    
                        $pagestyle = $this->targets[0]->get_style_tags($this->output_reportbody_styles);

                        $this->panels["MAIN"]->smarty->assign('REPORT_PAGE_STYLE', $pagestyle);
						$this->panels["MAIN"]->smarty->assign('TITLE', $title);
						$this->panels["MAIN"]->smarty->assign('CONTENT', $text);

						$this->panels["MAIN"]->smarty->assign('EMBEDDED_REPORT', $this->embedded_report);

                        // When printing in separate html window make sure we dont treat report as embedded
                        if ( get_request_item("new_reportico_window",  false ) )
		                    $this->panels["MAIN"]->smarty->assign('EMBEDDED_REPORT',  false);

						if ( $this->email_recipients )
						{

							$recipients = explode(',', $this->email_recipients);
							foreach ( $recipients as $rec )
							{
				                load_mode_language_pack("languages", $this->output_charset, true);
								load_mode_language_pack("execute", $this->output_charset);
                                localise_template_strings($this->panels["MAIN"]->smarty);
								$mailtext = $this->panels["MAIN"]->smarty->fetch('execute.tpl', NULL, NULL, false);
								//$boundary = '-----=' . md5( uniqid ( rand() ) );
								//$message = "Content-Type: text/html; name=\"my attachment\"\n";
								//$message .= "Content-Transfer-Encoding: base64\n";
								//$message .= "Content-Transfer-Encoding: quoted-printable\n";
								//$message .= "Content-Disposition: attachment; filename=\"report.html\"\n\n";
								$content_encode = chunk_split(base64_encode($mailtext));
								$message = $mailtext . "\n";
								//$message .= $boundary . "\n";
								$headers  = "From: \"Report Admin\"<me@here.com>\n";
								$headers .= "MIME-Version: 1.0\n";
								$headers .= "Content-Transfer-Encoding: base64\n";
								//$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"";
								$headers = "Content-Type: text/html\n";
								mail ( $rec, "$title", $message, $headers );
							}
						}
						else
						{	
			                load_mode_language_pack("languages", $this->output_charset, true);
							load_mode_language_pack("execute", $this->output_charset);
                            localise_template_strings($this->panels["MAIN"]->smarty);
						    $reportname = preg_replace("/.xml/", "", $this->xmloutfile.'_execute.tpl');
                            restore_error_handler();

						    if (preg_match("/$reportname/", find_best_location_in_include_path( "templates/". $reportname )))
						    {
							    $this->panels["MAIN"]->smarty->display($reportname);
						    }
						    else
							    if ( $this->user_template )
								    $this->panels["MAIN"]->smarty->display($this->user_template.'_execute.tpl');
							    else
							    {
								    $this->panels["MAIN"]->smarty->display('execute.tpl');
							    }
	                        $old_error_handler = set_error_handler("ErrorHandler");
						}
					}
				}
				break;
				
			case "MAINTAIN":
				$this->handle_xml_query_input($mode);
				if ( $this->top_level_query )
				{
					$this->initialize_panels($mode);
				    load_mode_language_pack("maintain", $this->output_charset);
                    localise_template_strings($this->panels["MAIN"]->smarty);
					$this->xmlin->handle_user_entry();
					set_reportico_session_param("xmlintext",$this->xmlintext);

					$text = $this->panels["BODY"]->draw_smarty();
					$this->panels["MAIN"]->smarty->assign('CONTENT', $text);
					$this->panels["MAIN"]->smarty->display('maintain.tpl');
				}
				else
				{
					$this->premaintain_query();
				}
				 
				break;
				
			case "XMLOUT":
				$this->handle_xml_query_input($mode);
				$this->xmlout = new reportico_xml_writer($this);
				$this->xmlout->prepare_xml_data();

				if ( array_key_exists("xmlout", $_REQUEST) )
					$this->xmlout->write_file($_REQUEST["xmlout"]);
				else
					$this->xmlout->write();
				break;

			case "XMLSHOW":
				$this->handle_xml_query_input($mode);
				$this->xmlout = new reportico_xml_writer($this);
				$this->xmlout->prepare_xml_data();
				$this->xmlout->write();
				break;

			case "WSDLSHOW":
				$this->handle_xml_query_input($mode);
				$this->xmlout = new reportico_xml_writer($this);
				$this->xmlout->prepare_wsdl_data();
				break;

			case "SOAPSAVE":
				$this->handle_xml_query_input($mode);
				$this->xmlout = new reportico_xml_writer($this);
				$this->xmlout->generate_web_service($this->xmloutfile);
				break;
		}
	
	    $this->handled_initial_settings();

        close_reportico_session();
	}
	
	// -----------------------------------------------------------------------------
	// Function : build_admin_screen()
	// -----------------------------------------------------------------------------
	function build_admin_screen()
	{

		global $g_menu;
		global $g_menu_title;
		global $g_dropdown_menu;
		global $g_language;

		$p = new reportico_panel($this, "ADMIN");
		$this->initialize_panels("ADMIN");
		$this->set_attribute("ReportTitle", $g_menu_title);
	    load_mode_language_pack("languages", $this->output_charset);
	    load_mode_language_pack("admin", $this->output_charset);
        localise_template_strings($this->panels["MAIN"]->smarty);
		
		global $g_projpath;
		if ( $g_menu && is_array($g_menu) )
		{
			$ct = 0;
			foreach  ( $g_menu as $menuitem )
			{
				if ( $menuitem["title"] == "<AUTO>" )
				{
					// Generate Menu from XML files
					if (is_dir($g_projpath)) 
					{
						if ($dh = opendir($g_projpath)) 
						{
							while (($file = readdir($dh)) !== false) 
							{
								$mtch = "/".$menuitem["report"]."/";
								if ( preg_match ( $mtch, $file ) )
								{
									$repxml = new reportico_xml_reader($this, $file, false, "ReportTitle");
									$this->panels["MENU"]->set_menu_item($file, $repxml->search_response);
								}
							}
							closedir($dh);
						}
					}
				}
				else
                {
					$this->panels["MENU"]->set_menu_item($menuitem["report"], template_xlate($menuitem["title"]));
                }
				$ct++;
			}


			if ( $ct == 0 )
				handle_error( "No Menu Items Available - Check Language - ".$g_language);

			// Generate list of projects to choose from by finding all folders above the
			// current project area (i.e. the projects folder) and looking for any folder
			// that contains a config.php file (which means it proably is a project)
			if (is_dir($g_projpath."/..")) 
			{
				$ct = 0;
				if ($dh = opendir($g_projpath."/..")) 
				{
					while (($file = readdir($dh)) !== false) 
					{
						if ( is_dir ( $g_projpath."/../".$file ) )
							if ( is_file ( $g_projpath."/../".$file."/config.php" ) )
							{
								//$repxml = new reportico_xml_reader($this, $file, false, "ReportTitle");
								$this->panels["ADMIN"]->set_project_item($file, $file);
							}
					}
					closedir($dh);
				}
			}

		}
	}

	// -----------------------------------------------------------------------------
	// Function : build_menu()
	// -----------------------------------------------------------------------------
	function build_menu()
	{

		global $g_menu;
		global $g_menu_title;
		global $g_dropdown_menu;
		global $g_language;

		$p = new reportico_panel($this, "MENU");
		$this->initialize_panels("MENU");
		$this->set_attribute("ReportTitle", $g_menu_title);
		
		global $g_projpath;
		if ( $g_menu && is_array($g_menu) )
		{
			$ct = 0;
			foreach  ( $g_menu as $menuitem )
			{
				if ( $menuitem["title"] == "<AUTO>" )
				{
					// Generate Menu from XML files
					if (is_dir($g_projpath)) 
					{
						if ($dh = opendir($g_projpath)) 
						{
							while (($file = readdir($dh)) !== false) 
							{
								$mtch = "/".$menuitem["report"]."/";
								if ( preg_match ( $mtch, $file ) )
								{
									$repxml = new reportico_xml_reader($this, $file, false, "ReportTitle");
									$this->panels["MENU"]->set_menu_item($file, sw_translate($repxml->search_response));
								}
							}
							closedir($dh);
						}
					}
				}
				else
					$this->panels["MENU"]->set_menu_item($menuitem["report"], sw_translate($menuitem["title"]));
				$ct++;
			}


			if ( $ct == 0 )
				handle_error( "No Menu Items Available - Check Language - ".$g_language);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : premaintain_query
	// -----------------------------------------------------------------------------
	function premaintain_query()
	{
		foreach ( $this->pre_sql as $sql)
		{
			$nsql = reportico_assignment::reportico_lookup_string_to_php($sql);
			$recordSet = $conn->Execute($sql) ;
			echo $this->query_statement."<br>Query failed : ".$conn->ErrorMsg();
		}

		

		$this->fetch_column_attributes();

		// Run query for each target. Currently having more than
		// one target means first target is array which becomes source
		// for second target
		//for ($i = 0; $i < count($this->targets); $i++ )
		for ($i = 0; $i < 1; $i++ )
		{
			$target =& $this->targets[$i];

			$target->set_query($this);
			$target->set_columns($this->columns);
			$target->start();
		}
	}

	// -----------------------------------------------------------------------------
	// Function : execute_query
	// -----------------------------------------------------------------------------
	function execute_query($in_criteria_name)
	{

		global $g_code_area;
		global $g_code_source;
		global $g_error_status;

		$text = "";
		$g_error_status = false;

		$this->fetch_column_attributes();

		// Run query for each target. Currently having more than
		// one target means first target is array which becomes source
		// for second target
		//for ($i = 0; $i < count($this->targets); $i++ )
		for ($_counter = 0; $_counter < 1; $_counter++ )
		{
			$target =& $this->targets[$_counter];
			$target->set_query($this);
			$target->set_columns($this->columns);
			$target->start();
		//}

		// Reset all old column values to junk
		foreach ( $this->columns as $k => $col )
		{
			$this->columns[$k]->old_column_value = "";
		}

		if ( $_counter > 0 )
		{
			// Execute query 2
			$this->assignment = array();
			$ds = new reportico_datasource("array", "localhost");
			$this->set_datasource($ds);

			$ds->set_database($this->targets[0]->results);
			$ds->connect();

			foreach ( $this->columns as $k => $col )
			{
				$this->columns[$k]->in_select = true;
			}
		}

		/* Performing SQL query */ 
		$ds =& $this->datasource;
		$conn =& $this->datasource->ado_connection;

		$this->debug($this->query_statement);
		//$conn->debug = true;

		foreach ( $this->pre_sql as $sql)
		{
			$g_code_area = "Custom User SQLs";
			$nsql = reportico_assignment::reportico_meta_sql_criteria($this, $sql, true);
			handle_debug("Pre-SQL".$nsql, SW_DEBUG_LOW);
			//echo "<br>META SQL<br>========<br>";
			//echo "<BR>$nsql<BR>";
			$recordSet = $conn->Execute($nsql) 
				or handle_error("Pre-Query Failed<BR>$nsql<br><br>" . 
						$conn->ErrorMsg());
			$g_code_area = "";
		}

		// Execute Any Pre Execute Code, if not specified then
        // attempt to pick up code automatically from a file "projects/project/report.xml.php"
		$code = $this->get_attribute("PreExecuteCode");
		if ( !$code || $code == "NONE" || $code == "XX" )
        {
		    global $g_project;
	        $source_path = find_best_location_in_include_path( "projects/".$g_project."/".$this->xmloutfile.".php" );
            if ( is_file($source_path) )
            {
                $code = file_get_contents($source_path);
            }
            else
                $code = false;
        }

		if ( $code )
		{
			$g_code_area = "";
			$code = "\$lk =& \$this->lookup_queries;". $code;
			$code = "\$ds =& \$this->datasource->ado_connection;". $code;
			$code = "\$_criteria =& \$this->lookup_queries;". $code;
			$code = "\$_pdo =& \$_connection->_connectionID;". $code;
			$code = "\$_connection =& \$this->datasource->ado_connection;". $code;

			// set to the user defined error handler
			global $g_eval_code;
			$g_eval_code = $code;
			// If parse error in eval code then use output buffering contents to show user the error
			$ob_level =  ob_get_level();
			if ( $ob_level > 0 )
				ob_start();
			eval($code);
			$eval_output = ob_get_contents();	
			if ( $ob_level > 0 )
			    ob_end_clean();

            // Check for parse error
			if ( preg_match ( "/.*Parse error.*on line <b>(.*)<.b>/", $eval_output, $parseerrors ) )
			{
				// There is a parse error in the evaluated code .. find the relevant line
				$errtext = "Parse Error in custom report code: <br><hr>$eval_output<PRE>";
				foreach(preg_split("/(\r?\n)/", $code) as $lno => $line){
    					// do stuff with $line
					if ( $lno > $parseerrors[1] - 3 && $lno < $parseerrors[1] + 3 )
					{
						if ( $lno == $parseerrors[1] )
							$errtext .= ">>>  ";	
						else
							$errtext .= "     ";	
						$errtext .= $line;
						$errtext .= "\n";
					}
				}
				$errtext .= "</PRE>";
				trigger_error($errtext, E_USER_ERROR);

			}
			else
			{
                echo $eval_output;
			}
			$g_code_area = "";
			$g_code_source = "";
		}
		$recordSet = false;

		if ( $in_criteria_name )
			$g_code_area = "Criteria ".$in_criteria_name;
		else
			$g_code_area = "Main Report Query";

		// User may have flagged returning before SQL performed
		global $g_no_sql;
		if ( $g_no_sql )
			return;

		if ( !$g_error_status && $conn != false )
			$recordSet = $conn->Execute($this->query_statement) 
			or handle_error("Query Failed<BR><BR>".$this->query_statement."<br><br>" . 
			"Status ".$conn->ErrorNo()." - ".
			$conn->ErrorMsg());
		if ( $conn != false )
			handle_debug($this->query_statement, SW_DEBUG_LOW);

		// Begin Target Output
		if (!$recordSet) 
		{
			return;
		}

        // Main Query Result Fetching
		$this->query_count = 0;
		while (!$recordSet->EOF) {

			$line = $recordSet->FetchRow();
			$this->query_count++;

			$g_code_area = "Build Column";
			$this->build_column_results($line);

			$g_code_area = "Assignment";

			if ( $_counter < 1 )
            {
			    $target->set_default_styles();
                $this->charset_encode_db_to_output();
				$this->assign();
            }
			$g_code_source = false;

            // Skip line if required
            if ( $this->output_skipline )
            {
			    $this->query_count--;
                $this->output_skipline = false;
                continue;
            }

			$g_code_area = "Line Output";
			$target->each_line($line);

			$g_code_area = "Store Output";
			$this->store_column_results();
			if ($recordSet->EOF)
			 	break;
		}
		$g_code_area = "";

        global $g_no_data;
        $g_no_data = false;
		if ( $this->query_count == 0 && !$in_criteria_name && ( !$this->access_mode || $this->access_mode != "REPORTOUTPUT" ) )
		{
            $g_no_data = true;
			handle_error ( template_xlate("NO_DATA_FOUND"), E_USER_WARNING );
		}

		// Complete Target Output
		//for ($_counter = 0; $_counter < count($this->targets); $_counter++ )
		//{
			//$target =& $this->targets[$_counter];
			$target->finish();
			$text =& $target->text;

			/* Free resultset */
			$recordSet->Close();

		}
		return $text;

	}

	// -----------------------------------------------------------------------------
	// Function : get_column
	// -----------------------------------------------------------------------------
	function & get_column($query_name)
	{
		$retval = NULL;
		foreach ( $this->columns as $col )
		{
			if ( $col->query_name == $query_name )
			{
				$retval =& $col;
				break;
			}
		}
		return $retval;
	}

	// -----------------------------------------------------------------------------
	// Function : fetch_column_attributes
	// -----------------------------------------------------------------------------
	function fetch_column_attributes()
	{
		$conn = $this->datasource->ado_connection; 
		//$a = new reportico($this->datasource);
		//$old_database = $a->database;

		$datadict = false;
		reset($this->columns);
		$lasttab = "";
		while ( $d = key($this->columns) )
		{
			$value =& $this->columns[$d];

			if ( array_key_exists( $value->query_name, $this->clone_columns ) )
			{
				$value->column_type = 
					$this->clone_columns[$value->query_name][0];
				$value->column_length = 
					$this->clone_columns[$value->query_name][1];
				
			}
			else if ( $value->table_name )
			{
				if ( $lasttab != $value->table_name )
				{
					$datadict = $this->datasource->ado_connection->MetaColumns($value->table_name);
					if ( !$datadict )
					{
						// echo "Data Dictionary Attack Failed Table $value->table_name\n";
						// echo "Error ".$this->datasource->ado_connection->ErrorMsg()."<br>";
						//die;
					}
				}
				foreach ( $datadict as $k => $v )
				{

					if ( strtoupper(trim($k)) == strtoupper($value->column_name ) )
					{
						//$coldets = $datadict[strtoupper($value->column_name)];
						$coldets = $datadict[$k];
						$value->column_type = 
								reportico_datasource::map_column_type(
										$this->datasource->driver, 
										$datadict[$k]->type);

						if ( strtoupper($value->column_type) == "INTEGER" )
							$value->column_length = 0;
						else if ( strtoupper($value->column_type) == "SMALLINT" )
							$value->column_length = 0;
						else
							$value->column_length = (int)$datadict[$k]->max_length;
						break;
					}
				}
			}
			$lasttab = $value->table_name;
			next($this->columns);
		}
	}

	// -----------------------------------------------------------------------------
	// Function : add_assignment
	// -----------------------------------------------------------------------------
	function add_assignment
		(
			$query_name,
			$expression,
			$criteria
		)
		{
			//print("Added assign $query_name, $expression, $criteria\n");
			$this->assignment[] = new reportico_assignment
				(
					$query_name,
					$expression,
					$criteria
				);
		}

	// -----------------------------------------------------------------------------
	// Function : charset_encode_db_to_output
	// -----------------------------------------------------------------------------
	function charset_encode_db_to_output()
	{
        if ( $this->db_charset && $this->output_charset )
            if ( $this->db_charset != $this->output_charset )
		        foreach ( $this->columns as $col )
                {
                        $col->column_value = iconv($this->db_charset, $this->output_charset, $col->column_value);
                }
        
    }

	// -----------------------------------------------------------------------------
	// Function : assign
	// -----------------------------------------------------------------------------
	function assign()
	{
		global $g_debug_mode;
		global $g_code_area;
		global $g_code_source;

        // Clear any styles or instructions left over from previous rows
		foreach ( $this->columns as $col )
        {

                $col->output_cell_styles = false;
                $col->output_images = false;
                $col->output_hyperlinks = false;
        }
	
        // Perform assignments
		foreach ( $this->assignment as $assign )
		{
			$col = get_query_column($assign->query_name, $this->columns ) ;
			if ( !$col )
			{
				continue;
			}
			$g_code_area = "Assignment";
			$g_code_source = "<BR>In Assignment if ".$assign->criteria."<BR>";
			$g_code_source = "<BR>In Assignment ".$assign->query_name."=".$assign->expression;
			if ( $this->test($assign->criteria) )
			{
                if ( $assign->non_assignment_operation )
				    $a = $assign->expression.';';
                else
				    $a = '$col->column_value = '.$assign->expression.';';
				$r = eval($a);

				if ( /*SW_DEBUG ||*/ $g_debug_mode )
					handle_debug ("Assignment ".$assign->query_name." = ". $assign->expression.
						" => ".$col->column_value, SW_DEBUG_HIGH );

			}

		}
	}

	// -----------------------------------------------------------------------------
	// Function : test
	// -----------------------------------------------------------------------------
	function test($criteria)
	{

		$test_result = false;

		if ( !$criteria )
			return(true);

		$test_string = 'if ( '.$criteria.' ) $test_result = true;';
		eval($test_string);
		return $test_result;
	}

	// -----------------------------------------------------------------------------
	// Function : changed
	// -----------------------------------------------------------------------------
	function changed($query_name)
	{

		$result = false;

		if ( $query_name == "REPORT_BODY" )
			return false;

		$col = get_query_column($query_name, $this->columns ) ;
		if ( !$col )
		{
			handle_error ("The report includes a changed assignment involving a column ($query_name) that does not exist within the report. Perhaps a group needs to be deleted");
			return $result;
		}

		if ( $col->column_value
			!= $col->old_column_value )
			$result = true;

		return $result;
	}

	// -----------------------------------------------------------------------------
	// Function : reset
	// -----------------------------------------------------------------------------
	function reset($query_name)
	{

		$col = get_query_column($query_name, $this->columns ) ;
		if ( !$col )
		{
			handle_error ("The report includes an assignment involving a column ($query_name) that does not exist within the report. Perhaps a group needs to be deleted");
			return 0;
		}
		$col->reset_flag = true;
		$col->column_value= 0;

		return 0;
	}

	// -----------------------------------------------------------------------------
	// Function : groupcount
	// -----------------------------------------------------------------------------
	function groupcount($groupname, $result_name)
	{

		$col = get_query_column($query_name, $this->columns ) ;
		if ( !$col )
		{
			handle_error ("The report includes an assignment involving a column ($query_name) that does not exist within the report. Perhaps a group needs to be deleted");
			return 0;
		}
		$res = get_query_column($result_name, $this->columns ) ;

		if ( $this->changed($groupname) )
			$this->reset($result_name);

		if ( $res->old_column_value &&  !$res->reset_flag )
			$result = $res->old_column_value + $col->column_value;
		else
			$result =  $col->column_value;
		return $result;
	}

	// -----------------------------------------------------------------------------
	// Function : skipline 
    // Causes current line output to be skipped/not outputted
	// -----------------------------------------------------------------------------
	function skipline()
	{
        $this->output_skipline = true;
	}

	// -----------------------------------------------------------------------------
    // Function : embed_image 
    // Generates a link object against a column
	// -----------------------------------------------------------------------------
	function embed_image($column_assignee, $image, $width = false, $height = false )
	{
            get_query_column($column_assignee, $this->columns )->output_images = 
                        array("image" => $image, "width" => $width, "height" => $height );
	}

	// -----------------------------------------------------------------------------
    // Function : create_hyperlink 
    // Generates a link object against a column
	// -----------------------------------------------------------------------------
	function embed_hyperlink($column_assignee, $label, $url, $open_in_new = true, $is_drilldown = false)
	{
            get_query_column($column_assignee, $this->columns )->output_hyperlinks = 
                        array("label" => $label, "url" => $url, "open_in_new" => $open_in_new, "is_drilldown" => $is_drilldown);
	}

	// -----------------------------------------------------------------------------
	// Function : apply_style 
    // Sets up style instructions against an output row, cell or page
    // For example allows a cell to appear in a particular color
    // or with specified margins, or allows a row to have a border above etc
    // Styles relate to CSS and are transferred where supported through to PDF
	// -----------------------------------------------------------------------------
	function apply_style($column_assignee, $item_type, $style_type, $style_value)
	{
        if ( $item_type == "ALLCELLS" )
            $this->output_allcell_styles[$style_type] = $style_value;
        if ( $item_type == "ROW" )
            $this->output_row_styles[$style_type] = $style_value;
        if ( $item_type == "CELL" )
		    get_query_column($column_assignee, $this->columns )->output_cell_styles[$style_type] = $style_value;
        if ( $item_type == "PAGE" )
            $this->output_page_styles[$style_type] = $style_value;
        if ( $item_type == "BODY" )
            $this->output_reportbody_styles[$style_type] = $style_value;
        if ( $item_type == "COLUMNHEADERS" )
            $this->output_header_styles[$style_type] = $style_value;
        if ( $item_type == "GROUPHEADERLABEL" )
            $this->output_group_header_label_styles[$style_type] = $style_value;
        if ( $item_type == "GROUPHEADERVALUE" )
            $this->output_group_header_value_styles[$style_type] = $style_value;
        if ( $item_type == "GROUPTRAILER" )
            $this->output_group_trailer_styles[$style_type] = $style_value;
	}

	// -----------------------------------------------------------------------------
	// Function : lineno
	// -----------------------------------------------------------------------------
	function lineno($group_name = false)
	{
		$result = 0;
		if ( $group_name )
		{
			if ( !array_key_exists($group_name, $this->groupvals) )
				$this->groupvals[$group_name] = 
					array ( "lineno" => 0 );

			if ( $this->changed($group_name) )
				$this->groupvals[$group_name]["lineno"] = 1;
			else
				$this->groupvals[$group_name]["lineno"]++;
			$result = $this->groupvals[$group_name]["lineno"];
		}
		else
			$result = $this->query_count;
		return($result);
	}

	// -----------------------------------------------------------------------------
	// Function : sum
	// -----------------------------------------------------------------------------
	function sum($query_name,$group_name = false)
	{

		$col = get_query_column($query_name, $this->columns ) ;
		if ( !$col )
		{
			handle_error ("The report includes an sum assignment involving a group or column ($query_name) that does not exist within the report");
			return 0;
		}
		$result = $col->column_value;

		if ( $col->old_column_value &&  !$col->reset_flag )
		{
			$result = 
				$col->old_column_value +
				$col->column_value;
		}
		if ( $group_name )
		{
			if ( !array_key_exists($group_name, $col->groupvals) )
				$col->groupvals[$group_name] = 
					array ( "average" => 0,
						"sum" => "0",
						"avgct" => 0,
						"avgsum" => 0,
						"min" => 0,
						"max" => 0 );

			if ( $this->changed($group_name) )
				$col->groupvals[$group_name]["sum"] = $col->column_value;
			else
				$col->groupvals[$group_name]["sum"] += $col->column_value;
			$result = $col->groupvals[$group_name]["sum"];
		}
		else
		{
			if ( $col->reset_flag || !$col->sum)
				$col->sum = $col->column_value;
			else
				$col->sum += $col->column_value;

			$result = $col->sum;
		}

		return $result;
	}

	// -----------------------------------------------------------------------------
	// Function : sum
	// -----------------------------------------------------------------------------
	function solosum($query_name,$group_name = false)
	{

		$col = get_query_column($query_name, $this->columns ) ;
		if ( !$col )
		{
			handle_error ("The report includes an assignment involving a group or column ($query_name) that does not exist within the report");
			return 0;
		}

		$result = $col->column_value;

		if ( $group_name )
		{
			if ( !array_key_exists($group_name, $col->groupvals) )
				$col->groupvals[$group_name] = 
					array ( "average" => 0,
						"sum" => "0",
						"avgct" => 0,
						"avgsum" => 0,
						"min" => 0,
						"max" => 0 );

			if ( $this->changed($group_name) )
				$col->groupvals[$group_name]["sum"] = $col->column_value;
			else
				$col->groupvals[$group_name]["sum"] += $col->column_value;
			$result = $col->groupvals[$group_name]["sum"];
		}
		else
		{
			if ( $col->reset_flag || !$col->sum)
				$col->sum = $col->column_value;
			else
				$col->sum += $col->column_value;

			$result = $col->sum;
		}

		return $result;
	}

	// -----------------------------------------------------------------------------
	// Function : avg
	// -----------------------------------------------------------------------------
	function avg($query_name,$group_name = false)
	{

		$col = get_query_column($query_name, $this->columns ) ;
		if ( !$col )
		{
			handle_error ("The report includes an assignment involving a group or column ($query_name) that does not exist within the report");
			return 0;
		}

		$result = $col->column_value;

		if ( $group_name )
		{
			if ( !array_key_exists($group_name, $col->groupvals) )
				$col->groupvals[$group_name] = 
					array ( "average" => 0,
						"sum" => "0",
						"avgct" => 0,
						"avgsum" => 0,
						"min" => 0,
						"max" => 0 );

			$grpval =& $col->groupvals[$group_name];
			if ( $this->changed($group_name) )
			{
				$grpval["avgct"] = 1;
				$grpval["average"] = $col->column_value;
				$grpval["avgsum"] = $col->column_value;
			}
			else
			{
				$grpval["avgct"]++;
				$grpval["avgsum"] += $col->column_value;
				$grpval["average"] = 
					$grpval["avgsum"] / 
					$grpval["avgct"];
			}
			$result = $grpval["average"];
		}
		else
		{
			if ( $col->reset_flag || !$col->average)
			{
				$col->avgct = 1;
				$col->average = $col->column_value;
				$col->avgsum = $col->column_value;
			}
			else
			{
				$col->avgct++;
				$col->avgsum += $col->column_value;
				$col->average = $col->avgsum / $col->avgct;
			}
			$result = $col->average;
		}

		return $result;
	}

	// -----------------------------------------------------------------------------
	// Function : max
	// -----------------------------------------------------------------------------
	function max($query_name,$group_name = false)
	{

		$col = get_query_column($query_name, $this->columns ) ;
		if ( !$col )
		{
			handle_error ("The report includes an assignment involving a group or column ($query_name) that does not exist within the report");
			return 0;
		}

		$result = $col->column_value;

		if ( $group_name )
		{
			if ( !array_key_exists($group_name, $col->groupvals) )
				$col->groupvals[$group_name] = 
					array ( "average" => 0,
						"sum" => "0",
						"avgct" => 0,
						"avgsum" => 0,
						"min" => 0,
						"max" => 0 );

			$grpval =& $col->groupvals[$group_name];
			if ( $this->changed($group_name) )
			{
				$grpval["max"] = $col->column_value;
			}
			else
			{
				if ( $grpval["max"] < $col->column_value )
					$grpval["max"] = $col->column_value;
			}
			$result = $grpval["max"];
		}
		else
		{
			if ( $col->reset_flag || !$col->maximum)
			{
				$col->maximum = $col->column_value;
			}
			else
				if ( $col->maximum < $col->column_value)
					$col->maximum = $col->column_value;
			$result = $col->maximum;
		}

		return $result;
	}

	// -----------------------------------------------------------------------------
	// Function : min
	// -----------------------------------------------------------------------------
	function min($query_name,$group_name = false)
	{

		$col = get_query_column($query_name, $this->columns ) ;
		if ( !$col )
		{
			handle_error ("The report includes an assignment involving a group or column ($query_name) that does not exist within the report");
			return 0;
		}

		$result = $col->column_value;

		if ( $group_name )
		{
			if ( !array_key_exists($group_name, $col->groupvals) )
				$col->groupvals[$group_name] = 
					array ( "average" => 0,
						"sum" => "0",
						"avgct" => 0,
						"avgsum" => 0,
						"min" => 0,
						"max" => 0 );

			$grpval =& $col->groupvals[$group_name];
			if ( $this->changed($group_name) )
			{
				$grpval["min"] = $col->column_value;
			}
			else
			{
				if ( $grpval["min"] > $col->column_value )
					$grpval["min"] = $col->column_value;
			}
			$result = $grpval["min"];
		}
		else
		{
			if ( $col->reset_flag || !$col->minimum)
			{
				$col->minimum = $col->column_value;
			}
			else
				if ( $col->minimum > $col->column_value)
					$col->minimum = $col->column_value;
			$result = $col->minimum;
		}

		return $result;
	}

	// -----------------------------------------------------------------------------
	// Function : old
	// -----------------------------------------------------------------------------
	function old($query_name)
	{
		$col = get_query_column($query_name, $this->columns ) ;
		if ( !$col )
		{
			handle_error ("The report includes an assignment involving a group or column ($query_name) that does not exist within the report");
			return 0;
		}

		if ( !$col->reset_flag )
			return $col->old_column_value;
		else
			return false;
	}

	// -----------------------------------------------------------------------------
	// Function : imagequery
	// -----------------------------------------------------------------------------
	function imagequery($imagesql, $width=200)
	{

		$conn =& $this->datasource;
	
		//$imagesql = str_replace($imagesql, '"', "'");
		$imagesql = preg_replace("/'/", "\"", $imagesql);
		//$params="driver=".$conn->driver."&dbname=".$conn->database."&hostname=".$conn->host_name;
		$params="dummy=xxx";

        // Link to db image depaends on the framework used. For straight reportico, its a call to the imageget.php
        // file, for Joomla it must go through the Joomla index file
        $imagegetpath = dirname($this->url_path_to_reportico_runner)."/".find_best_url_in_include_path( "imageget.php" );
        if ( $this->framework_parent )
        {
            $imagegetpath = "";
        }

		$forward_url_params = session_request_item('forward_url_get_parameters', $this->forward_url_get_parameters);
        if ( $forward_url_params )
            $params .= "&".$forward_url_params;
        $params .= "&session_name=".reportico_session_name();

		$result = '<img width="'.$width.'" src=\''.$imagegetpath.'?'.$params.'&reportico_call_mode=dbimage&imagesql='.$imagesql.'\'>';

		return $result;
	}

    /**
     * Function generate_dropdown_menu
     *
     * Writes new admin password to the admin config.php 
     */
    function generate_dropdown_menu ( &$menu )
    {
        foreach ( $menu as $k => $v )
        {
            $project = $v["project"];
            $projtitle = "<AUTO>";
            if ( isset ( $v["title"] ) )
                $projtitle = $v["title"];
            $menu[$k]["title"] = sw_translate($projtitle);
            foreach ( $v["items"] as $k1 => $menuitem )
            {
                if ( !isset ($menuitem["reportname"] ) || $menuitem["reportname"] == "<AUTO>" )
                {
                    // Generate Menu from XML files
                    global $g_projpath;
                    $proj_parent = find_best_location_in_include_path( "projects" );
                    $filename = $proj_parent."/".$project."/".$menuitem["reportfile"];
                    if (is_file($filename)) 
                    {
                        $query = false;
                        $repxml = new reportico_xml_reader($query, $filename, false, "ReportTitle");
                        $menu[$k]["items"][$k1]["reportname"] = sw_translate($repxml->search_response);
                    }
                }
            }
        }
    }


}
// -----------------------------------------------------------------------------

/**
 * Class reportico_page_end
 *
 * Handles storage of page footer attributes for PDF report output.
 */
class reportico_page_end extends reportico_object
{
	var	$text = "";
	var	$line = 1;
	var $attributes = array (
		"ColumnStartPDF" => false,
		"justify" => "center",
		"ColumnWidthPDF" => false
		);

	function reportico_page_end($line, $text)
	{
            parent::__construct();
			$this->line = $line;
			$this->text = $text;
	}

}

/**
 * Class reportico_group
 *
 * Identifies a report output group and the associated
 * group  header and footers.
 */
class reportico_group extends reportico_object
{
	var 	$group_name;
	var 	$query;
	var 	$group_column;
	var 	$headers = array();
	var 	$trailers = array();
	var 	$trailer_level_ct = 0;
	var 	$max_level = 0;
	var	$attributes = array(
			"before_header" => "blankline",
			"after_header" => "blankline",
			"before_trailer" => "blankline",
			"after_trailer" => "blankline"
				);

	function reportico_group($in_name, &$in_query)
	{
		reportico_object::reportico_object();

		$this->group_name = $in_name;
		$this->query =& $in_query;

		$this->formats = array(
			"before_header" => "blankline",
			"after_header" => "blankline",
			"before_trailer" => "blankline",
			"after_trailer" => "blankline"
				);
	}

	function add_header(&$in_value_column)
	{
		$this->headers[] = $in_value_column;
	}			

	function add_trailer($in_trailer_column, &$in_value_column)
	{
		if ( !array_key_exists($in_trailer_column, $this->trailers) )
		{
			$this->trailers[$in_trailer_column] =  array();
		}
		$this->trailers[$in_trailer_column][] =& $in_value_column;
		$level = count($this->trailers[$in_trailer_column]) - 1;
		if ( $this->max_level < $level )
			$this->max_level = $level;
	}			

}


class reportico_criteria extends reportico
{
	function reportico_criteria()
	{
	}
	
	
}

/**
 * Class reportico_criteria_column
 *
 * Identifies a criteria item. Holds all the necessary information
 * to allow users to input criteria values including criteria presentation
 * information. Holds database query parameters to criteria selection
 * lists can be generated from the database when the criteria type is LOOKUP
 */
class reportico_criteria_column extends reportico_query_column
{
	var $defaults = array();
	var $defaults_raw = "";
	var $value;
	var $range_start;
	var $range_end;
	var $criteria_type;
	var $_use;
	var $criteria_display;
	var $criteria_help;
	var $expand_display;
	var $list_values = array();
	var	$first_criteria_selection = true;
    
    // For criteria that is linked to in another report
    // Specifies both the report to link to and the criteria item
    // a blank criteria item means all criterias are pulled in
    var $link_to_report = false;
    var $link_to_report_item = false;
	
	var $criteria_types = array (
						"FROMDATE",
						"TODATE",
						"FROMTIME",
						"TOTIME",
						"ANY",
						"NOINPUT",
						"ANYCHAR",
						"TEXTFIELD",
						"ANYINT",
						"LOOKUP",
						"DATERANGE",
						"DATE",
						"SWITCH"
						);

	function reportico_criteria_column
	(
			$query_name,
			$table_name,
			$column_name, 
			$column_type,
			$column_length,
			$column_mask,
			$in_select
	)
	{
		reportico_query_column::reportico_query_column(	
			$query_name,
			$table_name,
			$column_name, 
			$column_type,
			$column_length,
			$column_mask,
			$in_select);
	}

	function set_lookup($table, $return_columns, $display_columns)
	{
	}

	// -----------------------------------------------------------------------------
	// Function : execute_criteria_lookup
	// -----------------------------------------------------------------------------
	function execute_criteria_lookup($in_is_expanding = false, $parent_query = false)
	{
		global $g_code_area;
		require_once("swoutput.php");

		$g_code_area = "Criteria ".$this->query_name;
		$rep = new reportico_report_array();

		$this->lookup_query->rowselection = true;
		$this->lookup_query->set_datasource($this->datasource);
		$this->lookup_query->targets = array();
		$this->lookup_query->add_target($rep);
		$this->lookup_query->build_query($in_is_expanding, $this->query_name);
		if ( $this->lookup_query->datasource->connect() )
		{
			$this->lookup_query->fetch_column_attributes();
			$this->lookup_query->execute_query($this->query_name);
		}
		else
			handle_error( "Error in Connection: ");
		$g_code_area = "";
	}

	// -----------------------------------------------------------------------------
	// Function : criteria_summary_display
    //
    // For a given criteria item that has been checked to identify the values
    // that would be passed to the main query, this returns the summary of user
    // selected values for displaying in the criteria summary at top of report
	// -----------------------------------------------------------------------------
	function criteria_summary_display()
	{
		$text = "";

		$type = $this->criteria_display;

		$value_string = "";

		$params = array();
		$manual_params = array();
		$hidden_params = array();
		$expanded_params = array();
		$manual_override = false;

        if ( get_request_item("MANUAL_".$this->query_name."_FROMDATE", "" ) )
        {
            $this->criteria_summary = get_request_item("MANUAL_".$this->query_name."_FROMDATE","");
            if ( get_request_item("MANUAL_".$this->query_name."_TODATE", "" ) )
            {
                $this->criteria_summary .= "-";
                $this->criteria_summary .= get_request_item("MANUAL_".$this->query_name."_TODATE");
            }
            return;
        }

        if ( get_request_item("HIDDEN_".$this->query_name."_FROMDATE", "" ) )
        {
            $this->criteria_summary = get_request_item("HIDDEN_".$this->query_name."_FROMDATE","");
            if ( get_request_item("HIDDEN_".$this->query_name."_TODATE", "" ) )
            {
                $this->criteria_summary .= "-";
                $this->criteria_summary .= get_request_item("HIDDEN_".$this->query_name."_TODATE");
            }
            return;
        }

		if ( ! array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
			if ( array_key_exists($this->query_name, $_REQUEST) )
			{
					$params = $_REQUEST[$this->query_name];
					if ( !is_array($params) )
						$params = array ( $params );
			}

		$hidden_params = array();
		if ( ! array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
			if ( array_key_exists("HIDDEN_".$this->query_name, $_REQUEST) )
			{
					$hidden_params = $_REQUEST["HIDDEN_".$this->query_name];
					if ( !is_array($hidden_params) )
						$hidden_params = array ( $hidden_params );
			}

		$manual_params = array();
		if ( ! array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
			if ( array_key_exists("MANUAL_".$this->query_name, $_REQUEST) )
			{
				$manual_params = explode(',',$_REQUEST["MANUAL_".$this->query_name]);
				if ( $manual_params )
				{
					$hidden_params = $manual_params;
					$manual_override = true;
				}
			}

		$expanded_params = array();
		if ( array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
		{
				$expanded_params = $_REQUEST["EXPANDED_".$this->query_name];
				if ( !is_array($expanded_params) )
					$expanded_params = array ( $expanded_params );
		}

        if ( $this->criteria_type == "LIST" )
        {
            $checkedct = 0;
                $res =& $this->list_values;
                $text = "";
                if ( !$res )
                {
                    $text = "";
                }
                else
                {
                    reset($res);
                    $k = key($res);
                    for ($i = 0; $i < count($res); $i++ )
                    {
                        $line =&$res[$i];
                        $lab = $res[$i]["label"];
                        $ret = $res[$i]["value"];
                        $checked=false;
            
                        if ( in_array($ret, $params) )
                            $checked = true;
           
                        if ( in_array($ret, $hidden_params) )
                            $checked = true;
          
                        if ( in_array($ret, $expanded_params) )
                            $checked = true;
         
                        if ( $checked )
                        {
                                if ( $checkedct++ )
                                    $text .= ",";
                                $text .=  $lab;
                        }
                    }
                    $this->criteria_summary = $text;
                    return;
                }
        }

        $txt = "";
		$res =& $this->lookup_query->targets[0]->results;
		if ( !$res )
		{
			$res = array();
			$k = 0;
		}
		else
		{
			reset($res);
			$k = key($res);
            $checkedct = 0;
		    for ($i = 0; $i < count($res[$k]); $i++ )
		    {
			    $line =&$res[$i];
			    foreach ( $this->lookup_query->columns as $ky => $col )
			    {
				    if ( $col->lookup_display_flag )
				    {
					    $lab = $res[$col->query_name][$i];
				    }
				    if ( $col->lookup_return_flag )
					    $ret = $res[$col->query_name][$i];
				    if ( $col->lookup_abbrev_flag )
					    $abb = $res[$col->query_name][$i];
			    }
			    $checked=false;

			    if ( in_array($ret, $params) )
				    $checked = true;

			    if ( in_array($ret, $hidden_params) && !$manual_override )
				    $checked = true;

			    if ( in_array($ret, $expanded_params) )
				    $checked = true;

			    if ( in_array($abb, $hidden_params) && $manual_override )
				    $checked = true;

			    if ( $checked )
                {
                        if ( $checkedct++ )
                            $text .= ",";
   					    $text .=  $lab;
                }
		    }
		}

		if ( array_key_exists("EXPAND_".$this->query_name, $_REQUEST) ||
			array_key_exists("EXPANDCLEAR_".$this->query_name, $_REQUEST) ||
			array_key_exists("EXPANDSELECTALL_".$this->query_name, $_REQUEST) ||
			array_key_exists("EXPANDSEARCH_".$this->query_name, $_REQUEST) ||
			$this->criteria_display == "NOINPUT" )
		{
			$tag = $value_string;
			if ( strlen($tag) > 40 )
				$tag = substr($tag, 0, 40)."...";

			if ( !$tag )
				$tag = "ANY";

			$text .= $tag;
		}
		else if ( $this->criteria_display == "ANYCHAR" || $this->criteria_display == "TEXTFIELD" )
		{
			$txt =  $value_string;
		}

        $this->criteria_summary = $text;
	}


	// -----------------------------------------------------------------------------
	// Function : set_criteria_list
	//
	// Generates a criteria list item by taking a string of list labels and values
	// seaprated by commas and each item separated by =
	// -----------------------------------------------------------------------------
	function set_criteria_list($in_list)
	{
		if ( $in_list )
		{
            $choices = array();
            if ( $in_list == "{languages}" )
            {
                $langs = available_languages();
                foreach ( $langs as $k => $v )
                {
                   $choices[] = template_xlate($v["value"])."=".$v["value"];
                }
			    $this->criteria_list = $in_list;
            }
            else
            {
			    $this->criteria_list = $in_list;
			    if ( !is_array($in_list) )
				    $choices = explode(',', $in_list);
            }

			foreach ( $choices as $items )
			{
				$itemval = explode('=', $items);
				if ( count ( $itemval ) > 1 )
				{
					$this->list_values[] = array ( "label" => $itemval[0],
								"value" => $itemval[1] );
				}
			}
		}
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_defaults
	// -----------------------------------------------------------------------------
	function set_criteria_defaults($in_default, $in_delimiter = false)
	{
		if ( !$in_delimiter )
			$in_delimiter = ",";

		$this->defaults_raw = $in_default;
		$this->defaults = preg_split("/".$in_delimiter."/", $this->derive_meta_value($in_default));
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_lookup
	// -----------------------------------------------------------------------------
	function set_criteria_lookup(&$lookup_query)
	{
		$this->lookup_query = $lookup_query;
	}


	// -----------------------------------------------------------------------------
	// Function : set_criteria_type
	// -----------------------------------------------------------------------------
	function set_criteria_type($criteria_type)
	{
		$this->criteria_type = $criteria_type;
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_help
	// -----------------------------------------------------------------------------
	function set_criteria_help($criteria_help)
	{
		$this->criteria_help = $criteria_help;
	}

	// -----------------------------------------------------------------------------
	// Function : set_criteria_link
	// -----------------------------------------------------------------------------
	function set_criteria_link_report($in_report, $in_report_item)
	{
		$this->link_to_report = $in_report;
		$this->link_to_report_item = $in_report_item;
	}


	// -----------------------------------------------------------------------------
	// Function : set_criteria_input
	// -----------------------------------------------------------------------------
	function set_criteria_input($in_source, $in_display, $in_expand_display = false, $use = "")
	{
		$this->criteria_type = $in_source;
		$this->criteria_display = $in_display;
		$this->expand_display = $in_expand_display;
		$this->_use = $use;
	}


	// -----------------------------------------------------------------------------
	// Function : collate_request_date
	// -----------------------------------------------------------------------------
	function collate_request_date($in_query_name, $in_tag, $in_default, $in_format)
	{
		$retval = $in_default;
		if ( array_key_exists($this->query_name."_".$in_tag."_DAY", $_REQUEST) )
		{
            if ( !class_exists("DateTime", false ) )
            {
                handle_error("This version of PHP does not have the DateTime class. Must be PHP >= 5.3 to use date criteria");
                return $retval;
            }
			$dy = $_REQUEST[$this->query_name."_".$in_tag."_DAY"];
			$mn = $_REQUEST[$this->query_name."_".$in_tag."_MONTH"] + 1;
			$yr = $_REQUEST[$this->query_name."_".$in_tag."_YEAR"];
			$retval = sprintf("%02d-%02d-%04d", $dy, $mn, $yr);

			$datetime = DateTime::createFromFormat("d-m-Y", $retval);
			$in_format = get_locale_date_format ( $in_format );
			$retval =$datetime->format ( $in_format );
		}
		return($retval);
	}

	// -----------------------------------------------------------------------------
	// Function : date_display
	// -----------------------------------------------------------------------------
	function & date_display()
	{

		$text = "";
		$this->range_start = $this->range_end = "";
		$this->range_start = $this->column_value;

		if ( !array_key_exists("clearform", $_REQUEST) && array_key_exists("MANUAL_".$this->query_name."_FROMDATE", $_REQUEST) )
		{
			$this->range_start = $_REQUEST["MANUAL_".$this->query_name."_FROMDATE"];
			$this->range_start = $this->collate_request_date($this->query_name, "FROMDATE", $this->range_start, SW_PREP_DATEFORMAT);
		}
		else
		if ( !array_key_exists("clearform", $_REQUEST) && array_key_exists("HIDDEN_".$this->query_name."_FROMDATE", $_REQUEST) )
		{
			$this->range_start = $_REQUEST["HIDDEN_".$this->query_name."_FROMDATE"];
			$this->range_start = $this->collate_request_date($this->query_name, "FROMDATE", $this->range_start, SW_PREP_DATEFORMAT);
		}
		else
		{
			if ( count($this->defaults) == 0 )
			{
				$this->defaults[0] = "TODAY";
			}
			if ( $this->defaults[0] )
			{
                $dummy="";
                if ( !convert_date_range_defaults_to_dates("DATE", $this->defaults[0], $this->range_start, $dummy) )
                    trigger_error( "Date default '".$this->defaults[0]."' is not a valid date. Should be in date format (e.g. yyyy-mm-dd, dd/mm/yyyy) or a date type (TODAY, TOMMORROW etc", E_USER_ERROR );
			}
            unset ( $_REQUEST["HIDDEN_".$this->query_name."_FROMDATE"] );
            unset ( $_REQUEST["HIDDEN_".$this->query_name."_TODATE"] );
		}

		$this->range_start = parse_date($this->range_start,false, SW_PREP_DATEFORMAT);
		$text .= $this->format_date_value($this->query_name.'_FROMDATE', $this->range_start, SW_PREP_DATEFORMAT );

		return $text;

	}

	// -----------------------------------------------------------------------------
	// Function : daterange_display
	// -----------------------------------------------------------------------------
	function & daterange_display()
	{

		$text = "";
		$this->range_start = $this->range_end = "";

		if ( !array_key_exists("clearform", $_REQUEST) && array_key_exists("MANUAL_".$this->query_name."_FROMDATE", $_REQUEST) )
		{

			$this->range_start = $_REQUEST["MANUAL_".$this->query_name."_FROMDATE"];
			$this->range_start = $this->collate_request_date($this->query_name, "FROMDATE", $this->range_start, SW_PREP_DATEFORMAT);
		}
		else
		if ( !array_key_exists("clearform", $_REQUEST) && array_key_exists("HIDDEN_".$this->query_name."_FROMDATE", $_REQUEST) )
		{
			$this->range_start = $_REQUEST["HIDDEN_".$this->query_name."_FROMDATE"];
			$this->range_start = $this->collate_request_date($this->query_name, "FROMDATE", $this->range_start, SW_PREP_DATEFORMAT);
		}
		else
		{
            // User reset form or first time in, set defaults and clear existing form info
			if ( count($this->defaults) == 0 )
				$this->defaults[0] = "TODAY-TODAY";

			if ( $this->defaults[0] )
			{
                if ( !convert_date_range_defaults_to_dates("DATERANGE", $this->defaults[0], $this->range_start, $this->range_end) )
                    trigger_error( "Date default '".$this->defaults[0]."' is not a valid date range. Should be 2 values separated by '-'. Each one should be in date format (e.g. yyyy-mm-dd, dd/mm/yyyy) or a date type (TODAY, TOMMORROW etc", E_USER_ERROR );

                unset ( $_REQUEST["MANUAL_".$this->query_name."_FROMDATE"] );
                unset ( $_REQUEST["MANUAL_".$this->query_name."_TODATE"] );
                unset ( $_REQUEST["HIDDEN_".$this->query_name."_FROMDATE"] );
                unset ( $_REQUEST["HIDDEN_".$this->query_name."_TODATE"] );
			}
		}

        if ( !$this->range_start )
            $this->range_end = "TODAY";

		$this->range_start = parse_date($this->range_start, false, SW_PREP_DATEFORMAT);
		$text .= $this->format_date_value($this->query_name.'_FROMDATE', $this->range_start, SW_PREP_DATEFORMAT );

		$text .= "&nbsp;- ";

		if ( array_key_exists("MANUAL_".$this->query_name."_TODATE", $_REQUEST) )
		{
			$this->range_end = $_REQUEST["MANUAL_".$this->query_name."_TODATE"];
			$this->range_end = $this->collate_request_date($this->query_name, "TODATE", $this->range_end, SW_PREP_DATEFORMAT);
		}
		else if ( array_key_exists("HIDDEN_".$this->query_name."_TODATE", $_REQUEST) )
		{
			$this->range_end = $_REQUEST["HIDDEN_".$this->query_name."_TODATE"];
			$this->range_end = $this->collate_request_date($this->query_name, "TODATE", $this->range_end, SW_PREP_DATEFORMAT);
		}

        if ( !$this->range_end )
            $this->range_end = "TODAY";

		$this->range_end = parse_date($this->range_end, false, SW_PREP_DATEFORMAT);
		$text .= $this->format_date_value($this->query_name.'_TODATE', $this->range_end, SW_PREP_DATEFORMAT);
		return $text;
	}

	// -----------------------------------------------------------------------------
	// Function : format_date_value
	// -----------------------------------------------------------------------------
	function format_date_value($in_tag, $in_value, $in_label)
	{

		$text = "";

        if ( !$in_value )
            return $text;

		$in_label = get_locale_date_format ( $in_label );


 

		$dy_tag = $in_tag."_DAY";
		$mn_tag = $in_tag."_MONTH";
		$yr_tag = $in_tag."_YEAR";

		$tag = "";
		$tag .= '<input  type="hidden" name="HIDDEN_'.$in_tag.'"';
		$tag .= ' size="'.($this->column_length).'"';
		$tag .= ' maxlength="'.$this->column_length.'"';
		$tag .= ' value="'.$in_value.'">';
		$text .= $tag;

		if ( AJAX_ENABLED )
		{
			$tag = "";

       			if ( preg_match ( "/TODATE/", $in_tag ) )
              			$tag .= "";
			$tag .= '<input  class="swDateField" id="swDateField_'.$in_tag.'" type="text" name="MANUAL_'.$in_tag.'"';
			$tag .= ' size="20"';
			$tag .= ' maxlength="20"';
			$tag .= ' value="'.$in_value.'">';
			$text .= $tag;
			return $text;
		}

		switch ( $this->criteria_display )
		{
			case "YMDFIELD":
			case "MDYFIELD":
			case "DMYFIELD":
			case "DMYFORM":

				$dyinput = '<SELECT name="'.$dy_tag.'">';
				for ( $ct = 1; $ct <= 31; $ct++ )
				{
					$checked="";
					if ( $ct == (int)$dy )
						$checked="selected";
		
					$dyinput .= '<OPTION '.$checked.' label="'.$ct.'" value="'.$ct.'">'.$ct.'</OPTION>';
				}
				$dyinput .= '</SELECT>';
		
				$mtinput = '<SELECT name="'.$mn_tag.'">';
				$cal = array  ( sw_translate('January'), sw_translate('February'), sw_translate('March'), sw_translate('April'), sw_translate('May'), sw_translate('June'),
					sw_translate('July'), sw_translate('August'), sw_translate('September'), sw_translate('October'), sw_translate('November'), sw_translate('December') );
				for ( $ct = 0; $ct <= 11; $ct++ )
				{
					$checked="";
					if ( $ct == $mn - 1 )
						$checked="selected";
		
					$mtinput .= '<OPTION '.$checked.' label="'.$cal[$ct].'" value="'.$ct.'">'.$cal[$ct].'</OPTION>';
				}
				$mtinput .= '</SELECT>';
		
				$yrinput = '<SELECT name="'.$yr_tag.'">';
				for ( $ct = 2000; $ct <= 2020; $ct++ )
				{
					$checked="";
					if ( $ct == $yr )
						$checked="selected";
		
					$yrinput .= '<OPTION '.$checked.' label="'.$ct.'" value="'.$ct.'">'.$ct.'</OPTION>';
				}
				$yrinput .= '</SELECT>';

				switch ( $this->criteria_display )
				{
					case "YMDFIELD":
						$text .= $yrinput . $mtinput . $dyinput;
						break;

					case "MDYFIELD":
						$text .= $mtinput . $dyinput . $yrinput;
						break;

					case "DMYFIELD":
					case "DMYFORM":
					default:
						$text .= $dyinput . $mtinput . $yrinput;
						break;
				}

				break;

				default:
					$tag = "";

					if ( preg_match ( "/TODATE/", $in_tag ) )
						$tag .= "";
						$tag .= '<input  type="text" name="MANUAL_'.$in_tag.'"';
						$tag .= ' size="20"';
					//$tag .= ' maxlength="'.$this->column_length.'"';
						$tag .= ' maxlength="20"';
						$tag .= ' value="'.$in_value.'">';
						$text .= $tag;


		}
		return $text;
		
	}

	// -----------------------------------------------------------------------------
	// Function : list_display
	// -----------------------------------------------------------------------------
	function & list_display($in_is_expanding)
	{
		$text = "";
		if ( $in_is_expanding )
		{	
			$tag_pref = "EXPANDED_";
			$type = $this->expand_display;
		}
		else
		{	
			$tag_pref = "";
			$type = $this->criteria_display;
		}

		$value_string = "";

		$params = array();
		$manual_params = array();
		$hidden_params = array();
		$expanded_params = array();

		if ( !array_key_exists("clearform", $_REQUEST) )
		{
			if ( ! array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
				if ( array_key_exists($this->query_name, $_REQUEST) )
				{
						$params = $_REQUEST[$this->query_name];
						if ( !is_array($params) )
							$params = array ( $params );
				}

			$hidden_params = array();
			if ( ! array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
				if ( array_key_exists("HIDDEN_".$this->query_name, $_REQUEST) )
				{
						$hidden_params = $_REQUEST["HIDDEN_".$this->query_name];
						if ( !is_array($hidden_params) )
							$hidden_params = array ( $hidden_params );
				}

			$manual_params = array();
			if ( ! array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
				if ( array_key_exists("MANUAL_".$this->query_name, $_REQUEST) )
				{
					$manual_params = explode(',',$_REQUEST["MANUAL_".$this->query_name]);
					if ( $manual_params )
						$hidden_params = $manual_params;
				}

			// If this is first time into screen and we have defaults then
			// use these instead
			if ( !$params && !$hidden_params && get_reportico_session_param("firstTimeIn") )
			{
				$hidden_params = $this->defaults;
				$manual_params = $this->defaults;
			}

			$expanded_params = array();
			if ( array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
			{
					$expanded_params = $_REQUEST["EXPANDED_".$this->query_name];
					if ( !is_array($expanded_params) )
						$expanded_params = array ( $expanded_params );
			}
		}
		else
		{
			$hidden_params = $this->defaults;
			$manual_params = $this->defaults;
		}

		switch ( $type )
		{
				case "NOINPUT":
				case "ANYCHAR":
				case "TEXTFIELD":
 						$text .= '<SELECT style="display:none" name="'."HIDDEN_".$this->query_name.'[]" size="1" multiple>';
						$text .= '<OPTION selected label="ALL" value="(ALL)">ALL</OPTION>';
						//break;

				case "MULTI":
						$res =& $this->lookup_query->targets[0]->results;
						$k = key($res);
						$multisize = 4;
						if ( $res && count($res[$k]) > 4 )
							$multisize = count($res[$k]);
                        if ( isset ( $res[$k] ) )
						    if ( count($res[$k]) >= 10 )
							    $multisize = 10;
 						$text .= '<SELECT class="swPrpDropSelect" name="'.$tag_pref.$this->query_name.'[]" size="'.$multisize.'" multiple>';
						break;

				case "CHECKBOX":
				case "RADIO":
						break;

				default:
 						$text .= '<SELECT class="swPrpDropSelectRegular" name="'.$tag_pref.$this->query_name.'">';
						break;
		}

		$check_text = "";
		switch ( $type )
		{
			case "MULTI":
			case "DROPDOWN":
			case "ANYCHAR":
			case "TEXTFIELD":
			case "NOINPUT":
				$check_text = "selected";
				break;

			default:
				$check_text = "checked";
				break;
		}

		// If clear has been pressed we dont want any list items selected
		if ( $this->submitted('EXPANDCLEAR_'.$this->query_name) ) 
			$check_text = "";
			
		// If select all has been pressed we want all highlighted
		$selectall = false;
		if ( $this->submitted('EXPANDSELECTALL_'.$this->query_name) ) 
			$selectall = true;

		$res =& $this->list_values;
		if ( !$res )
		{
			$res = array();
			$k = 0;
		}
		else
		{
			reset($res);
			$k = key($res);
		for ($i = 0; $i < count($res); $i++ )
		{
			$line =&$res[$i];
			$lab = $res[$i]["label"];
			$ret = $res[$i]["value"];
			$checked="";

			if ( in_array($ret, $params) )
				$checked = $check_text;

			if ( in_array($ret, $hidden_params) )
				$checked = $check_text;

			if ( in_array($ret, $expanded_params) )
				$checked = $check_text;

			if ( $selectall )
				$checked = $check_text;

			if ( $checked != "" )
				if ( !$value_string )
					$value_string = $lab;
				else
					$value_string .= ",".$lab;

			switch ( $type )
			{
				case "MULTI":
					$text .= '<OPTION label="'.$lab.'" value="'.$ret.'" '.$checked.'>'.$lab.'</OPTION>';
					break;

				case "RADIO":
    				$text .= '<INPUT type="radio" name="'.$tag_pref.$this->query_name.'" value="'.$ret.'" '.$checked.'>'.sw_translate($lab).'<BR>';
					break;

				case "CHECKBOX":
    					$text .= '<INPUT type="checkbox" name="'.$tag_pref.$this->query_name.'[]" value="'.$ret.'" '.$checked.'>'.sw_translate($lab).'<BR>';
					break;

				default:
   					$text .= '<OPTION label="'.$lab.'" value="'.$ret.'" '.$checked.'>'.$lab.'</OPTION>';
					break;
				}

			}
		}

		switch ( $type )
		{
				case "MULTI":
 						$text .= '</SELECT>';
						break;

				case "CHECKBOX":
				case "RADIO":
						break;

				default:
 						$text .= '</SELECT>';
						break;
		}

		if ( !$in_is_expanding )
		{
		
			if ( array_key_exists("EXPAND_".$this->query_name, $_REQUEST) ||
				array_key_exists("EXPANDCLEAR_".$this->query_name, $_REQUEST) ||
				array_key_exists("EXPANDSELECTALL_".$this->query_name, $_REQUEST) ||
				array_key_exists("EXPANDSEARCH_".$this->query_name, $_REQUEST) ||
				$this->criteria_display == "NOINPUT" )
			//if ( $this->criteria_display == "NOINPUT" )
			{
				$tag = $value_string;
				if ( strlen($tag) > 40 )
					$tag = substr($tag, 0, 40)."...";
	
				if ( !$tag )
					$tag = "ANY";
	
				$text .= '<br>'.$tag;
			}
			else if ( $this->criteria_display == "ANYCHAR" || $this->criteria_display == "TEXTFIELD" )
			{
				$tag = "";
				$tag .= '<br><input  class="swPrpTextField" type="text" name="MANUAL_'.$this->query_name.'"';
				$tag .= ' size="50%"';
				$tag .= ' value="'.$value_string.'">';
				$tag .= '<br>';
				$text .= $tag;
			}
		}

		return $text;
	}
	// -----------------------------------------------------------------------------
	// Function : lookup_display
	// -----------------------------------------------------------------------------
	function & lookup_display($in_is_expanding)
	{

		$text = "";
		if ( $in_is_expanding )
		{	
			$tag_pref = "EXPANDED_";
			$type = $this->expand_display;
		}
		else
		{	
			$tag_pref = "";
			$type = $this->criteria_display;
		}

		$value_string = "";

		$params = array();
		$manual_params = array();
		$hidden_params = array();
		$expanded_params = array();
		$manual_override = false;

		if ( !array_key_exists("clearform", $_REQUEST) )
		{
			if ( ! array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
				if ( array_key_exists($this->query_name, $_REQUEST) )
				{
						$params = $_REQUEST[$this->query_name];
						if ( !is_array($params) )
							$params = array ( $params );
				}

			$hidden_params = array();
			if ( ! array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
				if ( array_key_exists("HIDDEN_".$this->query_name, $_REQUEST) )
				{
						$hidden_params = $_REQUEST["HIDDEN_".$this->query_name];
						if ( !is_array($hidden_params) )
							$hidden_params = array ( $hidden_params );
				}

			$manual_params = array();
			if ( ! array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
				if ( array_key_exists("MANUAL_".$this->query_name, $_REQUEST) )
				{
					$manual_params = explode(',',$_REQUEST["MANUAL_".$this->query_name]);
					if ( $manual_params )
					{
						$hidden_params = $manual_params;
						$manual_override = true;
					}
				}

			// If this is first time into screen and we have defaults then
			// use these instead
			if ( !$hidden_params && get_reportico_session_param("firstTimeIn") )
			{
				$hidden_params = $this->defaults;
				$manual_params = $this->defaults;
			}

			$expanded_params = array();
			if ( array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) )
			{
					$expanded_params = $_REQUEST["EXPANDED_".$this->query_name];
					if ( !is_array($expanded_params) )
						$expanded_params = array ( $expanded_params );
			}
		}
		else
		{
			$hidden_params = $this->defaults;
			$manual_params = $this->defaults;
			$params = $this->defaults;
		}

		switch ( $type )
		{
				case "NOINPUT":
				case "ANYCHAR":
				case "TEXTFIELD":
 						$text .= '<SELECT style="display:none" name="'."HIDDEN_".$this->query_name.'[]" size="0" multiple>';
						break;

				case "MULTI":
						$multisize = 12;
						$res =& $this->lookup_query->targets[0]->results;
						$k = key($res);
						$multisize = 4;
						if ( $res && count($res[$k]) > 4 )
							$multisize = count($res[$k]);
                        if ( isset ( $res[$k] ) )
						    if ( count($res[$k]) >= 10 )
							    $multisize = 10;
						if ( $in_is_expanding )
							$multisize = 12;
						$text .= '<SELECT class="swPrpDropSelect" name="'.$tag_pref.$this->query_name.'[]" size="'.$multisize.'" multiple>';
						break;

				case "CHECKBOX":
				case "RADIO":
						break;

				default:
 						$text .= '<SELECT class="swPrpDropSelectRegular" name="'.$tag_pref.$this->query_name.'">';
						break;
		}

		$check_text = "";
		switch ( $type )
		{
			case "MULTI":
			case "DROPDOWN":
			case "ANYCHAR":
			case "TEXTFIELD":
			case "NOINPUT":
				$check_text = "selected";
				break;

			default:
				$check_text = "checked";
				break;
		}

		// If clear has been pressed we dont want any list items selected
		if ( $this->submitted('EXPANDCLEAR_'.$this->query_name) ) 
			$check_text = "";
			
		// If select all has been pressed we want all highlighted
		$selectall = false;
		if ( $this->submitted('EXPANDSELECTALL_'.$this->query_name) ) 
			$selectall = true;

		$res =& $this->lookup_query->targets[0]->results;
		if ( !$res )
		{
			$res = array();
			$k = 0;
		}
		else
		{
			reset($res);
			$k = key($res);
		for ($i = 0; $i < count($res[$k]); $i++ )
		{
			$line =&$res[$i];
			foreach ( $this->lookup_query->columns as $ky => $col )
			{
				if ( $col->lookup_display_flag )
				{
					$lab = $res[$col->query_name][$i];
				}
				if ( $col->lookup_return_flag )
					$ret = $res[$col->query_name][$i];
				if ( $col->lookup_abbrev_flag )
					$abb = $res[$col->query_name][$i];
				
			}
       			//$text .= '<OPTION label="'.$ret.'" value="'.$ret.'">'.$lab.'</OPTION>';
			$checked="";

			if ( in_array($ret, $params) )
			{
				$checked = $check_text;
			}

			if ( in_array($ret, $hidden_params) && !$manual_override )
			{
				$checked = $check_text;
			}

			if ( in_array($ret, $expanded_params) )
			{
				$checked = $check_text;
			}

			if ( in_array($abb, $hidden_params) && $manual_override )
			{
				$checked = $check_text;
			}

			if ( $selectall )
			{
				$checked = $check_text;
			}

			if ( $checked != "" )
				if ( !$value_string )
					$value_string = $abb;
				else
					$value_string .= ",".$abb;

			switch ( $type )
			{
				case "MULTI":
   					$text .= '<OPTION label="'.$lab.'" value="'.$ret.'" '.$checked.'>'.$lab.'</OPTION>';
					break;

				case "RADIO":
    				$text .= '<INPUT type="radio" name="'.$tag_pref.$this->query_name.'" value="'.$ret.'" '.$checked.'>'.$lab.'<BR>';
					break;

				case "CHECKBOX":
    					$text .= '<INPUT type="checkbox" name="'.$tag_pref.$this->query_name.'[]" value="'.$ret.'" '.$checked.'>'.$lab.'<BR>';
					break;

				default:
                    if ( $i == 0 )
			            $text .= '<OPTION label="" value=""></OPTION>';
   					$text .= '<OPTION label="'.$lab.'" value="'.$ret.'" '.$checked.'>'.$lab.'</OPTION>';
					break;
				}

		}
		}

		switch ( $type )
		{
				case "MULTI":
 						$text .= '</SELECT>';
						break;

				case "CHECKBOX":
				case "RADIO":
						break;

				default:
 						$text .= '</SELECT>';
						break;
		}

		if ( !$in_is_expanding )
		{
		
			if ( array_key_exists("EXPAND_".$this->query_name, $_REQUEST) ||
				array_key_exists("EXPANDCLEAR_".$this->query_name, $_REQUEST) ||
				array_key_exists("EXPANDSELECTALL_".$this->query_name, $_REQUEST) ||
				array_key_exists("EXPANDSEARCH_".$this->query_name, $_REQUEST) ||
				$this->criteria_display == "NOINPUT" )
			//if ( $this->criteria_display == "NOINPUT" )
			{
				$tag = $value_string;
				if ( strlen($tag) > 40 )
					$tag = substr($tag, 0, 40)."...";
	
				if ( !$tag )
					$tag = "ANY";
	
				$text .= $tag;
			}
			else if ( $this->criteria_display == "ANYCHAR" || $this->criteria_display == "TEXTFIELD" )
			{
				if ( $manual_override && !$value_string )
                {
					$value_string = $_REQUEST["MANUAL_".$this->query_name];
                }

				$tag = "";
				$tag .= '<input  type="text" class="swPrpTextField" name="MANUAL_'.$this->query_name.'"';
				$tag .= ' value="'.$value_string.'">';
				$text .= $tag;
			}
		}

		return $text;
	}


	// -----------------------------------------------------------------------------
	// Function : get_criteria_value
	// -----------------------------------------------------------------------------
	function get_criteria_value($in_type, $use_del = true)
	{

		$cls = "";
		switch($in_type)
		{
				case "RANGE1":
						$cls = $this->get_criteria_clause(false, false, false, true, false, $use_del);
						break;

				case "RANGE2":
						$cls = $this->get_criteria_clause(false, false, false, false, true, $use_del);
						break;
				case "FULL" :
						$cls = $this->get_criteria_clause(true, true, true, false, false, $use_del);
						break;

				case "VALUE" :
						$cls = $this->get_criteria_clause(false, false, true, false, false, $use_del);
						break;

				default :
						handle_error( "Unknown Criteria clause type $in_type for criteria ".$this->query_name);
						break;
		}
		return $cls;
	}

	// -----------------------------------------------------------------------------
	// Function : get_criteria_clause
	// -----------------------------------------------------------------------------
	function get_criteria_clause($lhs = true, $operand = true, $rhs = true, $rhs1 = false, $rhs2 = false, $add_del = true)
	{

		$cls = "";

		if ( $this -> _use == "SHOW/HIDE-and-GROUPBY") $add_del = false;

		if ( $this->column_value == "(ALL)" )
			return $cls;

		if ( $this->column_value == "(NOTFOUND)" )
		{
			$cls = " AND 1 = 0";
			return $cls;
		}

		if ( !$this->column_value ) 
		{
			return ($cls);
		}

		$del = '';

		switch($this->criteria_type)
		{

			case "ANY":
			case "ANYCHAR":
			case "TEXTFIELD":
				if ( $add_del )
					$del = $this->get_value_delimiter();

				$extract= explode(',', $this->column_value);
				if ( is_array($extract) )
				{
					$ct = 0;
					foreach ( $extract as $col )
					{
						if ( is_string($col) )
						{
							$col = trim($col);
						}
	
						if ( !$col )
							continue;

						if ( $col == "(ALL)" )
						{
							continue;
						}

						if ( $ct == 0 )
						{
							if ( $lhs )
							{
								//$cls .= " XX".$this->table_name.".".$this->column_name;
								$cls .= " AND ".$this->column_name;
							}
							if ( $rhs )
							{
								if ( $operand )
									$cls .= " IN (";
								$cls .= $del.$col.$del;
							}
						}
						else
							if ( $rhs )
								$cls .= ",".$del.$col.$del;
						$ct++;
					}

					if ( $ct > 0 && $rhs )
						if ( $operand )
							$cls .= " )";
				}
				else
				{
					if ( $lhs )
					{
						if ( $this->table_name  && $this->column_name )
							$cls .= " AND ".$this->table_name.".".$this->column_name;
						else 
							if ( $this->column_name )
								$cls .= " AND ".$this->column_name;
					}
					if ( $rhs )
						if ( $operand )
							$cls .= " =".$del.$this->column_value.$del;
						else
							$cls .= $del.$this->column_value.$del;
				}
				break;

			case "LIST":
				if ( $add_del )
					$del = $this->get_value_delimiter();

				if ( !is_array($this->column_value) )
					$this->column_value = explode(',', $this->column_value);

				if ( is_array($this->column_value) )
				{
					$ct = 0;
					foreach ( $this->column_value as $col )
					{
						if ( is_string($col) )
						{
							$col = trim($col);
						}

						if ( $col == "(ALL)" )
						{
							continue;
						}

						if ( $ct == 0 )
						{
							if ( $lhs )
							{
								if ( $this->table_name  && $this->column_name )
									$cls .= " AND ".$this->table_name.".".$this->column_name;
								else 
									if ( $this->column_name )
										$cls .= " AND ".$this->column_name;
							}
							if ( $rhs )
							{
								if ( $operand )
									$cls .= " IN (";
								$cls .= $del.$col.$del;
							}
						}
						else
							if ( $rhs )
								$cls .= ",".$del.$col.$del;
						$ct++;
					}

					if ( $ct > 0 )
						if ( $operand )
							$cls .= " )";
				}
				else
				{
					if ( $lhs )
					{
						if ( $this->table_name  && $this->column_name )
							$cls .= " AND ".$this->table_name.".".$this->column_name;
						else 
							if ( $this->column_name )
								$cls .= " AND ".$this->column_name;
					}
					if ( $rhs )
					{
						if ( $operand )
							$cls .= " =".$del.$this->column_value.$del;
						else
							$cls .= $del.$this->column_value.$del;
					}
				}
				break;
				
			case "DATE":
				$cls = "";
				if ( $this->column_value )
				{
					$val1 = parse_date($this->column_value, false, SW_PREP_DATEFORMAT);
					$val1 = convertYMDtoLocal($val1, SW_PREP_DATEFORMAT, SW_DB_DATEFORMAT);
					if ( $lhs )
					{
						if ( $this->table_name  && $this->column_name )
							$cls .= " AND ".$this->table_name.".".$this->column_name;
						else 
							if ( $this->column_name )
								$cls .= " AND ".$this->column_name;
					}
					if ( $add_del )
						$del = $this->get_value_delimiter();

					if ( $rhs )
					{
						if ( $operand )
							$cls .= " = ";
						$cls .= $del.$val1.$del;
					}
				}
				break;
				
			case "DATERANGE":
				$cls = "";
				if ( $this->column_value )
				{
                    // If daterange value here is a range in a single value then its been
                    // run directly from command line and needs splitting up using "-"


					$val1 = parse_date($this->column_value,false, SW_PREP_DATEFORMAT);
					$val2 = parse_date($this->column_value2,false, SW_PREP_DATEFORMAT);
					$val1 = convertYMDtoLocal($val1, SW_PREP_DATEFORMAT, SW_DB_DATEFORMAT);
					$val2 = convertYMDtoLocal($val2, SW_PREP_DATEFORMAT, SW_DB_DATEFORMAT);
					if ( $lhs )
					{	
						if ( $this->table_name  && $this->column_name )
							$cls .= " AND ".$this->table_name.".".$this->column_name;
						else 
							if ( $this->column_name )
								$cls .= " AND ".$this->column_name;
					}

					if ( $add_del )
						$del = $this->get_value_delimiter();
					if ( $rhs )
					{
						$cls .= " BETWEEN ";
						//$cls .= $del.$this->column_value.$del;
						$cls .= $del.$val1.$del;
						$cls .= " AND ";
						//$cls .= $del.$this->column_value2.$del;
						$cls .= $del.$val2.$del;
					}
					if ( $rhs1 )
					{
						$cls = $del.$val1.$del;
					}
					if ( $rhs2 )
					{
						$cls = $del.$val2.$del;
					}
				}
				break;
				
			case "LOOKUP":
				if ( $add_del )
					$del = $this->get_value_delimiter();

				if ( !is_array($this->column_value) )
					$this->column_value = explode(',', $this->column_value);

				if ( is_array($this->column_value) )
				{
					$ct = 0;
					foreach ( $this->column_value as $col )
					{
						if ( is_string($col) )
						{
							$col = trim($col);
						}

						if ( $col == "(ALL)" )
						{
							continue;
						}

						if ( $ct == 0 )
						{
							if ( $lhs )
							{
								if ( $this->table_name  && $this->column_name )
									$cls .= " AND ".$this->table_name.".".$this->column_name;
								else 
									if ( $this->column_name )
										$cls .= " AND ".$this->column_name;
							}
							if ( $rhs )
							{
								if ( $operand )
									$cls .= " IN (";
								$cls .= $del.$col.$del;
							}
						}
						else
							if ( $rhs )
								$cls .= ",".$del.$col.$del;
						$ct++;
					}

					if ( $ct > 0 )
						if ( $operand )
							$cls .= " )";
				}
				else
				{
					if ( $lhs )
					{
						if ( $this->table_name  && $this->column_name )
							$cls .= " AND ".$this->table_name.".".$this->column_name;
						else 
							if ( $this->column_name )
								$cls .= " AND ".$this->column_name;
					}
					if ( $rhs )
					{
						if ( $operand )
							$cls .= " =".$del.$this->column_value.$del;
						else
							$cls .= $del.$this->column_value.$del;
					}
				}
				break;
				
			default:
				break;
		}

		return($cls);
	}

	function & expand_template()
	{
	 	$text = "";

		if ( $this->submitted('EXPANDSEARCH_'.$this->query_name) ) 
				$dosearch = true;

		// Only use then expand value if Search was press
		$expval="";
		if ( $this->submitted('EXPANDSEARCH_'.$this->query_name) )
			if ( array_key_exists("expand_value", $_REQUEST) )
				$expval=$_REQUEST["expand_value"];

		$type = $this->criteria_type;
		if ( $this->expand_display == "ANYCHAR" )
			$type = $this->expand_display;
		if ( $this->expand_display == "TEXTFIELD" )
			$type = $this->expand_display;

		switch($type)
		{
			case "LIST":
				$text .= $this->list_display(true);
				break;

			case "LOOKUP":
				$this->execute_criteria_lookup(true);
				$text .= $this->lookup_display(true);
				break;

			case "DATE":
				$text .= $this->date_display(true);
				break;

			case "DATERANGE":
				$text .= $this->daterange_display(true);
				break;

			case "ANYCHAR":
			case "TEXTFIELD":
				$tag = "";
				$tag .= '<input  type="text" name="EXPANDED_'.$this->query_name.'"';
				$tag .= ' size="'.($this->column_length).'"';
				$tag .= ' maxlength="'.$this->column_length.'"';
				$tag .= ' value="'.$this->column_value.'">';
				$text .= $tag;

				break;
				
			default:
				break;
		}

		return $text;
	}

	function & expand()
	{
	 	$text = "";
		$text .= template_xlate("Search")." ";
		$text .= $this->derive_attribute("column_title", $this->query_name);
		$text .= " :<br>";

		$tag = "";
		$tag .= '<input  type="text" name="expand_value"';
		$tag .= ' size="30"';

		if ( $this->submitted('EXPANDSEARCH_'.$this->query_name) ) 
				$dosearch = true;

		// Only use then expand value if Search was press
		$expval="";
		if ( $this->submitted('EXPANDSEARCH_'.$this->query_name) )
			if ( array_key_exists("expand_value", $_REQUEST) )
				$expval=$_REQUEST["expand_value"];

		$tag .= ' value="'.$expval.'">';
		$text .= $tag;
		$text .= '<input class="swPrpSubmit" type="submit" name="EXPANDSEARCH_'.$this->query_name.'" value="'.template_xlate("Search").'">';
		$text .= "<br>";


		$type = $this->criteria_type;
		if ( $this->expand_display == "ANYCHAR" )
			$type = $this->expand_display;
		if ( $this->expand_display == "TEXTFIELD" )
			$type = $this->expand_display;

		$text .= '<DIV id="hello" style="visibility:hide">';
		$text .= '</DIV>';
		switch($type)
		{
			case "LIST":
				$text .= $this->list_display(true);
				break;

			case "LOOKUP":
				$this->execute_criteria_lookup(true);
				$text .= $this->lookup_display(true);
				break;

			case "DATE":
				$text .= $this->date_display(true);
				break;

			case "DATERANGE":
				$text .= $this->daterange_display(true);
				break;

			case "ANYCHAR":
			case "TEXTFIELD":
				//ECHO $TAG;
				$tag = "";
				$tag .= '<input  type="text" name="EXPANDED_'.$this->query_name.'"';
				$tag .= ' size="'.($this->column_length).'"';
				$tag .= ' maxlength="'.$this->column_length.'"';
				$tag .= ' value="'.$this->column_value.'">';
				$text .= $tag;

				break;
				
			default:
				break;
		}

		$text .= '<br><input class="swPrpSubmit" type="submit" name="EXPANDCLEAR_'.$this->query_name.'" value="Clear">';
		$text .= '<input class="swPrpSubmit" type="submit" name="EXPANDSELECTALL_'.$this->query_name.'" value="Select All">';
		$text .= '<input class="swPrpSubmit" type="submit" name="EXPANDOK_'.$this->query_name.'" value="OK">';

		return $text;
	}

	function format_form_column()
	{
		$text = "";
		$type = $this->criteria_type;

		switch($type)
		{
			case "LIST":
				$text .= $this->list_display(false);
				break;

			case "LOOKUP":
				if ( 
						//!array_key_exists("clearform", $_REQUEST) &&
						//(
						( $this->criteria_display !== "TEXTFIELD"  && $this->criteria_display !== "ANYCHAR" && $this->criteria_display != "NOINPUT" ) 
						||
						(
						array_key_exists("EXPANDED_".$this->query_name, $_REQUEST) || 
						array_key_exists("HIDDEN_".$this->query_name, $_REQUEST) ||
						$this->column_value
						)
						//)
					)
				{

					$this->execute_criteria_lookup();
				}
				$text .= $this->lookup_display(false);
				break;

			case "DATE":
				$text .= $this->date_display();
				break;

			case "DATERANGE":
				$text .= $this->daterange_display();
				break;

			case "ANYCHAR":
			case "TEXTFIELD":
				//$text .= '<SELECT style="visibility:hidden" name="'."HIDDEN_".$this->query_name.'[]" size="1" multiple>';
				//$text .= '<SELECT name="'."HIDDEN_".$this->query_name.'[]" size="1" multiple>';
				$tag = "";
				$tag .= '<input  type="text" class="swPrpTextField" name="MANUAL_'.$this->query_name.'"';
				$tag .= ' size="50%"';
				$tag .= ' value="'.$this->column_value.'">';
				$text .= $tag;

				break;
				
			default:
				break;
		}

		return $text;
	}
}

/**
 * Class reportico_assignment
 *
 * Identifies instructions for report column output
 * that must be calculated upon report execution. 
 */
class reportico_assignment extends reportico_object
{
	VAR $QUERY_NAME;
	var $expression;
	var $criteria;
	var $raw_expression;
	var $raw_criteria;
    
    // Indicates an operation which causes an action rather than setting a value
	var $non_assignment_operation = false;

	function reportico_assignment($query_name, $expression, $criteria)
	{
		//echo "ink ".$query_name." ".$expression." ".$criteria."\n<br>";
		$this->raw_expression = $expression;
		$this->raw_criteria = $criteria;
		$this->query_name = $query_name;
		$this->expression = $this->reportico_string_to_php($expression);
		$this->criteria = $this->reportico_string_to_php($criteria);
	}

	// -----------------------------------------------------------------------------
	// Function : reportico_lookup_string_to_php
	// -----------------------------------------------------------------------------
	function reportico_lookup_string_to_php($in_string)
	{
		$out_string = preg_replace('/{([^}]*)}/', 
			'\"".$this->lookup_queries[\'\1\']->column_value."\"', 
			$in_string);

		$cmd = '$out_string = "'.$out_string.'";';
		// echo  "==$cmd===";
		eval($cmd);
		return $out_string;
	}

	// -----------------------------------------------------------------------------
	// Function : reportico_meta_sql_criteria
	// -----------------------------------------------------------------------------
	static function reportico_meta_sql_criteria(&$in_query, $in_string, $prev_col_value = false)
	{
        // To allow reports to return data related to user, the
        // reportico_user session variable maintains
        // a current user id which can be used as part of select
        // statement. Just include {SESSION["reportico"]_USER} in queries and it will be
        // replaced by a quote encased user.
        global $g_external_param1;
        global $g_external_param2;
        global $g_external_param3;
        global $g_external_user;
        if ( $g_external_param1 ) $in_string = preg_replace ("/{EXTERNAL_PARAM1}/", "'".$g_external_param1."'", $in_string);
        if ( $g_external_param2 ) $in_string = preg_replace ("/{EXTERNAL_PARAM2}/", "'".$g_external_param2."'", $in_string);
        if ( $g_external_param3 ) $in_string = preg_replace ("/{EXTERNAL_PARAM3}/", "'".$g_external_param3."'", $in_string);
        if ( $g_external_user ) $in_string = preg_replace ("/{FRAMEWORK_USER}/", "'".$g_external_param3."'", $in_string);
        if ( $g_external_param1 ) $in_string = preg_replace ("/{USER_PARAM,[^}*]}/", "'".$g_external_param1."'", $in_string);

        // Replace External parameters specified by {USER_PARAM,xxxxx}
		if ( preg_match_all ( "/{USER_PARAM,([^}]*)}/", $in_string, $matches ) )
        {
            foreach ( $matches[0] as $k => $v )
            {
                $param = $matches[1][$k];
                if ( isset($in_query->user_parameters[$param] ) )
                {
                    $in_string = preg_replace("/{USER_PARAM,$param}/", $in_query->user_parameters[$param], $in_string);
                }
                else
                {
		            trigger_error("User parameter $param, specified but not provided to reportico", E_USER_ERROR);
                }
            }
        }

		$looping = true;
		$out_string = $in_string;
		$ct = 0;
		while ( $looping )
		{
			$ct++;
			if ( $ct > 100 )
			{
				echo "Problem with SQL cannot resolve Criteria Items<br>";
				break;
			}
			$regpat = "/{([^}]*)/";
			if ( preg_match ( $regpat, $out_string, $matches ) )
			{
				$crit = $matches[1];
				$first = substr($crit, 0, 1);

				$critexp = $crit;
				if ( $first == "=" )
				{
					$crit = substr ( $crit, 1 );
					$critexp = $crit;
					if ( array_key_exists($crit, $in_query->lookup_queries) )
						$clause = $in_query->lookup_queries[$crit]->get_criteria_clause(false, false, true);
					else if ( $cl = get_query_column($crit, $this->query->columns ) )
						if ( $prev_col_value )
							$clause = $cl->old_column_value;
						else
							$clause = $cl->column_value;
					else
					{
						handle_error( "Unknown Criteria Item $crit in Query $in_string");
						return $in_string;
					}
				}
				else
				{
					$eltype = "VALUE";
                    $showquotes = true;
					if ( preg_match ( "/(.*),(.*),(.*)/", $crit, $critel ) )
					{
							$crit = $critel[1];
							$eltype = $critel[2];
							if ( $critel[3] == "false" )
                                $showquotes = false;
					}
					if ( preg_match ( "/(.*),(.*)/", $crit, $critel ) )
					{
							$crit = $critel[1];
							if ( $critel[2] == "false" )
                                $showquotes = false;
                            else
							    $eltype = $critel[2];
					}
					if ( array_key_exists($crit, $in_query->lookup_queries) )
					{
						switch ( $eltype )
						{
							case "FULL" :
								$clause = $in_query->lookup_queries[$crit]->get_criteria_clause(true, true, true, false, false, $showquotes);
								break;
	
							case "RANGE1" :
								$clause = $in_query->lookup_queries[$crit]->get_criteria_clause(false, false, false, true, false, $showquotes);
								break;
	
							case "RANGE2" :
								$clause = $in_query->lookup_queries[$crit]->get_criteria_clause(false, false, false, false, true, $showquotes);
								break;
	
							case "VALUE" :
							default :
								$clause = $in_query->lookup_queries[$crit]->get_criteria_clause(false, false, true, false, false, $showquotes);
						}
					}
					else if ( $cl = get_query_column($crit, $in_query->columns ) )
							if ( $prev_col_value )
								$clause = $cl->old_column_value;
							else
								$clause = $cl->column_value;
					else
					{
						//handle_error( "Unknown Criteria Item $crit in Query $in_string");
						return $in_string;
					}
				}

				if  (!$clause)
				{
					$out_string = preg_replace("/\[[^[]*\{$critexp\}[^[]*\]/", '',  $out_string);
				}
				else
				{
					$out_string = preg_replace("/\{=*$critexp\}/", 
						$clause,
						$out_string);
					$out_string = preg_replace("/\[\([^[]*\)\]/", "\1", $out_string);
				}


			}
			else
				$looping = false;

		}
	

		$out_string = preg_replace("/\[\[/", "<STARTBRACKET>", $out_string);
		$out_string = preg_replace("/\]\]/", "<ENDBRACKET>", $out_string);
		$out_string = preg_replace("/\[/", "", $out_string);
		$out_string = preg_replace("/\]/", "", $out_string);
		$out_string = preg_replace("/<STARTBRACKET>/", "[", $out_string);
		$out_string = preg_replace("/<ENDBRACKET>/", "]", $out_string);
		// echo "<br>Meta clause: $out_string<BR>";

		//$out_string = addcslashes($out_string, "\"");
		//$cmd = trim('$out_string = "'.$out_string.'";');
		//echo $out_string;
		
		//if ( $cmd )
			//eval($cmd);
		return $out_string;
	}

	// -----------------------------------------------------------------------------
	// Function : reportico_string_to_php
	// -----------------------------------------------------------------------------
	function reportico_string_to_php($in_string)
	{
		// first change '(colval)' parameters
		$out_string = $in_string;

		$out_string = preg_replace('/{TARGET_STYLE}/', 
			'$this->target_style', 
			$out_string);

		$out_string = preg_replace('/{TARGET_FORMAT}/', 
			'$this->target_format', 
			$out_string);

		$out_string = preg_replace('/old\({([^}]*)},{([^}]*)}\)/', 
			'$this->old("\1")', 
			$out_string);

		$out_string = preg_replace('/old\({([^}]*)}\)/', 
			'$this->old("\1")', 
			$out_string);

		$out_string = preg_replace('/max\({([^}]*)},{([^}]*)}\)/', 
			'$this->max("\1","\2")', 
			$out_string);

		$out_string = preg_replace('/max\({([^}]*)}\)/', 
			'$this->max("\1")', 
			$out_string);

		$out_string = preg_replace('/min\({([^}]*)},{([^}]*)}\)/', 
			'$this->min("\1","\2")', 
			$out_string);

		$out_string = preg_replace('/min\({([^}]*)}\)/', 
			'$this->min("\1")', 
			$out_string);

		$out_string = preg_replace('/avg\({([^}]*)},{([^}]*)}\)/', 
			'$this->avg("\1","\2")', 
			$out_string);

		$out_string = preg_replace('/avg\({([^}]*)}\)/', 
			'$this->avg("\1")', 
			$out_string);

		$out_string = preg_replace('/sum\({([^}]*)},{([^}]*)}\)/', 
			'$this->sum("\1","\2")', 
			$out_string);

		$out_string = preg_replace('/sum\({([^}]*)}\)/', 
			'$this->sum("\1")', 
			$out_string);

		$out_string = preg_replace('/imagequery\(/', 
			'$this->imagequery(', 
			$out_string);

		$out_string = preg_replace('/reset\({([^}]*)}\)/', 
			'$this->reset("\1")', 
			$out_string);

		$out_string = preg_replace('/changed\({([^}]*)}\)/', 
			'$this->changed("\1")', 
			$out_string);

		$out_string = preg_replace('/groupsum\({([^}]*)},{([^}]*)},{([^}]*)}\)/', 
			'$this->groupsum("\1","\2", "\3")', 
			$out_string);

		//$out_string = preg_replace('/count\(\)/', 
			//'$this->query_count', 
			//$out_string);
		$out_string = preg_replace('/lineno\({([^}]*)}\)/', 
			'$this->lineno("\1")', 
			$out_string);

        if ( preg_match ( '/skipline\(\)/', $out_string ) )
        {
            $this->non_assignment_operation = true;
		    $out_string = preg_replace('/skipline\(\)/', 
			    '$this->skipline()', 
			    $out_string);
        }

        if ( preg_match ( '/apply_style\(.*\)/', $out_string ) )
        {
            $this->non_assignment_operation = true;
		    $out_string = preg_replace('/apply_style\(/', 
			    '$this->apply_style("'.$this->query_name."\",", $out_string);
        }

        if ( preg_match ( '/embed_image\(.*\)/', $out_string ) )
        {
            $this->non_assignment_operation = true;
		    $out_string = preg_replace('/embed_image\(/', 
			    '$this->embed_image("'.$this->query_name."\",", $out_string);
        }

        if ( preg_match ( '/embed_hyperlink\(.*\)/', $out_string ) )
        {
            $this->non_assignment_operation = true;
		    $out_string = preg_replace('/embed_hyperlink\(/', 
			    '$this->embed_hyperlink("'.$this->query_name."\",", $out_string);
        }

		$out_string = preg_replace('/lineno\(\)/', 
			'$this->lineno()', 
			$out_string);

		$out_string = preg_replace('/count\({([^}]*)}\)/', 
			'$this->lineno("\1")', 
			$out_string);

		$out_string = preg_replace('/count\(\)/', 
			'$this->lineno()', 
			$out_string);

		$out_string = preg_replace('/{([^}]*)}/', 
			//'$this->columns[\'\1\']->column_value', 
			'get_query_column_value(\'\1\', $this->columns)', 
			$out_string);

		return $out_string;
	}

}

/**
 * Class reportico_query_column
 *
 * Holds presentation and database retrieval information
 * about a data column that mus tbe retrieved from the database
 * or calculated during report execution.
 */
class reportico_query_column extends reportico_object
{
	var $query_name;
	var $table_name;
	var $column_name;
	var $column_type;
	var $column_length;
	var $column_mask;
	var $in_select;
	var $order_style;
	var $column_value;
	var $column_value2;
	var $old_column_value = "*&^%_+-=";
	var $column_value_count;
	var $column_value_sum;
	var $summary_columns;
	var $header_columns;
	var $assoc_column;
	var $reset_flag = false;
	var $criteria_type = "";
	var $criteria_list = "";
	var $match_column = "";
	var $lookup_query;

	var $lookup_return_flag;
	var $lookup_display_flag;
	var $lookup_abbrev_flag;
	var $datasource = false;

	var $minimum = false;
	var $maximum = false;
	var $lineno = 0;
	var $groupvals = array();
	var $average = 0;
	var $sum = 0;
	var $avgct = 0;
	var $avgsum = 0;
	var $median = false;
	var $value_list = array();
    var $output_cell_styles = false;
    var $output_hyperlinks = false;
    var $output_images = false;

	var $attributes = array (
		"column_display" => "show",
		"content_type" => "plain",
		"ColumnStartPDF" => "",
		"justify" => "left",
		"ColumnWidthPDF" => "",
		"ColumnWidthHTML" => "",
		"column_title" => "",
		"tooltip" => "",
		"group_header_label" => "0",
		"group_header_label_xpos" => "",
		"group_header_data_xpos" => "",
		"group_trailer_label" => "0"
		);

	var $values = array (
		"column_value" => "",
		"column_count" => 0,
		"column_sum" => 0
		);

	function set_datasource(&$datasource)
	{ 	
		$this->datasource =& $datasource;
	}


	function reportico_query_column
		(
			$query_name = "",
			$table_name = "table_name",
			$column_name = "column_name", 
			$column_type = "string",
			$column_length = 0,
			$column_mask = "MASK",
			$in_select = true
		)
		{
			reportico_object::reportico_object();

			$this->query_name = $query_name;
			$this->table_name = $table_name;
			$this->column_name = $column_name;
			$this->column_type = $column_type;
			$this->column_length = $column_length;
			$this->column_mask = $column_mask;
			$this->in_select = $in_select;

			if ( !($this->query_name) )
				$this->query_name = $this->column_name;
			
		}			

	// -----------------------------------------------------------------------------
	// Function : get_value_delimiter
	// -----------------------------------------------------------------------------
	function get_value_delimiter()
	{
		if ( strtoupper($this->column_type) == "CHAR" )
			return ("'");

		return("");
	}

}

/**
 * Function set_project_environment
 *
 * Analyses configuration and current session to identify which project area
 * is to be used. 
 * If a project is specified in the HTTP parameters then that is used, otherwise
 * the current SESSION
 * "reports" project is used
 */
function set_project_environment($initial_project = false)
{
	global $g_project;
	global $g_projpath;
	global $g_language;
	global $g_translations;
	global $g_menu;
	global $g_menu_title;
	global $g_dropdown_menu;
	global $g_report_desc;
	global $g_included_config;
	
	$target_menu = "";
	$project = "";

    $last_project = "";

    if ( isset_reportico_session_param("project") )
        if ( get_reportico_session_param("project") )
            $last_project = get_reportico_session_param("project");

	if ( !$project && array_key_exists("submit_delete_project", $_REQUEST) )
	{
		$project = get_request_item("jump_to_delete_project", "");	
		$_REQUEST["xmlin"] = "deleteproject.xml";
		set_reportico_session_param("project",$project);
	}

	if ( !$project && array_key_exists("submit_configure_project", $_REQUEST) )
	{
		$project = get_request_item("jump_to_configure_project", "");	
		$_REQUEST["xmlin"] = "configureproject.xml";
		set_reportico_session_param("project",$project);
	}

	if ( !$project && array_key_exists("submit_menu_project", $_REQUEST) )
	{
		$project = get_request_item("jump_to_menu_project", "");	
		set_reportico_session_param("project",$project);
	}

	if ( !$project && array_key_exists("submit_design_project", $_REQUEST) )
	{
		$project = get_request_item("jump_to_design_project", "");	
		set_reportico_session_param("project",$project);
	}
    
	if ( $initial_project )
	{
		$project = $initial_project;
		set_reportico_session_param("project",$project);
	}
    

	if ( !$project )
		$project = session_request_item("project", "admin");

	if ( !$target_menu )
		$target_menu = session_request_item("target_menu", "");

	$menu = false;
	$menu_title = "Set Menu Title";

	// Now we now the project include the relevant config.php
	$projpath = "projects/".$project;
	$configfile = $projpath."/config.php";
	$configtemplatefile = $projpath."/adminconfig.template";

	$menufile = $projpath."/menu.php";
	if ( $target_menu != "" )
		$menufile = $projpath."/menu_".$target_menu.".php";

	if ( !is_file($projpath) )
	{
		find_file_to_include($projpath, $projpath);
	}

	if ( !$projpath )
	{
		find_file_to_include("config.php", $configfile);
        if ( $g_included_config && $g_included_config != $configfile )
            handle_error("Cannot load two different instances on a single page from different projects.", E_USER_ERROR);
        else
        {
            $g_included_config = $configfile;
            include_once($configfile);
        }
		$g_projpath = false;
		$g_project = false;
		$g_menu = false;
		$g_menu_title = "";
		$g_dropdown_menu = false;
		$old_error_handler = set_error_handler("ErrorHandler");
		handle_error("Project Directory $project not found. Check INCLUDE_PATH or project name");
		return;
	}

	$g_projpath = $projpath;
	if ( !is_file($configfile) )
		find_file_to_include($configfile, $configfile);
	if ( !is_file($menufile) )
		find_file_to_include($menufile, $menufile);

	if ( $project == "admin" && !is_file($configfile))
	{
		find_file_to_include($configtemplatefile, $configfile);
	}
	
	if ( $configfile )
	{
		if ( !is_file($configfile) )
        {
			handle_error("Config file $menufile not found in project $project", E_USER_WARNING);
        }

        if ( $g_included_config && $g_included_config != $configfile )
	        handle_error("Cannot load two different instances on a single page from different projects.", E_USER_ERROR);
        else
        {
		    include_once($configfile);
            $g_included_config = $configfile;
        }

		if ( is_file($menufile) )
			include($menufile);
		else
			handle_error("Menu Definition file $menufile not found in project $project", E_USER_WARNING);

	}
	else
	{
		find_file_to_include("config.php", $configfile);
		if ( $configfile )
        {
            if ( $g_included_config && $g_included_config != $configfile )
	            handle_error("Cannot load two different instances on a single page from different projects.", E_USER_ERROR);
            else
            {
		        include_once($configfile);
                $g_included_config = $configfile;
            }
        }
		$g_project = false;
		$g_projpath = false;
		$g_menu = false;
		$g_menu_title = "";
		$g_dropdown_menu = false;
		$old_error_handler = set_error_handler("ErrorHandler");
		handle_error("Configuration Definition file config.php not found in project $project", E_USER_ERROR);
	}

    // Ensure a Database and Output Character Set Encoding is set
    if ( !defined("SW_DB_ENCODING" ) )
        define("SW_DB_ENCODING", "UTF8");
    if ( !defined("SW_OUTPUT_ENCODING" ) )
        define("SW_OUTPUT_ENCODING", "UTF8");

    // Ensure a language is set
    if ( !defined("SW_LANGUAGE" ) )
        define("SW_LANGUAGE", "en_gb");

	$g_project = $project;
	if ( !defined('SW_PROJECT') )
	    define('SW_PROJECT', $g_project);

	$language = "en_gb";
    // Default language to first language in avaible_languages
    $langs = available_languages();
    if ( count($langs) > 0 )
    {
        $language = $langs[0]["value"];
    }

	if ( defined('SW_LANGUAGE') && SW_LANGUAGE && SW_LANGUAGE != "PROMPT" )
	    $language = session_request_item("reportico_language", SW_LANGUAGE);
    else
	    $language = session_request_item("reportico_language", "en_gb");

    // language not found the default to first
    $found = false;
    foreach ( $langs as $k => $v )
    {
        if ( $v["value"] == $language )
        {
            $found = true;
            break;
        }
    }
    if ( !$found && count($langs) > 0 )
        $language = $langs[0]["value"];

    // If project has change then change to default project language
    // Ignore for now as want to use chosen Administrator language if set
    //if ( $last_project && ( $last_project != $project ) )
    //{
        //$language = SW_LANGUAGE;
		//set_reportico_session_param("language",$language);
    //}

	if ( array_key_exists("submit_language", $_REQUEST) )
	{
		$language = $_REQUEST["jump_to_language"];
		set_reportico_session_param("reportico_language",$language);
	}

	$g_language = $language;
	$g_menu = $menu;
	$g_menu_title = $menu_title;
    if ( isset($dropdown_menu ) )
	    $g_dropdown_menu = $dropdown_menu;

    // Include project specific language translations
    load_project_language_pack($project, output_charset_to_php_charset(SW_OUTPUT_ENCODING));

	return $project;
}


/**
 * Function save_admin_password
 *
 * Writes new admin password to the admin config.php 
 */
function save_admin_password($password1, $password2, $language)
{
    global $g_language;
    if ( $language )
	    $g_language = $language;

	if ( $password1 != $password2 )
		return sw_translate("The passwords are not identical please reenter");
	if ( strlen($password1) == 0 )
		return sw_translate("The password may not be blank");

	$proj_parent = find_best_location_in_include_path( "projects" );
	$proj_dir = $proj_parent."/admin";
	$proj_conf = $proj_dir."/config.php";
	$proj_template = $proj_dir."/adminconfig.template";

	if ( !file_exists ( $proj_parent ) )
    		return "Projects area $proj_parent does not exist - cannot write project";

	if ( file_exists ( $proj_conf ) )
	{
		if ( !is_writeable ( $proj_conf  ) )
			return "Projects config file $proj_conf is not writeable - cannot write config file - change permissions to continue";
	}

	if ( !is_writeable ( $proj_dir  ) )
    		return "Projects area $proj_dir is not writeable - cannot write project password in config.php - change permissions to continue";


	if ( !file_exists ( $proj_conf ) )
		if ( !file_exists ( $proj_template ) )
    		return "Projects config template file $proj_template does not exist - please contact reportico.org";

	if ( file_exists ( $proj_conf ) )
	{
		$txt = file_get_contents($proj_conf);
	}
	else
	{
		$txt = file_get_contents($proj_template);
	}

	$proj_language = find_best_location_in_include_path( "language" ) ;
	$lang_dir = $proj_language."/".$language;
	if ( !is_dir ( $lang_dir ) )
    	return "Language directory $language does not exist within the language folder";

	$txt = preg_replace ( "/(define.*?SW_ADMIN_PASSWORD',).*\);/", "$1'$password1');", $txt);
	$txt = preg_replace ( "/(define.*?SW_LANGUAGE',).*\);/", "$1'$language');", $txt);

    unset_reportico_session_param('admin_password');
	$retval = file_put_contents($proj_conf, $txt );
	
	// Password is saved so use it so user can login
	if ( !defined('SW_ADMIN_PASSWORD') )
		define ('SW_ADMIN_PASSWORD', $password1);
	else
		define ('SW_ADMIN_PASSWORD_RESET', $password1);

	return ;

}

// Setup SESSION
set_up_reportico_session();

?>
