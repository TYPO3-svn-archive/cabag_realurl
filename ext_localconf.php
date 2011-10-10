<?php
if (!defined ('TYPO3_MODE'))     die ('Access denied.');

// Manage Xclasses by ext manager
$tx_cabagrealurl_extconf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cabag_realurl']);

// load extension manager information from realurl
include(t3lib_extMgm::extPath('realurl').'ext_emconf.php');

// Its a little bit confusing but in this constant there is the version of realurl
define('REALURL_version', $EM_CONF['cabag_realurl']['version']);

//die('realurl version:'.REALURL_version);
if($tx_cabagrealurl_extconf['enableRealUrlXclass']) {
	/* xclass for realurl */
	if(file_exists(t3lib_extMgm::extPath($_EXTKEY).'realurl_versions/'.REALURL_version.'/class.ux_tx_realurl.php')){
		$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl.php'] = t3lib_extMgm::extPath($_EXTKEY).'realurl_versions/'.REALURL_version.'/class.ux_tx_realurl.php';
	}
}

/* xclass for realurl advanced redirect feature */
/* if($tx_cabagrealurl_extconf['enableAdvancedRealURLRedirect']) { */
	if(file_exists(t3lib_extMgm::extPath($_EXTKEY).'realurl_versions/'.REALURL_version.'/class.ux_tx_realurl_advanced.php')){
		$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_advanced.php'] = t3lib_extMgm::extPath($_EXTKEY).'realurl_versions/'.REALURL_version.'/class.ux_tx_realurl_advanced.php';
	}
/* } */

if(file_exists(t3lib_extMgm::extPath($_EXTKEY).'realurl_versions/'.REALURL_version.'/class.ux_tx_realurl_modfunc1.php')){
	$TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/modfunc1/class.tx_realurl_modfunc1.php'] = t3lib_extMgm::extPath($_EXTKEY).'realurl_versions/'.REALURL_version.'/class.ux_tx_realurl_modfunc1.php';
}

t3lib_extMgm::addUserTSConfig('
	options.saveDocNew.tx_realurl_redirects=1
');

?>
