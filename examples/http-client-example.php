<?php
/**
 * HttpClient usage examples.
 *
 * Run: php examples/http-client-example.php
 */

require __DIR__ . '/../src/HttpClient.php';

// 1. Simple GET with query params -------------------------------------------
$client = new HttpClient( array( 'timeout' => 10 ) );
$res    = $client->get( 'https://httpbin.org/get', array( 'hello' => 'world' ) );

echo 'GET status: ' . $res->status . PHP_EOL;
echo 'OK: ' . ( $res->ok() ? 'yes' : 'no' ) . PHP_EOL;
$data = $res->json();
echo 'Echoed query: ' . ( isset( $data['args']['hello'] ) ? $data['args']['hello'] : '(none)' ) . PHP_EOL . PHP_EOL;

// 2. JSON POST ---------------------------------------------------------------
$res = $client->postJson( 'https://httpbin.org/post', array( 'name' => 'Azan' ) );
echo 'POST status: ' . $res->status . PHP_EOL;
echo 'Content-Type: ' . $res->header( 'content-type' ) . PHP_EOL . PHP_EOL;

// 3. Bearer token ------------------------------------------------------------
$res = $client->bearer( 'GET', 'https://httpbin.org/bearer', 'secret-token' );
echo 'Bearer status: ' . $res->status . PHP_EOL . PHP_EOL;

// 4. Strict mode: exceptions on 4xx/5xx or network failure -------------------
$strict = new HttpClient( array( 'throw' => true ) );
try {
	$strict->get( 'https://httpbin.org/status/404' );
} catch ( HttpClientException $e ) {
	echo 'Caught: ' . $e->getMessage() . PHP_EOL;
}
