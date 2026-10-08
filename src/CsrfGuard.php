<?php
/**
 * CsrfGuard — session-based CSRF protection, no dependencies.
 *
 * Usage:
 *   $csrf = new CsrfGuard();
 *   // in the form:
 *   echo $csrf->field();
 *   // on POST:
 *   if ( ! $csrf->validate() ) { http_response_code(403); exit('Bad CSRF token'); }
 *
 * Tokens are single-use and rotated after every successful validation
 * (double-submit safe for normal forms; use per-action names for AJAX).
 */
class CsrfGuard
{
    /** @var string */
    private $sessionKey;
    /** @var int max tokens kept per session (prevents unbounded growth) */
    private $maxTokens;

    public function __construct($sessionKey = 'csrf_tokens', $maxTokens = 20)
    {
        $this->sessionKey = $sessionKey;
        $this->maxTokens  = $maxTokens;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION[$this->sessionKey]) || !is_array($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = array();
        }
    }

    /**
     * Generate (and remember) a new token for a named action.
     *
     * @param string $action Form identifier, e.g. 'login', 'delete-post-12'
     * @return string token
     */
    public function token($action = 'default')
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash_hmac('sha256', $token . '|' . $action, $this->sessionSecret());
        $_SESSION[$this->sessionKey][$hash] = time();

        // prune oldest beyond the cap
        if (count($_SESSION[$this->sessionKey]) > $this->maxTokens) {
            asort($_SESSION[$this->sessionKey]);
            $_SESSION[$this->sessionKey] = array_slice($_SESSION[$this->sessionKey], -$this->maxTokens, null, true);
        }
        return $token;
    }

    /**
     * Render a hidden <input> for a form.
     */
    public function field($action = 'default')
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($this->token($action), ENT_QUOTES, 'UTF-8') . '">'
             . '<input type="hidden" name="_csrf_action" value="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate the submitted token. Single-use: consumed on success.
     * Reads $_POST['_csrf'] / $_POST['_csrf_action'] by default.
     */
    public function validate($token = null, $action = null)
    {
        if ($token === null) {
            $token = isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : '';
        }
        if ($action === null) {
            $action = isset($_POST['_csrf_action']) ? (string) $_POST['_csrf_action'] : 'default';
        }
        if ($token === '') {
            return false;
        }

        $hash = hash_hmac('sha256', $token . '|' . $action, $this->sessionSecret());
        if (isset($_SESSION[$this->sessionKey][$hash])) {
            unset($_SESSION[$this->sessionKey][$hash]); // single use
            return true;
        }
        return false;
    }

    /**
     * Per-session secret so tokens can't be forged without the session.
     */
    private function sessionSecret()
    {
        if (empty($_SESSION['csrf_secret'])) {
            $_SESSION['csrf_secret'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_secret'];
    }
}
