<?php
/**
 * IpAddress — client-IP detection behind trusted proxies + CIDR utilities.
 *
 * No dependencies, works on PHP 7.2+, IPv4 and IPv6 safe (no ip2long()).
 *
 * Usage:
 *   $ip = IpAddress::clientIp( ['203.0.113.10', '10.0.0.0/8'] ); // trusted proxies (plain IP or CIDR)
 *   if ( IpAddress::inCidr( $ip, '203.0.113.0/24' ) ) { ... }
 *   if ( IpAddress::isPrivate( $ip ) ) { ... }
 */

class IpAddress
{
    /**
     * Determine the real client IP from server headers.
     *
     * Trusts proxy headers only when REMOTE_ADDR itself is one of the
     * trusted proxies; then walks the X-Forwarded-For chain from right
     * to left and returns the first IP that is NOT a trusted proxy.
     *
     * @param array $trustedProxies Plain IPs or CIDR ranges (e.g. Cloudflare's ranges).
     * @param array|null $server    Override for $_SERVER (testing).
     * @return string
     */
    public static function clientIp( array $trustedProxies = array(), $server = null ) {
        $s = $server === null ? $_SERVER : $server;

        $remote = isset( $s['REMOTE_ADDR'] ) ? trim( (string) $s['REMOTE_ADDR'] ) : '';
        $direct = self::valid( $remote ) ? $remote : '';

        // Single-value headers set by known CDNs/proxies (only trusted if REMOTE_ADDR is trusted).
        $single = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP' );
        // Build the chain: leftmost = original client, rightmost = nearest proxy.
        $chain = array();
        if ( isset( $s['HTTP_X_FORWARDED_FOR'] ) ) {
            foreach ( explode( ',', $s['HTTP_X_FORWARDED_FOR'] ) as $part ) {
                $part = trim( $part );
                if ( self::valid( $part ) ) {
                    $chain[] = $part;
                }
            }
        }
        if ( isset( $s['HTTP_X_REAL_IP'] ) ) {
            $xri = trim( (string) $s['HTTP_X_REAL_IP'] );
            if ( self::valid( $xri ) ) {
                $chain[] = $xri;
            }
        }
        if ( $direct !== '' ) {
            $chain[] = $direct;
        }

        if ( $direct === '' || ! self::inRanges( $direct, $trustedProxies ) ) {
            // REMOTE_ADDR is not a trusted proxy — it IS the client.
            return $direct !== '' ? $direct : '0.0.0.0';
        }

        foreach ( $single as $header ) {
            if ( isset( $s[ $header ] ) && self::valid( trim( (string) $s[ $header ] ) ) ) {
                return trim( (string) $s[ $header ] );
            }
        }

        // Walk right-to-left: skip every trusted proxy, take the first unknown IP.
        for ( $i = count( $chain ) - 1; $i >= 0; $i-- ) {
            if ( ! self::inRanges( $chain[ $i ], $trustedProxies ) ) {
                return $chain[ $i ];
            }
        }

        return $direct;
    }

    /**
     * Is this a syntactically valid IP address?
     *
     * @param string $ip
     * @return bool
     */
    public static function valid( $ip ) {
        return filter_var( $ip, FILTER_VALIDATE_IP ) !== false;
    }

    /**
     * Is an IP inside a CIDR range? Accepts "1.2.3.4/24", "2001:db8::/32",
     * or a bare "1.2.3.4" (exact match).
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    public static function inCidr( $ip, $cidr ) {
        if ( ! self::valid( $ip ) ) {
            return false;
        }
        if ( strpos( $cidr, '/' ) === false ) {
            return strtolower( $ip ) === strtolower( trim( $cidr ) );
        }
        list( $net, $bits ) = explode( '/', $cidr, 2 );
        $net = trim( $net );
        $bits = (int) trim( $bits );
        if ( ! self::valid( $net ) || $bits < 0 ) {
            return false;
        }
        $ipBin  = @inet_pton( $ip );
        $netBin = @inet_pton( $net );
        if ( $ipBin === false || $netBin === false || strlen( $ipBin ) !== strlen( $netBin ) ) {
            return false;
        }
        $maxBits = strlen( $ipBin ) * 8;
        if ( $bits > $maxBits ) {
            return false;
        }
        // Build the mask byte-by-byte: full bytes, one partial byte, then zeros.
        $fullBytes  = (int) floor( $bits / 8 );
        $remBits    = $bits % 8;
        $mask       = str_repeat( "\xff", $fullBytes );
        if ( $remBits > 0 ) {
            $mask .= chr( 256 - ( 1 << ( 8 - $remBits ) ) );
        }
        $mask = str_pad( $mask, strlen( $ipBin ), "\x00" );
        return ( $ipBin & $mask ) === ( $netBin & $mask );
    }

    /**
     * Is an IP inside ANY of the given ranges (plain IPs or CIDRs)?
     *
     * @param string $ip
     * @param array  $ranges
     * @return bool
     */
    public static function inRanges( $ip, array $ranges ) {
        foreach ( $ranges as $range ) {
            if ( self::inCidr( $ip, trim( (string) $range ) ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is this a private / reserved address (RFC 1918, loopback, link-local,
     * CGNAT 100.64.0.0/10, unique-local fc00::/7, ::1)?
     *
     * @param string $ip
     * @return bool
     */
    public static function isPrivate( $ip ) {
        static $private = array(
            '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8',
            '100.64.0.0/10', '169.254.0.0/16', '192.0.2.0/24', '198.51.100.0/24',
            '203.0.113.0/24', '::1/128', 'fc00::/7', 'fe80::/10',
        );
        return self::inRanges( $ip, $private );
    }

    /**
     * Cloudflare's published IPv4 ranges (useful as $trustedProxies).
     * Source: https://www.cloudflare.com/ips/ — refresh occasionally.
     *
     * @return array
     */
    public static function cloudflareRanges() {
        return array(
            '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '104.16.0.0/13', '104.24.0.0/14', '108.162.192.0/18',
            '131.0.72.0/22', '141.101.64.0/18', '162.158.0.0/15',
            '172.64.0.0/13', '173.245.48.0/20', '188.114.96.0/20',
            '190.93.240.0/20', '197.234.240.0/22', '198.41.128.0/17',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32',
            '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29',
            '2c0f:f248::/32',
        );
    }
}
