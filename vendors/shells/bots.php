<?php

class BotsShell extends Shell {

	public function help() {
		$help =<<<EOF

cake bots start <botname>
EOF;
		$this->out($help);
	}

	function configure($params) {
		Configure::load('Messaging.bots');
		$config = Configure::read('Messaging.bots.' . $params[0]);
		if (empty($config)) {
			$this->out('No configuration found for bot: ' . $params[0]);
			exit();
		}
		$config['username'] = $params[0];
		$this->config = $config;
	}

	function startBot() {
		$class = $this->config['driver'] . 'Bot';
		App::import('Lib', $class);
		$Bot = new $class($this);
		$Bot->init($this->config);
		$Bot->run();
	}

	public function start() {
		if (empty($this->args)) {
			$this->help();
			exit;
		}

		$this->configure($this->args);
		switch ($this->config['driver']) {
		case 'YahooMessenger':
			$this->startBot($config);
			break;
		default:
			$this->out('Unsupported driver');
			break;
		}
	}

}
