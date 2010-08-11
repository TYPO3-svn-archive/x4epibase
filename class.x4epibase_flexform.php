<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005 Markus Stauffiger (markus@4eyes.ch)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Flexform class to fill the select fields with appropriate tables and fields of the tables
 *
 * @author	Markus Stauffiger (markus@4eyes.ch)
 */
class tx_x4epibase_flexform {

	/**
	 * Adding field list to selector box array
	 *
	 * @param	array		Parameters, changing "items". Passed by reference.
	 * @param	object		Parent object
	 * @return	void
	 */
	function listFieldsForFlexForm(&$params,&$pObj)	{
		global $TCA;
		$table = $params['config']['params']['table'];
		if ($table == '') {
			$table = tx_x4epibase_flexform::getSelectedTable($params,$pObj);
		}
		t3lib_div::loadTCA($table);


		$params['items']=array();
		if (is_array($TCA[$table]['columns']))	{
			foreach($TCA[$table]['columns'] as $key => $config)	{
				if ($config['label'])	{
					$label = t3lib_div::fixed_lgd(ereg_replace(':$','',$GLOBALS['LANG']->sL($config['label'])),30).' ('.$key.')';
					$params['items'][] = array($label, $key);
				}
			}
		}
		$params['items'][] = array('Stadt, Land', 'country_and_city');
	}

	/**
	 * Returns selected table
	 */
	function getSelectedTable(&$params,&$pObj) {
		$xml = t3lib_div::xml2array($params['row']['pi_flexform']);
		return $xml['data']['sDEF']['lDEF']['tableName']['vDEF'];
	}

	/**
	 * Puts all 'tx_x4e*' tables into params['items'] array
	 *
	 * @param $params
	 * @param $pObj
	 */
	function getTableNames(&$params,&$pObj) {
		global $TCA;
		$res = $GLOBALS['TYPO3_DB']->sql_query('SHOW TABLES;');
		while($t = $GLOBALS['TYPO3_DB']->sql_fetch_row($res)) {
			if (substr($t[0],0,5) == 'tx_x4') {
				$params['items'][]=Array($t[0], $t[0]);
			}
		}
	}
}


// Include extension?
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/x4epibase/class.x4epibase_flexform.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/x4epibase/class.x4epibase_flexform.php']);
}

?>