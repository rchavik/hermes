<?php
declare(ticks=1);

require 'config.php';
require 'jymengine.class.php';

class YahooMessengerDaemon {

	var $engine = false;

	var $seq = -1;

	function __construct($consumer, $secret, $username, $password, $debug = false) {

		pcntl_signal(SIGTERM,  array(&$this, 'disconnect'));
		pcntl_signal(SIGINT,  array(&$this, 'disconnect'));
		$this->engine = new JYMEngine(CONSUMER_KEY, SECRET_KEY, USERNAME, PASSWORD);
		$this->engine->debug = $debug;
	}

	function log($message) {
		echo $message . PHP_EOL;
	}

	function connect() {
		$engine =& $this->engine;
		if ($engine->debug) echo '> Fetching request token'. PHP_EOL;
		if (!$engine->fetch_request_token()) die('Fetching request token failed');

		if ($engine->debug) echo '> Fetching access token'. PHP_EOL;
		if (!$engine->fetch_access_token()) die('Fetching access token failed');

		if ($engine->debug) echo '> Signon as: '. USERNAME. PHP_EOL;
		if (!$engine->signon()) die('Signon failed');

	}

	function disconnect($sig) {
		$this->continue = false;
		exit;
	}

	function handleBuddyAuthorize($val) {
		//incoming contact request

		$engine =& $this->engine;
		if ($engine->debug) echo PHP_EOL. 'Accept buddy request from: '. $val['sender']. PHP_EOL;
		if ($engine->debug) echo '----------'. PHP_EOL;
		if (!$engine->response_contact($val['sender'], true, 'Welcome to my list')) {
			$engine->delete_contact($val['sender']);
			$engine->response_contact($val['sender'], true, 'Welcome to my list');
		}
	}

	function handleBuddyInfo() {
		//contact list
		$engine =& $this->engine;
		if (!isset($val['contact'])) return;;
		if ($engine->debug) echo PHP_EOL. 'Contact list: '. PHP_EOL;

		foreach ($val['contact'] as $item) {
			if ($engine->debug) echo $item['sender']. PHP_EOL;
		}
		if ($engine->debug) echo '----------'. PHP_EOL;
	}

	function handleMessage($val) {
		//incoming message
		$engine =& $this->engine;
		if ($engine->debug) echo '+ Incoming message from: "'. $val['sender']. '" on "'. date('H:i:s', $val['timeStamp']). '"'. PHP_EOL;
		if ($engine->debug) echo '   '. $val['msg']. PHP_EOL;
		if ($engine->debug) echo '----------'. PHP_EOL;

		$words = explode(' ', trim(strtolower($val['msg'])));
		$command = strtolower($words[0]);
		switch ($command) {
		case 'help':
			$out  = 'This is Xintesa notification daemon'. PHP_EOL;
			$out .= '  To get recent news from yahoo type: news'. PHP_EOL;
			$out .= '  To get recent entertainment news from yahoo type: omg'. PHP_EOL;
			break;
		default:
			$out = false;
		}

		//send message
		if ($engine->debug) echo '> Sending reply message '. PHP_EOL;
		if ($engine->debug) echo '    '. $out. PHP_EOL;
		if ($engine->debug) echo '----------'. PHP_EOL;
		$engine->send_message($val['sender'], json_encode($out));
		return $out;
	}

	function get($seq) {
		static $wait = 10;
		$resp = $engine->fetch_long_notification($seq);
		if (!isset($resp)) {
			error_log('empty response');
			sleep($wait);
			$wait = $wait > 600 ? $wait * 2 : 10;
			return false;
		}

		if ($resp === false) {
			if ($engine->get_error() != -10) {
				if ($engine->debug) echo '> Fetching access token'. PHP_EOL;
				if (!$engine->fetch_access_token()) die('Fetching access token failed');				
				if ($engine->debug) echo '> Signon as: '. USERNAME. PHP_EOL;
				if (!$engine->signon(date('H:i:s'))) die('Signon failed');
				$this->seq = -1;
			}
			return false;
		}
	}

	function run() {
		$continue = true;
		$statusIdle = false;
		while ( $continue && $resp = $this->get($this->seq+1) ) {
			if (!$statusIdle) { $engine->change_presence(' ', 2); $statusIdle = true; }

			foreach ($resp as $row) {
				foreach ($row as $key=>$val) {
					if ($val['sequence'] > $this->seq) $this->seq = intval($val['sequence']);

					$method = 'handle' . ucfirst($key);
					switch ($key) {
					case 'buddyInfo':
					case 'message':
						$this->{$method}($val);
						break;
					default:
						$this->log('unsupported key: ' . $key);
						break;
					}
				}
			}
		}
		$engine->signoff();
		$this->log("\ndaemon terminated.\n");
	}

}
