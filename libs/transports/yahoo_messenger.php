<?php
declare(ticks=1);

App::import('Vendor', 'JymEngine',
	array('file' => 'messenger-sdk-php' . DS . 'jymengine.class.php')
);

class YahooMessenger extends Object {

	private $engine = false;

	private $seq = -1;

	private $interval = 5; // minimum is 5 seconds, lower values will risk denial

	private $controller = false;

	private $connected = false;

	private $config = false;

	private $processor = false;

	function __construct(&$controller = null) {
		$this->controller = $controller;
		$this->interval = $this->interval >= 5 ? $this->interval : 5;
	}

	function init($config) {
		$config = Set::merge(array(
			'username' => false,
			'password' => false,
			'consumer' => false,
			'secret' => false,
			'debug' => false,
			'help' => false,
			'processor' => false,
			), $config);
		$this->config = $config;
		extract($config);

		pcntl_signal(SIGTERM,  array(&$this, 'disconnect'));
		pcntl_signal(SIGINT,  array(&$this, 'disconnect'));
		$this->username = $username;
		$this->engine = new JYMEngine($consumer, $secret, $username, $password);
		$this->engine->debug = $debug;
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
		$this->connected = true;

	}

	function disconnect($sig) {
		$this->continue = false;
	}

	function handleBuddyAuthorize($val) {
		//incoming contact request

		$engine =& $this->engine;
		$this->log('Accept buddy request from: '. $val['sender']);
		$this->log('----------');
		if (!$engine->response_contact($val['sender'], true, 'Welcome to my list')) {
			$engine->delete_contact($val['sender']);
			$engine->response_contact($val['sender'], true, 'Welcome to my list');
		}
	}

	function handleBuddyInfo($val) {
		//contact list
		$engine =& $this->engine;
		if (!isset($val['contact'])) return;;
		$this->log('Contact list: ');

		foreach ($val['contact'] as $item) {
			$this->log($item['sender']);
		}
		$this->log('----------');
	}

/** Process incoming message
 *  @return void
 */
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
			$out  = $this->config['help'];
			break;
		default:
			$out = false;
			$method = 'process' . ucfirst($command);
			if ($this->processor) {
				if (property_exists($this->processor, 'process' . ucfirst($command))) {
					$out = $this->processor->{$method}($val);
				}
			} else {
				$this->log('no processor configured');
			}
		}

		if ($out !== false) {
			//send message
			$this->debug('> Sending reply message ');
			$this->debug('    '. $out);
			$this->debug('----------');
			$engine->send_message($val['sender'], json_encode($out));
		}
	}

	function run() {
		if (!$this->connected) { $this->connect(); }

		$engine =& $this->engine;
		$this->continue = true;
		$statusIdle = false;

		while ( $this->continue ) {
			$resp = $this->engine->fetch_notification($this->seq+1);
			if ($resp === false) {
				sleep($this->interval);
				continue;
			}

			if (!$statusIdle) { $engine->change_presence(' ', 2); $statusIdle = true; }

			foreach ($resp as $row) {
				foreach ($row as $key=>$val) {
					if ($val['sequence'] > $this->seq) {
						$this->seq = intval($val['sequence']);
					}

					$method = 'handle' . ucfirst($key);
					switch ($key) {
					case 'buddyInfo':
					case 'buddyAuthorize':
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
		$this->log("daemon terminated.");
	}

}
