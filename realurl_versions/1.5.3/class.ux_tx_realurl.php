<?php
/**
 * changes to the realurl class by cab services ag
 * - fixes the cache realurl problem.
 *
 * @author	Jonas Dübi <jd@cabag.ch>
 * @package TYPO3
 * @subpackage cabag_patch
 */

if (TYPO3_MODE=='FE')	{
	include_once(PATH_tslib.'class.tslib_content.php');
}

class ux_tx_realurl extends tx_realurl{
    function isBEUserLoggedIn() {
        return false;
    }
	
	
	/**
	 * uses params for redirects -> xyz.cfm?id=x works now
	 */
	/* function decodeSpURL_checkRedirects($speakingURIpath) {
		if(!empty($this->extConf['useparamsforredirects'])){
			return parent::decodeSpURL_checkRedirects($this->pObj->siteScript);
		} else {
			return parent::decodeSpURL_checkRedirects($speakingURIpath);
		}
	} */
	
	function decodeSpURL_checkRedirects($speakingURIpath) {
		if(!empty($this->extConf['useparamsforredirects'])){
			//uses params for redirects -> xyz.cfm?id=x works now
			$speakingURIpath = $this->pObj->siteScript;
		} 
		
		$speakingURIpath = trim($speakingURIpath);

		if (isset($this->extConf['redirects'][$speakingURIpath])) {
			header('Location: ' . t3lib_div::locationHeaderUrl($this->extConf['redirects'][$speakingURIpath]));
			exit();
		}

		// Regex redirects:
		if (is_array($this->extConf['redirects_regex'])) {
			foreach ($this->extConf['redirects_regex'] as $regex => $substString) {
				if (ereg($regex, $speakingURIpath)) {
					$speakingURIpath = ereg_replace($regex, $substString, $speakingURIpath);
					header('Location: ' . t3lib_div::locationHeaderUrl($speakingURIpath));
					exit();
				}
			}
		}

		// DB defined redirects:
		/* Select the following redirects
			- for the current URIpath and the current HTTP_HOST, if a domain relation isset
			- with an regex and the current HTTP_HOST, if a domain relation isset
			
			Attention: if there are two redirects for the same URIpath the redirect for the current domain will be used although there is one with no domain relation
		*/
		$redirect_rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'DISTINCT tx_realurl_redirects.*, sys_domain.uid as sys_domain_uid, sys_domain.domainName as sys_domain_domainName', 
			"tx_realurl_redirects 
				LEFT JOIN tx_realurl_redirects_sys_domain_mm ON (
					tx_realurl_redirects.uid = tx_realurl_redirects_sys_domain_mm.uid_local)
				LEFT JOIN sys_domain ON (
					tx_realurl_redirects_sys_domain_mm.uid_foreign = sys_domain.uid
					AND sys_domain.domainName = '".t3lib_div::getIndpEnv('HTTP_HOST')."') ", 
			"(
				tx_realurl_redirects.url = '" .$speakingURIpath."' 
				OR tx_realurl_redirects.url_regular_expression != '' 
			)
				AND tx_realurl_redirects.deleted = 0 
				AND tx_realurl_redirects.hidden = 0 ",
			'',
			'sys_domain_domainName DESC, tx_realurl_redirects.url DESC, tx_realurl_redirects.url_regular_expression DESC'
			);
		
		if(is_array($redirect_rows) && !empty($redirect_rows)) {
			foreach ($redirect_rows as $redirect_row) {
				// check if there is a domain relation set
				if (
				($redirect_row['sys_domain_uid'] !== NULL && $redirect_row['sys_domain_domainName'] !== NULL && (int)$redirect_row['domain'] > 0) 
					|| 
				($redirect_row['sys_domain_uid'] === NULL && $redirect_row['sys_domain_domainName'] === NULL && (int)$redirect_row['domain'] == 0)
				) {
					// Check if the redirect has a url regex set
					if(!empty($redirect_row['url_regular_expression'])) {
						// Check if the regex matches the current speaking URI path
						$urlMatch = preg_match('#'.$redirect_row['url_regular_expression'].'#', $speakingURIpath);
						if($urlMatch == 0 || $urlMatch === FALSE) {
							// regular expression doesn't match to the current path - step to next redirect record
							continue;
						}
					}
					
					// Generate the fields array to update the statistic of the redirect
					$fields_values = array(
							'counter' => $redirect_row['counter'] + 1, 
							'last_time' => time(), 
							'last_referer' => t3lib_div::getIndpEnv('HTTP_REFERER')
							);
					
					// Update the redirect record
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						'tx_realurl_redirects', 
						'uid = '.$redirect_row['uid'].' AND deleted = 0 AND hidden = 0', 
						$fields_values);
							
					// check if the destination is defined as typolink URL
					if(!empty($redirect_row['destination_typolink'])) {
						$linkURLConfiguration = array(
							'linkURLConfiguration' => 'TEXT',
							'linkURLConfiguration.' => array(
								'typolink' => 1,
								'typolink.' => array(
									'parameter' => $redirect_row['destination_typolink'],
									'returnLast' => 'url'
									)
								),
							);
						
						// Set some needed vars to make typolink rendering possible at this early point, but this initialization doesn't matter because of the exit() after the header forward
						$GLOBALS['TSFE']->config = array('config' => array('typolinkEnableLinksAcrossDomains' => 1));
						$GLOBALS['TSFE']->clear_preview();
						$GLOBALS['TSFE']->determineId();
						$GLOBALS['TSFE']->initTemplate();
						
						$GLOBALS['TSFE']->includeTCA();
						$GLOBALS['TSFE']->id = $GLOBALS['TSFE']->sys_page->getDomainStartPage(t3lib_div::getIndpEnv('HTTP_HOST'));
						//$GLOBALS['TSFE']->getPageAndRootlineWithDomain($GLOBALS['TSFE']->id);
						$GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);
						
						// render the typolink destination path
						$tslib_cObj = t3lib_div::makeInstanceClassName('tslib_cObj');
						$this->cObj = new $tslib_cObj();
						$destinationURL = $this->cObj->cObjGetSingle(
							$linkURLConfiguration['linkURLConfiguration'], $linkURLConfiguration['linkURLConfiguration.']
							);
						
						// check if the returned destinationURL is an URL or a hole a tag
						if(strstr($destinationURL,'href="')) {
							$destinationURL = preg_replace('/(.*)href="(.*)"(.*)/','$2',$destinationURL);
							$destinationURL = str_replace('&amp;','&',$destinationURL);
						}
						
						// realurl the normal url
						$notused = false;
						$params['LD']['totalURL'] = $GLOBALS['TSFE']->absRefPrefix . $this->prefixEnablingSpURL . $destinationURL;
						$params['TCEmainHook'] = 1;
						$this->encodeSpURL($params, $notused);
						$destinationURL = $params['LD']['totalURL'];
						
						unset($tslib_cObj);
						unset($this->cObj);
					} else {
						$destinationURL = $redirect_row['destination'];
					}
					
					// Check if $destinationURL includes the marker {currentURL}
					if(strstr($destinationURL, '{currentURL}') !== FALSE) {
						$destinationURL = str_replace('{currentURL}',$speakingURIpath,$destinationURL);
					}
							
					if ($redirect_row['has_moved']) {
						header('HTTP/1.1 301 Moved Permanently');
					}
					header('Location: ' . t3lib_div::locationHeaderUrl($destinationURL));
					exit();
				}
			}
		}
	}
	
	function decodeSpURL($params, &$ref) {

		if ($this->enableDevLog) {
			t3lib_div::devLog('Entering decodeSpURL', 'realurl', -1);
		}

		// Setting parent object reference (which is $GLOBALS['TSFE'])
		$this->pObj = &$params['pObj'];

		// Initializing config / request URL:
		$this->setConfig();
		$this->adjustConfigurationByHost('decode');

		// If there has been a redirect (basically; we arrived here otherwise than via "index.php" in the URL) this can happend either due to a CGI-script or because of reWrite rule. Earlier we used $GLOBALS['HTTP_SERVER_VARS']['REDIRECT_URL'] to check but...
		// CAB
		if (/*$this->pObj->siteScript &&*/ substr($this->pObj->siteScript, 0, 9) != 'index.php' && substr($this->pObj->siteScript, 0, 1) != '?') {

			// Getting the path which is above the current site url:
			// For instance "first/second/third/index.html?&param1=value1&param2=value2" should be the result of the URL "http://localhost/typo3/dev/dummy_1/first/second/third/index.html?&param1=value1&param2=value2"
			$speakingURIpath = $this->pObj->siteScript;

			// Call hooks
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'] as $userFunc) {
					$params = array(
						'pObj' => &$this,
						'params' => $params,
						'URL' => &$speakingURIpath,
					);
					t3lib_div::callUserFunction($userFunc, $params, $this);
				}
			}

			// Append missing slash if configured for:
			if ($this->extConf['init']['appendMissingSlash']) {
				if (!preg_match('/\/(\?.*)?$/', $speakingURIpath)) { // Only process if a slash is missing:
					$options = t3lib_div::trimExplode(',', $this->extConf['init']['appendMissingSlash'], true);
					if (in_array('ifNotFile', $options)) {
						if (!preg_match('/\/[^\/]+\.[^\/]+(\?.*)?$/', '/' . $speakingURIpath)) {
							$speakingURIpath .= '/';
							$this->appendedSlash = true;
						}
					}
					else {
						$speakingURIpath .= '/';
						$this->appendedSlash = true;
					}
					if ($this->appendedSlash && count($options) > 0) {
						foreach ($options as $option) {
							$matches = array();
							if (preg_match('/^redirect(\[(30[1237])\])?$/', $option, $matches)) {
								$code = count($matches) > 1 ? $matches[2] : 301;
								if (version_compare(TYPO3_version, '4.3.0') >= 0) {
									$status = constant('t3lib_div::HTTP_STATUS_' . $code);
								}
								else {
									$status = 'HTTP/1.0 ' . $code . ' TYPO3 RealURL redirect';
								}
								@ob_end_clean();
								header($status);
								header('Location: ' . t3lib_div::locationHeaderUrl($speakingURIpath));
								exit;
							}
						}
					}
				}
			}

			// If the URL is a single script like "123.1.html" it might be an "old" simulateStaticDocument request. If this is the case and support for this is configured, do NOT try and resolve it as a Speaking URL
			$fI = t3lib_div::split_fileref($speakingURIpath);
			if (!t3lib_div::testInt($this->pObj->id) && $fI['path'] == '' && $this->extConf['fileName']['defaultToHTMLsuffixOnPrev'] && $this->extConf['init']['respectSimulateStaticURLs']) {
				// If page ID does not exist yet and page is on the root level and both
				// respectSimulateStaticURLs and defaultToHTMLsuffixOnPrev are set, than
				// ignore respectSimulateStaticURLs and attempt to resolve page id.
				// See http://bugs.typo3.org/view.php?id=1530
				$GLOBALS['TT']->setTSlogMessage('decodeSpURL: ignoring respectSimulateStaticURLs due defaultToHTMLsuffixOnPrev for the root level page!)', 2);
				$this->extConf['init']['respectSimulateStaticURLs'] = false;
			}
			if (!$this->extConf['init']['respectSimulateStaticURLs'] || $fI['path']) {
				if ($this->enableDevLog) {
					t3lib_div::devLog('RealURL powered decoding (TM) starting!', 'realurl');
				}

				// Parse path:
				$uParts = parse_url($speakingURIpath);
				$speakingURIpath = $this->speakingURIpath_procValue = $uParts['path'];

				// Redirecting if needed (exits if so).
				$this->decodeSpURL_checkRedirects($speakingURIpath);

				// Looking for cached information:
				$cachedInfo = $this->decodeSpURL_decodeCache($speakingURIpath);

				// If no cached info was found, create it:
				if (!is_array($cachedInfo)) {
					// Decode URL:
					$cachedInfo = $this->decodeSpURL_doDecode($speakingURIpath, $this->extConf['init']['enableCHashCache']);

					// Storing cached information:
					$this->decodeSpURL_decodeCache($speakingURIpath, $cachedInfo);
				}
				
				/* MODIFIED nb@cabag.ch start */
				if (!defined('TX_REALURL_ROOT_PAGE')) {
					define('TX_REALURL_PAGE_ID', $cachedInfo['id']);
				}
				/* MODIFIED nb@cabag.ch end */

				// Re-create QUERY_STRING from Get vars for use with typoLink()
				$_SERVER['QUERY_STRING'] = $this->decodeSpURL_createQueryString($cachedInfo['GET_VARS']);

				// Jump-admin if configured:
				$this->decodeSpURL_jumpAdmin_goBackend($cachedInfo['id']);

				// Setting info in TSFE:
				$this->pObj->mergingWithGetVars($cachedInfo['GET_VARS']);
				$this->pObj->id = $cachedInfo['id'];
			}
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl.php']);
}
?>
