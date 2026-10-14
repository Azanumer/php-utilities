<?php
/**
 * IpAddress usage demo. Run: php examples/ip-address-example.php
 */

require __DIR__ . '/../src/IpAddress.php';

// 1. CIDR membership (IPv4 + IPv6).
var_dump( IpAddress::inCidr( '203.0.113.45', '203.0.113.0/24' ) );   // true
var_dump( IpAddress::inCidr( '203.0.114.45', '203.0.113.0/24' ) );   // false
var_dump( IpAddress::inCidr( '2001:db8::1', '2001:db8::/32' ) );     // true
var_dump( IpAddress::inCidr( '192.168.1.5', '192.168.1.5' ) );       // true (bare IP = exact match)

// 2. Range lists and private-address detection.
var_dump( IpAddress::isPrivate( '10.5.6.7' ) );                      // true
var_dump( IpAddress::isPrivate( '8.8.8.8' ) );                       // false
var_dump( IpAddress::inRanges( '8.8.8.8', array( '8.8.8.0/24', '1.1.1.0/24' ) ) ); // true

// 3. Client IP behind a trusted proxy (simulated $_SERVER).
$fake = array(
    'REMOTE_ADDR'           => '203.0.113.10',                       // our trusted proxy
    'HTTP_X_FORWARDED_FOR'  => '198.51.100.23, 203.0.113.10',        // client, proxy
);
echo IpAddress::clientIp( array( '203.0.113.0/24' ), $fake ), "\n";  // 198.51.100.23

// Untrusted REMOTE_ADDR: proxy headers are ignored, REMOTE_ADDR wins.
$fake2 = array(
    'REMOTE_ADDR'          => '198.51.100.99',
    'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
);
echo IpAddress::clientIp( array( '203.0.113.0/24' ), $fake2 ), "\n";  // 198.51.100.99

// Cloudflare in front: trust their ranges, read CF-Connecting-IP.
$fake3 = array(
    'REMOTE_ADDR'           => '104.16.5.6',
    'HTTP_CF_CONNECTING_IP' => '198.51.100.77',
);
echo IpAddress::clientIp( IpAddress::cloudflareRanges(), $fake3 ), "\n"; // 198.51.100.77
