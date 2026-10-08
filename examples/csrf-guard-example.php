<?php
// CsrfGuard demo — run with: php -S localhost:8080 examples/csrf-guard-example.php
require __DIR__ . '/../src/CsrfGuard.php';

$csrf = new CsrfGuard();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($csrf->validate()) {
        echo '<p style="color:green">✔ Token valid — form processed. Token was single-use; reload to get a fresh one.</p>';
    } else {
        http_response_code(403);
        echo '<p style="color:red">✘ Invalid or reused CSRF token.</p>';
    }
    echo '<p><a href="">← back to the form</a></p>';
    exit;
}
?>
<!doctype html>
<html><body>
<h2>CSRF-protected form demo</h2>
<form method="post">
    <?php echo $csrf->field('demo-form'); ?>
    <input type="text" name="name" placeholder="Your name">
    <button type="submit">Submit</button>
</form>
<p>Try submitting twice with the same page (back button) — the second POST is rejected because tokens are single-use.</p>
</body></html>
