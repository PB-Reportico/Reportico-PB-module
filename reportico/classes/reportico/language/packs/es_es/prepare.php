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
"language" => "Spanish",
"template" => array (
		"T_PROJECT_MENU" => "Proyecto de Menú",
		"T_DESIGN_REPORT" => "Diseño de informes",
		"T_EXPAND" => ">>",
		"T_LOGOFF" => "Salir",
		"T_GO" => "Ejecutar",
		"T_RESET" => "Reinicializar",
		"T_SEARCH" => "Buscar",
		"T_SHOW_CRITERIA" => "Mostrar los criterios",
		"T_SHOW_GRAPH" => "Mostrar el gráfico",
		"T_OUTPUT" => "Salida:",
		"T_DEBUG_LEVEL" => "Depuración de nivel:",
		"T_DEBUG_NONE" => "Ninguno",
		"T_DEBUG_LOW" => "Bajo",
		"T_DEBUG_MEDIUM" => "Medio",
		"T_DEBUG_HIGH" => "Alto",
		"T_ENTER_PROJECT_PASSWORD" => "",
		"T_EXPAND" => ">>",
		"T_PASSWORD_ERROR" => "Contraseña incorrecta. Inténtelo de nuevo.",
        "T_DEFAULT_REPORT_DESCRIPTION" =>
            "&nbsp<br>".
            "Introduzca los criterios del informe aquí. Para introducir los criterios de uso de la correspondiente clave de expansión. Cuando eres feliz seleccionar el formato de salida correspondiente y haga clic en Aceptar.",
        "T_OK" => "Ok",
        "T_CLEAR" => "Limpiar",
        "T_SELECTALL" => "Seleccionar Todo",
        "T_UNABLE_TO_CONTINUE" => "Incapaz de Seguir",
        "T_Create A New Project" => "Crear un Nuevo Proyecto",
        "T_Configure Project" => "Configurar Proyecto",
        "T_Configure Tutorials" => "Configurar Tutoriales",

        "T_ADMIN_MENU" => "Admin Menu",
        "T_LOGIN" => "Log In",
        "T_SHOW" => "Show",
        "T_SHOW_GRPHEADERS" => "Group Headers",
        "T_SHOW_GRPTRAILERS" => "Group Trailers",
        "T_SHOW_COLHEADERS" => "Column Headers",
        "T_SHOW_DETAIL" => "Detail",
        "T_PRINTABLE" => "HTML imprimible",
        "T_PRINT_XML" => "Generar XML de salida",
        "T_PRINT_HTML" => "Generar informe HTML",
        "T_PRINT_PDF" => "Generar informe PDF",
        "T_PRINT_CSV" => "Generar CSV Informe",
        "T_PRINT_JSON" => "Generar JSON Informe",
        "T_STYLE_FORM" => "Estilo Form",
        "T_FORM" => "Forma",
        "T_TABLE" => "Tabla",
        "T_REPORT_STYLE" => "Estilo",
    ),

);
?>
