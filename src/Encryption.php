<?php
/**
 * Encryption — dependency-free authenticated encryption for PHP 7.4+.
 *
 * Wraps libsodium (XChaCha20-Poly1305, falling back to XSalsa20-Poly1305
 * secretbox) with:
 *  - HKDF key derivation: your master secret never becomes a raw key directly
 *  - Key rotation: a 'kid' key registry — decrypt() finds the right key even
 *    after you rotate, so old ciphertext stays readable
 *  - Self-describing packed format: v1.<kid>.<alg>.<base64url(nonce|ciphertext)>
 *
 * Usage:
 *   $enc = new Encryption(['v1' => $_ENV['MASTER_SECRET']]); // encrypts with 'v1'
 *   $token = $enc->encrypt('sensitive data', 'user-42');      // context binds the key
 *   $plain = $enc->decrypt($token, 'user-42');
 *
 *   // Rotate: add new key FIRST in the array, keep the old one for reads.
 *   $enc = new Encryption(['v2' => $newSecret, 'v1' => $oldSecret]);
 *
 * Ciphertext is authenticated: any tampering makes decrypt() return false.
 * Requires the sodium extension (bundled with PHP 7.2+).
 *
 * @author Azan Umer <https://github.com/Azanumer>
 * @license MIT
 */
final class Encryption
{
    private const FORMAT = 'v1';
    /** @var array<string,string> kid => master secret */
    private array $keys;
    private string $defaultKid;
    private bool $useXChaCha;

    /**
     * @param array<string,string> $keys    Non-empty kid => master-secret map.
     *                                      First entry is used for encryption.
     */
    public function __construct(array $keys)
    {
        if (empty($keys)) {
            throw new InvalidArgumentException('At least one key is required');
        }
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('The sodium extension is required');
        }
        foreach ($keys as $kid => $secret) {
            if (!is_string($kid) || $kid === '' || strpos($kid, '.') !== false) {
                throw new InvalidArgumentException('Key ids must be non-empty strings without dots');
            }
            if (!is_string($secret) || strlen($secret) < 16) {
                throw new InvalidArgumentException("Key '{$kid}' is too short (min 16 chars)");
            }
        }
        $this->keys = $keys;
        $this->defaultKid = (string) array_key_first($keys);
        $this->useXChaCha = function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt');
    }

    /**
     * Encrypt. $context (aad-ish) binds the ciphertext to a purpose —
     * decrypt() must use the same context or it fails.
     */
    public function encrypt(string $plaintext, string $context = ''): string
    {
        $key   = $this->deriveKey($this->keys[$this->defaultKid], $context);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); // 24 bytes, both primitives

        if ($this->useXChaCha) {
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $context, $nonce, $key);
            $alg = 'x';
        } else {
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
            $alg = 's';
        }

        sodium_memzero($key);
        return self::FORMAT . '.' . $this->defaultKid . '.' . $alg . '.'
            . rtrim(strtr(base64_encode($nonce . $cipher), '+/', '-_'), '=');
    }

    /**
     * Decrypt. Returns the plaintext, or false on tamper/wrong-key/bad-format.
     * Tries the stored kid first, then every other key (supports rotation).
     */
    public function decrypt(string $packed, string $context = '')
    {
        $parts = explode('.', $packed, 4);
        if (count($parts) !== 4 || $parts[0] !== self::FORMAT) {
            return false;
        }
        [, $kid, $alg, $b64] = $parts;
        if ($alg !== 'x' && $alg !== 's') {
            return false;
        }

        $raw = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1) {
            return false;
        }
        $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Stored kid first (fast path), then the rest — rotation-safe.
        $order = [$kid => true] + array_fill_keys(array_keys($this->keys), true);
        foreach (array_keys($order) as $tryKid) {
            if (!isset($this->keys[$tryKid])) {
                continue;
            }
            $key = $this->deriveKey($this->keys[$tryKid], $context);
            if ($alg === 'x') {
                // Token was sealed with XChaCha20; can't open it without the primitive.
                if (!$this->useXChaCha) {
                    sodium_memzero($key);
                    continue;
                }
                $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $context, $nonce, $key);
            } else {
                $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            }
            sodium_memzero($key);
            if ($plain !== false) {
                return $plain;
            }
        }
        return false;
    }

    /**
     * Derive a 32-byte AEAD key from the master secret, bound to $context
     * via HKDF-SHA256. Changing the context yields an unrelated key —
     * ciphertext encrypted for 'user-42' cannot be decrypted as 'user-43'.
     */
    private function deriveKey(string $masterSecret, string $context): string
    {
        $key = hash_hkdf('sha256', $masterSecret, 32, 'php-utilities-encryption-v1', $context);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('HKDF key derivation failed');
        }
        return $key;
    }
}
