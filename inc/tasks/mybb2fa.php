<?php
function task_mybb2fa($task)
{
	global $db, $lang;

	$lang->load("mybb2fa");

	$db->delete_query("mybb2fa_log", "time < ".(TIME_NOW-1800)); // Everything older than 30 min is deleted
	$log = $db->affected_rows();


	$db->delete_query("sessions", "time < ".(TIME_NOW-1800)." AND mybb2fa_block=1"); // Everything older than 30 min is deleted
	$sessions = $db->affected_rows();

	$db->delete_query("adminsessions", "dateline < ".(TIME_NOW-1800)." AND mybb2fa_auth=0"); // Everything older than 30 min is deleted
	$asessions = $db->affected_rows();

	add_task_log($task, $lang->sprintf($lang->mybb2fa_task, $log, $sessions, $asessions));
}