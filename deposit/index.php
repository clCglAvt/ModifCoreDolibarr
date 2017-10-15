<?php
/* Copyright (C) 2006      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2007-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2009      Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2016      Juanjo Menent	    <jmenent@2byte.es>
 * Copyright (C) 2017	   Claude Castellano    <claude@cigaleaventure.com>
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
 *		\file       htdocs/compta/paiement/deposit/index.php
 *		\ingroup    compta
 *		\brief      Home page for deposit receipts
 */

require('../../../main.inc.php');
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/deposit/class/deposit.class.php';
//require_once DOL_DOCUMENT_ROOT.'/compta/paiement/carte_bancaire/class/RemiseTelecollecte.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/cpaiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';


$langs->load("banks");
$langs->load("categories");
$langs->load("compta");
$langs->load("bills");

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'banque', '','');


$checkdepositstatic=new Deposit($db);
$checkdepositligne=new Deposit($db);
$accountstatic=new Account($db);
$accountligne=new Account($db);
$cpaimentstatic=new Cpaiement($db);


/*
 * View
 */

llxHeader('',$langs->trans("ChequesArea"));

print load_fiche_titre($langs->trans("ChequesArea"));

print '<div class="fichecenter"><div class="fichethirdleft">';

$sql = "SELECT count(b.rowid) as nb, tp.libelle as Mode_paiement, ba.label as banque, ba.rowid as bankid, tp.id as Mode_paiementId";
	$sql.= " FROM ".MAIN_DB_PREFIX."bank as b";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiement as p ON p.fk_bank = b.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account as ba ON (b.fk_account = ba.rowid)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as tp ON (p.fk_paiement = tp.id)";
$sql.= " WHERE ba.entity IN (".getEntity('bank_account').")";
$sql.= " AND b.fk_type <> 'VIR'";
$sql.= " AND b.fk_bordereau = 0";
$sql.= "  AND courant  <> 2";
$sql.= "  AND not isnull(tp.id)";
$sql.= "  AND NOT EXISTS (select (1) FROM llx_bank_url as burl WHERE burl.url_id = b.rowid and type = 'banktransfert') ";
$sql.= " AND b.amount > 0";
$sql.= " group by  ba.rowid,  ba.label, tp.id,  tp.libelle";

$resql = $db->query($sql);

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<th colspan="2">'.$langs->trans("BankChecks")."</th>\n";
print "</tr>\n";

if ($resql)
{
  while ($obj = $db->fetch_object($resql)) {	 
	  $num = $obj->nb;
	  $bank = $obj->bankid;
	  $paiement = $obj->Mode_paiementId;
	  $label_bank = $obj->banque;
	  $label_paiement = $obj->Mode_paiement;
	  print '<tr class="oddeven">';
	  print '<td>'.$label_paiement.' '.$langs->trans("ToReceipt").' sur ' .$label_bank.'</td>';
	  print '<td align="right">';
	  print '<a href="'.DOL_URL_ROOT.'/compta/paiement/deposit/card.php?leftmenu=customers_bills_checks&action=new'."&accountid=".$bank."&paiementid=".$paiement.'">'.$num."</a>";
	  print '</td></tr>';
  }
  print "</table>\n";
}
else
{
  dol_print_error($db);
}

print '</div><div class="fichetwothirdright"><div class="ficheaddleft">';

$sql = "SELECT bc.rowid, bc.date_bordereau as db, bc.amount, bc.ref as ref, bc.fk_type_paiement, tp.libelle";
$sql.= ", bc.statut, bc.nbcheque";
$sql.= ", ba.label, ba.rowid as bid, tp.libelle, tp.code, tp.id ";
$sql.= " , count(b.rowid) as TotalPaiement, sum(b.amount) as TotalMtt ";
$sql.= " FROM ".MAIN_DB_PREFIX."bordereau_cheque as bc";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as tp ON ( bc.fk_type_paiement = tp.id)";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."bank as b ON (fk_bordereau = bc.rowid) , ".MAIN_DB_PREFIX."bank_account as ba";
$sql.= " WHERE ba.rowid = bc.fk_bank_account";
$sql.= " AND bc.entity = ".$conf->entity;
$sql.= " AND year(date_bordereau) > year(now()) - 2";
$sql.= " GROUP BY bc.rowid, bc.date_bordereau , bc.amount, bc.ref , bc.fk_type_paiement, tp.libelle, bc.statut, bc.nbcheque, ba.label, ba.rowid, tp.libelle, tp.code, tp.id";
$sql.= " ORDER BY ba.rowid, bc.fk_type_paiement,  bc.date_bordereau DESC, bc.rowid DESC";

$resql = $db->query($sql);
if (empty($conf->global->DEPOSITNBLIGNE)) $maxligne = 10;
else $maxligne = $conf->global->DEPOSITNBLIGNE;
if ($resql)
{
	$nbligne = $db->num_rows($resql);
	$i=0;
	$objp = $db->fetch_object($resql);

	while ( $i < $nbligne ) {
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print '<th>';
		print( '<a href="'.DOL_URL_ROOT.'/compta/paiement/deposit/list.php?leftmenu=customers_bills_checks'."&bank=".$objp->bid.
			 "&mode_paiement=".$objp->id.'" title="Liste compète"><b>'.
			 $langs->trans("LastReceiptShort",$maxligne, $objp->libelle, $objp->label)	);
		print '</b></a></th>';
		print '<th>'.$langs->trans("Date")."</th>";
		print '<th>'.$langs->trans("Account").'</th>';
		print '<th align="right">'.$langs->trans("Number").'</th>';
		print '<th align="right">'.$langs->trans("Amount").'</th>';
		print '<th align="right">'.$langs->trans("Status").'</th>';
		print "</tr>\n";
		
		
		$checkdepositstatic->id		=$objp->rowid;
		$checkdepositstatic->ref	=($objp->ref?$objp->ref:$objp->rowid);
		$checkdepositstatic->statut	=$objp->statut;

		$accountstatic->id			=$objp->bid;
		$accountstatic->label		=$objp->label;
		$cpaimentstatic->code 		= $objp->code;
		$cpaimentstatic->libelle	=$objp->libelle;
		$cpaimentstatic->id			=$objp->id;
		$j=0;
		while (  $i < $nbligne  and $cpaimentstatic->id	== $objp->id and $accountstatic->id	== $objp->bid)
		{					
			$var=!$var;

			$accountligne->id			=$objp->bid;
			$accountligne->label		=$objp->label;
			
			$checkdepositligne->id		=$objp->rowid;
			$checkdepositligne->ref	=($objp->ref?$objp->ref:$objp->rowid);
			$checkdepositligne->statut	=$objp->statut;
			if ($j < $maxligne or $objp->TotalPaiement <> $objp->nbcheque or $objp->TotalMtt <> $objp->amount ) {
				if ( $objp->TotalPaiement <> $objp->nbcheque or $objp->TotalMtt <> $objp->amount ) print "<tr style='color:red'>\n";
				else print '<tr class="oddeven">'."\n";
				print '<td>'.$checkdepositligne->getNomUrl(1).'</td>';
				print '<td>'.dol_print_date($db->jdate($objp->db),'day').'</td>';
				print '<td>'.$accountligne->getNomUrl(1).'</td>';
				print '<td align="right">'.$objp->nbcheque.'</td>';
				print '<td align="right">'.price($objp->amount).'</td>';
				print '<td align="right">'.$checkdepositligne->LibStatut($objp->statut,3).'</td>';
				print '</tr>';
			}
			$j++; $i++;
			if ($i < $nbligne) $objp = $db->fetch_object($resql);
		}

		print "</table>";
		print "&nbsp";
		$i++;
	}
	$db->free($resql);
}
else
{
  dol_print_error($db);
}

print '</div></div></div>';

llxFooter();

$db->close();
