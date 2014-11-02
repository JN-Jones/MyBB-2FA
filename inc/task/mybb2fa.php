<?php
function task_cplus($task)
{
	global $db, $lang;
	
	$lang->load("cplus");

	$db->delete_query("mybb2fa_log", "time < ".(TIME_NOW-1800)); // Everything older than 30 min is deleted
	$num = $db->affected_rows();

	add_task_log($task, $lang->sprintf("{1} code(s) deleted", $num));
}
?>