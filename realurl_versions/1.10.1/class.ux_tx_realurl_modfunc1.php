<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2004-2005 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
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
 * Speaking Url management extension
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage tx_realurl
 */
class ux_tx_realurl_modfunc1 extends tx_realurl_modfunc1 {

	/**
	 * View error log
	 *
	 * @return	string		HTML
	 */
	function logView()	{

		$cmd = t3lib_div::_GP('cmd');
		if ($cmd==='deleteAll')	{
			$GLOBALS['TYPO3_DB']->exec_DELETEquery(
				'tx_realurl_errorlog',
				''
			);
		}

		$list = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_realurl_errorlog',
			'',
			'',
			'counter DESC, tstamp DESC',
			100
		);

		if (is_array($list))	{
			$output=''; $cc = 0;
			$hostNameCache = array();

			foreach($list as $rec)	{
				$host = '';
				if ($rec['rootpage_id'] != 0) {
					if (isset($hostCacheName[$rec['rootpage_id']])) {
						$host = $hostCacheName[$rec['rootpage_id']];
					}
					else {
						$hostCacheName[$rec['rootpage_id']] = $host = $this->getHostName($rec['rootpage_id']);
					}
				}
				
				$newIconOnClick = t3lib_BEfunc::editOnClick('&edit[tx_realurl_redirects]['.$this->pObj->id.']=new&defVals[tx_realurl_redirects][url]='.rawurlencode($rec['url']),$this->pObj->doc->backPath);
				
				$newRedirectLink = '<a href="#" onclick="'.htmlspecialchars($newIconOnClick).'">'.
					'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/napshot.gif','width="16" height="16"').' title="Set as redirect" alt="" />
				</a>';
				
					// Add data:
				$tCells = array();
				$tCells[]='<td>'.$rec['counter'].'</td>';
				$tCells[]='<td>'.t3lib_BEfunc::dateTimeAge($rec['tstamp']).'</td>';
				$tCells[]='<td><a href="'.htmlspecialchars($host.'/'.$rec['url']).'" target="_blank">'.($host ? $host . '/' : '') . htmlspecialchars($rec['url']).'</a>'.
							$newRedirectLink.
							'</td>';
				$tCells[]='<td>'.htmlspecialchars($rec['error']).'</td>';
				$tCells[]='<td>'.
								($rec['last_referer'] ? '<a href="'.htmlspecialchars($rec['last_referer']).'" target="_blank">'.htmlspecialchars($rec['last_referer']).'</a>' : '&nbsp;').
								'</td>';
				$tCells[]='<td>'.t3lib_BEfunc::datetime($rec['cr_date']).'</td>';

					// Compile Row:
				$output.= '
					<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
						'.implode('
						',$tCells).'
					</tr>';
				$cc++;
			}
				// Create header:
			$tCells = array();
			$tCells[]='<td>Counter:</td>';
			$tCells[]='<td>Last time:</td>';
			$tCells[]='<td>URL:</td>';
			$tCells[]='<td>Error:</td>';
			$tCells[]='<td>Last Referer:</td>';
			$tCells[]='<td>First time:</td>';

			$output = '
				<tr class="bgColor5 tableheader">
					'.implode('
					',$tCells).'
				</tr>'.$output;

				// Compile final table and return:
			$output = '
			<br/>
				<a href="'.$this->linkSelf('&cmd=deleteAll').'">'.
				'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/garbage.gif','width="11" height="12"').' title="Delete All" alt="" />'.
				' Flush log</a>
				<br/>
			<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">'.$output.'
			</table>';

			return $output;
		}
	}

	/**
	 * Redirect view
	 *
	 * @return	string		HTML
	 */
	function redirectView()	{

			// Init variables.
		$output='';
		
			// PATCH andreas.otto@dkd.de, Add vars to mod settings, to change select order.
		$gpVars = t3lib_div::GPvar('SET');
		( isset( $gpVars['ob'] ) ) ? $this->pObj->MOD_SETTINGS['ob'] = $gpVars['ob'] : $this->pObj->MOD_SETTINGS['ob'] = 'url';
		( isset( $gpVars['obdir'] ) ) ? $this->pObj->MOD_SETTINGS['obdir'] = $gpVars['obdir'] : $this->pObj->MOD_SETTINGS['obdir'] = 'ASC';

			// SELECT ALL redirects which are not deleted:
		$list = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'DISTINCT tx_realurl_redirects.*, sys_domain.uid as sys_domain_uid, sys_domain.domainName as sys_domain_domainName', 
			"tx_realurl_redirects 
				LEFT JOIN tx_realurl_redirects_sys_domain_mm ON (
					tx_realurl_redirects.uid = tx_realurl_redirects_sys_domain_mm.uid_local)
				LEFT JOIN sys_domain ON (
					tx_realurl_redirects_sys_domain_mm.uid_foreign = sys_domain.uid) ", 
			"tx_realurl_redirects.deleted = 0 ",
			'',
			$this->pObj->MOD_SETTINGS['ob'] . ' ' . $this->pObj->MOD_SETTINGS['obdir']
			);

		if (is_array($list))	{
			$cc = 0;

			foreach($list as $rec)	{
				$editIconOnClick = t3lib_BEfunc::editOnClick('&edit[tx_realurl_redirects]['.$rec['uid'].']=edit',$this->pObj->doc->backPath);
					
				// Decide wether the unhide or the hide button should be shown
				if(empty($rec['hidden'])) {
					$hideUnhideButton = '<a href="#" onclick="jumpToUrl(\''.htmlspecialchars($this->pObj->doc->issueCommand('&data[tx_realurl_redirects]['.$rec['uid'].'][hidden]=1')).'\');return false;">
								<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/button_hide.gif','width="16" height="16"').' title="Hide entry" alt="" />
							</a>';
				} else {
					$hideUnhideButton = '<a href="#" onclick="jumpToUrl(\''.htmlspecialchars($this->pObj->doc->issueCommand('&data[tx_realurl_redirects]['.$rec['uid'].'][hidden]=0')).'\');return false;">
								<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/button_unhide.gif','width="16" height="16"').' title="Unhide entry" alt="" />
							</a>';
				}
				
				$tCells = array();
				$tCells[] = '<td>'.
							'<a href="#" onclick="'.htmlspecialchars($editIconOnClick).'">'.
								'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/edit2.gif','width="16" height="16"').' title="Edit entry" alt="" />
							</a>
							'.$hideUnhideButton.
							
							'<a onclick="if(confirm(String.fromCharCode(68,105,101,115,101,110,32,68,97,116,101,110,115,97,116,122,32,116,97,116,115,228,99,104,108,105,99,104,32,108,246,115,99,104,101,110,63))){
								jumpToUrl(\''.htmlspecialchars($this->pObj->doc->issueCommand('&cmd[tx_realurl_redirects]['.$rec['uid'].'][delete]=1')).'\');};return false;" href="#" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:cm.delete', TRUE) . '"><img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/garbage.gif','width="16" height="16"').' title="Delete entry" alt="" /></a>'.
						'</td>';
				$tCells[]='<td>'.$rec['counter'].'</td>';
				
				if(!empty($rec['sys_domain_domainName'])) {
					$domain = 'http://'.$rec['sys_domain_domainName'].'/';
					if(!empty($rec['url'])) {
						$tCells[] = sprintf( '<td><a href="%s" target="_blank">%s</a></td>',htmlspecialchars($domain.$rec['url']), htmlspecialchars($domain.$rec['url']) );
					} else {
						$tCells[]='<td>'.$domain.$rec['url_regular_expression'].'</td>';
					}
					
				} else {
					if(!empty($rec['url'])) {
						$tCells[] = sprintf( '<td><a href="%s" target="_blank">%s</a></td>',htmlspecialchars(t3lib_div::getIndpEnv('TYPO3_SITE_URL').$rec['url']), htmlspecialchars($rec['url']) );
					} else {
						$tCells[]='<td>'.$rec['url_regular_expression'].'</td>';
					}
				}
				
				if(!empty($rec['destination'])) {
					$tCells[] = sprintf( '<td><a href="%s" target="_blank" title="%s">%s</a></td>', htmlspecialchars(t3lib_div::locationHeaderUrl($rec['destination'])), htmlspecialchars($rec['destination']), ( strlen( htmlspecialchars($rec['destination']) ) > 30 ) ? substr(htmlspecialchars($rec['destination']),0,30) . '...' : htmlspecialchars($rec['destination']) );
				} else {
					$tCells[]='<td>'.$rec['destination_typolink'].'</td>';
				}
				
				$tCells[] = '<td>'.($rec['has_moved'] ? 'YES' : '&nbsp;').'</td>';

				if ($rec['last_referer']) {
					$lastRef = htmlspecialchars($rec['last_referer']);
					$tCells[] = sprintf( '<td><a href="%s" target="_blank" title="%s">%s</a></td>', $lastRef, $lastRef, (strlen($rec['last_referer']) > 30) ? htmlspecialchars(substr($rec['last_referer'], 0, 30)) . '...' : $lastRef);
				} else {
					$tCells[] = '<td>&nbsp;</td>';
				}

					// Error:
				$eMsg = '';
				if (($pagesWithUrl = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('page_id','tx_realurl_urlencodecache','content='.$GLOBALS['TYPO3_DB']->fullQuoteStr($rec['url'],'tx_realurl_urlencodecache'))))	{
					foreach($pagesWithUrl as $k => $temp)	$pagesWithUrl[$k] = $temp['page_id'];
					$eMsg.= $this->pObj->doc->icons(3).'Also a page URL: '.implode(',',array_unique($pagesWithUrl));
				}
				$tCells[]='<td>'.$eMsg.'</td>';

				if(!empty($rec['last_time'])) {
					$tCells[]='<td>'.t3lib_BEfunc::dateTimeAge($rec['last_time']).'</td>';
				} else {
					$tCells[]='<td>'.t3lib_BEfunc::dateTimeAge($rec['tstamp']).'</td>';
				}

					// Compile Row:
				$output.= '
					<tr class="bgColor'.($cc%2 ? '-20':'-10').'">
						'.implode('
						',$tCells).'
					</tr>';
				$cc++;
			}

				// Create header:
				// PATCH andreas.otto@dkd.de, order by rules and making header linkable.
			if ( $this->pObj->MOD_SETTINGS['obdir'] == 'ASC' ) {
				$obdir = 'DESC';
			} else {
				$obdir = 'ASC';
			}
			$tCells = array();
			$tCells[]='<td>&nbsp;</td>';
			$tCells[]=sprintf( '<td><a href="%s">Counter:</a></td>', sprintf( 'index.php?id=%d&SET[type]=%s&SET[ob]=counter&SET[obdir]=%s', $this->pObj->id, $this->pObj->MOD_SETTINGS['type'], $obdir ) );
			$tCells[]=sprintf( '<td><a href="%s">URL:</a></td>', sprintf( 'index.php?id=%d&SET[type]=%s&SET[ob]=url&SET[obdir]=%s', $this->pObj->id, $this->pObj->MOD_SETTINGS['type'], $obdir ) );
			$tCells[]=sprintf( '<td><a href="%s">Redirect to:</a></td>', sprintf( 'index.php?id=%d&SET[type]=%s&SET[ob]=destination&SET[obdir]=%s', $this->pObj->id, $this->pObj->MOD_SETTINGS['type'], $obdir ) );
			$tCells[]=sprintf( '<td><a href="%s">301:</a></td>', sprintf( 'index.php?id=%d&SET[type]=%s&SET[ob]=has_moved&SET[obdir]=%s', $this->pObj->id, $this->pObj->MOD_SETTINGS['type'], $obdir ) );
			$tCells[]=sprintf( '<td><a href="%s">Last referer:</a></td>', sprintf( 'index.php?id=%d&SET[type]=%s&SET[ob]=last_referer&SET[obdir]=%s', $this->pObj->id, $this->pObj->MOD_SETTINGS['type'], $obdir ) );
			$tCells[]='<td>Error:</td>';
			$tCells[]='<td>Last time:</td>';

			$output = '
				<tr class="bgColor5 tableheader">
					'.implode('
					',$tCells).'
				</tr>'.$output;

			$newIconOnClick = t3lib_BEfunc::editOnClick('&edit[tx_realurl_redirects]['.$this->pObj->id.']=new',$this->pObj->doc->backPath);
			
				// Compile final table and return:
			$output = '<br/>
				<a href="#" onclick="'.htmlspecialchars($newIconOnClick).'">'.
					'<img'.t3lib_iconWorks::skinImg($this->pObj->doc->backPath,'gfx/new_el.gif','width="16" height="16"').' title="New entry" alt="" />
				</a><br/>
				<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding c-list">'.$output.'
				</table>';

			return $output;
		}
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.tx_realurl_modfunc1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_realurl/realurl_versions/'.REALURL_version.'/class.tx_realurl_modfunc1.php']);
}
?>