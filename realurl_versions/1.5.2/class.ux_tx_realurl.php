<?php
/**
 * changes to the realurl class by cab services ag
 * - fixes the cache realurl problem.
 *
 * @author	Jonas Dübi <jd@cabag.ch>
 * @package TYPO3
 * @subpackage cabag_patch
 */
class ux_tx_realurl extends tx_realurl{
    function isBEUserLoggedIn() {
        return false;
    }
	
	
	/**
	 * uses params for redirects -> xyz.cfm?id=x works now
	 */
	function decodeSpURL_checkRedirects($speakingURIpath) {
		if(!empty($this->extConf['useparamsforredirects'])){
			return parent::decodeSpURL_checkRedirects($this->pObj->siteScript);
		} else {
			return parent::decodeSpURL_checkRedirects($speakingURIpath);
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl.php']);
}
?>
