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

	private $bot = false;

	private $stats = false;

	function __construct(&$controller = null) {
		$this->controller = $controller;
		$this->interval = $this->interval >= 5 ? $this->interval : 5;
		$this->stats = array(
			'startTime' => time(),
			'sent' => 0,
			'received' => 0,
			);
	}

	function init($config) {
		$config = Set::merge(array(
			'username' => false,
			'password' => false,
			'consumer' => false,
			'secret' => false,
			'debug' => false,
			'help' => false,
			'bot' => false,
			'interval' => 5,
			), $config);
		$this->config = $config;
		$this->interval = $config['interval'];
		extract($config);

		pcntl_signal(SIGTERM,  array(&$this, 'stop'));
		pcntl_signal(SIGINT,  array(&$this, 'stop'));
		$this->username = $username;
		$this->engine = new JYMEngine($consumer, $secret, $username, $password);
		$this->engine->debug = $debug;

		if ($bot !== false) {
			$botClass = $bot . 'Bot';
			App::import('Lib', $botClass);
			$this->bot = new $botClass($this, $this->config);
		}
	}

	function debug($message) {
		if ($this->engine->debug) {
			$this->log($message);
		}
	}

	function getStats() {
		return $this->stats;
	}

	function connect() {
		$engine =& $this->engine;
		$this->debug('> Fetching request token');
		if (!$engine->fetch_request_token()) die('Fetching request token failed');

		$this->debug('> Fetching access token');
		if (!$engine->fetch_access_token()) die('Fetching access token failed');

		if (!$engine->signon()) die('Signon failed');
		$this->log('> Signon as: '. $this->username);
		$this->continue = $this->connected = true;

	}

	function disconnect() {
		$engine =& $this->engine;
		$engine->signoff();
		$this->connected = false;
		$this->log("daemon terminated.");
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
		$this->log('+ Incoming message from: "'. $val['sender']. '" on "'. date('H:i:s', $val['timeStamp']). '"');
		$this->log('   '. $val['msg']);
		$this->log('----------');

		$words = explode(' ', trim(strtolower($val['msg'])));
		$command = strtolower($words[0]);
		switch ($command) {
		case 'help':
			$out  = $this->config['help'];
			break;
		default:
			$out = false;
			$method = 'process' . ucfirst(strtolower($command));
			if ($this->bot) {
				if (method_exists($this->bot, $method)) {
					$out = $this->bot->{$method}($val);
				}
			} else {
				$this->log('no bot configured');
			}
		}

		if ($out !== false) {
			//send message
			$this->log('> Sending reply message ');
			$this->log('    '. $out);
			$this->log('----------');
			$this->stats['sent'] = ++$this->stats['sent'];
			$engine->send_message($val['sender'], json_encode($out));
		}
	}

	function start() {
		if (!$this->connected) { $this->connect(); }

		$engine =& $this->engine;
		$engine->change_presence(' ', 2);
		$this->_notifyMasters('All units Irene. I say again, Irene.');
		while ( $this->continue ) {
			$resp = $this->engine->fetch_notification($this->seq+1);
			$resp = $resp === false ? array() : $resp;
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
						if ($key == 'message') {
							$this->stats['received'] = ++$this->stats['received'];
						}
						$this->{$method}($val);
						break;
					default:
						$this->log('unsupported key: ' . $key);
						break;
					}
				}
			}

			if ($this->bot && method_exists($this->bot, 'outgoing')) {
				$messages = $this->bot->outgoing();
				$this->sendMessages($messages);
			}
			sleep($this->interval);
		}
		$this->disconnect();
	}

	private function _notifyMasters($message) {
		if (empty($this->config['masters'])) {
			$masters = array();
		} else {
			$masters = is_array($this->config['masters'])
				? $this->config['masters']
				: array($this->config['masters']);
		}
		$messages = array();
		foreach ($masters as $master) {
			$messages[] = array(
				'to' => $master,
				'message' => $message,
				);
		}
		$this->sendMessages($messages);
	}

	function stop() {
		$this->_notifyMasters('Super 6-4 is Going Down. See you later.');
		$this->continue = false;
	}

	function log($msg, $type = LOG_ERROR) {
		if (!empty($this->config['nick'])) {
			$type = $this->config['nick'];
		} else {
			$type = 'error';
		}
		parent::log('type: ' . $type);
		parent::log($msg, $type);
	}

	public function sendMessages($messages) {
		foreach ($messages as $message) {
			if (empty($message['to']) || empty($message['message'])) {
				continue;
			}
			$this->engine->send_message($message['to'], json_encode($message['message']));
			$this->stats['sent'] = ++$this->stats['sent'];
		}
	}
}
