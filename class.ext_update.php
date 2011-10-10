<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005-2008 René Fritz (r.fritz@colorcube.de)
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
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Class for updating the table tx_realurl_redirects
 *
 * @author	 Sonja Scholz <ss@cabag.ch>
 */
class ext_update  {

	/**
	 * Main function, returning the HTML content of the module
	 *
	 * @return	string		HTML
	 */
	function main()	{

		$content = '';

		$content .= '<br /><b>Change table tx_realurl_redirects:</b><br />
		Remove the field url_hash from the table tx_realurl_redirects, <br />because it\'s not needed anymore and it\'s replaced by the standard TCA field uid as soon as you do <br />the DB updates by the extension manager in the main view of this extension.<br /><br />ALTER TABLE tx_realurl_redirects DROP url_hash<br />ALTER TABLE tx_realurl_redirects ADD uid int(11) auto_increment PRIMARY KEY';
		
		$import = t3lib_div::_GP('update');

		if ($import == 'Update') {
			$result = $this->updateRedirectsTable();
			$content2 .= '<br /><br />';
			$content2 .= '<p>Result: '.$result.'</p>';
			$content2 .= '<p>Done. Please accept the update suggestions of the extension manager now!</p>';
		} else {
			$content2 = '</form>';
			$content2 .= '<form action="'.htmlspecialchars(t3lib_div::linkThisScript()).'" method="post">';
			$content2 .= '<br /><br />';
			$content2 .= '<input type="submit" name="update" value="Update" />';
			$content2 .= '</form>';
		} 

		return $content.$content2;
	}
	
	function updateRedirectsTable() {
		
		$query = 'ALTER TABLE tx_realurl_redirects DROP url_hash';
		$res = $GLOBALS['TYPO3_DB']->admin_query($query);
		$query2 = 'ALTER TABLE tx_realurl_redirects ADD uid int(11) auto_increment PRIMARY KEY';
		$res2 = $GLOBALS['TYPO3_DB']->admin_query($query2);
		return $GLOBALS['TYPO3_DB']->sql_error($res).$GLOBALS['TYPO3_DB']->sql_error($res2);
	}


	function access() {
		return TRUE;
	}

}

// Include extension?
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.ext_update.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.ext_update.php']);
}


?>