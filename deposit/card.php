<?php
/* Copyright (C) 2006		Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2007-2011	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2009-2012	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2011-2016	Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2013 		Philippe Grand      	<philippe.grand@atoo-net.com>
 * Copyright (C) 2015-2016  Alexandre Spangaro      <aspangaro.dolibarr@gmail.com>
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
 *	\file       htdocs/compta/paiement/deposit/card.php
 *	\ingroup    bank, invoice
 *	\brief      Page for paiement's deposits
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/deposit/class/deposit.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

$langs->load("banks");
$langs->load("categories");
$langs->load('bills');
$langs->load('companies');
$langs->load('compta');

$id =GETPOST('id','int');
$ref=GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');
$confirm=GETPOST('confirm', 'alpha');
$ref_ext = GETPOST('ref_ext');

// Security check
$fieldname = (! empty($ref)?'ref':'rowid');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'cheque', $id, 'bordereau_cheque','','',$fieldname);

$sortfield=GETPOST('sortfield', 'alpha');
$sortorder=GETPOST('sortorder', 'alpha');
$page=GETPOST('page', 'int');
if ($page < 0) { $page = 0 ; }
$limit = GETPOST('limit')?GETPOST('limit','int'):$conf->liste_limit;
$offset = $limit * $page ;

$dir=$conf->banque->dir_output.'/checkdeposits/';
$filterdate=dol_mktime(0, 0, 0, GETPOST('fdmonth'), GETPOST('fdday'), GETPOST('fdyear'));
$filteraccountid=GETPOST('accountid');
$filterpaiementid=GETPOST('paiementid');
$tabtoRemise = array();
$tabtoRemise = GETPOST('toRemise');
$object = new Deposit($db);


/*
 * Actions
 */

if ($action == 'setdate' && $user->rights->banque->cheque)
{
    $result = $object->fetch($id);
    if ($result > 0)
    {
        //print "x ".$_POST['liv_month'].", ".$_POST['liv_day'].", ".$_POST['liv_year'];
        $date=dol_mktime(0, 0, 0, GETPOST('datecreate_month'), GETPOST('datecreate_day'), GETPOST('datecreate_year'));

        $result=$object->set_date($user,$date);
        if ($result < 0)
        {
			setEventMessages($object->error, $object->errors, 'errors');
        }
    }
    else
    {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

if ($action == 'setrefext' && $user->rights->banque->cheque)
{
    $result = $object->fetch($id);
    if ($result > 0)
    {
        

        $result=$object->setValueFrom('ref_ext', $ref_ext, '', null, 'text', '', $user, 'CHECKDEPOSIT_MODIFY');
        if ($result < 0)
        {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }
    else
    {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

if ($action == 'setref' && $user->rights->banque->cheque)
{
	$result = $object->fetch($id);
	if ($result > 0)
	{
		$result=$object->set_number($user,$ref);
		if ($result < 0)
		{
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	else
	{
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

if ($action == 'complete' && $filteraccountid > 0 && $user->rights->banque->cheque)
{
	if (!empty($tabtoRemise) and is_array($tabtoRemise))
	{
		$result = $object->fetch($id);
		if ($result > 0) $object->AjouteCheque($tabtoRemise);
		else 
		{
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	else
	{
		setEventMessages($langs->trans("ErrorSelectAtLeastOne"), null, 'mesgs');
	    $action='new';
	}
}
if ($action == 'create' && $filteraccountid > 0  && $filterpaiementid > 0 && $user->rights->banque->cheque)
{
	if (!empty($tabtoRemise) and is_array($tabtoRemise))
	{
		$result = $object->create($user, $filteraccountid, $filterpaiementid, 0, $tabtoRemise);
		if ($result > 0)
		{
	        if ($object->statut == 1)     // If statut is validated, we build doc
	        {
	            $object->fetch($object->id);    // To force to reload all properties in correct property name
	    	    // Define output language
	    	    $outputlangs = $langs;
	            $newlang='';
	            if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
	            //if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
	            if (! empty($newlang))
	            {
	                $outputlangs = new Translate("",$conf);
	                $outputlangs->setDefaultLang($newlang);
	            }
	            $result = $object->generatePdf(GETPOST("model"), $outputlangs);
	        }
			$id = $object->id;
			$action = '';
       		//header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id."&accountid=".$filteraccountid."&paiementid=".$filterpaiementid);
        	//exit;
		}
		else
		{
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	else
	{
		setEventMessages($langs->trans("ErrorSelectAtLeastOne"), null, 'mesgs');
	    $action='new';
	}
}

if ($action == 'remove' && $id > 0 && GETPOST("lineid") > 0 && $user->rights->banque->cheque)
{
	$object->id = $id;
	$result = $object->removeCheck(GETPOST("lineid"));
	if ($result === 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	else
	{
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

if ($action == 'confirm_delete' && $confirm == 'yes' && $user->rights->banque->cheque)
{
	$object->id = $id;
	$result = $object->delete();
	if ($result == 0)
	{
		header("Location: index.php");
		exit;
	}
	else
	{
		setEventMessages($paiement->error, $paiement->errors, 'errors');
	}
}

if ($action == 'confirm_valide' && $confirm == 'yes' && $user->rights->banque->cheque)
{
	$result = $object->fetch($id);
	$result = $object->validate($user);
	if ($result >= 0)
	{
        // Define output language
        $outputlangs = $langs;
        $newlang='';
        if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
        //if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
        if (! empty($newlang))
        {
            $outputlangs = new Translate("",$conf);
            $outputlangs->setDefaultLang($newlang);
        }
        $result = $object->generatePdf(GETPOST('model'), $outputlangs);

        header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}
	else
	{
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

if ($action == 'confirm_reject_check' && $confirm == 'yes' && $user->rights->banque->cheque)
{
	$reject_date = dol_mktime(0, 0, 0, GETPOST('rejectdate_month'), GETPOST('rejectdate_day'), GETPOST('rejectdate_year'));
	$rejected_check = GETPOST('bankid');

	$object->fetch($id);
	$paiement_id = $object->rejectCheck($rejected_check, $reject_date);
	if ($paiement_id > 0)
	{
		setEventMessages($langs->trans("CheckRejectedAndInvoicesReopened"), null, 'mesgs');
		//header("Location: ".DOL_URL_ROOT.'/compta/paiement/card.php?id='.$paiement_id);
		//exit;
		$action='';
	}
	else
	{
		setEventMessages($object->error, $object->errors, 'errors');
		$action='';
	}
}

if ($action == 'builddoc' && $user->rights->banque->cheque)
{
	$result = $object->fetch($id);

	// Save last template used to generate document
	//if (GETPOST('model')) $object->setDocModel($user, GETPOST('model','alpha'));

    $outputlangs = $langs;
    $newlang='';
    if ($conf->global->MAIN_MULTILANGS && empty($newlang) && ! empty($_REQUEST['lang_id'])) $newlang=$_REQUEST['lang_id'];
    //if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang=$object->client->default_lang;
    if (! empty($newlang))
    {
        $outputlangs = new Translate("",$conf);
        $outputlangs->setDefaultLang($newlang);
    }
	$result = $object->generatePdf(GETPOST("model"), $outputlangs);
	if ($result <= 0)
	{
		dol_print_error($db,$object->error);
		exit;
	}
	else
	{
		header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id.(empty($conf->global->MAIN_JUMP_TAG)?'':'#builddoc'));
		exit;
	}
}

// Remove file in doc form
if ($action == 'remove_file' && $user->rights->banque->cheque)
{
	if ($object->fetch($id) > 0)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$langs->load("other");

		$file=$dir.get_exdir($object->ref,0,1,0,$object,'cheque') . GETPOST('file');
		$ret=dol_delete_file($file,0,0,0,$object);
		if ($ret) setEventMessages($langs->trans("FileWasRemoved", GETPOST('file')), null, 'mesgs');
		else setEventMessages($langs->trans("ErrorFailToDeleteFile", GETPOST('file')), null, 'errors');
	}
}


/*
 * View
 */

if (GETPOST('removefilter'))
{
    $filterdate='';
    $filteraccountid=0;
	$filterpaiementid=0;
}
// ENTETE

$title=$langs->trans("TiDeposit") . " - " . $langs->trans("Card");
$helpurl="";
llxHeader();

$formfile = new FormFile($db);


if ($action == 'new')
{
	$h=0;
	$head[$h][0] = $_SERVER["PHP_SELF"].'?action=new'."&accountid=".$filteraccountid."&paiementid=".$filterpaiementid;
	$head[$h][1] = $langs->trans("MenuChequeDeposits");
	$hselected = $h;
	$h++;

	print load_fiche_titre($langs->trans("Paiements"));
}
else
{
	$result = $object->fetch($id, $ref);	
	$filteraccountid=$object->account_id;
	$filterpaiementid=$object->mode_paiementid;
	if ($result < 0)
	{
		dol_print_error($db,$object->error);
		exit;
	}

	$h=0;
	$head[$h][0] = $_SERVER["PHP_SELF"].'?id='.$object->id;
	$head[$h][1] = $langs->trans("TiDeposit");
	$hselected = $h;
	$h++;
	//  $head[$h][0] = DOL_URL_ROOT.'/compta/paiement/deposit/info.php?id='.$object->id;
	//  $head[$h][1] = $langs->trans("Info");
	//  $h++;

	dol_fiche_head($head, $hselected, $langs->trans("TiDeposit"),-1,'payment');

	/*
	 * Confirmation de la suppression du bordereau
	 */
	if ($action == 'delete')
	{
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("DeleteCheckReceipt"), $langs->trans("ConfirmDeleteCheckReceipt"), 'confirm_delete','','',1);

	}

	/*
	 * Confirmation de la validation du bordereau
	 */
	if ($action == 'valide')
	{
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("ValidateCheckReceipt"), $langs->trans("ConfirmValidateCheckReceipt"), 'confirm_valide','','',1);

	}

	/*
	 * Confirm check rejection
	 */
	if ($action == 'reject_check')
	{
		$formquestion = array(
			array('type' => 'hidden','name' => 'bankid','value' => GETPOST('lineid')),
			array('type' => 'date','name' => 'rejectdate_','label' => $langs->trans("RejectCheckDate"),'value' => dol_now())
		);
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans("RejectCheck"), $langs->trans("ConfirmRejectCheck"), 'confirm_reject_check',$formquestion,'',1);
	}
}

$accounts = array();
// AFFICHAGE
if ($action == 'new')
{
	
	AfficheHautBordereauNouv($filteraccountid, $filterpaiementid, $filterdate); 
	AfficheNonRemis(0, $filteraccountid, $filterpaiementid, $filterdate, $accounts, $user, 'creer'); 
}
else
{
	AfficheBordereau($object, $action);
	AfficheRemis($object, $accounts);
	AfficheBoutonValid($object, $user);
		if ($object->statut == 0) {
		print '<br><br><br>';
		AfficheNonRemis($id, $filteraccountid,  $filterpaiementid, $filterdate, $accounts, $user, 'Completer'); 
	}


}

   dol_fiche_end();
// EDITION

if ($action != 'new')
{
	if ($object->statut == 1)
	{
		$filename=dol_sanitizeFileName($object->ref);
		$filedir= $dir .get_exdir($object->ref,0,1,0,$object,'cheque') . dol_sanitizeFileName($object->ref);
		$urlsource=$_SERVER["PHP_SELF"]."?id=".$object->id;

		print $formfile->show_documents('remisecheque', $filename, $filedir, $urlsource, 1, 1);

		print '<br>';
	}
}



llxFooter();

$db->close();
/**
 *  Affiche les filtres permettant la liste des paiements pouvant servir à la consitution d'un bordereau
 *
 *  @param	int		$filteraccountid     id du compte sélectionné
 *  @param	int		$filterpaiementid    id du mode de paiement sélectionné
 *  @param	date	$filterdate		     date sélectionnée
*/
function 	AfficheHautBordereauNouv($filteraccountid, $filterpaiementid, $filterdate)
{
	global $langs, $db, $object;
	$paymentstatic=new Paiement($db);
	$accountlinestatic=new AccountLine($db);
	$lines = array();
	$now=dol_now();
	$form = new Form($db);
	if (!empty($filterpaiementid)) $Mode_paiementLib = $object->getLibModePaiement($filterpaiementid, 'libelle');
	else $Mode_paiementLib = 'paiements' ;

	print $langs->trans("SelectTransactionAndGenerate", $Mode_paiementLib).'<br><br>'."\n";

	print '<form class="nocellnopadd" action="'.$_SERVER["PHP_SELF"].'?accountid='.$filteraccountid."&paiementid=".$filterpaiementid.'" method="GET">';
	print '<input type="hidden" name="action" value="new">';

	dol_fiche_head();

	print '<table class="border" width="100%">';
	//print '<tr><td width="30%">'.$langs->trans('Date').'</td><td width="70%">'.dol_print_date($now,'day').'</td></tr>';
	// Filter
	print '<tr><td class="titlefieldcreate">'.$langs->trans("DateReceived").'</td><td>';
	print $form->select_date($filterdate,'fd',0,0,1,'',1,1,1);
	print '</td></tr>';
    print '<tr><td>'.$langs->trans("BankAccount").'</td><td>';
    $form->select_comptes($filteraccountid,'accountid',0,'courant <> 2',1);
    print '</td></tr>';
    print '<tr><td>'.$langs->trans("TypePaiement").'</td><td>';
    $form->select_types_paiements($filterpaiementid,'paiementid',0,'',1);	
    print '</td></tr>';
	print '</table>';

	dol_fiche_end();

    print '<div class="center">';
	print '<input type="submit" class="button" name="filter" value="'.dol_escape_htmltag($langs->trans("ToFilter")).'">';
    if ($filterdate || $filteraccountid > 0   ||  $filterpaiementid > 0)
    {
    	print ' &nbsp; ';
    	print '<input type="submit" class="button" name="removefilter" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
    }
	print '</div>';
    print '</form>';
	print '<br>';
	unset ($form);
} //AfficheHautBordereauNouv
/**
 *  Affiche les information du bordereau existant
 *
 *  @param	Deposit	$object     	 bordereau
 *  @param	string	$action 		'editrefext', 'editdate' pour indiquer une modification de l'un ou 'autre des champs
*/
function AfficheBordereau($object, $action)
{
	global $db, $langs , $conf, $sortfield, $sortorder; 
	
	$form = new Form($db);
	$linkback='<a href="'.DOL_URL_ROOT.'/compta/paiement/cheque/list.php">'.$langs->trans("BackToList").'</a>';
	$paymentstatic=new Paiement($db);
	$accountlinestatic=new AccountLine($db);
	$accountstatic=new Account($db);
	$accountstatic->fetch($object->account_id);

	$accountstatic->id=$object->account_id;
	$accountstatic->label=$object->account_label;
	$paymentstatic->id = $object->mode_paiementid;
	$paymentstatic->libelle = $object->paiementlib;
	
	$morehtmlref='';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
	
	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
		
	print '<table class="border" width="100%">';
	print '<tr><td class="titlefield">';

	
	//if (empty($conf->global->DEPOSIT_REFEXT)) 
	//		print info_admin('Pour gerer la reference bancaire, valoriser la variable DEPOSIT_REFEXT', 1);
	
    print '<table class="nobordernopadding" width="100%"><tr><td>';
    print $langs->trans('Date');
    print '</td>';
    if ($action != 'editdate') print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editdate&amp;id='.$object->id.'">'.img_edit($langs->trans('SetDate'),1).'</a></td>';
    print '</tr></table>';
    print '</td><td colspan="2">';
    if ($action == 'editdate')
    {
        print '<form name="setdate" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
        print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
        print '<input type="hidden" name="action" value="setdate">';
        $form->select_date($object->date_bordereau,'datecreate_','','','',"setdate");
        print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
        print '</form>';
    }
    else
    {
        print $object->date_bordereau ? dol_print_date($object->date_bordereau,'day') : '&nbsp;';
    }

	print '</td>';
	print '</tr>';

	// External ref
	// Ext ref are not visible field on standard usage
	if ($conf->global->DEPOSIT_REFEXT) {
		print '<tr><td>';

		print '<table class="nobordernopadding" width="100%"><tr><td>';
		print $langs->trans('RefExt');
		print '</td>';
		if ($action != 'editrefext') print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editrefext&amp;id='.$object->id.'">'.img_edit($langs->trans('SetRefExt'),1).'</a></td>';
		print '</tr></table>';
		print '</td><td colspan="2">';
		if ($action == 'editrefext')
		{
			print '<form name="setrefext" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" method="post">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="setrefext">';
			print '<input type="text" name="ref_ext" value="'.$object->ref_ext.'">';
			print '<input type="submit" class="button" value="'.$langs->trans('Modify').'">';
			print '</form>';
		}
		else
		{
			print $object->ref_ext;
		}

		print '</td>';
		print '</tr>';
	}
	print '<tr><td>'.$langs->trans('Account').'</td><td colspan="2">';
	print $accountstatic->getNomUrl(1);
	print '</td></tr>';
	// Type de paiement
    print '<tr><td>'.$langs->trans("TypePaiement").'</td><td>';
	print $paymentstatic->libelle;	
    print '</td></tr>';
	// Nb of paiements
	print '<tr><td>'.$langs->trans('NbOfCheques').'</td><td colspan="2">';
	print $object->nbpaiement;
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('Total').'</td><td colspan="2">';
	print price($object->amount);
	print '</td></tr>';	

	if ($object->amount <>  $object->TotalPaiement) {
		setEventMessage($langs->trans("Error ").$langs->trans(" sur le total des paiements : "). price2num($object->TotalPaiement). '€','warnings');
	}
	elseif ($object->nbpaiement <>  $object->TotalNb) {
		setEventMessage($langs->trans("Error ").$langs->trans(" sur le nombre de paiements : "). $object->TotalNb,'warnings');
	}

	/*print '<tr><td>'.$langs->trans('Status').'</td><td colspan="2">';
	print $object->getLibStatut(4);
	print '</td></tr>';*/

	print '</table><br>';
	print '</div>';
	unset($form);
} //AfficheBordereau

/**
 *  Affiche les paiements du couple compte/mode de paiement du bordereau en cours ou en création, non intégré dans un bordereau, et le bouton d'intégration
 *
 *  @param	int		$id     			 Identifiant du bordereau, null en cas de création
 *  @param	int		$filteraccountid     Identifiant du compte
 *  @param	int		$filterpaiementid    Identifiant du mode de paiement
 *  @param	date	$filterdate   		 Date des paiements
 *  @param	int		$accounts   		 ???
 *  @param	User	$user       		 Object user
 *  @param	string	$titre 				 'Completer' s'il s'agit de compléter un bordereau ($id non null), 'creer' s'il s'agit de créer un bordereau (^$id null)
*/
function AfficheNonRemis($id, $filteraccountid, $filterpaiementid, $filterdate, $accounts, $user,  $titre)
{
	global $db, $langs, $conf , $sortfield, $sortorder; 
	$accountlinestatic=new AccountLine($db);
	$paymentstatic=new Paiement($db);
	
	$form = new Form($db);
	$sql = "SELECT ba.rowid as bid, b.datec as datec, b.dateo as date, b.rowid as transactionid, ";
	$sql.= " b.amount, ba.label, b.emetteur, b.num_chq, b.banque,";
	$sql.= " p.rowid as paymentid, tp.code as paiementcode, tp.libelle as paiementlib, tp.id as mode_paiementid";
	$sql.= " FROM ".MAIN_DB_PREFIX."bank as b";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiement as p ON p.fk_bank = b.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account as ba ON (b.fk_account = ba.rowid)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as tp ON (p.fk_paiement = tp.id)";
	//$sql.= " WHERE b.fk_type = 'CHQ'";
	$sql.= " WHERE ba.entity IN (".getEntity('bank_account').")";
	$sql.= " AND b.fk_bordereau = 0";
	$sql.= " AND b.amount > 0 and ! isnull(p.fk_paiement)  ";
	$sql.= " and p.fk_paiement <> 2 ";
	$sql.= " and courant <> 2 ";
	$sql.= " AND b.fk_type <> 'VIR'";
	$sql.= "  AND not isnull(tp.id)";
	$sql.= "  AND NOT EXISTS (select (1) FROM llx_bank_url as burl WHERE burl.url_id = b.rowid and type = 'banktransfert') ";
	
	
	
	if ($filterdate)          $sql.=" AND b.dateo = '".$db->idate($filterdate)."'";
    if ($filteraccountid > 0) $sql.=" AND ba.rowid= '".$filteraccountid."'";
    if ($filterpaiementid > 0) $sql.=" AND tp.id= '".$filterpaiementid."'";

	if (!empty($sortfield) and !empty($sortorder))  $sql.= $db->order($sortfield, $sortorder);
	else $sql.= $db->order("ba.rowid, tp.id, b.dateo,b.rowid","ASC");
	$resql = $db->query($sql);

	if ($resql)
	{
		$i = 0;
		while ( $obj = $db->fetch_object($resql) )
		{
			$key = $obj->bid.'-'.$obj->mode_paiementid;
			$accountspaiement[$key]["account"] = $obj->bid;
			$accountspaiement[$key]["accountlabel"] = $obj->label;
			$accountspaiement[$key]["paiement"] = $obj->mode_paiementid;
			$accountspaiement[$key]["paiementlabel"] = $obj->paiementlib;
			$accountspaiement[$key]["paiementcode"] = $obj->paiementcode;
			$lines[$key][$i]["date"] = $db->jdate($obj->date);
			$lines[$key][$i]["amount"] = $obj->amount;
			$lines[$key][$i]["emetteur"] = $obj->emetteur;
			$lines[$key][$i]["numero"] = $obj->num_chq;
			$lines[$key][$i]["banque"] = $obj->banque;
			$lines[$key][$i]["id"] = $obj->transactionid;
			$lines[$key][$i]["paymentid"] = $obj->paymentid;
			$lines[$key][$i]["mode_paiementid"] = $obj->mode_paiementid;
			$lines[$key][$i]["paiementlib"] = $obj->paiementlib;
			$lines[$key][$i]["paiementcode"] = $obj->paiementcode;
			$i++;
		}

		if ($i == 0)
		{
			print '<div class="opacitymedium">'.$langs->trans("NoWaitingChecks").'</div><br>';
		}
	}
	
	if (!empty($id)) 	$param="&amp;id=".$id;
	else $param = 'accountid='.$filteraccountid."&paiementid=".$filterpaiementid;
		
	//foreach ($accountspaiement as $bid => $account_label)
	if ($i > 0 ) 
		foreach ($accountspaiement as $key => $accountpaiement)
		{
			$bid = $accountpaiement	["account"];

			print '
			<script language="javascript" type="text/javascript">
			jQuery(document).ready(function()
			{
				jQuery("#checkall_'.$key.'").click(function()
				{
					jQuery(".checkforremise_'.$key.'").prop(\'checked\', true);
				});
				jQuery("#checknone_'.$key.'").click(function()
				{
					jQuery(".checkforremise_'.$key.'").prop(\'checked\', false);
				});
			});
			</script>
			';

			$num = $db->num_rows($resql);
			print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="complete">';
			print '<input type="hidden" name="accountid" value="'.$bid.'">';
			print '<input type="hidden" name="paiementid" value="'.$accountpaiement["paiement"].'">';
			print '<input type="hidden" name="id" value="'.$id.'">';
			$moreforfilter='';
			print '<div class="div-table-responsive-no-min">';
			print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";
			print '<tr class="liste_titre">';
			//	print '<td style="min-width: 120px">'.$langs->trans("DateChequeReceived").'</td>'."\n";
			print_liste_field_titre("DateReceived",$_SERVER["PHP_SELF"],"b.dateo,b.rowid", "",$param,'align="center"',$sortfield,$sortorder);
			print "</td>\n";
			//print '<td style="min-width: 120px">'.$langs->trans("ChequeNumber")."</td>\n";
			print_liste_field_titre("Numero",$_SERVER["PHP_SELF"],"b.num_chq", "",$param,'align="center"',$sortfield,$sortorder);
			//print '<td style="min-width: 200px">'.$langs->trans("CheckTransmitter")."</td>\n";
			print_liste_field_titre("CheckTransmitter",$_SERVER["PHP_SELF"],"b.emetteur", "",$param,"",$sortfield,$sortorder);
			//print '<td style="min-width: 200px">'.$langs->trans("Bank")."</td>\n";
			print_liste_field_titre("Bank",$_SERVER["PHP_SELF"],"b.banque", "",$param,"",$sortfield,$sortorder);
			//print '<td align="right" width="100px">'.$langs->trans("Amount")."</td>\n";
			print_liste_field_titre("Amount",$_SERVER["PHP_SELF"],"b.amount", "",$param,'align="right"',$sortfield,$sortorder);
			//print '<td align="center" width="100px">'.$langs->trans("Payment")."</td>\n";
			print_liste_field_titre("Payment",$_SERVER["PHP_SELF"],"p.rowid", "",$param,'align="center"',$sortfield,$sortorder);
			//print '<td align="center" width="100px">'.$langs->trans("LineRecord")."</td>\n";
			print_liste_field_titre("LineRecord",$_SERVER["PHP_SELF"],"b.rowid", "",$param,'align="center"',$sortfield,$sortorder);
			print '<td align="center" width="100px">'.$langs->trans("Select")."<br>";
			if ($conf->use_javascript_ajax) print '<a href="#" id="checkall_'.$key.'">'.$langs->trans("All").'</a> / <a href="#" id="checknone_'.$key.'">'.$langs->trans("None").'</a>';
			print '</td>';
			

			print "</tr>\n";
			if (count($lines[$key]))
			{
				foreach ($lines[$key] as $lid => $value)
				{
					$account_id = $bid;
		/*			Pourquoi?
					if (! isset($accounts[$bid]))
						$accounts[$bid]=0;
					$accounts[$bid] += 1;
		*/
					print '<tr class="oddeven">';
					//print '<tr>';
					print '<td>'.dol_print_date($value["date"],'day').'</td>';
					print '<td>'.$value["numero"]."</td>\n";
					print '<td>'.$value["emetteur"]."</td>\n";
					print '<td>'.$value["banque"]."</td>\n";
					print '<td align="right">'.price($value["amount"], 0, $langs, 1, -1, -1, $conf->currency).'</td>';
					
					// Link to payment
					print '<td align="center">';
					$paymentstatic->id=$value["paymentid"];
					$paymentstatic->ref=$value["paymentid"];
					if ($paymentstatic->id)
					{
						print $paymentstatic->getNomUrl(1);
					}
					else
					{
						print '&nbsp;';
					}
					print '</td>';
					// Link to bank transaction
					print '<td align="center">';
					$accountlinestatic->rowid=$value["id"];
					if ($accountlinestatic->rowid)
					{
						print $accountlinestatic->getNomUrl(1);
					}
					else
					{
						print '&nbsp;';
					}
					print '</td>';
					
					print '<td align="center">';
					print '<input id="'.$value["id"].'" class="flat checkforremise_'.$key.'" checked type="checkbox" name="toRemise['.$value["id"].']" value="'.$value["id"].'">';
					print '</td>' ;
					print '</tr>';

					$i++;
				}
			}
			print "</table>";
			print '</div>';

			print '<div class="tabsAction">';
			if ($titre == 'Completer'){
				if ($user->rights->banque->cheque)
				{
					print '<input type="submit" class="button" value="'.$langs->trans('CompleteCheckDepositOn').'">';
				}
				else
				{
					print '<a class="butActionRefused" href="#" title="'.$langs->trans("NotEnoughPermissions").'">'.$langs->trans('CompleteCheckDepositOn').'</a>';
				}
			}
			elseif ($titre == 'creer'){
				if ($user->rights->banque->cheque)
				{
					print '<input type="submit" class="button" value="'.$langs->trans('NewCheckDepositOn',$accountpaiement["paiementlabel"],$accountpaiement	["accountlabel"]).'">';
				}
				else
				{
					print '<a class="butActionRefused" href="#" title="'.$langs->trans("NotEnoughPermissions").'">'.$langs->trans('NewCheckDepositOn',$accountpaiement["paiementlabel"],$accountpaiement	["accountlabel"]).'</a>';
				}
			}
			print '</div><br>';
			print '</form>';
			
		} 
	unset($form);
} //AfficheNonRemis
/**
 *  Affiche les paiements du  bordereau
 *
 *  @param	Deposit	$object     Bordereau
 */
 function AfficheRemis($object, $accounts )
{
	global $db, $langs , $sortfield, $sortorder; 
	$accountlinestatic=new AccountLine($db);
	$paymentstatic=new Paiement($db);
	// List of paiements
	$sql = "SELECT b.rowid, b.amount, b.num_chq, b.emetteur,";
	$sql.= " b.dateo as date, b.datec as datec, b.banque,";
	$sql.= " p.rowid as pid, ba.rowid as bid, p.statut";
	$sql.= " FROM ".MAIN_DB_PREFIX."bank_account as ba";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."bank as b ON (b.fk_account = ba.rowid)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiement as p ON p.fk_bank = b.rowid";
	$sql.= " WHERE ba.entity IN (".getEntity('bank_account').")";
	$sql.= " AND b.fk_bordereau = ".$object->id;
	
	if (! $sortfield) $sortfield="b.dateo,b.rowid";	
	if (! $sortorder) $sortorder="ASC";
	$sql.= $db->order($sortfield, $sortorder);

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);

	    print '<div class="div-table-responsive">';
		print '<table class="noborder" width="100%">';

		$param="&amp;id=".$object->id;
		
		print '<tr class="liste_titre">';
		print_liste_field_titre("Cheques",'','','','','width="30"');
		print_liste_field_titre("DateReceived",$_SERVER["PHP_SELF"],"b.dateo,b.rowid", "",$param,'align="center"',$sortfield,$sortorder);
		print_liste_field_titre("Numero",$_SERVER["PHP_SELF"],"b.num_chq", "",$param,'align="center"',$sortfield,$sortorder);
		print_liste_field_titre("CheckTransmitter",$_SERVER["PHP_SELF"],"b.emetteur", "",$param,"",$sortfield,$sortorder);
		print_liste_field_titre("Bank",$_SERVER["PHP_SELF"],"b.banque", "",$param,"",$sortfield,$sortorder);
		print_liste_field_titre("Amount",$_SERVER["PHP_SELF"],"b.amount", "",$param,'align="right"',$sortfield,$sortorder);
		print_liste_field_titre("Payment",$_SERVER["PHP_SELF"],"p.rowid", "",$param,'align="center"',$sortfield,$sortorder);
		print_liste_field_titre("LineRecord",$_SERVER["PHP_SELF"],"b.rowid", "",$param,'align="center"',$sortfield,$sortorder);
		print_liste_field_titre('');
		print "</tr>\n";
		$i=1;
        if ($num > 0)
        {
			while ($objp = $db->fetch_object($resql))
			{
				$account_id = $objp->bid;
				/* pôurquoi ?
				if (! isset($accounts[$objp->bid]))
					$accounts[$objp->bid]=0;
				$accounts[$objp->bid] += 1;*/

				print '<tr class="oddeven">';
				print '<td align="center">'.$i.'</td>';
				print '<td align="center">'.dol_print_date($db->jdate($objp->date),'day').'</td>';	// Date operation
				print '<td align="center">'.($objp->num_chq?$objp->num_chq:'&nbsp;').'</td>';
				print '<td>'.dol_trunc($objp->emetteur,24).'</td>';
				print '<td>'.dol_trunc($objp->banque,24).'</td>';
				print '<td align="right">'.price($objp->amount).'</td>';
				// Link to payment
				print '<td align="center">';
				$paymentstatic->id=$objp->pid;
				$paymentstatic->ref=$objp->pid;
				if ($paymentstatic->id)
				{
					print $paymentstatic->getNomUrl(1);
				}
				else
				{
					print '&nbsp;';
				}
				print '</td>';
				// Link to bank transaction
				print '<td align="center">';
				$accountlinestatic->rowid=$objp->rowid;
				if ($accountlinestatic->rowid)
				{
					print $accountlinestatic->getNomUrl(1);
				}
				else
				{
					print '&nbsp;';
				}
				print '</td>';
				// Action button
				print '<td align="right">';
				if ($object->statut == 0)
				{
					print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=remove&amp;lineid='.$objp->rowid.'">'.img_delete().'</a>';
				}
				if ($object->statut == 1 && $objp->statut != 2)
				{
					print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=reject_check&amp;lineid='.$objp->rowid.'">'.img_picto($langs->trans("RejectCheck"),'disable').'</a>';
				}
				if ($objp->statut == 2) 
				{
					print ' &nbsp; '.img_picto($langs->trans('CheckRejected'),'statut8').'</a>';
				}
				print '</td>';
				print '</tr>';
				$i++;
			} 
		}
        else
        {
            print '<td colspan="8" class="opacitymedium">';
            print $langs->trans("None");
            print '</td>';
        }

		print "</table>";
		print "</div>";
	}
	else
	{
		dol_print_error($db);
	}

} //AfficheRemis

/**
 *  Affiche les boutons de validation/suppression du bordereau
 *
 *  @param	Deposit	$object     Bordereau
 *  @param	User	$user       Object user
 */
function AfficheBoutonValid($object, $user)
{
	global  $langs; 
	
	print '<div class="tabsAction">';

	/*if ($user->societe_id == 0 && count($accounts) == 1 && $action == 'new' && $user->rights->banque->cheque)
	{
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=create&amp;accountid='.$account_id.'">'.$langs->trans('NewCheckReceipt').'</a>';
	}*/

	if ($user->societe_id == 0 && ! empty($object->id) && $object->statut == 0 && $user->rights->banque->cheque)
	{
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=valide&amp;sortfield='.$sortfield.'&amp;sortorder='.$sortorder.'">'.$langs->trans('Validate').'</a>';
	}

	if ($user->societe_id == 0 && ! empty($object->id) && $user->rights->banque->cheque)
	{
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=delete&amp;sortfield='.$sortfield.'&amp;sortorder='.$sortorder.'">'.$langs->trans('Delete').'</a>';

	}
	print '</div>';
} //AfficheBoutonValid
