<?php

// This is a little wrapper for the GoogleAuthenticator class which checks codes also against the database to avoid multiple use of the same code

class Authenticator extends PHPGangsta_GoogleAuthenticator
{
    public function verifyCode($secret, $code, $discrepancy = 1)
	{
		$test = parent::verifyCode($secret, $code, $discrepance);

		if($test === false)
			// Code is wrong, no need to check it against our log
			return false;

		global $db;
		// Check whether the combination of this secret and code has been used previously
		$secret = $db->escape_string($secret); $code = $db->escape_string($code);
		$query = $db->simple_select("mybb2fa_log", "id", "secret='{$secret}' AND code='{$code}'");
		if($db->num_rows($query) > 0)
		    // Old code
		    return false;

		// Code is ok, add it to the log
		$insert = array(
			"secret"	=> $secret,
			"code"		=> $code,
			"time"		=> TIME_NOW
		);
		$db->insert_query("mybb2fa_log", $insert);
		return true;
	}
}