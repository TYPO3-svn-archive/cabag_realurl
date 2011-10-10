<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_extMgm::allowTableOnStandardPages('tx_realurl_redirects');

$TCA['tx_realurl_redirects'] = array (
	'ctrl' => array (
		'title'     => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects',		
		'label'     => 'url',
		'label_alt' => 'url_regular_expression',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',	
		'dividers2tabs' => 1,
		'rootLevel' => -1,
		'default_sortby' => 'ORDER BY url',	
		'delete' => 'deleted',	
		'enablecolumns' => array (		
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_realurl_redirects.gif',
	),
	'feInterface' => array (
		'fe_admin_fieldList' => 'domain,hidden,url,destination,destination_typolink,last_referer,last_time,counter,has_moved',
	)
);

?>