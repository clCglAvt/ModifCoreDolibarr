<?php
/*
 * Copyright (C) 2017 Claude Castellano        <claude@cigaleaventure.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file       compta/bank/class/html.bankcateg.class.php
 * \ingroup    bank
 * \brief      This file is html form D class file (select_depot)
 */

/**
 *    Class to manage bank categories
 */
class FormDeposit // extends CommonObject
{
	//public $element='bank_categ';			//!< Id that identify managed objects
	//public $table_element='bank_categ';	//!< Name of table without prefix where object is stored
    public $picto='generic';
    
	public $id;
	public $label;
   

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	public function  select_depots($selected, $htmlname = 'bordereau', $sqlfrom, $sqlwhere,  $empty=0,  $morecss='' )
	{	
        dol_syslog(__METHOD__." ".$selected.", ".$htmlname.", ".$filtertype.", ".$format, LOG_DEBUG);

		$undeposit= new Deposit($this->db);
		$listdepost = $undeposit->fetch_all($sqlfrom, $sqlwhere);		

        print '<select id="select'.$htmlname.'" class="flat selectpaymenttypes'.($morecss?' '.$morecss:'').'" name="'.$htmlname.'">';
        if ($empty) print '<option value="">&nbsp;</option>';
        foreach($listdepost as  $ligndeposit)
        {
            print '<option value="'.$ligndeposit['id'].'"';
            if ($ligndeposit['id'] == $selected) print ' selected';
            print '>';
            print $ligndeposit['ref'];
            print '</option>';
        }
        print '</select>';
		
	} //select_depots

}
