<?php
/*
 * Copyright 2005-2013 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once('../libs/common.php');
require_once('../libs/chat.php');
require_once('../libs/userinfo.php');
require_once('../libs/operator.php');
require_once('../libs/groups.php');

$operator = get_logged_in();
if (!$operator) {
	start_xml_output();
	echo "<error><descr>" . myiconv($webim_encoding, "utf-8", escape_with_cdata(getstring("agent.not_logged_in"))) . "</descr></error>";
	exit;
}

$threadstate_to_string = array(
	$state_queue => "wait",
	$state_waiting => "prio",
	$state_chatting => "chat",
	$state_closed => "closed",
	$state_loading => "wait",
	$state_left => "closed"
);

$threadstate_key = array(
	$state_queue => "chat.thread.state_wait",
	$state_waiting => "chat.thread.state_wait_for_another_agent",
	$state_chatting => "chat.thread.state_chatting_with_agent",
	$state_closed => "chat.thread.state_closed",
	$state_loading => "chat.thread.state_loading"
);

function thread_to_xml($thread, $link)
{
	global $state_chatting, $threadstate_to_string, $threadstate_key,
$webim_encoding, $operator, $settings,
$can_viewthreads, $can_takeover, $mysqlprefix;
	$state = $threadstate_to_string[$thread['istate']];
	$result = "<thread id=\"" . $thread['threadid'] . "\" stateid=\"$state\"";
	if ($state == "closed")
		return $result . "/>";

	$state = getstring($threadstate_key[$thread['istate']]);
	$nextagent = $thread['nextagent'] != 0 ? operator_by_id_($thread['nextagent'], $link) : null;
	$threadoperator = $nextagent ? get_operator_name($nextagent)
			: ($thread['agentName'] ? $thread['agentName'] : "-");

	if ($threadoperator == "-" && $thread['groupname']) {
		$threadoperator = "- " . $thread['groupname'] . " -";
	}

	if (!($thread['istate'] == $state_chatting && $thread['agentId'] != $operator['operatorid'] && !is_capable($can_takeover, $operator))) {
		$result .= " canopen=\"true\"";
	}
	if ($thread['agentId'] != $operator['operatorid'] && $thread['nextagent'] != $operator['operatorid']
		&& is_capable($can_viewthreads, $operator)) {
		$result .= " canview=\"true\"";
	}
	if ($settings['enableban'] == "1") {
		$result .= " canban=\"true\"";
	}

	$banForThread = $settings['enableban'] == "1" ? ban_for_addr_($thread['remote'], $link) : false;
	if ($banForThread) {
		$result .= " ban=\"blocked\" banid=\"" . $banForThread['banid'] . "\"";
	}

	$result .= " state=\"$state\" typing=\"" . $thread['userTyping'] . "\">";
	$result .= "<name>";
	if ($banForThread) {
		$result .= htmlspecialchars(getstring('chat.client.spam.prefix'));
	}
	$result .= htmlspecialchars(htmlspecialchars(get_user_name($thread['userName'], $thread['remote'], $thread['userid']))) . "</name>";
	$result .= "<time>" . $thread['unix_timestamp(dtmcreated)'] . "000</time>";
	$result .= "<modified>" . $thread['unix_timestamp(dtmmodified)'] . "000</modified>";

	if ($banForThread) {
		$result .= "<reason>" . $banForThread['comment'] . "</reason>";
	}

	$userAgent = get_useragent_version($thread['userAgent']);
	$result .= "<useragent>" . $userAgent . "</useragent>";
	if ($thread["shownmessageid"] != 0) {
		$query = "select tmessage from ${mysqlprefix}chatmessage where messageid = " . $thread["shownmessageid"];
		$line = select_one_row($query, $link);
		if ($line) {
			$message = preg_replace("/[\r\n\t]+/", " ", $line["tmessage"]);
			$result .= "<message>" . htmlspecialchars(htmlspecialchars($message)) . "</message>";
		}
	}
	$result .= "</thread>";
	return $result;
}

function print_pending_threads($groupids, $since)
{
	global $webim_encoding, $settings, $state_closed, $state_left, $mysqlprefix;
	$link = connect();

	$revision = $since;
	$output = array();
	$query = "select threadid, userName, agentName, unix_timestamp(dtmcreated), userTyping, " .
			 "unix_timestamp(dtmmodified), lrevision, istate, remote, nextagent, agentId, userid, shownmessageid, userAgent, (select vclocalname from ${mysqlprefix}chatgroup where ${mysqlprefix}chatgroup.groupid = ${mysqlprefix}chatthread.groupid) as groupname " .
			 "from ${mysqlprefix}chatthread where lrevision > $since " .
			 ($since <= 0
					 ? "AND istate <> $state_closed AND istate <> $state_left "
					 : "") .
			 ($settings['enablegroups'] == '1'
					 ? "AND (groupid is NULL" . ($groupids
							 ? " OR groupid IN ($groupids)"
							 : "") .
					   ") "
					 : "") .
			 "ORDER BY threadid";
	$rows = select_multi_assoc($query, $link);
	foreach ($rows as $row) {
		$thread = thread_to_xml($row, $link);
		$output[] = $thread;
		if ($row['lrevision'] > $revision)
			$revision = $row['lrevision'];
	}

	mysql_close($link);

	echo "<threads revision=\"$revision\" time=\"" . time() . "000\">";
	foreach ($output as $thr) {
		print myiconv($webim_encoding, "utf-8", $thr);
	}
	echo "</threads>";
}

function print_operators()
{
	global $webim_encoding;
	echo "<operators>";
	$operators = operator_get_all();

	foreach ($operators as $operator) {
		if (!operator_is_online($operator))
			continue;

		$name = myiconv($webim_encoding, "utf-8", htmlspecialchars(htmlspecialchars($operator['vclocalename'])));
		$away = operator_is_away($operator) ? " away=\"1\"" : "";

		echo "<operator name=\"$name\"$away/>";
	}
	echo "</operators>";
}

$since = verifyparam("since", "/^\d{1,9}$/", 0);
$status = verifyparam("status", "/^\d{1,2}$/", 0);
$showonline = verifyparam("showonline", "/^1$/", 0);

$link = connect();
loadsettings_($link);
if (!isset($_SESSION["${mysqlprefix}operatorgroups"])) {
	$_SESSION["${mysqlprefix}operatorgroups"] = get_operator_groupslist($operator['operatorid'], $link);
}
close_old_threads($link);
mysql_close($link);
$groupids = $_SESSION["${mysqlprefix}operatorgroups"];

start_xml_output();
echo '<update>';
if ($showonline) {
	print_operators();
}
print_pending_threads($groupids, $since);
echo '</update>';
notify_operator_alive($operator['operatorid'], $status);
exit;

?>