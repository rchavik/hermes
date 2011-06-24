<?php
declare(ticks=1);

pcntl_signal(SIGTERM,  'disconnect');
pcntl_signal(SIGHUP,  'disconnect');
pcntl_signal(SIGINT,  'disconnect');

require 'config.php';
require 'jymengine.class.php';

$engine = new JYMEngine(CONSUMER_KEY, SECRET_KEY, USERNAME, PASSWORD);
$engine->debug = true;

if ($engine->debug) echo '> Fetching request token'. PHP_EOL;
if (!$engine->fetch_request_token()) die('Fetching request token failed');

if ($engine->debug) echo '> Fetching access token'. PHP_EOL;
if (!$engine->fetch_access_token()) die('Fetching access token failed');

if ($engine->debug) echo '> Signon as: '. USERNAME. PHP_EOL;
if (!$engine->signon()) die('Signon failed');

function debug($o) {
	if (is_string($o)) {
		echo "$o\n";
	} else {
		print_r($o);
	}
}

function disconnect($sig) {
	global $continue;
	$continue = false;
}

$continue = true;
$seq = -1;
while ( $continue ) {

	$resp = $engine->fetch_long_notification($seq+1);
	if (isset($resp)) {	
		if ($resp === false) {		
			if ($engine->get_error() != -10) {
				if ($engine->debug) echo '> Fetching access token'. PHP_EOL;
				if (!$engine->fetch_access_token()) die('Fetching access token failed');				
				
				if ($engine->debug) echo '> Signon as: '. USERNAME. PHP_EOL;
				if (!$engine->signon(date('H:i:s'))) die('Signon failed');
				
				$seq = -1;
			}
			continue;							
		}

		$engine->change_presence(' ', 2);

		foreach ($resp as $row) {
			foreach ($row as $key=>$val) {
				if ($val['sequence'] > $seq) $seq = intval($val['sequence']);
				
				/*
				 * do actions
				 */
				if ($key == 'buddyInfo') {
					//contact list
					if (!isset($val['contact'])) continue;
					
					if ($engine->debug) echo PHP_EOL. 'Contact list: '. PHP_EOL;
					foreach ($val['contact'] as $item) {
						if ($engine->debug) echo $item['sender']. PHP_EOL;
					}
					if ($engine->debug) echo '----------'. PHP_EOL;
				}
				
				else if ($key == 'message') {
					//incoming message
					if ($engine->debug) echo '+ Incoming message from: "'. $val['sender']. '" on "'. date('H:i:s', $val['timeStamp']). '"'. PHP_EOL;
					if ($engine->debug) echo '   '. $val['msg']. PHP_EOL;
					if ($engine->debug) echo '----------'. PHP_EOL;
					
					//reply
					$words = explode(' ', trim(strtolower($val['msg'])));
					if ($words[0] == 'help') {
						$out = 'This is Xintesa notification daemon'. PHP_EOL;
						$out .= '  To get recent news from yahoo type: news'. PHP_EOL;
						$out .= '  To get recent entertainment news from yahoo type: omg'. PHP_EOL;
					} else if ($words[0] == 'news') {
						if ($engine->debug) echo '> Retrieving news rss'. PHP_EOL;
						$rss = file_get_contents('http://rss.news.yahoo.com/rss/topstories');
												
						if (preg_match_all('|<title>(.*?)</title>|is', $rss, $m)) {
							$out = 'Recent Yahoo News:'. PHP_EOL;
							for ($i=2; $i<7; $i++)
							{
								$out .= str_replace("\n", ' ', $m[1][$i]). PHP_EOL;
							}
						}
					} else if ($words[0] == 'omg') {
						if ($engine->debug) echo '> Retrieving OMG news rss'. PHP_EOL;
						$rss = file_get_contents('http://rss.omg.yahoo.com/latest/news/');
												
						if (preg_match_all('|<title>(.*?)</title>|is', $rss, $m))
						{
							$out = 'Recent OMG News:'. PHP_EOL;
							for ($i=2; $i<7; $i++)
							{
								$out .= str_replace(array('<![CDATA[', ']]>'), array('', ''), $m[1][$i]). PHP_EOL;
							}
						}
					} else {
						$out = 'Please type: help';
					}

					//send message
					if ($engine->debug) echo '> Sending reply message '. PHP_EOL;
					if ($engine->debug) echo '    '. $out. PHP_EOL;	
					if ($engine->debug) echo '----------'. PHP_EOL;
					$engine->send_message($val['sender'], json_encode($out));

				} else if ($key == 'buddyAuthorize') {
					//incoming contact request

					if ($engine->debug) echo PHP_EOL. 'Accept buddy request from: '. $val['sender']. PHP_EOL;
					if ($engine->debug) echo '----------'. PHP_EOL;
					if (!$engine->response_contact($val['sender'], true, 'Welcome to my list')) {
						$engine->delete_contact($val['sender']);
						$engine->response_contact($val['sender'], true, 'Welcome to my list');
					}
				}
			}
		}
	}
}

$engine->signoff();
echo "\ndaemon terminated.\n";
