<?php

$config = array(
	'Messaging.bots' =>  array(

		array(
			'nick' => 'bot1',
			'driver' => 'YahooMessenger',
			'password' => '',
			'consumer' => '',
			'secret' => '',
			'debug' => true,
			'help' => 'Sorry, I can\'t help you. Find Thomas Anderson.',
			'bot' => 'Lame',
			'outgoing_path' => TMP . 'outgoing',
			'outgoing_prefix' => 'lame',
			),

		array(
			'nick' => 'bot2',
			'driver' => 'YahooMessenger',
			'password' => '',
			'consumer' => '',
			'secret' => '',
			'debug' => false,
			'help' => 'Sorry, I can\'t help you. Find Morpheus.',
			'bot' => 'Lame',
			'outgoing_path' => TMP . 'outgoing',
			'outgoing_prefix' => 'lame',
			),

		),

	);
