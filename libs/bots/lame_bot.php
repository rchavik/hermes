<?php
App::import('Vendor', 'preg_find');
App::import('Helper', 'Time');

class LameBot extends Object {

	private $name = 'Lame';

	public $keywords = array('help');

	private $transporter = false;

	private $config = false;

	function __construct(&$transporter, $config = array()) {
		$this->startTime = time();
		parent::__construct();
		$this->transporter = $transporter;
		$this->config = $config;
		$this->TimeHelper = new TimeHelper;
	}

	public function processAbout() {
		return 'Hi, you can clone me from http://github.com/rchavik/hermes';
	}

	public function processUptime() {
		$stats = $this->transporter->getStats();
		$secs = time() - $stats['startTime'];
		$timeAgoInWords = $this->TimeHelper->timeAgoInWords($this->startTime);
		$reply = sprintf('I have been up for %d seconds (%s).', $secs, $timeAgoInWords);
		return $reply;
	}

	public function processStats() {
		$stats = $this->transporter->getStats();
		$secs = time() - $stats['startTime'];
		$timeAgoInWords = $this->TimeHelper->timeAgoInWords($this->startTime);
		$reply = sprintf(
			'I have been up for %d seconds (%s), ',
			$secs, $timeAgoInWords
			);
		$reply .= sprintf(
			'during which I have received %d instant messages, ' .
			'and sent %d replies.',
			$stats['received'], $stats['sent']
			);
		return $reply;
	}

	public function outgoing() {
		$config = $this->config;
		$outgoing = array();
		if (empty($config['outgoing_path']) || empty($config['outgoing_prefix'])) {
			$this->log('bot is not lame enough');
			return $outgoing;
		}

		$regex = sprintf('/%s.*\.out$/', $config['outgoing_prefix']);
		$files = preg_find($regex, $config['outgoing_path'], PREG_FIND_RETURNASSOC|PREG_FIND_SORTMODIFIED);
		foreach ($files as $file => $fileInfo) {
			$lines = file_get_contents($file);
			unlink($file);
			$debris = explode("\t", $lines, 2);
			if (count($debris) !== 2) {
				$this->log('Number of fields mismatch');
				continue;
			}
			$to = trim($debris[0]);
			$messages = str_split(trim($debris[1]), 2000);
			foreach ($messages as $message) {
				$outgoing[] = compact('to', 'message');
			}
		}
		return $outgoing;
	}
}
