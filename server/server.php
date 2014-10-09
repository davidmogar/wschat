<?php

	/* Turn on erros */
	error_reporting(E_ALL);

	/* Parse config file */
	$config = parse_ini_file("config.ini", 1);

	/* Null variable to be used in socket_select (only accept variables) */
	$null = NULL;

	/* Create socket */
	$server_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	socket_set_option($server_socket, SOL_SOCKET, SO_REUSEADDR, 1);
	socket_bind($server_socket, 0, $config['server']['port']);
	socket_listen($server_socket);

	/* Create an array of clients and add the server socket to it */
	$clients = array($server_socket);

	while (true) {
		$selected = $clients;
		socket_select($selected, $null, $null, 0, $config['sockets']['timeout']);

		/* Accept new sockets */
		if (in_array($server_socket, $selected)) {
			$socket = socket_accept($server_socket);
			$clients[] = $socket;

			$header = socket_read($socket, $config['sockets']['read_length']);
			handshake($header, $socket, $config['server']['host'], $config['server']['port']);

			/* Notify clients about new connection */
			socket_getpeername($socket, $ip);
			$response = mask(json_encode(array(
					'type' => 'system',
					'message' => $config['messages']['connection'] . " ($ip)"
			)));
			send_message($response, $clients);

			/* Remove server socket from selected sockets array */
			$key = array_search($server_socket, $selected);
			unset($selected[$key]);
		}

		foreach ($selected as $socket) {
			while (socket_recv($socket, $buffer, $config['sockets']['read_length'], 0) >= 1) {
				/* Get message data */
				$data = unmask($buffer);
				$json = json_decode($data);
				$user = $json->user;
				$message = $json->message;

				/* Notify clients about new message */
				$response = mask(json_encode(array(
					'type' => 'usermsg',
					'user' => $user,
					'message' => $message
				)));
				send_message($response, $clients);
				break 2;
			}

			/* Check if the client disconnected */
			$buffer = @socket_read($socket, $config['sockets']['read_length'], PHP_NORMAL_READ);
			if ($buffer === false) {
				socket_getpeername($socket, $ip);

				$key = array_search($socket, $clients);
				unset($clients[$key]);

				/* Notify clients about client disconnection */
				$response = mask(json_encode(array(
					'type' => 'system',
					'message' => $config['messages']['disconnection']
				)));
				send_message($response, $clients);
			}
		}
	}

	/* Close server socket */
	socket_close($server_socket);

	function send_message($message, $receivers) {
		foreach ($receivers as $receiver) {
			@socket_write($receiver, $message, strlen($message));
		}

		return true;
	}

	function mask($text) {
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($text);

		if ($length <= 125) {
			$header = pack('CC', $b1, $length);
		} elseif ($length > 125 && $length < 65536) {
			$header = pack('CCn', $b1, 126, $length);
		} elseif ($length >= 65536) {
			$header = pack('CCNN', $b1, 127, $length);
		}

		return $header.$text;
	}

	function unmask($payload) {
		$length = ord($payload[1]) & 127;

		if ($length == 126) {
			$masks = substr($payload, 4, 4);
			$data = substr($payload, 8);
		} elseif ($length == 127) {
			$masks = substr($payload, 10, 4);
			$data = substr($payload, 14);
		} else {
			$masks = substr($payload, 2, 4);
			$data = substr($payload, 6);
		}

		$text = "";

		for ($i = 0; $i < strlen($data); $i++) {
			$text .= $data[$i] ^ $masks[$i % 4];
		}

		return $text;
	}

	function handshake($header, $client, $host, $port) {
		$headers = array();
		$lines = preg_split("/\r\n/", $header);

		foreach ($lines as $line) {
			$line = chop($line);

			if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
				$headers[$matches[1]] = $matches[2];
			}
		}

		$secKey = $headers['Sec-WebSocket-Key'];
		$acceptKey = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
		$upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
				"Upgrade: websocket\r\n" .
				"Connection: Upgrade\r\n" .
				"WebSocket-Origin: $host\r\n" .
				"WebSocket-Location: ws://$host:$port\r\n" .
				"Sec-WebSocket-Accept: $acceptKey\r\n\r\n";

		socket_write($client, $upgrade, strlen($upgrade));
	}
	
?>