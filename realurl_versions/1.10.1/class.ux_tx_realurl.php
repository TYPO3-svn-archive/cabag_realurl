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
			$url = $this->extConf['redirects'][$speakingURIpath];
			if (preg_match('/^30[1237];/', $url)) {
				$redirectCode = intval(substr($url, 0, 3));
				$url = substr($url, 4);
				header('HTTP/1.0 ' . $redirectCode . ' Redirect');
			}
			header('Location: ' . t3lib_div::locationHeaderUrl($url));
			exit();
		}

		// Regex redirects:
		if (is_array($this->extConf['redirects_regex'])) {
			foreach ($this->extConf['redirects_regex'] as $regex => $substString) {
				if (preg_match('/' . $regex . '/', $speakingURIpath)) {
					$url = @preg_replace('/' . $regex . '/', $substString, $speakingURIpath);
					if ($url) {
						if (preg_match('/^30[1237];/', $url)) {
							$redirectCode = intval(substr($url, 0, 3));
							header('HTTP/1.0 ' . $redirectCode . ' Redirect');
							$url = substr($url, 4);
						}
						header('Location: ' . t3lib_div::locationHeaderUrl($url));
						exit();
					}
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
						
						// realurl the normal url if it is needed - not for filelinks
						if(strstr($destinationURL,'?')) {
							$notused = false;
							$params['LD']['totalURL'] = $GLOBALS['TSFE']->absRefPrefix . $this->prefixEnablingSpURL . $destinationURL;
							$params['TCEmainHook'] = 1;
							$this->encodeSpURL($params, $notused);
							$destinationURL = $params['LD']['totalURL'];
							
						}
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
	
	function decodeSpURL($params) {

		$this->devLog('Entering decodeSpURL');

		// Setting parent object reference (which is $GLOBALS['TSFE'])
		$this->pObj = &$params['pObj'];

		// Initializing config / request URL:
		$this->setConfig();
		$this->adjustConfigurationByHost('decode');
		$this->adjustRootPageId();

		// If there has been a redirect (basically; we arrived here otherwise than via "index.php" in the URL) this can happend either due to a CGI-script or because of reWrite rule. Earlier we used $GLOBALS['HTTP_SERVER_VARS']['REDIRECT_URL'] to check but...
		// CAB
		if (/*$this->pObj->siteScript &&*/ substr($this->pObj->siteScript, 0, 9) != 'index.php' && substr($this->pObj->siteScript, 0, 1) != '?') {

			// Getting the path which is above the current site url:
			// For instance "first/second/third/index.html?&param1=value1&param2=value2"
			// should be the result of the URL
			// "http://localhost/typo3/dev/dummy_1/first/second/third/index.html?&param1=value1&param2=value2"
			// Note: sometimes in fcgi installations it is absolute, so we have to make it
			// relative to work properly.
			$speakingURIpath = $this->pObj->siteScript{0} == '/' ? substr($this->pObj->siteScript, 1) : $this->pObj->siteScript;
			
			// add URI to error log - thanks to Marc Bastian Heinrichs <heinrichs@steuerungb.de>
			$this->speakingURIpath_procValue = $speakingURIpath;

			// Call hooks
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'] as $userFunc) {
					$hookParams = array(
						'pObj' => &$this,
						'params' => $params,
						'URL' => &$speakingURIpath,
					);
					t3lib_div::callUserFunction($userFunc, $hookParams, $this);
				}
			}

			// Append missing slash if configured for:
			if ($this->extConf['init']['appendMissingSlash']) {
				$regexp = '~^([^\?]*[^/])(\?.*)?$~';
				if (preg_match($regexp, $speakingURIpath)) { // Only process if a slash is missing:
					$options = t3lib_div::trimExplode(',', $this->extConf['init']['appendMissingSlash'], true);
					if (in_array('ifNotFile', $options)) {
						if (!preg_match('/\/[^\/]+\.[^\/]+(\?.*)?$/', '/' . $speakingURIpath)) {
							$speakingURIpath = preg_replace($regexp, '\1/\2', $speakingURIpath);
							$this->appendedSlash = true;
						}
					}
					else {
						$speakingURIpath = preg_replace($regexp, '\1/\2', $speakingURIpath);
						$this->appendedSlash = true;
					}
					if ($this->appendedSlash && count($options) > 0) {
						foreach ($options as $option) {
							$matches = array();
							if (preg_match('/^redirect(\[(30[1237])\])?$/', $option, $matches)) {
								$code = count($matches) > 1 ? $matches[2] : 301;
								$status = 'HTTP/1.0 ' . $code . ' TYPO3 RealURL redirect';

								
								// Check path segment to be relative for the current site.
								// parse_url() does not work with relative URLs, so we use it to test
								if (!@parse_url($speakingURIpath, PHP_URL_HOST)) {
									@ob_end_clean();
									header($status);
									header('Location: ' . t3lib_div::locationHeaderUrl($speakingURIpath));
									exit;
								}
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
				$this->devLog('RealURL powered decoding (TM) starting!');

				// Parse path:
				$uParts = @parse_url($speakingURIpath);
				if (!is_array($uParts)) {
					$this->decodeSpURL_throw404('Current URL is invalid');
				}

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
				
				if ($this->mimeType) {
					header('Content-type: ' . $this->mimeType);
					$this->mimeType = null;
				}
			}
		}
	}
	
	/**
	 * Decodes a speaking URL path into an array of GET parameters and a page id.
	 *
	 * @param	string		Speaking URL path (after the "root" path of the website!) but without query parameters
	 * @param	boolean		If cHash caching is enabled or not.
	 * @return	array		Array with id and GET parameters.
	 * @see decodeSpURL()
	 */
	function decodeSpURL_doDecode($speakingURIpath, $cHashCache = FALSE) {
		// Cached info:
		$cachedInfo = array();

		// Convert URL to segments
		$pathParts = explode('/', $speakingURIpath);
		array_walk($pathParts, create_function('&$value', '$value = rawurldecode($value);'));
		
		// CAB: SS: 26.11.09 - fix bad postVarSet as expected bug for notfound page
		$this->filePart = array_pop($pathParts);
		if(strpos($this->filePart, '?') !== false) {
			$position = strpos($this->filePart, '?');
			$this->filePart = substr($this->filePart, 0, $position);
		}
		array_push($pathParts,$this->filePart);

		if (!is_array($pathParts)) {
			$pathParts = array($pathParts);
		}

		// Strip/process file name or extension first
		$file_GET_VARS = $this->decodeSpURL_decodeFileName($pathParts);

		// Setting original dir-parts:
		$this->dirParts = $pathParts;

		// Setting "preVars":
		$pre_GET_VARS = $this->decodeSpURL_settingPreVars($pathParts, $this->extConf['preVars']);
		
		// Setting page id:
		list($cachedInfo['id'], $id_GET_VARS, $cachedInfo['rootpage_id']) = $this->decodeSpURL_idFromPath($pathParts);
		

		// Fixed Post-vars:
		$fixedPostVarSetCfg = $this->getPostVarSetConfig($cachedInfo['id'], 'fixedPostVars');
		$fixedPost_GET_VARS = $this->decodeSpURL_settingPreVars($pathParts, $fixedPostVarSetCfg);

		// Setting "postVarSets":
		$postVarSetCfg = $this->getPostVarSetConfig($cachedInfo['id']);
		$post_GET_VARS = $this->decodeSpURL_settingPostVarSets($pathParts, $postVarSetCfg);

		// Looking for remaining parts:
		if (count($pathParts)) {
			$this->decodeSpURL_throw404('"' . $speakingURIpath . '" could not be found, closest page matching is ' . substr(implode('/', $this->dirParts), 0, -strlen(implode('/', $pathParts))) . '');
		}

		// Merge Get vars together:
		$cachedInfo['GET_VARS'] = array();
		if (is_array($pre_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $pre_GET_VARS);
		if (is_array($id_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $id_GET_VARS);
		if (is_array($fixedPost_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $fixedPost_GET_VARS);
		if (is_array($post_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $post_GET_VARS);
		if (is_array($file_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $file_GET_VARS);

		// cHash handling:
		if ($cHashCache) {
			$cHash_value = $this->decodeSpURL_cHashCache($speakingURIpath);
			if ($cHash_value) {
				$cachedInfo['GET_VARS']['cHash'] = $cHash_value;
			}
		}

		// Return information found:
		return $cachedInfo;
	}
	
	
	/**
	 * Checks if the suffix matches to the configured one.
	 *
	 * @param string $fileName
	 * @param string $segment
	 * @param string $extension
	 * @param array $pathPartsCopy
	 * @see tx_realurl::decodeSpURL_decodeFileName()
	 */
	protected function decodeSpURL_decodeFileName_checkHtmlSuffix($fileName, $segment, $extension, array &$pathParts) {
		$handled = false;
		if (isset($this->extConf['fileName']['defaultToHTMLsuffixOnPrev'])) {
			$suffix = $this->extConf['fileName']['defaultToHTMLsuffixOnPrev'];
			$suffix = (!$this->isString($suffix, 'defaultToHTMLsuffixOnPrev') ? '.html' : $suffix);
			if ($suffix == '.' . $extension) {
				$pathParts[] = rawurlencode($segment);
				$this->filePart = '.' . $extension;
			}
			else {
				// CAB SS:2010-11-22 check if path is a special advanced url before forwarding to 404
				if(is_array($this->decodeSpURL_idFromPath($fileName))) {
					// Something was found - return to decodeSpURL_doDecode() to go further
					$pathParts = array(0 => $fileName);
				} else {
					$this->decodeSpURL_throw404('File "' . $fileName . '" was not found (2)!');
				}
			}
			$handled = true;
		}
		return $handled;
	}
	
	/**
	 * Pulling variables of the path parts
	 *
	 * @param	array		Parts of path. NOTICE: Passed by reference.
	 * @param	array		Setup array for segments in set.
	 * @return	string		GET parameter string
	 * @see decodeSpURL_settingPreVars(), decodeSpURL_settingPostVarSets()
	 */
	protected function decodeSpURL_getSequence(&$pathParts, $setupArr) {
		$GET_string = '';
		$prevVal = '';
		foreach ($setupArr as $setup) {
			if (count($pathParts) == 0) {
				// If we are here, it means we are at the end of the URL.
				// Since some items still remain in the $setupArr, it means
				// we stripped empty segments at the end of the URL on encoding.
				// Reconstruct them or cHash check will fail in TSFE.
				// Related to bug #15906.
				$GET_string .= '&' . rawurlencode($setup['GETvar']) . '=';
			}
			else {
				// Get value and remove from path parts:
				$value = $origValue = array_shift($pathParts);
				$value = rawurldecode($value);

				switch ($setup['type']) {
					case 'action':
						// Find index key:
						$idx = isset($setup['index'][$value]) ? $value : '_DEFAULT';

						// Look up type:
						switch ((string)$setup['index'][$idx]['type']) {
							case 'redirect':
								$url = (string)$setup['index'][$idx]['url'];
								$url = str_replace('###INDEX###', rawurlencode($value), $url);
								$this->appendFilePart($pathParts);
								$remainPath = implode('/', $pathParts);
								if ($this->appendedSlash) {
									$remainPath = substr($remainPath, 0, -1);
								}
								$url = str_replace('###REMAIN_PATH###', rawurlencode(rawurldecode($remainPath)), $url);

								header('Location: ' . t3lib_div::locationHeaderUrl($url));
								exit();
								break;
							case 'admin':
								$this->decodeSpURL_jumpAdmin();
								break;
							case 'notfound':
								$this->decodeSpURL_throw404('A required value from "' . @implode(',', @array_keys($setup['match'])) . '" of path was not matching "' . $value . '" which was actually found.');
								break;
							case 'bypass':
								array_unshift($pathParts, $origValue);
								break;
							case 'feLogin':
								// Do nothing.
								break;
						}
						break;
					default:
						if (!is_array($setup['cond']) || $this->checkCondition($setup['cond'], $prevVal)) {

							// Map value if applicable:
							if (isset($setup['valueMap'][$value])) {
								$value = $setup['valueMap'][$value];
							} elseif ($setup['noMatch'] == 'bypass') {
								// If no match and "bypass" is set, then return the value to $pathParts and break
								array_unshift($pathParts, $origValue);
								break;
							} elseif ($setup['noMatch'] == 'null') { // If no match and "null" is set, then break (without setting any value!)
								break;
							} elseif ($setup['userFunc']) {
								$params = array(
									'decodeAlias' => true,
									'origValue' => $origValue,
									'pathParts' => &$pathParts,
									'pObj' => &$this,
									'value' => $value,
								);
								$value = t3lib_div::callUserFunction($setup['userFunc'], $params, $this);
							} elseif (is_array($setup['lookUpTable'])) {
								$temp = $value;
								$value = $this->lookUpTranslation($setup['lookUpTable'], $value, TRUE);
								if ($setup['lookUpTable']['enable404forInvalidAlias'] && !t3lib_div::testInt($value) && !strcmp($value, $temp)) {
									$this->decodeSpURL_throw404('Couldn\'t map alias "' . $value . '" to an ID');
								}
							} elseif (isset($setup['valueDefault'])) { // If no matching value and a default value is given, set that:
								//$value = $setup['valueDefault'];
								
								// CAB: SS: 07.04.11 - fix bug that www.domain.com/zzz leads to the startpage instead of the notfound page as soon as valueDefault isset for language
								array_unshift($pathParts, $origValue);
								break;
							}

							// Set previous value:
							$prevVal = $value;

							// Add to GET string:
							if ($setup['GETvar'] && strlen($value)) { // Checking length of value; normally a *blank* parameter is not found in the URL! And if we don't do this we may disturb "cHash" calculations!
								if (isset($this->extConf['init']['emptySegmentValue']) && $this->extConf['init']['emptySegmentValue'] === $value) {
									$value = '';
								}
								$GET_string .= '&' . rawurlencode($setup['GETvar']) . '=' . rawurlencode($value);
							}
						} else {
							array_unshift($pathParts, $origValue);
							break;
						}
						break;
				}
			}
		}

		return $GET_string;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl.php']);
}
?>
