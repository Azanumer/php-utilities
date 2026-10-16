<?php
// PasswordPolicy demo — run from the CLI: php password-policy-example.php
require __DIR__ . '/../src/PasswordPolicy.php';

$policy = new PasswordPolicy(50.0);          // require ~50 bits of entropy
$policy->addBlocklist(array('chat4smile'));  // block site/company names too

$candidates = array(
    'password123',            // blocklisted -> instant fail
    'qwe123!@#',              // sequence/keyboard-pattern penalty -> fail
    'aaaBBB111!!!',           // repeated-run penalty -> barely passes, with tips
    'K7#mQ2!vZ9@xP4&',        // 14 chars, 4 pools -> strong
    'correct horse battery staple', // long passphrase -> very strong
);

foreach ($candidates as $pw) {
    $r = $policy->analyze($pw);
    printf(
        "%-32s score %d/4  entropy %6.1f bits  %s\n",
        "'$pw'",
        $r['score'],
        $r['entropy'],
        $r['passed'] ? 'PASS' : 'FAIL'
    );
    foreach ($r['feedback'] as $tip) {
        echo "    - $tip\n";
    }
}

echo "\nQuick check: ";
var_dump($policy->passes('K7#mQ2!vZ9@xP4&'));   // bool(true)

echo 'Suggested password: ' . $policy->suggest(16) . "\n";
echo 'Suggested, analyzed: ';
print_r($policy->analyze($policy->suggest(16)));
