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
	function findIDByURL(&$urlParts) {
		$tx_cabagpatch_extconf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cabag_realurl']);
		// Does only anything if all needed information from extension manager are available!
		if($tx_cabagpatch_extconf['advancedRealURLRedirectTable'] && $tx_cabagpatch_extconf['advancedRealURLRedirectField'] && $tx_cabagpatch_extconf['advancedRealURLRedirectPIDField']) {
		
			if(count($urlParts) == 1 || count($urlParts) == 2) {
				if(count($urlParts) == 2) {
					$searchString = urldecode($urlParts[0]).'/'.urldecode($urlParts[1]);
				} else {
					$searchString = urldecode($urlParts[0]);
				}
				
				// There is only one URL part - look for a record with this kurscode
				$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						$tx_cabagpatch_extconf['advancedRealURLRedirectTable'].'.*', 
						$tx_cabagpatch_extconf['advancedRealURLRedirectTable'],
						$tx_cabagpatch_extconf['advancedRealURLRedirectTable'].".".$tx_cabagpatch_extconf['advancedRealURLRedirectField']."=" . $GLOBALS['TYPO3_DB']->fullQuoteStr($searchString,$tx_cabagpatch_extconf['advancedRealURLRedirectTable'])."
						 AND ".$tx_cabagpatch_extconf['advancedRealURLRedirectTable'].".deleted=0 AND ".$tx_cabagpatch_extconf['advancedRealURLRedirectTable'].".hidden=0 ");
	
				if($GLOBALS['TYPO3_DB']->sql_num_rows($result) > 0) {
					$record = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
					
					$info = array();
					$info['id'] = $record[$tx_cabagpatch_extconf['advancedRealURLRedirectPIDField']];
					$GET_VARS = '';
					
					// Unset code - very important and needed - ask ss for questions
					if(count($urlParts) == 2) {
						unset($urlParts[0]);
						unset($urlParts[1]);
					} else {
						unset($urlParts[0]);
					}
					
					return array($info, $GET_VARS);
				} else {
					// There were no record found in the db - do the standart way
					$GLOBALS['TYPO3_DB']->sql_free_result($result);
					return parent::findIDByURL(&$urlParts);
				}
			} else {
				// There are more than one path part - do the standart way
				return parent::findIDByURL(&$urlParts);
			}
			
		} else {
			// The configuration is not complete - do the standart way
			return parent::findIDByURL(&$urlParts);
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl_advanced.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.ux_tx_realurl_advanced.php']);
}
?>