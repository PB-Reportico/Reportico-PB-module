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

 * File:        admin.php
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
        "T_en_gb" => "英語（英國）",
        "T_en_us" => "英語（美國）",
        "T_zh_cn" => "中文（简体）",
        "T_fr_fr" => "法國",
        "T_es_es" => "西班牙語",
        "T_ADMINTITLE" => "Reportico當局頁",
        "T_LOGGED_IN_AS" => "登錄為",
        "T_LOGIN" => "登錄",
        "T_LOGOFF" => "註銷",
        "T_ENTER_PROJECT_PASSWORD" => "進入該項目的密碼..",
        "T_ADMIN_INSTRUCTIONS" => "選擇從下拉下面的報告套件或輸入的Reportico管理員密碼開始使用Reportico和訪問管理功能和設計報告的能力......",
        "T_SET_ADMIN_PASSWORD_INFO" => "管理員密碼目前沒有設置您需要設置一個密碼，開始使用Reportico和參考訪問Reportico的的參考行政職能的參考，請輸入您選擇的密碼：",
        "T_ADMIN_PASSWORD_NOT_SET" => "目前沒有設置管理員密碼..",
        "T_SET_ADMIN_PASSWORD_PROMPT" => "管理密碼",
        "T_SET_ADMIN_PASSWORD_REENTER" => "重新輸入管理密碼參考",
        "T_SET_ADMIN_PASSWORD" => "設置管理員密碼",
        "T_RUN_SUITE" => "運行項目報告套房",
        "T_CREATE_REPORT" => "在項目中創建報告",
        "T_CONFIG_PARAM" => "配置參數：項目",
        "T_DELETE_PROJECT" => "删除项目",
        "T_DOCUMENTATION" => "文檔",
        "T_CHOOSE_LANGUAGE" => "選擇語言",
        "T_GO" => "去",
        "T_UNABLE_TO_CONTINUE" => "無法繼續",
        "T_ADMIN_PASSWORD_ERROR" => "不正確的密碼再試一次......",
        "T_BLANKLINE" => "BLANKLINE",
        "T_LINE" => "線",
        "T_Create A New Project" => "創建一個新項目",
        "T_Configure Tutorials" => "配置教程",
),
);
?>
