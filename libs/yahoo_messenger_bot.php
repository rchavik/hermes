<?php
declare(ticks=1);

require '../vendors/messenger-sdk-php/jymengine.class.php';

class YahooMessengerBot {

	var $engine = false;

	var $seq = -1;

	var $interval = 5; // minimum is 5 seconds, lower values will risk denial

	function __construct($consumer, $secret, $username, $password, $debug = false) {
		pcntl_signal(SIGTERM,  array(&$this, 'disconnect'));
		pcntl_signal(SIGINT,  array(&$this, 'disconnect'));
		$this->username = $username;
		$this->engine = new JYMEngine($consumer, $secret, $username, $password);
		$this->engine->debug = $debug;
	}

	function log($message) {
		echo $message . PHP_EOL;
	}

	function debug($message) {
		if ($this->engine->debug) {
			$this->log($message);
		}
	}

	function connect() {
		$engine =& $this->engine;
		$this->debug('> Fetching request token');
		if (!$engine->fetch_request_token()) die('Fetching request token failed');

		$this->debug('> Fetching access token');
		if (!$engine->fetch_access_token()) die('Fetching access token failed');

		$this->debug('> Signon as: '. $this->username);
		if (!$engine->signon()) die('Signon failed');

	}

	function disconnect($sig) {
		$this->continue = false;
	}

	function handleBuddyAuthorize($val) {
		//incoming contact request

		$engine =& $this->engine;
		$this->debug('Accept buddy request from: '. $val['sender']);
		$this->debug('----------');
		if (!$engine->response_contact($val['sender'], true, 'Welcome to my list')) {
			$engine->delete_contact($val['sender']);
			$engine->response_contact($val['sender'], true, 'Welcome to my list');
		}
	}

	function handleBuddyInfo() {
		//contact list
		$engine =& $this->engine;
		if (!isset($val['contact'])) return;;
		$this->debug('Contact list: ');

		foreach ($val['contact'] as $item) {
			$this->debug($item['sender']);
		}
		$this->debug('----------');
	}

	function handleMessage($val) {
		//incoming message
		$engine =& $this->engine;
		$this->debug('+ Incoming message from: "'. $val['sender']. '" on "'. date('H:i:s', $val['timeStamp']). '"');
		$this->debug('   '. $val['msg']);
		$this->debug('----------');

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
		$this->debug('> Sending reply message ');
		$this->debug('    '. $out);
		$this->debug('----------');
		$engine->send_message($val['sender'], json_encode($out));
		return $out;
	}

	function get($seq) {
		static $wait = 10;
		$engine =& $this->engine;
		$resp = $engine->fetch_notification($seq);
		if (!isset($resp)) {
			$this->log('empty response');
			sleep($wait);
			$wait = $wait > 600 ? $wait * 2 : 10;
			return false;
		}

		if ($resp === false) {
			if ($engine->get_error() != -10) {
				$this->debug('> Fetching access token');
				if (!$engine->fetch_access_token()) die('Fetching access token failed');				
				$this->debug('> Signon as: '. $this->username);
				if (!$engine->signon(date('H:i:s'))) die('Signon failed');
				$this->seq = -1;
			}
			return false;
		}
		return $resp;
	}

	function run() {
		$this->continue = true;
		$statusIdle = false;
		$engine =& $this->engine;
		while ( $this->continue ) {
			if (($resp = $this->get($this->seq+1)) === false) { continue; }
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
			sleep($this->interval);
		}
		$engine->signoff();
		$this->log("\ndaemon terminated.\n");
	}

}
