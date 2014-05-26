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

 * File:        prepare.php
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
$locale_arr = array (
"language" => "Chinese (simplified)",
"template" => array (
		"T_PROJECT_MENU" => "项目菜单",
		"T_DESIGN_REPORT" => "设计报告",
						"T_LOGOFF" => "注销",
        "T_LOGIN" => "登录",
		"T_GO" => "去",
		"T_RESET" => "重置",
		"T_SEARCH" => "搜索",
		"T_SHOW_CRITERIA" => "显示标准",
		"T_SHOW_GRAPH" => "显示图形",
		"T_OUTPUT" => "输出：",
		"T_DEBUG_LEVEL" => "调试级别：",
		"T_DEBUG_NONE" => "没有",
		"T_DEBUG_LOW" => "低",
		"T_DEBUG_MEDIUM" => "中等",
		"T_DEBUG_HIGH" => "高",
		"T_ENTER_PROJECT_PASSWORD" => "进入该项目的密码。",
        "T_EXPAND" => ">>",
        "T_DEFAULT_REPORT_DESCRIPTION" =>
"&nbsp<br>".
"在这里输入你的报告标准。进入标准的适当扩大key.When的使用，你是乐意选择适当的输出格式，然后单击确定。",
		"T_PASSWORD_ERROR" => "不正确的密码。再试一次。",
		"T_OK" => "确定",
		"T_CLEAR" => "清除",
		"T_SELECTALL" => "选择全部",
		"T_UNABLE_TO_CONTINUE" => "无法继续",
		"T_Create A New Project" => "创建一个新项目",
		"T_Configure Project" => "配置项目",

		"T_ADMIN_MENU" => "管理菜单",
				"T_SHOW" => "显示",
		"T_SHOW_GRPHEADERS" => "组头",
		"T_SHOW_GRPTRAILERS" => "集团预告",
		"T_SHOW_COLHEADERS" => "列标题",
		"T_SHOW_DETAIL" => "细节",
		"T_Configure Tutorials" => "配置教程",
        "T_PRINTABLE" => "打印HTML",
        "T_PRINT_XML" => "生成XML输出",
        "T_PRINT_HTML" => "生成HTML报告",
        "T_PRINT_PDF" => "生成PDF报告书“",
        "T_PRINT_CSV" => "生成CSV报告书“",
        "T_PRINT_JSON" => "生成JSON报告“",
        "T_STYLE_FORM" => "表格输出",
        "T_FORM" => "形式",
        "T_TABLE" => "表",
        "T_REPORT_STYLE" => "风格",

),
);
?>
