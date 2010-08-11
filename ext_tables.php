<?php
if (TYPO3_MODE=='BE')	{
	require_once(t3lib_extMgm::extPath($_EXTKEY).'class.x4epibase_flexform.php');
	t3lib_div::loadTCA('tt_content');
	$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key,pages';
	$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1']='pi_flexform';
	t3lib_extMgm::addPlugin(Array('LLL:EXT:x4epibase/locallang_db.php:tt_content.list_type_pi1', $_EXTKEY.'_pi1'),'list_type');
	t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_pi1', 'FILE:EXT:'.$_EXTKEY.'/pi1/flexform_ds.xml');
	require_once(t3lib_extMgm::extPath($_EXTKEY).'class.x4epibase_flexform.php');
}
?>