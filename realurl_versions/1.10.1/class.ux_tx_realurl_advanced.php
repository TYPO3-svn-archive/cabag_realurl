<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2004 Martin Poelstra (martin@beryllium.net)
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * Class for translating page ids to/from path strings (Speaking URLs)
 *
 * $Id: class.tx_realurl_advanced.php 12390 2008-09-27 13:57:38Z dmitry $
 *
 * @author	Martin Poelstra <martin@beryllium.net>
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 */

/**
 * Class for translating page ids to/from path strings (Speaking URLs)
 *
 * @author	Martin Poelstra <martin@beryllium.net>
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 * @package realurl
 * @subpackage tx_realurl
 */
class ux_tx_realurl_advanced extends tx_realurl_advanced {

	/**
	 * Search recursively for the URL in the page tree and return the ID of the path ("manual" id resolve)
	 *
	 * @param	array		Path parts, passed by reference.
	 * @return	array		Info array, currently with "id" set to the ID.
	 */
	function findIDByURL(array &$urlParts) {
		$tx_cabagrealurl_extconf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cabag_realurl']);
		// Does only anything if all needed information from extension manager are available!
		if(!empty($tx_cabagrealurl_extconf['enableAdvancedRealURLRedirect']) && !empty($tx_cabagrealurl_extconf['advancedRealURLRedirectTable']) && !empty($tx_cabagrealurl_extconf['advancedRealURLRedirectField']) && !empty($tx_cabagrealurl_extconf['advancedRealURLRedirectPIDField'])) {
			if(count($urlParts) == 1 || count($urlParts) == 2) {
				if(count($urlParts) == 2) {
					$searchString = urldecode($urlParts[0]).'/'.urldecode($urlParts[1]);
				} else {
					$searchString = urldecode($urlParts[0]);
				}
				
				// There is only one URL part - look for a record with this kurscode
				$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						$tx_cabagrealurl_extconf['advancedRealURLRedirectTable'].'.*', 
						$tx_cabagrealurl_extconf['advancedRealURLRedirectTable'],
						$tx_cabagrealurl_extconf['advancedRealURLRedirectTable'].".".$tx_cabagrealurl_extconf['advancedRealURLRedirectField']."=" . $GLOBALS['TYPO3_DB']->fullQuoteStr($searchString,$tx_cabagrealurl_extconf['advancedRealURLRedirectTable'])."
						 AND ".$tx_cabagrealurl_extconf['advancedRealURLRedirectTable'].".deleted=0 AND ".$tx_cabagrealurl_extconf['advancedRealURLRedirectTable'].".hidden=0 ");
	
				if($GLOBALS['TYPO3_DB']->sql_num_rows($result) > 0) {
					$record = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
					
					$id = 0;
					$id = $record[$tx_cabagrealurl_extconf['advancedRealURLRedirectPIDField']];
					$GET_VARS = '';
					
					// Unset code - very important and needed - ask ss for questions
					if(count($urlParts) == 2) {
						unset($urlParts[0]);
						unset($urlParts[1]);
					} else {
						unset($urlParts[0]);
					}

					return array(intval($id), $GET_VARS);
				} else {
					// There were no record found in the db - do the standart way
					$GLOBALS['TYPO3_DB']->sql_free_result($result);
					return parent::findIDByURL($urlParts);
				}
			} else {
				// There are more than one path part - do the standart way
				return parent::findIDByURL($urlParts);
			}
			
		} else {
			// The configuration is not complete - do the standart way
			return parent::findIDByURL($urlParts);
		}
	}

	/**
	 * Convert a page path to an ID.
	 *
	 * @param	array		Array of segments from virtual path
	 * @return	integer		Page ID
	 * @see decodeSpURL_idFromPath()
	 */
	protected function pagePathtoID(&$pathParts) {

		// Init:
		$GET_VARS = '';

		// If pagePath cache is not disabled, look for entry:
		if (!$this->conf['disablePathCache']) {

			if (!isset($this->conf['firstHitPathCache'])) {
				$this->conf['firstHitPathCache'] = ((!isset($this->pObj->extConf['postVarSets']) || count($this->pObj->extConf['postVarSets']) == 0) && (!isset($this->pObj->extConf['fixedPostVars']) || count($this->pObj->extConf['fixedPostVars']) == 0));
			}

			// Work from outside-in to look up path in cache:
			$postVar = false;

			// dk@cabag.ch - 23.11.2010 - start - copy_pathParts must be an array so that the next query will find something
			if (is_array($pathParts)) {
				$copy_pathParts = $pathParts;
			} else {
				$copy_pathParts = array($pathParts);
			}
			// dk@cabag.ch - 23.11.2010 - end - copy_pathParts must be an array so that the next query will find something

			$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->defaultCharSet;
			foreach ($copy_pathParts as $key => $value) {
				$copy_pathParts[$key] = $GLOBALS['TSFE']->csConvObj->conv_case($charset, $value, 'toLower');
			}
			while (count($copy_pathParts)) {
				// Using pathq1 index!
				/* CHECK SERVER OS */
				if(strpos($_SERVER['OS'],'Windows') !== false) {
					list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
						'tx_realurl_pathcache.*', 'tx_realurl_pathcache,pages',
						'tx_realurl_pathcache.page_id=pages.uid AND pages.deleted=0' .
						' AND rootpage_id=' . intval($this->conf['rootpage_id']) .
						' AND pagepath=' . $GLOBALS['TYPO3_DB']->fullQuoteStr(implode('/', $copy_pathParts), 'tx_realurl_pathcache'),
						'', 'expire', '1');
				} else {
					list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
						'tx_realurl_pathcache.*', 'tx_realurl_pathcache,pages',
						'tx_realurl_pathcache.page_id=pages.uid AND pages.deleted=0' .
						' AND rootpage_id=' . intval($this->conf['rootpage_id']) .
						' AND pagepath LIKE ' . $GLOBALS['TYPO3_DB']->fullQuoteStr(implode('/', $copy_pathParts), 'tx_realurl_pathcache'),
						'', 'expire', '1');
				}

				// This lookup does not include language and MP var since those are supposed to be fully reflected in the built url!
				if (is_array($row) || $this->conf['firstHitPathCache']) {
					break;
				}

				// If no row was found, we simply pop off one element of the path and try again until there are no more elements in the array - which means we didn't find a match!
				$postVar = array_pop($copy_pathParts);
			}
		} else {
			$row = false;
		}

		// It could be that entry point to a page but it is not in the cache. If we popped
		// any items from path parts, we need to check if they are defined as postSetVars or
		// fixedPostVars on this page. This does not guarantie 100% success. For example,
		// if path to page is /hello/world/how/are/you and hello/world found in cache and
		// there is a postVar 'how' on this page, the check below will not work. But it is still
		// better than nothing.
		if ($row && $postVar) {
			$postVars = $this->pObj->getPostVarSetConfig($row['pid'], 'postVarSets');
			if (!is_array($postVars) || !isset($postVars[$postVar])) {
				// Check fixed
				$postVars = $this->pObj->getPostVarSetConfig($row['pid'], 'fixedPostVars');
				if (!is_array($postVars) || !isset($postVars[$postVar])) {
					// Not a postVar, so page most likely in not in cache. Clear row.
					// TODO It would be great to update cache in this case but usually TYPO3 is not
					// complitely initialized at this place. So we do not do it...
					$row = false;
				}
			}
		}

		// Process row if found:
		if ($row) { // We found it in the cache

			// Check for expiration. We can get one of three:
			//   1. expire = 0
			//   2. expire <= time()
			//   3. expire > time()
			// 1 is permanent, we do not process it. 2 is expired, we look for permanent or non-expired
			// (in this order!) entry for the same page od and redirect to corresponding path. 3 - same as
			// 1 but means that entry is going to expire eventually, nothing to do for us yet.
			if ($row['expire'] > 0) {
				$this->pObj->devLog('pagePathToId found row', $row);
				// 'expire' in the query is only for logging
				// Using pathq2 index!
				list($newEntry) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pagepath,expire', 'tx_realurl_pathcache',
						'page_id=' . intval($row['page_id']) . '
						AND language_id=' . intval($row['language_id']) . '
						AND (expire=0 OR expire>' . $row['expire'] . ')', '', 'expire', '1');
				$this->pObj->devLog('pagePathToId searched for new entry', $newEntry);

				// Redirect to new path immediately if it is found
				if ($newEntry) {
					// Replace path-segments with new ones:
					$originalDirs = $this->pObj->dirParts; // All original
					$cp_pathParts = $pathParts;
					// Popping of pages of original dirs (as many as are remaining in $pathParts)
					for ($a = 0; $a < count($pathParts); $a++) {
						array_pop($originalDirs); // Finding all preVars here
					}
					for ($a = 0; $a < count($copy_pathParts); $a++) {
						array_shift($cp_pathParts); // Finding all postVars here
					}
					$newPathSegments = explode('/', $newEntry['pagepath']); // Split new pagepath into segments.
					$newUrlSegments = array_merge($originalDirs, $newPathSegments, $cp_pathParts); // Merge those segments.
					$this->pObj->appendFilePart($newUrlSegments);
					$redirectUrl = implode('/', $newUrlSegments);

					header('HTTP/1.1 301 Moved Permanently');
					header('Location: ' . t3lib_div::locationHeaderUrl($redirectUrl));
					exit();
				}
				$this->pObj->disableDecodeCache = true;	// Do not cache this!
			}

			// Unshift the number of segments that must have defined the page:
			$cc = count($copy_pathParts);
			for ($a = 0; $a < $cc; $a++) {
				array_shift($pathParts);
			}

			// Assume we can use this info at first
			$id = $row['page_id'];
			$GET_VARS = $row['mpvar'] ? array('MP' => $row['mpvar']) : '';
		}
		else {

			// nb@cabag.ch - 11.02.2011 - start - findIDByUrl needs an array in this version!
			if (is_array($pathParts)) {
				$arrayPathParts = &$pathParts;
			} else {
				$arrayPathParts = array($pathParts);
			}
			// nb@cabag.ch - 11.02.2011 - end - findIDByUrl needs an array in this version!

			// Find it
			list($id, $GET_VARS) = $this->findIDByURL($arrayPathParts);

		}

		// Return found ID:
		return array($id, $GET_VARS);
	}
	
	/**
	 * Adds a new entry to the path cache
	 *
	 * @param string $currentPagePath
	 * @param string $pathCacheCondition
	 * @param int $pageId
	 * @param string $mpvar
	 * @param int $langId
	 * @return void
	 */
	protected function addNewPagePathEntry($currentPagePath, $pathCacheCondition, $pageId, $mpvar, $langId, $rootPageId) {
		/* CHECK SERVER OS */
		if(strpos($_SERVER['OS'],'Windows') !== false) {
			$condition = $pathCacheCondition . ' AND pagepath LIKE ' .
				$GLOBALS['TYPO3_DB']->fullQuoteStr($currentPagePath, 'tx_realurl_pathcache');
			list($count) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('COUNT(*) AS t',
				'tx_realurl_pathcache', $condition);
			if ($count['t'] == 0) {
				$insertArray = array(
					'page_id' => $pageId,
					'language_id' => $langId,
					'pagepath' => $currentPagePath,
					'expire' => 0,
					'rootpage_id' => $rootPageId,
					'mpvar' => $mpvar
				);
				$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_pathcache', $insertArray);
			}
		} else {
			parent::addNewPagePathEntry($currentPagePath, $pathCacheCondition, $pageId, $mpvar, $langId, $rootPageId);
		}
	}
	
	/**
	 * Sets expiration time for the old path cache entries
	 *
	 * @param string $currentPagePath
	 * @param string $pathCacheCondition
	 * @return void
	 */
	protected function setExpirationOnOldPathCacheEntries($currentPagePath, $pathCacheCondition) {
		/* CHECK SERVER OS */
		if(strpos($_SERVER['OS'],'Windows') !== false) {
			$expireDays = (isset($this->conf['expireDays']) ? $this->conf['expireDays'] : 60) * 24 * 3600;
			$condition = $pathCacheCondition . ' AND expire=0 AND pagepath NOT LIKE ' .
				$GLOBALS['TYPO3_DB']->fullQuoteStr($currentPagePath, 'tx_realurl_pathcache');
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_pathcache', $condition,
				array(
					'expire' => $this->makeExpirationTime($expireDays)
				),
				'expire'
			);
		} else {
			parent::setExpirationOnOldPathCacheEntries($currentPagePath, $pathCacheCondition);
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl_advanced.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl_advanced.php']);
}
?>