<?php
/**
 * SessionGuard — session hardening in one call.
 *
 * Sets strict cookie params (Secure auto-detected, HttpOnly, SameSite=Lax),
 * enforces idle + absolute timeouts, optionally binds the session to the
 * client IP / User-Agent, and rotates the session ID probabilistically.
 *
 *     SessionGuard::start(array('idle_timeout' => 1800));
 *     if (!SessionGuard::validate()) {
 *         SessionGuard::destroy();
 *         header('Location: /login.php');
 *         exit;
 *     }
 *     SessionGuard::touch();
 *
 * Call validate() + touch() on every authenticated request.
 */

class SessionGuard
{
    /** @var array Effective options from the last start() call. */
    private static $options = array();

    /** @var string Session key used for guard metadata. */
    const META_KEY = '__sg';

    public static function start(array $options = array())
    {
        $defaults = array(
            'lifetime'          => 0,        // session cookie lifetime (0 = browser session)
            'path'              => '/',
            'domain'            => '',
            'secure'            => null,     // null = auto-detect HTTPS
            'samesite'          => 'Lax',    // Lax | Strict | None
            'idle_timeout'      => 1800,     // 30 min of inactivity kills the session
            'absolute_timeout'  => 43200,    // 12 h max session age, even if active
            'rotate_probability'=> 5,        // % chance per request to regenerate the ID
            'bind_ip'           => 'loose',  // none | loose (/24, /64) | strict (full IP)
            'bind_user_agent'   => true,
        );
        self::$options = array_merge($defaults, $options);
        $o = self::$options;

        $secure = $o['secure'];
        if ($secure === null) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        }

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params(array(
                'lifetime' => (int) $o['lifetime'],
                'path'     => $o['path'],
                'domain'   => $o['domain'],
                'secure'   => (bool) $secure,
                'httponly' => true,
                'samesite' => $o['samesite'],
            ));
        } else {
            session_set_cookie_params((int) $o['lifetime'], $o['path'], $o['domain'], (bool) $secure, true);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION[self::META_KEY])) {
            $_SESSION[self::META_KEY] = array(
                'created'    => time(),
                'last'       => time(),
                'ip'         => self::clientIp(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '',
            );
        }

        // Probabilistic rotation spreads the cost of session_regenerate_id().
        if (mt_rand(1, 100) <= (int) $o['rotate_probability']) {
            session_regenerate_id(true);
        }
    }

    /**
     * Check timeouts and bindings. Returns false when the session is stale,
     * expired, or the client no longer matches its bindings.
     */
    public static function validate()
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION[self::META_KEY])) {
            return false;
        }
        $meta = $_SESSION[self::META_KEY];
        $now  = time();
        $o    = self::$options;

        if (($now - $meta['created']) > (int) $o['absolute_timeout']) {
            return false;
        }
        if (($now - $meta['last']) > (int) $o['idle_timeout']) {
            return false;
        }
        if (!self::ipMatches($meta['ip'], self::clientIp(), $o['bind_ip'])) {
            return false;
        }
        if ($o['bind_user_agent']) {
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
            if ($ua !== $meta['user_agent']) {
                return false;
            }
        }
        return true;
    }

    /** Refresh the last-activity timestamp (call after validate() passes). */
    public static function touch()
    {
        if (isset($_SESSION[self::META_KEY])) {
            $_SESSION[self::META_KEY]['last'] = time();
        }
    }

    /** Force a fresh session ID (e.g. right after login — prevents fixation). */
    public static function rotate()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Destroy the session and its cookie. */
    public static function destroy()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
            session_destroy();
        }
    }

    private static function clientIp()
    {
        // Trust X-Forwarded-For only when you KNOW a proxy sets it.
        // For a hardened default we use the direct remote address.
        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    private static function ipMatches($stored, $current, $mode)
    {
        if ($mode === 'none') {
            return true;
        }
        if ($mode === 'strict') {
            return $stored === $current;
        }
        // loose: compare /24 for IPv4, /64 for IPv6
        $a = self::networkOf($stored, $mode);
        $b = self::networkOf($current, $mode);
        return $a !== '' && $a === $b;
    }

    private static function networkOf($ip, $mode)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_slice(explode('.', $ip), 0, 3)) . '.0/24';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed === false) {
                return '';
            }
            // First 64 bits.
            $half = substr($packed, 0, 8);
            return inet_ntop($half . str_repeat("\x00", 8)) . '/64';
        }
        return '';
    }
}
