<?php
/******************************************************************************
* Copyright (C) 2006 Jonas Genannt <jonas.genannt@brachium-system.net>
* 
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 2
* of the License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
******************************************************************************/
session_start();
include("config.inc.php");
include("check_access.php");
if (isset($_GET['user']) && $_GET['user']=='y' || $_SESSION['ad_user']=='y')
{
	$smarty->assign('if_ad_user','y');
	$_SESSION['ad_user']='y';
}
if (isset($_GET['user']) && $_GET['user']=='n')
{
	$smarty->assign('if_ad_user','n');
	$_SESSION['ad_user']='n';
}


//Save Options to database:
if (isset($_POST['save_option']) && $_SESSION['spamassassin']==1)
{
	if (isset($_POST['active']) && is_numeric($_POST['active']))
	{
		$sql=sprintf("UPDATE users SET spamassassin='%d' WHERE id='%d'",
			$db->escapeSimple($_POST['active']),
			$db->escapeSimple($_SESSION['uid']));
		$result=&$db->query($sql);
		//FIXME: in mailfilter ein und austragen!
		if ($_POST['active']=='1')
		{
			//INSERT INTO
			$sql=sprintf("UPDATE mailfilter SET active='0' WHERE email='%d' AND type='spamassassin'",
				$db->escapeSimple($_SESSION['uid']));
			$result=&$db->query($sql);
			$sql=sprintf("INSERT INTO mailfilter SET email='%d', active='1',prio='5', type='spamassassin'",
				$db->escapeSimple($_SESSION['uid']));
			$result=&$db->query($sql);
		}
		else if($_POST['active']=='0')
		{
			//reset filter
			$sql=sprintf("UPDATE mailfilter SET active='0' WHERE email='%d' AND type='spamassassin'",
				$db->escapeSimple($_SESSION['uid']));
			$result=&$db->query($sql);
		}
	}
	if (isset($_POST['rewrite_subject']) && is_numeric($_POST['rewrite_subject']))
	{
		$sql=sprintf("SELECT value FROM spamassassin WHERE username='%s' AND preference='rewrite_header subject'",
			$db->escapeSimple($_SESSION['email']));
		$result=&$db->query($sql);
		unset($sql);
		if (strlen($_POST['rewrite_subject_header'])>15)
		{
			$smarty->assign('if_subject_to_long','1');
		}
		elseif ($result->numRows()==1)
		{
			if ($_POST['rewrite_subject']==0)
			{
			$sql=sprintf("UPDATE spamassassin SET value='' WHERE username='%s' AND preference='rewrite_header subject'",
				$db->escapeSimple($_SESSION['email']));
			}
			elseif ($_POST['rewrite_subject']==1)
			{
				$sql=sprintf("UPDATE spamassassin SET value='%s' WHERE username='%s' AND preference='rewrite_header subject'",
				$db->escapeSimple($_POST['rewrite_subject_header']),
				$db->escapeSimple($_SESSION['email']));
			
			}
			
		}
		else
		{
			if ($_POST['rewrite_subject']==0)
			{
			$sql=sprintf("INSERT INTO spamassassin SET value='', username='%s',preference='rewrite_header subject'",
				$db->escapeSimple($_SESSION['email']));
			}
			elseif ($_POST['rewrite_subject']==1)
			{
				$sql=sprintf("INSERT INTO spamassassin SET value='%s', username='%s', preference='rewrite_header subject'",
				$db->escapeSimple($_POST['rewrite_subject_header']),
				$db->escapeSimple($_SESSION['email']));
			
			}
			
		}
		if (!empty($sql))
		{
			$result=&$db->query($sql);
		}
	}
	if (isset($_POST['threshold']) && !empty($_POST['threshold']))
	{
		$sql=sprintf("SELECT value FROM spamassassin WHERE username='%s' AND preference='required_score'",
			$db->escapeSimple($_SESSION['email']));
		$result=&$db->query($sql);
		unset($sql);
		if (ereg("^[0-9]{1,2}\.[0-9]$",$_POST['threshold'])==0)
		{
			$smarty->assign('if_wrong_threshold', '1');
		}
		elseif($result->numRows()==1)
		{
			$sql=sprintf("UPDATE spamassassin SET value='%s' WHERE username='%s' AND preference='required_score'",
				$db->escapeSimple($_POST['threshold']),
				$db->escapeSimple($_SESSION['email']));
		}
		elseif($result->numRows()==0)
		{
			$sql=sprintf("INSERT INTO spamassassin SET value='%s', username='%s', preference='required_score'",
				$db->escapeSimple($_POST['threshold']),
				$db->escapeSimple($_SESSION['email']));
		}
		if (!empty($sql))
		{
			$result=&$db->query($sql);
		}
	
	}
	//save move_spam 
	if ($_POST['move_spam']==0) {
		$sql=sprintf("UPDATE users SET move_spam=NULL WHERE id='%d'",
			$db->escapeSimple($_SESSION['uid']));
		$result=&$db->query($sql);
		//reset filter
		$sql=sprintf("UPDATE mailfilter SET active='0' WHERE email='%d' AND type='move_spam'",
			$db->escapeSimple($_SESSION['uid']));
		$result=&$db->query($sql);
	}
	elseif ($_POST['move_spam']==1) {
		//reset filter
		$sql=sprintf("UPDATE mailfilter SET active='0' WHERE email='%d' AND type='move_spam'",
			$db->escapeSimple($_SESSION['uid']));
		$result=&$db->query($sql);
		
		$sql=sprintf("UPDATE users SET move_spam='%s' WHERE id='%d'",
			$db->escapeSimple($_POST['spam_folder']),
			$db->escapeSimple($_SESSION['uid']));
		$result=&$db->query($sql);
		//INSERT INTO
		$sql=sprintf("INSERT INTO mailfilter SET email='%d', active='1',prio='20', type='move_spam',filter='%s'",
			$db->escapeSimple($_SESSION['uid']),
			$db->escapeSimple($_POST['spam_folder']));
		$result=&$db->query($sql);
		
	}
	// activate System-Script
	run_systemscripts();
	
}
// SAVE new whitelist
if (isset($_POST['white_add']) && isset($_POST['white_add_email']) && !empty($_POST['white_add_email']))
{
	 if (ereg("^(.*)@(.*)$",$_POST['white_add_email'])==1)
	 {
	 	$sql=sprintf("SELECT value FROM spamassassin WHERE username='%s' AND preference='whitelist_from'",
			$db->escapeSimple($_SESSION['email']));
		$result=&$db->query($sql);
		unset($sql);
		if ($result->numRows()==0)
		{
			$sql=sprintf("INSERT INTO spamassassin SET username='%s', preference='whitelist_from', value='%s'",
				$db->escapeSimple($_SESSION['email']),
				$db->escapeSimple($_POST['white_add_email']));
		}
		else
		{
			$data=$result->fetchrow(DB_FETCHMODE_ASSOC);
			if (empty($data['value']))
			{
				$data['value']=$_POST['white_add_email'];
			}
			else
			{
				$data['value'].=" ".$_POST['white_add_email'];
			}
			$sql=sprintf("UPDATE spamassassin SET value='%s' WHERE username='%s' AND preference='whitelist_from'",
				$db->escapeSimple($data['value']),
				$db->escapeSimple($_SESSION['email']));
		}
		if (!empty($sql))
		{
			$result=&$db->query($sql);
		}
	 }

}





$sql=sprintf("SELECT spamassassin FROM users WHERE id='%d'",
	$db->escapeSimple($_SESSION['uid']));
$result=&$db->query($sql);
$u_data=$result->fetchrow(DB_FETCHMODE_ASSOC);
$smarty->assign('active', $u_data['spamassassin']);
$smarty->assign('email', $_SESSION['email']);

// Database output rewrite_header subject 
$sql=sprintf("SELECT value FROM spamassassin WHERE username='%s' AND preference='rewrite_header subject'",
	$db->escapeSimple($_SESSION['email']));
$result=&$db->query($sql);
$rewrite_subject=$result->fetchrow(DB_FETCHMODE_ASSOC);
if ($result->numRows()==0 || empty($rewrite_subject['value']))
{
	$smarty->assign('rewrite_subject', '0');
	$smarty->assign('rewrite_subject_header', "*** SPAM ***");
}
else
{
	$smarty->assign('rewrite_subject', '1');
	$smarty->assign('rewrite_subject_header', $rewrite_subject['value']);
}

//fill available spam folders:
$mbox = @imap_open("{".$config['imap_server'].":143/imap/notls}", $_SESSION['email'],decrypt_passwd($_SESSION['cpasswd']));
if (! $mbox) {
 //       exit ("Can't connect: " . imap_last_error() ."\n");
}
else
{
	$list = imap_getmailboxes($mbox, "{ffo}", "*");
	if (is_array($list)) {
		$imap_foldery= array();
		sort($list);
		foreach ($list as $key => $val) {
			$trenner= $val->delimiter;
			$name = ereg_replace("{ffo}", "", $val->name);
			$name = ereg_replace("INBOX$trenner", "", $name);
			$name_display=mb_convert_encoding($name, "ISO-8859-15", "UTF7-IMAP");
			$name_display=str_replace($trenner, "\\ ", $name_display);
			if (!preg_match('/^sent$/i', $name ) && !preg_match('/^INBOX$/i', $name) && !preg_match("/^Trash$trenner/i", $name) && !preg_match('/^drafts$/i',$name)) {
				
				array_push($imap_foldery, array('name_display' => $name_display, 'name' => $name));

			}
		}
	} 
	else {
	}
	$i = count($imap_foldery);
	if ($i > 0) {
		$smarty->assign('imap_folder_exits', 1);
		$smarty->assign('available_folders',$imap_foldery);
	}
	else
	{
		$smarty->assign('imap_folder_exits', 0);
	}
	imap_close($mbox);
}
//move spam feature:
$sql=sprintf("SELECT move_spam FROM users WHERE id='%s'",
	$db->escapeSimple($_SESSION['uid']));
$result=&$db->query($sql);
$move_spam=$result->fetchrow(DB_FETCHMODE_ASSOC);
if ($move_spam['move_spam']==NULL)
{
	$smarty->assign('move_spam','0');
}
else
{
	$smarty->assign('move_spam', '1');
	$smarty->assign('move_spam_folder',$move_spam['move_spam'] );
}




// Database output required_score
$sql=sprintf("SELECT value FROM spamassassin WHERE username='%s' AND preference='required_score'",
	$db->escapeSimple($_SESSION['email']));
$result=&$db->query($sql);
$required_score=$result->fetchrow(DB_FETCHMODE_ASSOC);
if ($result->numRows()==0)
{
	$smarty->assign('threshold', "5.0");
}
else
{
	$smarty->assign('threshold', $required_score['value']);
}


//Database output whitelist_from
$sql=sprintf("SELECT value FROM spamassassin WHERE username='%s' AND preference='whitelist_from'",
	$db->escapeSimple($_SESSION['email']));
$result=&$db->query($sql);
if ($result->numRows()>0)
{
	$data=$result->fetchrow(DB_FETCHMODE_ASSOC);
	$value_array=array();
	$whitelist=array();
	$value_array=split(' ', $data['value']);
	foreach ($value_array as $key)
	{
		array_push($whitelist, array(
			'email' => $key));
	}
	$smarty->assign('whitelist', $whitelist);
}



if ($_SESSION['spamassassin'] == 1) {
	$smarty->assign('template','user_spam.tpl');
	$smarty->display('structure.tpl');
}
else
{
	header("Location: index.php");
}	
$db->disconnect();
?>