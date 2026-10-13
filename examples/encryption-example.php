<?php
/**
 * encryption-example.php — demo of the Encryption class.
 * Run: php examples/encryption-example.php
 */

require __DIR__ . '/../src/Encryption.php';

// ------------------------------------------------------------------ basic use
$enc = new Encryption(['v1' => 'a-long-random-master-secret-min-16-chars']);

$token = $enc->encrypt('card-number: 4111…', 'order-123');
echo "Packed: {$token}\n";
echo 'Decrypted: ' . var_export($enc->decrypt($token, 'order-123'), true) . "\n";

// ------------------------------------------------------------- tamper proof
$tampered = substr($token, 0, -4) . 'AAAA';
echo 'Tampered decrypt: ' . var_export($enc->decrypt($tampered, 'order-123'), true) . " (false)\n";

// ------------------------------------------------------------ context binds
echo 'Wrong context: ' . var_export($enc->decrypt($token, 'order-999'), true) . " (false)\n";

// ----------------------------------------------------------- key rotation
// Add the new key FIRST (it becomes the encryption default); keep the old one
// so ciphertext from before the rotation still decrypts.
$rotated = new Encryption(['v2' => 'brand-new-master-secret-rotation!', 'v1' => 'a-long-random-master-secret-min-16-chars']);
echo 'Old token after rotation: ' . var_export($rotated->decrypt($token, 'order-123'), true) . "\n";
$newToken = $rotated->encrypt('fresh secret', 'order-124');
echo 'New token uses v2: ' . (strpos($newToken, 'v1.v2.') === 0 ? 'yes' : 'no') . "\n";
