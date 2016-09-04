<?php

// ------
// CONFIG
// ------
$version	  = 60002;
$node		  = array('1.1.1.1', 8333); // node you want to connect to
$local		  = array('2.2.2.2', 8333); // this node
$start_height = 0; // starting block height for this node

list($node_ip, $node_port) = $node;
list($local_ip, $local_port) = $local;

echo 'Config'.PHP_EOL.'------'.PHP_EOL;
echo 'version:      '.$version.PHP_EOL;
echo 'node:         '.implode($node, ':').PHP_EOL;
echo 'local:        '.implode($local, ':').PHP_EOL;
echo 'start_height: '.$start_height.PHP_EOL.PHP_EOL;


// ------------------
// 1. VERSION MESSAGE
// ------------------

// General Functions
function fieldsize($field, $bytes = 1) {
	$length = $bytes * 2;
	$result = str_pad($field, $length, '0', STR_PAD_LEFT);
	return $result;
}

function swapEndian($hex) {
    return implode('', array_reverse(str_split($hex, 2)));
}

function byteSpaces($bytes) { // add spaces between bytes
	$bytes = implode(str_split(strtoupper($bytes), 2), ' ');
	return $bytes;
}

// Version Message Functions 
function timestamp($time) { // convert timestamp to network byte order
	$time = dechex($time);
	$time = fieldsize($time, 8);
	$time = swapEndian($time);
	return byteSpaces($time);
}

function networkaddress($ip, $port = '8333') { // convert ip address to network byte order
	$services = '01 00 00 00 00 00 00 00'; // 1 = NODE_NETWORK
	
	$ipv6_prefix = '00 00 00 00 00 00 00 00 00 00 FF FF';
	
	$ip = explode('.', $ip);
	$ip = array_map("dechex", $ip);
	$ip = array_map("fieldsize", $ip);
	$ip = array_map("strtoupper", $ip);
	$ip = implode($ip, ' ');
	
	$port = dechex($port); // for some fucking reason this is big-endian
	$port = byteSpaces($port);
	
	return "$services $ipv6_prefix $ip $port";
}

function checksum($string) {
	$string = hex2bin($string);
	$hash = hash('sha256', hash('sha256', $string, true));
	$checksum = substr($hash, 0, 8);
	return byteSpaces($checksum);
}


// VERSION MESSAGE
$version = bytespaces(swapEndian(fieldsize(dechex($version), 4)));
$timestamp = timestamp(time()); // 73 43 c9 57 00 00 00 00
$recv = networkaddress($node_ip, $node_port);
$from = networkaddress($local_ip, $local_port);
$nonce = bytespaces(swapEndian(fieldsize(dechex(mt_rand()), 8)));
$start_height = bytespaces(swapEndian(fieldsize(dechex($start_height), 4)));

$version_array = [ // hexadecimal, network byte order
	'version'   	=> $version,					// 4 bytes (60002)
	'services'  	=> '01 00 00 00 00 00 00 00',	// 8 bytes (1 = NODE_NETORK)
	'timestamp' 	=> $timestamp,					// 8 bytes
	'addr_recv' 	=> $recv,				  		// 26 bytes
	'addr_from' 	=> $from,				  		// 26 bytes
	'nonce'			=> $nonce,						// 8 bytes
	'user_agent'	=> '00',						// varint
	'start_height'	=> $start_height				// 4 bytes
];

$version_message = str_replace(' ', '', implode($version_array));

// VERSION MESSAGE HEADER
$payload = swapEndian(fieldsize(dechex(strlen($version_message) / 2), 4));
$checksum = checksum($version_message);

$header_array = [
	'magicbytes'	=> 'F9 BE B4 D9',
	'command'		=> '76 65 72 73 69 6F 6E 00 00 00 00 00',
	'payload_size'	=> $payload,
	'checksum'		=> $checksum,
];

$message_header = str_replace(' ', '', implode($header_array));


// Print Results
echo PHP_EOL.'VERSION MESSAGE'.PHP_EOL.'---------------'.PHP_EOL;
echo 'Header:'; print_r ($header_array).PHP_EOL;
echo 'Payload:'; print_r ($version_array).PHP_EOL;

$message = $message_header.$version_message;
echo PHP_EOL.'Serialized:'.PHP_EOL.$message.PHP_EOL.PHP_EOL;


// -----------------
// 2. SOCKET CONNECT
// -----------------

// Print socket error
function error() {
	$error = socket_strerror(socket_last_error());
	return $error.PHP_EOL;
}


// HANDSHAKE

echo PHP_EOL.'SOCKET'.PHP_EOL.'------'.PHP_EOL;

$socket = socket_create(AF_INET, SOCK_STREAM, 6); // IPv4, TCP uses this type, TCP protocol
socket_connect($socket, $node_ip, $node_port);				echo "Connect: ".error();
socket_send($socket, $message, strlen($message) / 2, 0); 	echo "Send:    ".error();
socket_recv($socket, $buf, 84, MSG_WAITALL);				echo "Receive: ".error();

var_dump($buf);


// https://en.bitcoin.it/wiki/Protocol_documentation
// https://coinlogic.wordpress.com/2014/03/09/the-bitcoin-protocol-4-network-messages-1-version/