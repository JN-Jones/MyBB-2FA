<?php
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook("usercp_start", "mybb2fa_usercp");
$plugins->add_hook("datahandler_login_complete_end", "mybb2fa_do_login");
$plugins->add_hook("global_start", "mybb2fa_check_block");
$plugins->add_hook("misc_start", "mybb2fa_check");

$plugins->add_hook("admin_load", "mybb2fa_admin_do_login");

function mybb2fa_info()
{
	return array(
		"name"			=> "MyBB 2FA",
		"description"	=> "Add 2 way authentication to your MyBB",
		"website"		=> "http://jonesboard.de/",
		"author"		=> "Jones",
		"authorsite"	=> "http://jonesboard.de/",
		"version"		=> "1.0",
		"compatibility" => "18*",
		"codename"		=> "mybb2fa"
	);
}

function mybb2fa_install()
{
	global $db;

	$db->add_column("users", "secret", "VARCHAR(16) NOT NULL default ''");
	$db->add_column("adminsessions", "mybb2fa_auth", "TINYINT(1) NOT NULL default '0'");
	$db->add_column("sessions", "mybb2fa_block", "TINYINT(1) NOT NULL default '0'");

	$templateset = array(
		"prefix"	=> "mybb2fa",
		"title"		=> "MyBB 2FA"
	);
	$db->insert_query("templategroups", $templateset);
	
	$template = '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->mybb2fa}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
	<td valign="top">
		<form action="misc.php" method="post">
		<input type="hidden" name="action" value="mybb2fa" />
		<input type="hidden" name="uid" value="{$loginhandler->login_data[\'uid\']}" />
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead"><strong>{$lang->mybb2fa}</strong></td>
			</tr>
			<tr>
				<td class="trow1"{$lang->mybb2fa_code}: <input type="text" class="textbox" name="code" /></td>
			</tr>
			<tr>
				<td class="trow2"><input type="submit" class="button" value="{$lang->mybb2fa_check}" /></td>
			</tr>
		</table>
		</form>
	</td>
</tr>
</table>
{$footer}
</body>
</html>';
	$templatearray = array(
		"title" => "mybb2fa_form",
		"template" => $db->escape_string($template),
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	$template = '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->mybb2fa}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
	{$usercpnav}
	<td valign="top">
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead"><strong>{$lang->mybb2fa}</strong></td>
			</tr>
			<tr>
				<td class="trow">{$lang->mybb2fa_activated_desc} <a href="usercp.php?action=mybb2fa&do=deactivate">{$lang->deactivate}</a></td>
			</tr>
          <tr>
            <td class="trow2"><img src="{$qr}" /></td>
          </tr>
	</td>
</tr>
</table>
</form>
{$footer}
</body>
</html>';
	$templatearray = array(
		"title" => "mybb2fa_usercp_activated",
		"template" => $db->escape_string($template),
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	$template = '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->mybb2fa}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
	{$usercpnav}
	<td valign="top">
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead" colspan="2"><strong>{$lang->mybb2fa}</strong></td>
			</tr>
			<tr>
				<td class="trow" colspan="2">{$lang->mybb2fa_deactivated_desc} <a href="usercp.php?action=mybb2fa&do=activate">{$lang->mybb2fa_activate}</a></td>
			</tr>
	</td>
</tr>
</table>
</form>
{$footer}
</body>
</html>';
	$templatearray = array(
		"title" => "mybb2fa_usercp_deactivated",
		"template" => $db->escape_string($template),
		"sid" => "-2",
	);
	$db->insert_query("templates", $templatearray);

	require_once MYBB_ROOT."inc/functions_task.php";
	$new_task = array(
		"title"			=> "MyBB 2FA",
		"description"	=> "Deletes old codes",
		"file"			=> "mybb2fa",
		"minute"		=> "0,30",
		"hour"			=> "*",
		"day"			=> "*",
		"weekday"		=> "*",
		"month"			=> "*",
		"enabled"		=> "0",
		"logging"		=> "1"
	);
	$new_task['nextrun'] = fetch_next_run($new_task);
	$db->insert_query("tasks", $new_task);

	$col = $db->build_create_table_collation();
	$db->query("CREATE TABLE `".TABLE_PREFIX."mybb2fa_log` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`secret` varchar(16) NOT NULL,
				`code` varchar(6) NOT NULL,
				`time` bigint(30) NOT NULL,
	PRIMARY KEY (`id`) ) ENGINE=MyISAM {$col}");
}

function mybb2fa_is_installed()
{
	global $db;
	return $db->table_exists("mybb2fa_log");
}

function mybb2fa_uninstall()
{
	global $db;

	$db->drop_column("users", "secret");
	$db->drop_column("adminsessions", "mybb2fa_auth");
	$db->drop_column("sessions", "mybb2fa_block");

	$db->delete_query("templategroups", "prefix='mybb2fa'");
	$db->delete_query("templates", "title='mybb2fa_form'");
	$db->delete_query("templates", "title='mybb2fa_usercp_activated'");
	$db->delete_query("templates", "title='mybb2fa_usercp_deactivated'");

	$db->drop_table("mybb2fa_log");

    $db->delete_query("tasks", "file='mybb2fa'");
}


function mybb2fa_activate()
{
	global $db;

	require_once MYBB_ROOT."inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('{$lang->ucp_nav_change_pass}</a></div>')."#i", "{\$lang->ucp_nav_change_pass}</a></div>
		<div><a href=\"usercp.php?action=mybb2fa\" class=\"usercp_nav_item usercp_nav_password\">MyBB 2FA</a></div>");

	$db->update_query("tasks", array("enabled" => "1"), "file='mybb2fa'");

	// We won't logout admin which have 2FA activated
	$db->update_query("adminsessions", array("mybb2fa_auth" => 1));
}

function mybb2fa_deactivate()
{
	global $db;

	require_once MYBB_ROOT."inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_profile", "#".preg_quote('<div><a href="usercp.php?action=mybb2fa" class="usercp_nav_item usercp_nav_password">MyBB 2FA</a></div>')."#i", '', 0);

	$db->update_query("tasks", array("enabled" => "0"), "file='mybb2fa'");
}

function mybb2fa_usercp()
{
	global $db, $mybb, $headerinclude, $header, $usercpnav, $theme, $footer, $templates, $lang;

	if($mybb->input['action'] != "mybb2fa")
	    return;

	$lang->load("mybb2fa");

	require_once MYBB_ROOT."inc/plugins/mybb2fa/GoogleAuthenticator.php";
	require_once MYBB_ROOT."inc/plugins/mybb2fa/AuthWrapper.php";
	$auth = new Authenticator;

	if(isset($mybb->input['do']))
	{
		if($mybb->input['do'] == "deactivate")
		{
			// Deactivating 2FA
			$mybb->user['secret'] = "";
			$db->update_query("users", array("secret" => ""), "uid={$mybb->user['uid']}");
		}
		else
		{
			// Activating 2FA
			$secret = $auth->createSecret();
			$mybb->user['secret'] = $secret;
			$db->update_query("users", array("secret" => $secret), "uid={$mybb->user['uid']}");
			// Redirect to avoid multiple different secrets
			redirect("usercp.php?action=mybb2fa", $lang->mybb2fa_activated);
		}
	}

	if(empty($mybb->user['secret']))
	{
		// 2FA is deactivated
		$mybb2fa = eval($templates->render("mybb2fa_usercp_deactivated"));
	}
	else
	{
		// 2FA is activated
		$qr = $auth->getQRCodeGoogleUrl($mybb->user['username']."@".str_replace(" ", "", $mybb->settings['bbname']), $mybb->user['secret']);
		$mybb2fa = eval($templates->render("mybb2fa_usercp_activated"));
	}
	output_page($mybb2fa);
}

function mybb2fa_do_login($loginhandler)
{
	global $mybb, $db, $headerinclude, $header, $theme, $footer, $templates, $lang;

	// Ok, everything is ok so far; let's figure out whether we need to show our form
	$query = $db->simple_select("users", "secret", "uid={$loginhandler->login_data['uid']}");
	$secret = $db->fetch_field($query, "secret");
	if(empty($secret))
	    // User doesn't use the plugin, nothing to do
		return;

	$lang->load("mybb2fa");

	// Though the user is logged in we want to block him till he really logs in
	$db->update_query("sessions", array("mybb2fa_block" => 1), "sid='".$db->escape_string($mybb->cookies['sid'])."'");

	// Show our nice form
	$mybb2fa = eval($templates->render("mybb2fa_form"));
	output_page($mybb2fa);
	exit;
}

function mybb2fa_check_block()
{
	global $session, $mybb, $db;

	$query = $db->simple_select("sessions", "mybb2fa_block", "sid='".$db->escape_string($mybb->cookies['sid'])."'");
	$block = $db->fetch_field($query, "mybb2fa_block");

	if($block == 1)
	{
	    $session->load_guest();
	}
}

function mybb2fa_check()
{
	global $mybb, $db, $lang;

	if($mybb->input['action'] != "mybb2fa")
	    return;

	// Nope, we don't want you here
	if(!isset($mybb->input['uid']) || $mybb->user['uid'] > 0)
	    return;

	$uid = (int)$mybb->input['uid'];
	$query = $db->simple_select("users", "secret", "uid={$uid}");
	$secret = $db->fetch_field($query, "secret");
	if(empty($secret))
		return;

	$lang->load("mybb2fa");

	require_once MYBB_ROOT."inc/plugins/mybb2fa/GoogleAuthenticator.php";
	require_once MYBB_ROOT."inc/plugins/mybb2fa/AuthWrapper.php";
	$auth = new Authenticator;

	$test = $auth->verifyCode($secret, $mybb->input['code']);

	// No need to block the user anymore, either he failed (logout) or passed (login)
	$db->update_query("sessions", array("mybb2fa_block" => 0), "sid='".$db->escape_string($mybb->cookies['sid'])."'");

	if($test === true)
	{
		// Correct code, unblock the user
		redirect("index.php", $lang->mybb2fa_loggedin);
	}
	else
	{
		// Sorry little guy, you failed; unset everything
		my_unsetcookie("mybbuser");
		my_unsetcookie("sid");
		redirect("index.php", $lang->mybb2fa_failed);
	}
}

function mybb2fa_admin_do_login()
{
	global $mybb, $db, $page, $cp_style, $lang, $admin_session;

	if(empty($mybb->user['secret']))
	    // User doesn't use the plugin, nothing to do
		return;

	// We're logged in here, check whether our cookie is set
	if(isset($admin_session['mybb2fa_auth']) && $admin_session['mybb2fa_auth'] == 1)
	    return;

	$lang->load("mybb2fa");

	if($mybb->request_method == "post")
	{
		require_once MYBB_ROOT."inc/plugins/mybb2fa/GoogleAuthenticator.php";
		require_once MYBB_ROOT."inc/plugins/mybb2fa/AuthWrapper.php";
		$auth = new Authenticator;
	
		$test = $auth->verifyCode($mybb->user['secret'], $mybb->input['code']);
	
		if($test === true)
		{
			// Correct code, set our the session value and leave
			$db->update_query("adminsessions", array("mybb2fa_auth" => 1), "sid='".$db->escape_string($mybb->cookies['adminsid'])."'");
			$admin_session['mybb2fa_auth'] = 1;
			return;
		}
		else
		{
			// Sorry little guy, you failed; logging you out
			$db->delete_query("adminsessions", "sid='".$db->escape_string($mybb->cookies['adminsid'])."'");
			my_unsetcookie('adminsid');
			$page->show_login($lang->mybb2fa_failed, "error");
		}
	}

	// Show the nice form
	$mybb2fa_page = <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head profile="http://gmpg.org/xfn/1">
<title>MyBB 2FA</title>
<meta name="author" content="MyBB Group" />
<meta name="copyright" content="Copyright {$copy_year} MyBB Group." />
<link rel="stylesheet" href="./styles/{$cp_style}/login.css" type="text/css" />
<script type="text/javascript" src="../jscripts/jquery.js"></script>
<script type="text/javascript" src="../jscripts/general.js"></script>
<script type="text/javascript" src="./jscripts/admincp.js"></script>
<script type="text/javascript">
//<![CDATA[
	loading_text = '{$lang->loading_text}';
//]]>
</script>
</head>
<body>
<div id="container">
	<div id="header">
		<div id="logo">
			<h1><a href="../" title="{$lang->return_to_forum}"><span class="invisible">{$lang->mybb_acp}</span></a></h1>

		</div>
	</div>
	<div id="content">
		<h2>{$lang->mybb2fa}</h2>
EOF;

		// Make query string nice and pretty so that user can go to his/her preferred destination
		$query_string = '';
		if($_SERVER['QUERY_STRING'])
		{
			$query_string = '?'.preg_replace('#adminsid=(.{32})#i', '', $_SERVER['QUERY_STRING']);
			$query_string = preg_replace('#my_post_key=(.{32})#i', '', $query_string);
			$query_string = str_replace('action=logout', '', $query_string);
			$query_string = preg_replace('#&+#', '&', $query_string);
			$query_string = str_replace('?&', '?', $query_string);
			$query_string = htmlspecialchars_uni($query_string);
		}

		$mybb2fa_page .= <<<EOF
		<p>{$lang->mybb2fa_code}</p>
		<form method="post" action="index.php{$query_string}">
		<div class="form_container">

			<div class="label"><label for="code">{$lang->mybb2fa_label}</label></div>

			<div class="field"><input type="text" name="code" id="code" class="text_input initial_focus" /></div>

		</div>
		<p class="submit">
			<input type="submit" value="{$lang->login}" />
			<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
		</p>
		</form>
	</div>
</div>
</body>
</html>
EOF;
		echo $mybb2fa_page;
		exit;
}