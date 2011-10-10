<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_realurl_redirects'] = array (
	'ctrl' => $TCA['tx_realurl_redirects']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'domain,hidden,url,destination,destination_typolink,last_referer,last_time,counter,has_moved'
	),
	'feInterface' => $TCA['tx_realurl_redirects']['feInterface'],
	'columns' => array (
		'hidden' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'domain' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.domain',		
			'config' => Array (
				'type' => 'select',	
				'foreign_table' => 'sys_domain',	
				'foreign_table_where' => 'ORDER BY sys_domain.domainName',
				'size' => 10,	
				'minitems' => 0,
				'maxitems' => 1000,	
				'MM' => 'tx_realurl_redirects_sys_domain_mm',
			)
		),
		'url' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.url',		
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '500',
			)
		),
		'url_regular_expression' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.url_regular_expression',		
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '500',
				'checkbox' => '',
			)
		),
		'destination' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.destination',		
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '500',
			)
		),
		'destination_typolink' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.destination_typolink',		
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '500',
				'wizards' => array (
					'_PADDING' => 2,
					'link' => array (
						'type' => 'popup',
						'title' => 'Link',
						'icon' => 'link_popup.gif',
						'script' => 'browse_links.php?mode=wizard',
						'JSopenParams' => 'height=300,width=500,status=0,menubar=0,scrollbars=1',
					),
				),
				'softref' => 'typolink',
			)
		),
		'last_referer' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.last_referer',		
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '500',
				'readOnly' => 1,
			)
		),
		'last_time' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.last_time',		
			'config' => array (
				'type' => 'input',
				'size' => '11',
				'max'  => '11',
				'eval' => 'datetime',
				'readOnly' => 1,
			)
		),
		'counter' => Array (		
			'exclude' => 1,		
			'label' => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.counter',		
			'config' => Array (
				'type'     => 'input',
				'size'     => '11',
				'max'      => '11',
				'eval'     => 'int',
				'checkbox' => '0',
				'range'    => Array (
					'upper' => '99999999999',
					'lower' => '0'
				),
				'default' => 0,
				'readOnly' => 1,
			)
		),
		'has_moved' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.has_moved',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
	),
	'types' => array (
		'0' => array('showitem' => '--div--;LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.tabGeneral, hidden;;;;1-1-1, domain;;;;2-2-2, has_moved, counter;;;;3-3-3, last_referer, last_time, --div--;LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.tabSourceURL, url, url_regular_expression,  --div--;LLL:EXT:cabag_realurl/locallang_db.xml:tx_realurl_redirects.tabTargetURL, destination, destination_typolink')
	),
	'palettes' => array (
		'1' => array('showitem' => '')
	)
);
?>