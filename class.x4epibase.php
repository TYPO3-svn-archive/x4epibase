<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005 Markus Stauffiger (markus@4eyes.ch)
*  All rights reserved
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
 * Plugin 'Templated based pibase-functions' replacing some of the pibase
 * function with a template based approach
 *
 *
 * Feel free to send me comments, wishes, bug reports etc.
 *
 * @author	Markus Stauffiger <markus@4eyes.ch>
 */


require_once(PATH_tslib.'class.tslib_pibase.php');

class x4epibase extends tslib_pibase {
	/**
	 * Tablename of the main table
	 * 
	 * @var string
	 */
	var $tableName;

	/**
	 * Category table, needed for listByCategory
	 *
	 * @var string
	 */
	var $categoryTable = '';

	/**
	 * Category field, needed for listByCategory
	 *
	 * @var string
	 */
	var $categoryField = 'category_id';

	/**
	 * Label field in the category table
	 */
	var $categoryLabelField = '';

	/**
	 * Sorting field for the categories
	 */
	var $categorySortField = '';

	/**
	 * Template for Listview
	 */
	var $listT = '';

	/**
	 * contains fields to display
	 */
	var $manualFieldOrder_list = '';

	/**
	 * Current category
	 *
	 * @var Array
	 */
	var $currentCategory = array();

	/**
	 * This array contains all the fields that are not supposed to be run through
	 * htmlentities()
	 *
	 * @var array
	 */
	var $skipHtmlEntitiesFields = array();

	/**
	 * Contains labels of related record
	 *
	 * @var	Array
	 */
	var $relatedRecords = array();

	/**
	 * Multiple typo3 variables
	 *
	 * @var unknown_type
	 */
	var $prefixId = 'tx_x4epibase_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_x4epibase_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey = 'x4epibase';	// The extension key.
	var $pi_checkCHash = TRUE;

	/**
	 * Contains the mm-related templates for the detail view
	 *
	 * Scaffold-related
	 *
	 * @var array
	 */
	var $mmTemplates = array();

	/**
	 * Contains markers which are globally used
	 *
	 * @var array
	 */
	var $globalMarkerArray = array();

	/**
	 * Sets inital variables form typoscript and flexform
	 * 
	 * @param $content				string	Deprecated, not used
	 * @param $conf					array	Typoscript configuration array
	 * @param $overrideTableName	string	sets different main table name
	 * @return void
	 *
	 */
	function init($content,$conf, $overrideTableName = '') {
		global $TCA;
		// standard typo3 settings
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_initPIflexForm();
		
		// overriding default table name
		if($overrideTableName == '') {
			$this->setMemberVariableTSFF('tableName');
		} else {
			$this->tableName = $overrideTableName;
		}
		
		$this->internal['searchFieldList'] = $this->conf['searchFieldList'];

		// set db-variables
		if (isset($this->conf['orderByList']) && ($this->conf['orderByList'] != '')) {
			$this->internal['orderByList'] = implode(',',t3lib_div::trimExplode(',',$this->conf['orderByList'], 1));
		} else {
			$this->internal['orderByList'] = $this->conf['searchFieldList'];
		}
		$this->internal['descFlag'] = $this->conf['orderDesc'];
		$this->internal['currentTable'] = $this->tableName;
		
		// set fields to display in listview
		$this->manualFieldOrder_list = t3lib_div::trimExplode(',',$this->getTSFFvar('field_orderList'),1);
		
		// use default label-column, if field-list is empty
		if(count($this->manualFieldOrder_list) == 0) {
			$this->manualFieldOrder_list[0] = $TCA[$this->tableName]['ctrl']['label'];
		}
		$this->conf['recursive'] = 0;

		// set pid list (pages with records)
		$this->conf['pidList'] = $this->getTSFFvar('pidList');
		// use current page as pid if no pidList available
		if ($this->conf['pidList'] == '') {
			$this->conf['pidList'] = $GLOBALS['TSFE']->id;
		}
		
		$this->skipHtmlEntitiesFields = t3lib_div::trimExplode(',',$this->conf['skipHtmlEntitiesFields'],1);

		t3lib_div::loadTCA($this->internal['currentTable']);

		// put the link-fields in appropriate array
		if (!is_array($this->conf['utf8DecodeFields'])) {
			$this->conf['utf8DecodeFields'] = t3lib_div::trimExplode(',',$this->conf['utf8DecodeFields'],1);
		}

		// put the mandatory-fields in appropriate array
		if (isset($this->conf['create.']['mandatoryFields'])) {
			$this->conf['create.']['mandatoryFields'] = t3lib_div::trimExplode(',',$this->conf['create.']['mandatoryFields'],1);
		}

		// put the link-fields in appropriate array
		if (!is_array($this->conf['listView.']['detailLinkFields'])) {
			$this->conf['listView.']['detailLinkFields'] = t3lib_div::trimExplode(',',$this->conf['listView.']['detailLinkFields'],1);
		}
	}

	/**
	 * Main function, just init
	 * 
	 * @param string 	$content
	 * @param string 	$conf		Typoscript
	 *
	 * @return string	HTML-String, extension output
	 */
	function main($content,$conf){
		$this->init($content,$conf);
		$content = $this->getView();
		
		// trigger baseClass-Wrap via TS
		if(intval($this->getTSFFvar('noWrapInBaseClass')) != 1){
			return $this->pi_wrapInBaseClass($content);
		} else {
			return $content;
		}
	}

	/**
	 * Returs either the given orderFieldList or makes it's own by retreving
	 * TCA information
	 *
	 * @return	String		Comma-separated list of fields
	 */
	function getOrderByList() {
		if (($this->conf['orderFieldList'] != '') && (substr($this->conf['orderFieldList'],0,2) != '{$')) {
			return $this->conf['orderByFieldList'];
		} else {
			global $TCA;
			if (isset($TCA[$this->tableName]['ctrl']['sorting'])) {
				return $TCA[$this->tableName]['ctrl']['sorting'];
			} else {
				return $TCA[$this->tableName]['ctrl']['default_sortby'];
			}
		}
	}

	/**
	 * Puts a typoscript or flexform value into the appropriate member variable
	 *
	 * @param	string	$name	Name of the variable
	 * 
	 * @return	void
	 */
	function setMemberVariableTSFF($name) {
		$value = $this->getTSFFvar($name);
		if ($value != '') {
			$this->$name = $this->getTSFFvar($name);
		}
	}

	/**
	 * Functions which establishes what kind of view can be presented (controller)
	 *
	 * @return	String
	 */
	function getView() {
		if ($this->piVars['showUid']) {
			return $this->singleView();
		} else {
			return $this->getCorrectListView();
		}
	}

	/**
	 * Checks which list view is supposed to show up and calls the appropriate
	 * function
	 *
	 * @return String
	 */
	function getCorrectListView() {
		switch($this->getTSFFvar('modeSelection')) {
			case 'category':
				return $this->listByCategory();
			break;
			case 'categoryMenu':
				return $this->getCategoryMenu();
			break;
			case 'listOfDetail':
				return $this->listOfDetailView();
			break;
			case 'alphabeticalList':
				return $this->listByAlphabet();
			break;
			default:
				return $this->listView();
			break;
		}
	}

	/**
	 * Creates a category menu based on the typoscript settings
	 *
	 * @param string $addWhere [optional] Additional where clause for the category query
	 * @return string html
	 */
	function getCategoryMenu($addWhere='') {
		$templateCode = $this->cObj->fileResource($this->conf['categoryMenu.']['template']);
		if ($templateCode == '') {
			$templateCode = $this->template;
		}
		
		$template['total'] = $this->cObj->getSubpart($templateCode, '###catMenu###');
		$template['menu'] = $this->cObj->getSubpart($template['total'], '###menu###');

		if (isset($this->conf['categoryMenu.']['catTable'])) {
			$catTable = $this->conf['categoryMenu.']['catTable'];
		} else {
			$catTable = $this->categoryTable;
		}
		if (isset($this->conf['categoryMenu.']['catField'])) {
			$catField = $this->conf['categoryMenu.']['catField'];
		} else {
			$catField = $this->categoryField;
		}

		if (isset($this->conf['categoryMenu.']['catLabelField'])) {
			$catLabelField = $this->conf['categoryMenu.']['catLabelField'];
		} else {
			$catLabelField = $this->categoryLabelField;
		}
		
		if(empty($this->conf['categoryMenu.']['pidList'])){
			$this->conf['categoryMenu.']['pidList'] = $this->getTSFFvar('categoryPidList');
			if(empty($this->conf['categoryMenu.']['pidList'])){
				$this->conf['categoryMenu.']['pidList'] = $this->getTSFFvar('pidList');
			}
		}

		// hide categories with no records
		if($this->conf['categoryMenu.']['onlyShowNecessaryCats'] == '1') {
			$catArr = array();
			$projects = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,'.$catField, $this->tableName, '1 '.$this->cObj->enableFields($this->tableName));
			while($p = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($projects)) {
				array_push($catArr, $p[$catField]);
			}
			$catArr = array_unique($catArr);
			$WHERE = 'uid IN ('.implode(',',$catArr).')';
		} else {
			$WHERE = '1';
		}
		global $TCA;
		t3lib_div::loadTCA($catTable);
		if (isset($TCA[$catTable]['ctrl']['languageField'])) {
			$WHERE .= ' AND sys_language_uid = 0';
		}
		
		$WHERE .= ' AND '.$catTable.'.pid IN ('.$this->conf['categoryMenu.']['pidList'].')'.$this->cObj->enableFields($catTable);
		
		$WHERE .= $addWhere;
		
		$this->conf['categoryMenu.']['orderCatBy'] ? $ORDERBY = $this->conf['categoryMenu.']['orderCatBy'] : $this->categorySortField;

		$altPageUid = intval($this->conf['categoryMenu.']['altPageUid']);
		$cats = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', $catTable, $WHERE, '', $ORDERBY);
		$bakTable = $this->tableName;
		$this->tableName = $catTable;
		while($this->internal['currentRow'] = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($cats)) {
			$this->getLanguageOverlay();
			$params = array("category" => $this->internal['currentRow']['uid']);
			$markerArray['###item###'] = $this->pi_linkTP_keepPIvars($this->internal['currentRow'][$catLabelField], $params,1,0,$altPageUid);
			$markerArray['###itemLabel###'] = $this->internal['currentRow'][$catLabelField];
			$markerArray['###itemUid###'] = $this->internal['currentRow']['uid'];
			if($this->piVars['category'] == $this->internal['currentRow']['uid']) {
				$markerArray['###class###'] = 'act';
				$markerArray['###selected###'] = 'selected="selected"';
			} else {
				$markerArray['###class###'] = 'no';
				$markerArray['###selected###'] = '';
			}
			$items .= $this->cObj->substituteMarkerArrayCached($template['menu'], $markerArray);
		}
		$this->tableName = $bakTable;
		$subpartArray['###menu###'] = $items;
		$markerArray['###categorySearchLabel###'] = $this->pi_getLL('categorySearchLabel');
		$content = $this->cObj->substituteMarkerArrayCached($template['total'], $markerArray, $subpartArray);

		return $content;
	}

	/**
	 * Functions lists all items, but ordered by category
	 *
	 * Requires member variable "categoryTable" to be set
	 *
	 * @param 	Integer		$step		Step to determin which result we want
	 * @return	String		html
	 */
	function listByCategory($step=-1) {
		global $TCA;
		
		$limit = '';
		if ($step>=0) {
			$limit = intval($step).',1';
		}

		$catsPID = $this->getTSFFvar('categoryPidList');
		if($catsPID == '{$plugin.tx_x4econgress_pi1.pidList}') $catsPID = $this->getTSFFvar('pidList');
		
		$addWhere = '';
			// if category is selected, show only this category
		if (intval($this->piVars['category'])>0) {
			$addWhere .= ' AND '.$this->categoryTable.'.uid = '.intval($this->piVars['category']);
		}

		$sortBy = '';
		if (isset($TCA[$this->categoryTable]['ctrl']['sortby'])) {
			$sortBy = $TCA[$this->categoryTable]['ctrl']['sortby'];
		} elseif (isset($TCA[$this->categoryTable]['ctrl']['default_sortby'])) {
			$sortBy = trim(str_replace('ORDER BY','',$TCA[$this->categoryTable]['ctrl']['default_sortby']));
		}

		$cats = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*',$this->categoryTable,$this->categoryTable.'.pid IN ('.$catsPID.')'.$this->cObj->enableFields($this->categoryTable).$addWhere,$sortBy,$limit);

		if ($this->template == '') {
			$this->template = $this->cObj->fileResource($this->conf['listView.']['categoryViewTemplate']);
		}

		if ($this->template == '') {
			return 'No template found...';
		}

		$tmpl = $this->cObj->getSubpart($this->template,'###listView###');
		while($c = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($cats)) {
			$this->currentCategory = $c;
			$sub['###listView###'] .= $this->renderCategory($c);
		}
		unset($m,$s,$tmpl,$c);
		$GLOBALS['TYPO3_DB']->sql_free_result($cats);
		return $this->cObj->substituteMarkerArrayCached($this->template,array(),$sub);
	}

	/**
	 * Renders a category
	 *
	 * @param	Array	$category	Category record (by reference)
	 * @return 	String				Rendered category
	 *
	 */
	function renderCategory(&$category) {
		global $TCA;
		t3lib_div::loadTCA($this->categoryTable);

		if (isset($TCA[$this->tableName]['columns'][$this->categoryField]['config']['MM'])) {
			$where = $GLOBALS['TYPO3_DB']->SELECTquery('uid_local',$TCA[$this->tableName]['columns'][$this->categoryField]['config']['MM'],'uid_foreign = '.$category['uid']);
			$where = 'uid IN ('.$where.')';
		} else {
			$where = $this->categoryField.'='.intval($category['uid']);
		}
		
		$s['###list###'] = $this->listView(' AND '.$where);
		$bakTable = $this->tableName;
		$this->tableName = $this->categoryTable;
		$this->internal['currentRow'] = $category;
		$this->getLanguageOverlay();
		$m['###categoryLabel###'] = $this->internal['currentRow'][$TCA[$this->categoryTable]['ctrl']['label']];
		$m['###categoryUid###'] = $this->internal['currentRow']['uid'];
		$this->tableName = $bakTable;
		return $this->cObj->substituteMarkerArray($s['###list###'],$m);
	}


	/**
	 * Returns number of categories
	 *
	 * @return 	Integer
	 */
	function numberOfCategories() {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('count(*)',$this->categoryTable,$this->categoryTable.'.pid IN ('.$this->getTSFFvar('pidList').')'.$this->cObj->enableFields($this->categoryTable),'',$TCA[$this->categoryTable]['ctrl']['sortby']);
		return $res[0]['count(*)'];
	}

	/**
	 * Sets some inital variables for the list view
	 * 
	 * return void
	 *
	 */
	function initListView() {
		$this->lConf = $lConf = $this->conf['listView.'];	// Local settings for the listView function
		$this->pi_alwaysPrev = $this->lConf['alwaysPrev'];
		$this->viewMode = 'listView';

		//if (!isset($this->piVars['pointer']))	$this->piVars['pointer']=0;
		
		if (!isset($this->piVars['sort']))	$this->internal['orderBy']=array_shift(explode(',',$this->internal['orderByList']));

			// Initializing the query parameters:
		if (isset($this->piVars['sort'])) {
			list($this->internal['orderBy'],$this->internal['descFlag']) = explode(':',$this->piVars['sort']);
		}

		if ($this->internal['orderBy'] == '') {
			$this->internal['orderBy']=array_shift(explode(',',$this->internal['orderByList']));
		}

		$this->internal['results_at_a_time']=t3lib_div::intInRange($lConf['results_at_a_time'],0,1000,3);		// Number of results to show in a listing.
		$this->internal['maxPages']=t3lib_div::intInRange($lConf['maxPages'],0,1000,2);;		// The maximum number of "pages" in the browse-box: "Page 1", 'Page 2', etc.

		//$this->internal['results_at_a_time'] = $lConf['results_at_a_time'];

		$this->internal['showFirstLast'] = $lConf['showFirstLast'];
	}

	/**
	 * Lists records, but one after the other (no table!)
	 *
	 * @param	string	$addwhere	Additional where for the query
	 * @return	string	html
	 */
	function listOfDetailView($addWhere='') {
		if ($this->template == '') {
			$this->template = $this->cObj->fileResource($this->conf['listView.']['template']);
		}

		if (trim($this->template) == '') {
			return 'No template available. According to the current TS-Template, the template is supposed to be at '.$this->conf['listView.']['template'];
		}

		$addWhere .= $this->getDefinedWhereCondition();
		$addWhere .= $this->addSearchParameters();

		$this->initListView();

		$tmpl = $this->cObj->getSubpart($this->template,'###listOfDetailView###');

		$res = $this->pi_exec_query($this->internal['currentTable'],1,$addWhere);
		
		list($this->internal['res_count']) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		
		$mArr = $this->globalMarkerArray;
		if ($this->internal['res_count'] > 0) {
			$res = $this->pi_exec_query($this->internal['currentTable'],0,$addWhere);
			$tmplBak = $this->template;
			$uidBackup = $this->piVars['showUid'];
			unset($this->piVars['showUid']);
			$this->template = $this->cObj->getSubpart($tmpl,'###listElements###');
			while($this->internal['currentRow'] = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$subArr['###listElements###'] .= $this->singleView();
			}
			
			$this->piVars['showUid'] = $uidBackup;
			$this->template = $tmplBak;
			$mArr['###noResultFound###'] = '';
				// Adds the result browser:
			if ($this->internal['res_count'] <= $this->internal['results_at_a_time']) {
				$mArr['###pageBrowser###'] = '';
			} else {
				$mArr['###pageBrowser###'] = $this->pi_list_browseresults($this->conf['listView.']['showResultCount'],'',$this->conf['listView.']);
			}
		} else {
			$subArr['###list###'] = '';
			$subArr['###listElements###'] = '';
			$mArr['###noResultFound###'] = $this->noResultFound();
			$mArr['###pageBrowser###'] = '';
		}
		$subArr['###linkBox###'] = $this->cObj->substituteMarker($this->cObj->getSubpart($this->template,'###linkBox###'),'###backLink###',$this->getBackLink());
		return $this->cObj->substituteMarkerArrayCached($tmpl,$mArr,$subArr);
	}


	/**
	 * Lists records alphabetically and creates a menu that ist
	 * configured via typoscript.
	 *
	 * @return html
	 */
	function listByAlphabet()	{
		$items = $this->conf['alphabeticalList.']['menu.'];
		$alphabet = 'abcdefghijklmnopqrstuvwxyz';
		// create menu
		$content .= $this->getAlphabeticalMenu($items);
		// create additional where statement
		$filter = strtolower($this->piVars['alphabetFilter']);
		if($filter != '') {
			$filterArr = explode('-', $filter);
			$begin = strpos($alphabet, trim($filterArr[0]));
			$end = strrpos($alphabet, trim($filterArr[1]));
			$chars = substr($alphabet, 0, $end+1);
			$chars = substr($chars, $begin);
			$conditions = array();
			for($i = 0; $i < strlen($chars); $i++) {
				$char = substr($chars, $i, 1);
				array_push($conditions, $this->conf['alphabeticalList.']['orderByField'].' LIKE "'.$char.'%"');
				array_push($conditions, $this->conf['alphabeticalList.']['orderByField'].' LIKE "\"'.$char.'%"');
			}
			$where = 'AND ('.implode(' OR ', $conditions).')';
		}
			else $where = '';
		$content .= $this->listView($where);
		return $content;
	}

	/**
	 * Creates a typoscript-configured alphabetical menu
	 * 
	 * @param 	array	items
	 * @return	string	html
	 *
	 */
	function getAlphabeticalMenu($items) {
		$templateCode = $this->cObj->fileResource($this->conf['alphabeticalList.']['template']);
		$template['total'] = $this->cObj->getSubpart($templateCode, '###alphabeticalMenu###');
		$template['menu'] = $this->cObj->getSubpart($template['total'], '###menu###');
		foreach($items as $item) {
			$params = array('parameter' => $GLOBALS['TSFE']->id);
			if($item == 'all') {
			} else {
					$params['additionalParams'] = '&'.$this->prefixId.'[alphabetFilter]='.$item;
			}
			// Aktiver Status
			if($this->piVars['alphabetFilter'] == $item) $params['ATagParams'] = 'class="active-alphabetfilter"';
			$markerArray['###item###'] = $this->cObj->typolink($item, $params);
			$menuItems .= $this->cObj->substituteMarkerArrayCached($template['menu'], $markerArray);
		}
		$subpartArray['###menu###'] = $menuItems;
		$content = $this->cObj->substituteMarkerArrayCached($template['total'], $markerArray, $subpartArray);
		return $content;
	}


	/**
	 * Adds search parameters given by the form to the query
	 *
	 * @return 	String		SQL Where statement
	 */
	function addSearchParameters() {
		global $TCA;
		$queries = array();
		foreach($this->piVars as $k=>$v) {
			if (intval($v)>0) {
				$conf = $TCA[$this->tableName]['columns'][$k]['config'];
				if (isset($conf['foreign_table'])) {
					if (isset($conf['MM'])) {
						$q = 'uid IN ('.$GLOBALS['TYPO3_DB']->SELECTquery('uid_local',$conf['MM'],'uid_foreign='.intval($v)).')';
						array_push($queries,$q);
						unset($q);
					} else {
						array_push($queries,$k.'='.intval($v));
					}
				}
			}
		}
		if (count($queries)>0) {
			return ' AND '.implode(' AND ',$queries);
		} else {
			return '';
		}
	}

	/**
	 * Function to create a default table-like list view
	 *
	 * @param	String 	$addWhere	Additional where condition to select the records
	 * @return	String				HTML-View of list
	 */
	function listView($addWhere=''){
		if ($this->template == '') {
			$this->template = $this->cObj->fileResource($this->conf['listView.']['template']);
		}

		if ($this->template == '') {
			return 'No template for list view found. File: '.$this->conf['listView.']['template'];
		}

		if ($this->listT == '') {
			$this->listT = $this->cObj->getSubpart($this->template,'###list###');
		}

		$this->rowsT = $this->cObj->getSubpart($this->listT,'###rows###');
		$this->rowT[0] = $this->cObj->getSubpart($this->rowsT,'###row0###');
		$this->rowT[1] = $this->cObj->getSubpart($this->rowsT,'###row1###');
		$this->cellT[0] = $this->cObj->getSubpart($this->rowT[0],'###cell###');
		$this->cellT[1] = $this->cObj->getSubpart($this->rowT[1],'###cell###');
		$this->lConf = $lConf = $this->conf['listView.'];	// Local settings for the listView function

		$this->initListView();
		$addWhere .= $this->getDefinedWhereCondition();
		$addWhere .= $this->addSearchParameters();

		$res = $this->pi_exec_query($this->tableName,1,$addWhere);
		list($this->internal['res_count']) = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		
		if ($this->internal['res_count'] > 0) {
			$res = $this->getListResultSet($this->tableName,$addWhere);
			//$res = $this->pi_exec_query();
				// Adds the whole list table
			$subArr['###list###'] = $this->pi_list_makelist($res);
			$mArr['###noResultFound###'] = '';

			// Adds the result browser:
			if ($this->internal['res_count'] <= $this->internal['results_at_a_time']) {
				$mArr['###pageBrowser###'] = '';
			} else {
				$mArr['###pageBrowser###'] = $this->pi_list_browseresults(0,'',$this->conf['listView.']);
			}
		} else {
			$subArr['###list###'] = '';
			$mArr['###noResultFound###'] = $this->noResultFound();
			$mArr['###pageBrowser###'] = '';
		}
			// Adds the search box:
		$mArr['###search###'] = $this->pi_list_searchBox();
		$mArr = $this->additionalListMarkers($mArr);
		$tmpl = $this->cObj->getSubpart($this->template,'###listView###');
		return $this->cObj->substituteMarkerArrayCached($tmpl,$mArr,$subArr);
	}

	/**
	 * Generates an additional marker array
	 *
	 * @param 	array $mArr
	 * @return 	array
	 */
	function additionalListMarkers ($mArr){
		return $mArr;
	}


	/**
	 * Generates the correct result set (incl. order by foreign tables)
	 *
	 * @param	String	$tableName		Name of the table
	 * @param	String	$addWhere		Additional where clause
	 * 
	 * @return	object	SQL result set
	 */
	function getListResultSet($tableName,$addWhere='') {
		global $TCA;
		if (isset($this->internal['orderBy'])) {
			$conf = $TCA[$tableName]['columns'][$this->internal['orderBy']]['config'];
			if (isset($conf['foreign_table']) && !isset($conf['MM'])) {
				$queryParts = $this->pi_list_query($tableName,0,$addWhere,'','','','',true);
				$queryParts['FROM'] .= ' LEFT JOIN '.$conf['foreign_table'].' ON '.$conf['foreign_table'].'.uid='.$tableName.'.'.$this->internal['orderBy'];
				$queryParts['ORDERBY'] = $conf['foreign_table'].'.'.$TCA[$conf['foreign_table']]['ctrl']['label'].($this->internal['descFlag']?' DESC':'');
				return $GLOBALS['TYPO3_DB']->exec_SELECT_queryArray($queryParts);
			}
		}
		return $this->pi_exec_query($tableName,0,$addWhere);
	}
	

	/**
	 * Returns additional where parameters which are defined in typoscript
	 *
	 * Example:
	 * TS: whereFields = category
	 *
	 * Now, if a get parameter "category" is given, it'll be added to the where
	 * clause.
	 *
	 * @return String	Additional where condition
	 */
	function getDefinedWhereCondition() {
		$parameters = t3lib_div::trimExplode(',',$this->conf['whereFields'],1);
		$conds = array();
		if ($this->conf['addtionalWhereCondition']) {
			array_push($conds, $this->conf['addtionalWhereCondition']);
		}
		global $TCA;
		foreach($parameters as $p) {
			$conf = $TCA[$this->tableName]['columns'][$p];
			if (isset($this->piVars[$p])) {
				array_push($conds,$p.'='.intval($this->piVars[$p]));
			}
			// if condition is a foreign table, add the value to the global
			// markers, as it might be interessting to display
			if(isset($conf['config']['foreign_table'])) {
				$fTable = $conf['config']['foreign_table'];
				$rec = $this->pi_getRecord($fTable,$this->piVars[$p]);
				$this->globalMarkerArray['###'.$p.'###'] = $rec[$TCA[$fTable]['ctrl']['label']];
			}
		}
		if (count($conds)>0) {
			return ' AND '.implode(' AND ',$conds);
		} else {
			return '';
		}
	}

	/**
	 * Retrieves field content, processed, prepared for HTML output.
	 *
	 * @param	string		Fieldname
	 * @return	string		Content, ready for HTML output.
	 */
	function getFieldContent($fN)	{
		global $TCA;
		$t = $TCA[$this->internal['currentTable']]['columns'][$fN]['config'];
		$this->handleStdWrap($fN);
		switch($t['type']) {
			case 'input':
				$out = $this->getInputContent($fN,$t);
			break;
			case 'text':
				if (isset($t['wizards']['RTE'])) {
					return $this->pi_RTEcssText($this->internal['currentRow'][$fN]);
				} else {
					$out = $this->internal['currentRow'][$fN];
					if (!in_array($fN,$this->skipHtmlEntitiesFields) && !($this->skipHtmlEntitiesFields[0]=='all')) {
						$out = htmlentities($out);
					}
					return nl2br($out);
				}
			break;
			case 'group':
				switch($t['internal_type']){
					case 'file':
						// images
						if($t['allowed'] == $GLOBALS["TYPO3_CONF_VARS"]["GFX"]["imagefile_ext"]) {
							return $this->getImages($t,$fN);
						} else {
								$fileArr = explode(',', $this->internal['currentRow'][$fN]);
								$fileLinkConf = $this->conf['filelink'];
								
								$fileLinkConf['path'] = $t['uploadfolder'].'/';
								$fileLinks = array();
								foreach ($fileArr as $file)	{
									array_push($fileLinks, $this->cObj->filelink($file,$fileLinkConf));
								}
								return implode(', ', $fileLinks);
							}
					break;
					default:
						$out = $this->internal['currentRow'][$fN];
					break;
				}
			break;
			case 'select':
				if ($t['foreign_table'] != '') {
					$out = $this->getRelation($t['foreign_table'],$fN);
				} else {
					$out = $this->internal['currentRow'][$fN];
				}
			break;
			default:
				switch($fN) {
					case 'crdate':
					case 'tstamp':
						if (intval($this->internal['currentRow'][$fN])) {
							$out = strftime($this->conf['dateFormat'],$this->internal['currentRow'][$fN]);
						} else {
							$out = '';
						}
					break;
					default:
						$out = $this->internal['currentRow'][$fN];
					break;
				}
			break;
		}

		// handle utf8 encoding
		if (in_array($fN,$this->conf['utf8DecodeFields']) || ($this->conf['utf8DecodeFields'][0]=='all')) {
			$out = utf8_decode($out);
		}

		if (!in_array($fN,$this->skipHtmlEntitiesFields) && !($this->skipHtmlEntitiesFields[0]=='all')) {
			$out = htmlentities($out);
		}
		
		$out = $this->handlePostStdWrap($out);
		
		// check if list mode
		if ((!isset($this->piVars['showUid']) || ($this->conf['detailView.']['enableDetailLinks']==1)) && in_array($fN,$this->conf['listView.']['detailLinkFields'])) {
			$out = $this->pi_linkTP_keepPIvars($out,array('showUid'=>$this->internal['currentRow']['uid']),1,0,$this->conf['listView.']['detailPageUid']);
		}

		return $out;
	}
	
	/**
	 * Applies the standardwrap after field has been retrieved
	 * 
	 * @param 	string 	$out	Field content
	 * @return	string	Standard-wrapped field content
	 */
	function handlePostStdWrap($out){
		$stdWrapConf = array();
		$stdWrapConf = $this->conf['fields.'][$fN.'.']['post_stdWrap.'];
		return $this->cObj->stdWrap($out,$stdWrapConf);
	}

	/**
	 * Create images out of database field
	 *  
	 * @param array 	$t		internal_type field (TCA)
	 * @param string 	$fN		field name
	 * 
	 * @return string	image tags (HTML)
	 */
	function getImages($t, $fN) {
		$imgTSConfig = $this->conf['imageConfig.']['pictures.'];
		$fileString = '';
		$fileArr = explode(',', $this->internal['currentRow'][$fN]);
		foreach ($fileArr as $file) {
			$imgTSConfig['file'] = $t['uploadfolder'].'/'.$file;
			$fileString .= $this->cObj->IMAGE($imgTSConfig);
		}
		return $fileString;
	}

	/**
	 * Get the content of a field with type "input"
	 *
	 * @param	String	$fN		Name of the field
	 * @param 	Array	$t		TCA config of this field
	 * @return	String			Handled content;
	 */
	function getInputContent($fN,&$t) {
		if (strpos($t['eval'],'date')!== false) {
			if ($this->internal['currentRow'][$fN] != 0) {
				$out = strftime($this->conf['dateFormat'],$this->internal['currentRow'][$fN]);
			} else {
				return '';
			}
		} elseif (isset($t['wizards']['link'])) {
			if ($this->internal['currentRow'][$fN.'Original'] == '') {
				$this->internal['currentRow'][$fN.'Original'] = $this->internal['currentRow'][$fN];
				$this->internal['currentRow'][$fN] = '';
			}
			$out = $this->cObj->getTypoLink(htmlentities($this->internal['currentRow'][$fN]),$this->internal['currentRow'][$fN.'Original']);
			array_push($this->skipHtmlEntitiesFields,$fN);
		} else {
			$out = $this->internal['currentRow'][$fN];
		}
		if($fN == 'email'){
			$out = $this->cObj->typoLink(htmlentities($this->internal['currentRow'][$fN]),array('parameter'=>$this->internal['currentRow'][$fN]));
			array_push($this->skipHtmlEntitiesFields,$fN);
		}
		return $out;
	}

	/**
	 * Checks wheter there is any stdwrap set for this field
	 *
	 * @param String	$fN		Fieldname
	 * 
	 * @return void
	 */
	function handleStdWrap($fN) {
		if (isset($this->conf['fields.'][$fN.'.'])) {
			// keep original
			$this->internal['currentRow'][$fN.'Original'] = $this->internal['currentRow'][$fN];
			$this->internal['currentRow'][$fN] = $this->cObj->stdWrap($this->internal['currentRow'][$fN],$this->conf['fields.'][$fN.'.']);
		}
	}


	/**
	 * Returns the label field of the related record. Saves the values in
	 * member variable to provide a caching mechanism
	 *
	 * @param 	string $table	Name of the related table
	 * @param	string $fN		Field name
	 * @return	string			Label of the related record
	 */
	function getRelation($table,$fN) {
		global $TCA;
		if (isset($TCA[$this->tableName]['columns'][$fN]['config']['MM'])) {
			return $this->getMMRelation($table,$fN);
		} else {
			// get regular fields
			$id = $this->internal['currentRow'][$fN];
			if (!isset($this->relatedRecords[$table][$id])) {
				$r = $this->pi_getRecord($table,$id);
				if (isset($r['uid'])) {
					$this->relatedRecords[$table][$id] = $r[$TCA[$table]['ctrl']['label']];
				} else {
					$this->relatedRecords[$table][$id]='';
				}
				unset($r);
			}
			return $this->relatedRecords[$table][$id];
		}
	}

	/**
	 * Renders the records of the mm relation so it can be display in the original
	 * record. Makes another instance of itself to render the relations in a standard
	 * list view
	 * 
	 * @param	string	$table	table name of the related table
	 * @param	string	$fN		field name in the current table
	 *
	 * @return	stirng	html	List view of the related records
	 */
	function getMMRelation($table,$fN) {
		global $TCA;
		array_push($this->skipHtmlEntitiesFields,$fN);
		$pi = t3lib_div::makeInstance('x4epibase');
		if (is_array($this->conf['mmRelatedConfig.'][$table.'.'])) {
			$pi->init('',array_merge($this->conf,$this->conf['mmRelatedConfig.'][$table.'.']));
		}
		$pi->tableName = $table;
		$pi->cObj = $this->cObj;
		$mmTable = $TCA[$this->tableName]['columns'][$fN]['config']['MM'];
		$pi->conf['addtionalWhereCondition'] = 'uid IN ('.$GLOBALS['TYPO3_DB']->SELECTquery('uid_foreign',$mmTable,'uid_local='.$this->internal['currentRow']['uid']).')';
		$pi->template = $this->cObj->getSubpart($this->completeTemplate,'###'.$fN.'RelationBox###');
		return $pi->listView();
	}


	/**
	 * Returns the list of items based on the input SQL result pointer
	 * For each result row the internal var, $this->internal['currentRow'], is set with the row returned.
	 * $this->pi_list_header() makes the header row for the list
	 * $this->pi_list_row() is used for rendering each row
	 * Notice that these two functions are typically ALWAYS defined in the extension class of the plugin since they are directly concerned with the specific layout for that plugins purpose.
	 *
	 * @param	pointer		Result pointer to a SQL result which can be traversed.
	 * @param	array		Set of already fetched rows instead of a DB result pointer
	 * @return	string		Output HTML, wrapped in <div>-tags with a class attribute
	 * @see pi_list_row(), pi_list_header()
	 */
	function pi_list_makelist($res, $rowSet = array())	{
		// get all templates
		if ($this->manualFieldOrder_list == ''){
			$this->manualFieldOrder_list = $this->fields;
		}
		if ($this->listT == '') {
			$this->listT = $this->cObj->getSubpart($this->template,'###list###');
		}
		$this->rowsT = $this->cObj->getSubpart($this->listT,'###rows###');
		$this->rowT[0] = $this->cObj->getSubpart($this->rowsT,'###row0###');
		$this->rowT[1] = $this->cObj->getSubpart($this->rowsT,'###row1###');
		$this->cellT[0] = $this->cObj->getSubpart($this->rowT[0],'###cell###');
		$this->cellT[1] = $this->cObj->getSubpart($this->rowT[1],'###cell###');
		// Make list table header:
		$tRows=array();
		$this->internal['currentRow']='';
		// get header and replace marker
		if(!$this->conf['listView.']['hideHeaderRow']){
			$out = $this->cObj->substituteSubpart($this->listT,'###headRow###',$this->pi_list_header());
		}
		
			// Make list table rows
		$c=0;
		$rows = '';
		$simpleRows = array();
		if ($res !== NULL) {
			while($this->internal['currentRow'] = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				if ($this->conf['listView.']['simpleList']==1) {
					$simpleRows[] = $this->pi_list_row($c);
				} else {
					$rows .= $this->pi_list_row($c);
				}
				$c++;
			}
		} elseif (count($rowSet) > 0) {
			foreach ($rowSet as $row) {
				$this->internal['currentRow'] = $row;
				if ($this->conf['listView.']['simpleList']==1) {
					$simpleRows[] = $this->pi_list_row($c);
				} else {
					$rows .= $this->pi_list_row($c);
				}
				$c++;
			}
		}
		// make a simple list
		if ($this->conf['listView.']['simpleList']==1) {
			$rows = implode('|',$simpleRows);
			$array = t3lib_div::trimExplode('|',$rows,1);
			$rows = implode($this->conf['listView.']['simpleListSeparator'],$array);
		}
		
		return $this->cObj->substituteSubpart($out,'###rows###',$rows);
	}


	/**
	 * Displays single view of a record. It's possible to give a record,
	 * otherwise, the function gets the one in the piVars['showUid']
	 *
	 * @return	string				HTML-View of record
	 */
	function singleView(){
		global $TCA;
		if (isset($this->piVars['showUid'])) {
			$this->internal['currentRow'] = $this->pi_getRecord($this->tableName,intval($this->piVars['showUid']));
		}

		$this->getLanguageOverlay();

		if ($this->template == '') {
			$this->template = $this->cObj->fileResource($this->conf['detailView.']['template']);
		}

		if ($this->template == '') {
			return 'No detail view template found';
		}

		$tmpl = $this->cObj->getSubpart($this->template,'###singleView###');
		$this->completeTemplate = $this->template;

		if (isset($TCA[$this->tableName]['ctrl']['type']) && ($this->conf['ignoreTypeTemplate'] != 1)) {
			$this->template = $this->cObj->getSubpart($this->template,'###type'.$this->internal['currentRow'][$TCA[$this->tableName]['ctrl']['type']].'Box###');
		}

		foreach($this->internal['currentRow'] as $k=>$v){
			$sub['###'.$k.'Box###'] = $this->getBoxedFieldContent($k);
		}
		$mArr['###backLink###'] = $this->getBackLink();
		$mArr['###backLabel###'] = $this->pi_getLL('back');
		if ($this->conf['addTitleToPageTitle']) {
			$GLOBALS['TSFE']->page['title'] .= ' - '.$this->internal['currentRow'][$TCA[$this->tableName]['ctrl']['label']];
		}

		if (isset($TCA[$this->tableName]['ctrl']['type'])) {
			$mArr['###content###'] = $this->cObj->substituteMarkerArrayCached($this->template,array(),$sub);
		}
		return $this->cObj->substituteMarkerArrayCached($tmpl,$mArr,$sub);
	}

	/**
	 * Generates a link back from the detail to the list view
	 * 
	 * @return 	string	Link tag
	 */
	function getBackLink(){
		$id = $this->getTSFFvar('listPageUid');
		if ($id == '') {
			$id = $this->piVars['back'];
			if ($id == '') {
				$id = $GLOBALS['TSFE']->id;
			}
		}
		return $this->pi_linkTP_keepPIvars_url(array('showUid'=>null,'back'=>null),1,0,$id);
	}

	/**
	 * List header row, showing column names:
	 *
	 * @return	string		HTML content; a Table row, <tr>...</tr>
	 */
	function pi_list_header()	{
		$headT = $this->cObj->getSubpart($this->listT,'###headRow###');
		$cellT = $this->cObj->getSubpart($headT,'###cell###');
		$cells = '';

		foreach($this->manualFieldOrder_list as $fieldName)	{
			$cells .= $this->cObj->substituteMarker($cellT,'###content###',$this->getFieldHeader_sortLink($fieldName));
		}

		return $this->cObj->substituteSubpart($headT,'###cell###',$cells);
	}

	/**
	 * Returns a list row. Get data from $this->internal['currentRow'];
	 * 
	 * @param integer $c	Row number
	 * @return string	html, one record as a row
	 */
	function pi_list_row($c) {
		$cells = '';
		$this->getLanguageOverlay();
		foreach($this->manualFieldOrder_list as $fieldName)	{
			// add either link of field-value-only
			$mArr['###class###'] = '';
			if ($this->conf['columnClasses.'][$fieldName] != '') {
				$mArr['###class###'] = 'class="'.$this->conf['columnClasses.'][$fieldName].'"';
			}
			if (in_array($fieldName,$this->conf['listView.']['detailLinkFields'])) {
				$mArr['###content###'] = $this->pi_list_linkSingle($this->internal['currentRow'][$fieldName],$this->internal['currentRow']['uid'],true,$this->piVars,false,$this->conf['listView.']['detailPageUid']);
			} else {
				$mArr['###content###'] = $this->getFieldContent($fieldName);
			}
			$cells .= $this->cObj->substituteMarkerArray($this->cellT[$c%2],$mArr);
		}
		$this->addEditColumns($cells,$mArr);
		$mArr['###uid###'] = $this->internal['currentRow']['uid'];
		$sub['###cell###'] = $cells;
		return $this->cObj->substituteMarkerArrayCached($this->rowT[$c%2],$mArr,$sub);
	}

	/**
	 * Adds edit columns for extensions with frontend editing features
	 * 
	 * @param string $cells	[by reference] string with the rendered cells of the current record
	 * @param string $mArr	[by reference] marker array
	 *
	 * @return void
	 */
	function addEditColumns(&$cells,&$mArr) {
		if ($this->conf['edit']==1) {
			$mArr['###content###'] = $this->pi_linkTP($this->pi_getLL('edit'),array($this->prefixId.'[editUid]'=>$this->internal['currentRow']['uid'], $this->prefixId.'[action]'=>'edit'));
			$cells .= $this->cObj->substituteMarkerArray($this->cellT[$c%2],$mArr);
			$mArr['###content###'] = $this->pi_linkTP(html_entity_decode(utf8_encode($this->pi_getLL('remove'))),array($this->prefixId.'[editUid]'=>$this->internal['currentRow']['uid'], $this->prefixId.'[action]'=>'del'));
			$cells .= $this->cObj->substituteMarkerArray($this->cellT[$c%2],$mArr);
		}
	}
	
	/**
	 * Returns a Search box, sending search words to piVars "sword" and setting the "no_cache" parameter as well in the form.
	 * Submits the search request to the current REQUEST_URI
	 *
	 * @param	string		Attributes for the table tag which is wrapped around the table cells containing the search box
	 * @return	string		Output HTML, wrapped in <div>-tags with a class attribute
	 */
	function pi_list_searchBox($tableParams='')	{
		if (!$this->getTSFFvar('disableSearchBox')) {
				// Search box design:
			$mArr['###formAction###'] = $this->pi_getPageLink($GLOBALS['TSFE']->id);
			$mArr['###searchWord###'] = $this->piVars['sword'];
			$mArr['###submit###'] = $this->pi_getLL('pi_list_searchBox_search','Search',TRUE);
			$mArr['###extKey###'] = $this->prefixId;
	
			$tmpl = $this->cObj->getSubpart($this->template,'###searchBox###');
	
			global $TCA;
			if (isset($TCA[$this->tableName]['columns'])){
				foreach($TCA[$this->tableName]['columns'] as $key => $col) {
					if(isset($col['config']['foreign_table']) && (strpos($tmpl,'###search'.$key.'###') !== false)) {
						$mArr['###search'.$key.'###'] = $this->generateOptionsFromTable('<option value="###value###" ###selected###>###label###</option>',$col['config']['foreign_table'],$this->piVars[$key],true);
					}
				}
			}
			$mArr['###prefixId###'] = $this->prefixId;
			return $this->cObj->substituteMarkerArray($tmpl,$mArr);
		} else {
			return '';
		}
	}

	/**
	 * Field header name, but wrapped in a link for sorting by column.
	 *
	 * @param	string		Fieldname
	 * @return	string		Content, ready for HTML output.
	 */
	function getFieldHeader_sortLink($fN) {
		$tsContent = $this->conf['FieldHeader_sortLink.'];
		$tsType = $this->conf['FieldHeader_sortLink'];
		if ($tsContent && $tsType) {
			$localCObj = clone $this->cObj;
			$localCObj->data['extKey'] = $this->extKey;
			$localCObj->data['prefixId'] = $this->prefixId;
			$localCObj->data['sort'] = $fN . ':' . ($this->internal['descFlag'] ? 0 : 1);
			$localCObj->data['fieldHeader'] = $this->getFieldHeader($fN);
			if ($this->internal['orderBy'] == $fN) {
				$localCObj->data['orderByStatus'] = 'active';
			} else {
				$localCObj->data['orderByStatus'] = '';
			}
			return $localCObj->cObjGetSingle($tsType, $tsContent);
		} else {
			return $this->pi_linkTP_keepPIvars($this->getFieldHeader($fN),array('sort'=>$fN.':'.($this->internal['descFlag']?0:1)));
		}
	}

	/**
	 * Field header name; Getting the label for field headers.
	 *
	 * @param	string		Fieldname
	 * @deprecated Use typoscript "plugin...._LOCAL_LANG..." instead
	 *
	 * @return	string		Content, ready for HTML output.
	 */
	function getFieldHeader($fN) {
		if (isset($this->conf['overrideLabels.'][$fN])) {
			return $this->conf['overrideLabels.'][$fN];
		} else {
			switch($fN) {
				default:
					return $this->pi_getLL('listFieldHeader_'.$fN,'['.$fN.']',1);
				break;
			}
		}
	}

	/**
	 * Returns boxed content (or empty string, if field is empty), used to avoid empty labels
	 *
	 * @param	string		Fieldname
	 * @return	string		Content, ready for HTML output.
	 */
	function getBoxedFieldContent($fN){
		$tmpl = $this->cObj->getSubpart($this->template,'###'.$fN.'Box###');
		if (($tmpl != '') && ($this->internal['currentRow'][$fN]!='') && $this->checkDisplayField($fN)) {
			$mArr[$fN] = $this->getFieldContent($fN);
			$mArr[$fN.'Label'] = $this->pi_getLL($fN.'Label');
			return $this->cObj->substituteMarkerArray($tmpl,$mArr,'###|###');
		} else {
			return '';
		}
	}

	/**
	 * Checks if a field is supposed to be displayed
	 *
	 * @param 	String		$fN		Fieldname
	 * @return	Boolean
	 */
	function checkDisplayField($fN) {
		return true;
	}


	/**
	 * Returns a no-result found message according to your template and locallang
	 *
	 * First make sure your template has subpart similar to this:
	 *
	 * <!-- ###noResultFoundBox### begin -->
	 *		<p>###noResultFoundText###</p>
	 * <!-- ###noResultFoundBox### end -->
	 *
	 * Second make sure you've got a 'noResultFound' text in your locallang.php
	 *
	 * @return string
	 */
	function noResultFound() {
		return $this->cObj->substituteMarker($this->cObj->getSubpart($this->template,'###noResultFoundBox###'),'###noResultFoundText###',$this->pi_getLL('noResultFound'));
	}

	/**
	 * Creates a record using the values array, for front end editing features
	 *
	 * @param array
	 * @return void
	 */
	function createRecord($values) {
		$ins['tstamp'] = $ins['crdate'] = time();
		foreach($values as $k => $v) {
			$ins[$k] = mysql_real_escape_string($v);
		}
		$GLOBALS['TYPO3_DB']->exec_INSERTquery($this->tableName,$ins);
	}

	/**
	 * Updates a record using the values array, for front end editing features
	 *
	 * @param array
	 * @param integer $uid	Uid of the record to update
	 * @return void
	 */
	function updateRecord($values,$uid) {
		$ins['tstamp'] = time();
		foreach($values as $k => $v) {
			$ins[$k] = mysql_real_escape_string($v);
		}
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery($this->tableName,'uid='.intval($uid),$ins);
	}

	/**
	 * Sets the deleted flag of a record, for front end editing features
	 * 
	 * @param integer $uid	Uid of the record to update
	 * @return void
	 */
	function deleteRecord($uid) {
		$v['deleted']=1;
		$this->updateRecord($v,$uid);
	}

	/**
	 * Makes an instance of tcemain to clear the cache, for front end editing features
	 * 
	 * @see t3lib_tcemain
	 * @param object $cacheCmd [optional]
	 * @return void
	 */
	function clearCache($cacheCmd=0) {
		if ($cacheCmd != 0) {
			require_once(PATH_t3lib.'class.t3lib_tcemain.php');
			t3lib_TCEmain::clear_cacheCmd($cacheCmd);
		}
	}

	/** 
	 * Clears cache of sites defined in page-ts-config
	 *
	 * @TODO: is this function still needed?
	 * @param	integer	PageUid
	 * @return	void
	 */
	function clearCacheCmd($pageUid) {
		$page = $this->pi_getRecord('pages',$pageUid);
		$lines = t3lib_div::trimExplode("\n",$page['TSconfig'],1);
		foreach($lines as $l) {
			$v = t3lib_div::trimExplode('=',$l,1);
			if (isset($v[0]) && ($v[0] == 'TCEMAIN.clearCacheCmd')) {
				if (isset($v[1]) && ($v[1]!= '')) {
					require_once(PATH_t3lib.'class.t3lib_tcemain.php');
					$pageIds = t3lib_div::trimExplode(',',$v[1],1);
					foreach($pageIds as $p) {
						t3lib_TCEmain::clear_cacheCmd($p);
					}
					return;
				}
			}
		}
	}

	/**
	 * Generates options for a country menu
	 *
	 * @see generateOptionFromTable
	 * 
	 * @param	string	$selected	country to select
	 * @param	boolean	$addEmpty	adds an empty option to the select box
	 * @param	boolean $EUonly		limits the selection to EU & Switzerland
	 * @return	string	html, <options>-Tags
	 */
	function generateCountryOptions($selected='',$addEmpty=false,$EUonly =true) {
		if ($EUonly) {
			$addWhere = ' AND (cn_eu_member = 1 OR uid = 41)';
		}
		return $this->generateOptionsFromTable('','static_countries',$selected,$addEmpty,'cn_short_de','cn_short_de',$addWhere,'cn_short_de');
	}

	/**
     * Generates options for the select menu
     *
     * @param	string	$tmpl		Template to use
     * @param	array	$tableName	Name of the table where to get the info
     * @param	array	$selected	Uid of selected
     * @param	bool	$addEmpty	If true empty element will be inserted
     * @return	string				html string with <options>
     */
    function generateOptionsFromTable($tmpl,$tableName,$selected='',$addEmpty=false, $labelKey='', $valueKey='', $addWhere='',$orderBy='',$labelAlias='') {
    	if($tmpl == '') {
    		$tmpl = '<option value="###value###" ###selected###>###label###</option>';
    	}

    	// get correct sql statement
    	global $TCA;
    	if ($labelKey=='') {
    		$labelKey = $TCA[$tableName]['ctrl']['label'];
    	}
		
	if($labelAlias == ''){
    		$labelAlias = $labelKey;
    	}
		
    	if ($valueKey == '') {
    		$valueKey = 'uid';
    	}
    	$fields = $valueKey.','.$labelKey;

    	if ($valueKey != 'uid') {
    		$fields .=', uid';
    	}

    	$where = '1 '.$this->cObj->enableFields($tableName).$addWhere;

    	if ($orderBy == '') {
    		$orderBy = $TCA[$tableName]['ctrl']['sortby'];
    	}
		    	
    	// run statement
    	$opts = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$tableName,$where,'',$orderBy);
    	// free variables
    	unset($fields,$where,$orderBy);

    	// loop the result
       	while ($opt = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($opts)) {

    		$mArr['###label###'] = $opt[$labelAlias];
    		$mArr['###value###'] = $opt[$valueKey];

    		if ($opt['uid'] == $selected) {
    			$mArr['###selected###'] = 'selected="selected"';
    			$chosen = true;
    		} else {
    			$mArr['###selected###'] = '';
    		}
    		$returnStr .= $this->cObj->substituteMarkerArray($tmpl,$mArr);
    	}

    	// Add empty elment at the beginning, and select if no other element is selected
    	if ($addEmpty) {
    		$mArr['###label###'] = '';
    		$mArr['###value###'] = '';
    		if (!$chosen) {
    			$mArr['###selected###'] = 'selected="selected"';
    		} else {
    			$mArr['###selected###'] = '';
    		}
    		$returnStr = $this->cObj->substituteMarkerArray($tmpl,$mArr).$returnStr;
    	}
    	return $returnStr;
    }

	/**
	 * Adds the neccessary fValidate javascript files and minimal styling, for front end editing features
	 * 
	 * @deprecated use addJSValidation
	 * @return void
	 */
    function addfValidate() {
    	$GLOBALS['TSFE']->additionalHeaderData[$this->extKey].='
				<script type="text/javascript" src="typo3conf/ext/x4epibase/fValidate/fValidate.config.js"></script>
				<script type="text/javascript" src="typo3conf/ext/x4epibase/fValidate/fValidate.core.js"></script>
				<script type="text/javascript" src="typo3conf/ext/x4epibase/fValidate/fValidate.lang-enUS.js"></script>
				<script type="text/javascript" src="typo3conf/ext/x4epibase/fValidate/fValidate.validators.js"></script>
				<script language="javascript" type="text/javascript">
					var dontValidate = false;
				</script>
				<style type="text/css">
					.errHilite {
						color: #ff0000;
					}
					input.errHilite {
						background-color: #cccccc;
						color: black;
					}
				</style>
				';
    }
    
    /**
    * Writes piVars into a marker array
    * @return array marker-array
    */
    function getPiVarsIntoMarkerArray(){
		$mArr = array();
		foreach($this->piVars as $k=>$v) {
			$mArr['###'.$k.'###'] = $v;
		}
		return $mArr;
	}


	/**
	 * Adds validation javascript file, for front end editing features
	 * 
	 * @param 	string $formId	Id of the form to validate
	 * @return 	string	html-script tag to place below form
	 */
    function addJSValidation($formId) {
    	$GLOBALS['TSFE']->additionalHeaderData[$this->extKey].='
				<script type="text/javascript" src="typo3conf/ext/x4epibase/js/validation.js"></script>';
    	return '<script type="text/javascript">
     				new Validation("'.$formId.'");
				</script>';

    }

    /**
     * Returns human readable filesizes
     * @param integer 	$size	filesize in bytes
     * @return string	filesize in appropriate unit
     */
	function formatFilesize($size, $precision=0){
		  $i=0;
		  $iec = array("B", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB");
		  while (($size/1024)>1) {
		   $size=$size/1024;
		   $i++;
		  }
		  return round($size,$precision).' '.$iec[$i];
	}

    /**
	 * returns either the flexform or the ts-variable, flexform overrules ts
	 * 
	 * @param	string	$varName	Name of the variable (= flexform-tag name or plugin.tx_..._pi1.name)
	 * @return	string	value of the variable
	 */
	function getTSFFvar($varName,$page='sDEF') {
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],$varName,$page) != '') {
		    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],$varName,$page);
		} else {
			return $this->conf[$varName];
		}
	}


	/**
	 * Removes specialchars, whitespace etc.
	 *
	 * @deprecated I recommend t3lib_extFileFunctions for filehandling
	 * @param string $text
	 * @return string
	 */
	function websafeFilename($text) {
		$text = str_replace('ä','ae',$text);
		$text = str_replace('ö','oe',$text);
		$text = str_replace('ü','ue',$text);
		$text = str_replace('Ä','Ae',$text);
		$text = str_replace('Ö','Oe',$text);
		$text = str_replace('Ü','Ue',$text);
		$text = str_replace(' ','_',$text);

		return preg_replace("/[^a-zA-Z0-9\-_\.]+/", "_", $text);
	}

	/**
	 * Makes a standard query for listing of records based on standard input vars from the 'browser' ($this->internal['results_at_a_time'] and $this->piVars['pointer']) and 'searchbox' ($this->piVars['sword'] and $this->internal['searchFieldList'])
	 * Set $count to 1 if you wish to get a count(*) query for selecting the number of results.
	 * Notice that the query will use $this->conf['pidList'] and $this->conf['recursive'] to generate a PID list within which to search for records.
	 *
	 * @param	string		See pi_exec_query()
	 * @param	boolean		See pi_exec_query()
	 * @param	string		See pi_exec_query()
	 * @param	mixed		See pi_exec_query()
	 * @param	string		See pi_exec_query()
	 * @param	string		See pi_exec_query()
	 * @param	string		See pi_exec_query()
	 * @param	boolean		If set, the function will return the query not as a string but array with the various parts.
	 * @return	mixed		The query build.
	 * @access private
	 * @deprecated		Use pi_exec_query() instead!
	 */
	function pi_list_query($table,$count=0,$addWhere='',$mm_cat='',$groupBy='',$orderBy='',$query='',$returnQueryArray=FALSE)	{
			// Begin Query:
			
		if(isset($this->conf['includeHiddenRecords'])){
			$hidden = $this->conf['includeHiddenRecords'];
		} else {
			$hidden = 0;
		}

		if (!$query)	{
				// Fetches the list of PIDs to select from.
				// TypoScript property .pidList is a comma list of pids. If blank, current page id is used.


				// TypoScript property .recursive is a int+ which determines how many levels down from the pids in the pid-list subpages should be included in the select.
			$pidList = $this->pi_getPidList($this->conf['pidList'],$this->conf['recursive']);
			if (is_array($mm_cat))	{
				$query='FROM '.$table.','.$mm_cat['table'].','.$mm_cat['mmtable'].chr(10).
						' WHERE '.$table.'.uid='.$mm_cat['mmtable'].'.uid_local AND '.$mm_cat['table'].'.uid='.$mm_cat['mmtable'].'.uid_foreign '.chr(10).
						(strcmp($mm_cat['catUidList'],'')?' AND '.$mm_cat['table'].'.uid IN ('.$mm_cat['catUidList'].')':'').chr(10).
						' AND '.$table.'.pid IN ('.$pidList.')'.chr(10).
				$this->cObj->enableFields($table,$hidden).chr(10);	// This adds WHERE-clauses that ensures deleted, hidden, starttime/endtime/access records are NOT selected, if they should not! Almost ALWAYS add this to your queries!
			} else {
				$query='FROM '.$table.' WHERE '.$table.'.pid IN ('.$pidList.')'.chr(10).
				$this->cObj->enableFields($table,$hidden).chr(10);	// This adds WHERE-clauses that ensures deleted, hidden, starttime/endtime/access records are NOT selected, if they should not! Almost ALWAYS add this to your queries!
			}
		}

			// Split the "FROM ... WHERE" string so we get the WHERE part and TABLE names separated...:

		list($TABLENAMES,$WHERE) = spliti('WHERE', trim($query), 2);
		$TABLENAMES = trim(substr(trim($TABLENAMES),5));
		$WHERE = trim($WHERE);

			// Add '$addWhere'

		if ($addWhere)	{$WHERE.=' '.$addWhere.chr(10);}

			// Search word:

		if ($this->piVars['sword'] && $this->internal['searchFieldList'])	{
			$WHERE.=$this->searchWhere($this->piVars['sword'],$this->internal['searchFieldList'],$table).chr(10);
		}

		if ($count) {
			$queryParts = array(
				'SELECT' => 'count(*)',
				'FROM' => $TABLENAMES,
				'WHERE' => $WHERE,
				'GROUPBY' => '',
				'ORDERBY' => '',
				'LIMIT' => ''
			);
		} else {
				// Order by data:
			if (!$orderBy && $this->internal['orderBy'])	{
				if (t3lib_div::inList($this->internal['orderByList'],$this->internal['orderBy']))	{
					$orderBy = 'ORDER BY '.$table.'.'.$this->internal['orderBy'].($this->internal['descFlag']?' DESC':'');
				}
			}

				// Limit data:
			$pointer = $this->piVars['pointer'];
			$pointer = intval($pointer);
			$results_at_a_time = t3lib_div::intInRange($this->internal['results_at_a_time'],1,1000);

			$LIMIT = ($pointer*$results_at_a_time).','.$results_at_a_time;
				// Add 'SELECT'
			$queryParts = array(
				'SELECT' => $this->pi_prependFieldsWithTable($table,$this->pi_listFields),
				'FROM' => $TABLENAMES,
				'WHERE' => $WHERE,
				'GROUPBY' => $GLOBALS['TYPO3_DB']->stripGroupBy($groupBy),
				'ORDERBY' => $GLOBALS['TYPO3_DB']->stripOrderBy($orderBy),
				'LIMIT' => $LIMIT
			);
			
		}

		$query = $GLOBALS['TYPO3_DB']->SELECTquery (
					$queryParts['SELECT'],
					$queryParts['FROM'],
					$queryParts['WHERE'],
					$queryParts['GROUPBY'],
					$queryParts['ORDERBY'],
					$queryParts['LIMIT']
				);

		return $returnQueryArray ? $queryParts : $query;
	}

	/**
	 * Generates a search where clause based on the input search words (AND operation - all search words must be found in record.)
	 * Example: The $sw is "content management, system" (from an input form) and the $searchFieldList is "bodytext,header" then the output will be ' AND (bodytext LIKE "%content%" OR header LIKE "%content%") AND (bodytext LIKE "%management%" OR header LIKE "%management%") AND (bodytext LIKE "%system%" OR header LIKE "%system%")'
	 *
	 * @param	string		The search words. These will be separated by space and comma.
	 * @param	string		The fields to search in
	 * @param	string		The table name you search in (recommended for DBAL compliance. Will be prepended field names as well)
	 * @return	string		The WHERE clause.
	 */
	function searchWhere($sw,$searchFieldList,$searchTable='')	{
		global $TYPO3_DB,$TCA;
		$prefixTableName = $searchTable ? $searchTable.'.' : '';
		$where = '';
		if ($sw)	{
			$searchFields = t3lib_div::trimExplode(',',$searchFieldList,1);
			$kw = split('[ ,]',$sw);

			while(list(,$val)=each($kw))	{
				$val = trim($val);
				$where_p = array();
				if (strlen($val)>=2)	{
					$val = $TYPO3_DB->escapeStrForLike($TYPO3_DB->quoteStr($val,$searchTable),$searchTable);
					reset($searchFields);
					while(list(,$field)=each($searchFields))	{
						if (isset($TCA[$searchTable]['columns'][$field]['config']['MM'])) {
							$where_p[] = $this->searchWhereMM($sw,$field);
						} else {
							$where_p[] = $prefixTableName.$field.' LIKE \'%'.$val.'%\'';
						}
					}
				}

				if (count($where_p))	{
					$where.=' AND ('.implode(' OR ',$where_p).')';
				}
			}
		}
		return $where;
	}


	/**
	 * Creates a search string to search in mm-related tables
	 *
	 * @param 	String	$sw			The search words. These will be separated by space and comma.
	 * @param 	String	$fieldName	Name of the field
	 * @return	String				SQL-Query
	 *
	 */
	function searchWhereMM($sw,$fieldName) {
		global $TCA;

		$conf = $TCA[$this->tableName]['columns'][$fieldName]['config'];

		$table = $conf['foreign_table'];
		$fQuery = $this->cObj->searchWhere($this->piVars['sword'],$TCA[$table]['ctrl']['label'],$table);
		$sql = $GLOBALS['TYPO3_DB']->SELECTquery('uid',$table,'1 '.$fQuery.$this->cObj->enableFields($table));
		$sql = $GLOBALS['TYPO3_DB']->SELECTquery('uid_local',$conf['MM'],'uid_foreign IN ('.$sql.')');
		return $this->tableName.'.uid IN ('.$sql.')';
	}

	/**
	 * getting pi-vars for the extensions with dynamic extKey,
	 */
	function getPiVars() {
		if (is_array($_POST[$this->prefixId])) {
			foreach($_POST[$this->prefixId] as $k=>$v) {
				$this->piVars[$k] = $v;
			}
		}
		if (is_array($_GET[$this->prefixId])) {
			foreach($_GET[$this->prefixId] as $k=>$v) {
				$this->piVars[$k] = $v;
			}
		}
	}

	/**
	 * Gets the translated field, if no translation => fallback to original language
	 * 
	 * @deprecated use $GLOBALS['TSFE']->sys_page->getRecordOverlay
	 * 
	 * return void
	 *
	 */
	function getLanguageOverlay() {
		if ($GLOBALS['TSFE']->sys_language_uid > 0) {
			global $TCA;
			t3lib_div::loadTCA($this->tableName);
			if (isset($TCA[$this->tableName]['ctrl']['languageField'])) {
				$fields = array();
				foreach($TCA[$this->tableName]['columns'] as $key => $col) {
					if (($col['l10n_mode'] != 'exclude') && ($key != 'uid')) {
						$fields[]= $key;
					}
				}
				if(isset($TCA[$this->tableName]['ctrl']['transOrigPointerField'])){
					$translation = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(implode(',',$fields),$this->tableName,'sys_language_uid = '.$GLOBALS['TSFE']->sys_language_uid.' AND '.$TCA[$this->tableName]['ctrl']['transOrigPointerField'].' = '.$this->internal['currentRow']['uid'].$this->cObj->enableFields($this->tableName));
				} else {
					$translation = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(implode(',',$fields),$this->tableName,'sys_language_uid = '.$GLOBALS['TSFE']->sys_language_uid.' AND l18n_parent = '.$this->internal['currentRow']['uid'].$this->cObj->enableFields($this->tableName));
				}
				$translation = $translation[0];

				if (isset($translation['sys_language_uid'])) {
					foreach($fields as $key) {
						$this->internal['currentRow'][$key] = $translation[$key];
					}
				}
			}
		}
	}

	/**
	 * Adds all the language-labels as markers
	 *
	 * @param array 	$mArr [reference]
	 * @param string	$keyWrap	wrapping for the marker
	 * @return void
	 */
	function addLanguageLabels(&$mArr,$keyWrap = '###|###') {
		foreach($this->LOCAL_LANG['default'] as $key => $value) {
			if (isset($this->LOCAL_LANG[$this->LLkey][$key])) {
				$value = $this->LOCAL_LANG[$this->LLkey][$key];
			}
			$key = $this->cObj->wrap($key,$keyWrap);
			$mArr[$key] = $value;
		}
	}
	
	/**
	 * Empty function to be overriden if necessary
	 * 
	 * @return string 
	 */
	function listByCategoryWhere() {
		return '';
	}
	
	/**
	 * Includes the prototype javascripts library
	 * @return void
	 */
	function addPrototype() {
    	$GLOBALS['TSFE']->additionalHeaderData[$this->extKey].='
			<script type="text/javascript" src="typo3conf/ext/x4epibase/js/scriptaculous/prototype.js"></script>
			<script type="text/javascript" src="typo3conf/ext/x4epibase/js/scriptaculous/scriptaculous.js"></script>';
    }
	
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/x4epibase/class.x4epibase.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/x4epibase/class.x4epibase.php']);
}
?>