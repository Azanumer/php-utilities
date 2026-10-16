<?php
/**
 * PasswordPolicy — entropy-based password strength checking, no dependencies.
 *
 * Estimates real strength instead of ticking "has uppercase" boxes:
 * character-pool entropy minus penalties for repeated runs (aaa), sequences
 * (abc, 123, qwerty), and a blocklist of common passwords.
 *
 * Usage:
 *   $policy = new PasswordPolicy(50.0);          // require ~50 bits of entropy
 *   $result = $policy->analyze($_POST['password']);
 *   // ['score'=>0-4, 'entropy'=>52.3, 'passed'=>true, 'feedback'=>[...]]
 *   if (!$policy->passes($_POST['password'])) {
 *       echo implode(' ', $result['feedback']);
 *   }
 *   $strong = $policy->suggest(16);              // random 16-char password
 */
class PasswordPolicy
{
    /** @var float minimum adjusted entropy (bits) for passes() */
    private $minEntropy;

    /** @var array lowercased blocked passwords */
    private $blocklist;

    /** @var array small built-in list of the most abused passwords */
    private static $common = array(
        'password', '123456', '12345678', '123456789', '1234567', '12345', '123123',
        '111111', 'qwerty', 'qwerty123', 'abc123', 'letmein', 'welcome', 'admin',
        'iloveyou', 'monkey', 'dragon', 'football', 'sunshine', 'princess',
        'master', 'baseball', 'superman', 'trustno1', 'passw0rd', 'password1',
        '1q2w3e4r', '1qaz2wsx', 'changeme', 'secret',
    );

    public function __construct($minEntropy = 50.0, array $extraBlocklist = array())
    {
        $this->minEntropy = (float) $minEntropy;
        $this->blocklist  = array_merge(
            self::$common,
            array_map('strtolower', $extraBlocklist)
        );
    }

    /**
     * Add words (site name, company, usernames) to the blocklist.
     *
     * @param array $words
     */
    public function addBlocklist(array $words)
    {
        $this->blocklist = array_merge(
            $this->blocklist,
            array_map('strtolower', $words)
        );
    }

    /**
     * Full analysis of a password.
     *
     * @param string $password
     * @return array score (0-4), entropy (bits), passed (bool), feedback (array)
     */
    public function analyze($password)
    {
        $password = (string) $password;
        $feedback = array();

        // --- base entropy from the character pools actually used ---
        $pool = 0;
        $hasLower = preg_match('/[a-z]/', $password) === 1;
        $hasUpper = preg_match('/[A-Z]/', $password) === 1;
        $hasDigit = preg_match('/[0-9]/', $password) === 1;
        $hasOther = preg_match('/[^a-zA-Z0-9]/', $password) === 1;
        if ($hasLower) { $pool += 26; }
        if ($hasUpper) { $pool += 26; }
        if ($hasDigit) { $pool += 10; }
        if ($hasOther) {
            // Non-ASCII alphabets are a much bigger pool than ASCII symbols.
            $pool += (preg_match('/[^\x00-\x7F]/', $password) === 1) ? 100 : 32;
        }

        $len  = strlen($password);
        $bits = ($pool > 0 && $len > 0) ? $len * log($pool, 2) : 0.0;

        // --- blocklist: instant fail ---
        if ($this->isBlocked($password)) {
            return array(
                'score'    => 0,
                'entropy'  => 0.0,
                'passed'   => false,
                'feedback' => array('This password is too common — pick something unique.'),
            );
        }

        // --- penalty: repeated runs like "aaa" or "1111" ---
        if (preg_match_all('/(.)\\1{2,}/', $password, $m)) {
            $runs = count($m[0]);
            $bits -= min($runs * 8, 24);
            $feedback[] = 'Avoid repeated characters (like "aaa").';
        }

        // --- penalty: sequences like "abc", "321", "qwe" ---
        $seqs = $this->findSequences($password);
        if (!empty($seqs)) {
            $bits -= min(count($seqs) * 10, 30);
            $feedback[] = 'Avoid sequences and keyboard patterns (like "' . $seqs[0] . '").';
        }

        // --- helpful nudges for missing variety / short length ---
        if ($len > 0 && $len < 8) {
            $feedback[] = 'Use at least 8 characters (12+ recommended).';
        }
        $missing = array();
        if (!$hasLower) { $missing[] = 'lowercase letters'; }
        if (!$hasUpper) { $missing[] = 'uppercase letters'; }
        if (!$hasDigit) { $missing[] = 'numbers'; }
        if (!$hasOther) { $missing[] = 'symbols'; }
        if ($len >= 8 && count($missing) >= 2) {
            $feedback[] = 'Mix in ' . implode(' and ', array_slice($missing, 0, 2)) . ' for more strength.';
        }

        $bits = max(0.0, $bits);

        if ($bits < 28)      { $score = 0; }
        elseif ($bits < 36)  { $score = 1; }
        elseif ($bits < 50)  { $score = 2; }
        elseif ($bits < 64)  { $score = 3; }
        else                 { $score = 4; }

        return array(
            'score'    => $score,
            'entropy'  => round($bits, 1),
            'passed'   => $bits >= $this->minEntropy,
            'feedback' => $feedback,
        );
    }

    /**
     * Quick check: does the password meet the minimum entropy bar?
     *
     * @param string $password
     * @return bool
     */
    public function passes($password)
    {
        $result = $this->analyze($password);
        return $result['passed'];
    }

    /**
     * Generate a random password from an unambiguous alphabet.
     *
     * @param int $length minimum 8
     * @return string
     */
    public function suggest($length = 16)
    {
        $length   = max(8, (int) $length);
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%^&*';
        $max      = strlen($alphabet) - 1;
        $bytes    = random_bytes($length);
        $out      = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[ord($bytes[$i]) % ($max + 1)];
        }
        return $out;
    }

    /**
     * Blocklist hit? Checks the raw password and the password with
     * trailing digits/symbols stripped ("password123!" -> "password").
     *
     * @param string $password
     * @return bool
     */
    private function isBlocked($password)
    {
        $lc   = strtolower($password);
        $bare = rtrim(rtrim($lc, '0123456789'), '!@#$%^&*.');
        return in_array($lc, $this->blocklist, true)
            || ($bare !== $lc && $bare !== '' && in_array($bare, $this->blocklist, true));
    }

    /**
     * Find 3+ character ascending/descending runs from common sequences.
     *
     * @param string $password
     * @return array unique matched sequences
     */
    private function findSequences($password)
    {
        $lower = strtolower($password);
        $rows  = array(
            'abcdefghijklmnopqrstuvwxyz',
            '0123456789',
            'qwertyuiop',
            'asdfghjkl',
            'zxcvbnm',
        );
        $found = array();
        foreach ($rows as $row) {
            $rowLen = strlen($row);
            for ($i = 0; $i <= $rowLen - 3; $i++) {
                $seq = substr($row, $i, 3);
                if (strpos($lower, $seq) !== false || strpos($lower, strrev($seq)) !== false) {
                    $found[$seq] = true;
                }
            }
        }
        return array_keys($found);
    }
}
