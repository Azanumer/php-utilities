<?php
/**
 * Demo: HtmlSanitizer on hostile input.
 *
 * Run: php examples/html-sanitizer-example.php
 */
require __DIR__ . '/../src/HtmlSanitizer.php';

$hostile = <<<HTML
<p class="intro">Hello <strong>world</strong>!</p>
<script>alert('xss')</script>
<a href="javascript:alert(1)" onclick="steal()">click me</a>
<a href="https://example.com" target="_blank">safe link</a>
<img src="x" onerror="alert(2)" alt="broken">
<iframe src="https://evil.example"></iframe>
<div><custom-tag>unwrapped but <em>kept</em></custom-tag></div>
<!-- comment stripped -->
HTML;

echo "=== default policy ===\n";
echo (new HtmlSanitizer())->sanitize($hostile), "\n\n";

echo "=== strict policy (p/br/strong/em/a only) ===\n";
echo (new HtmlSanitizer())
    ->allowTags(['p', 'br', 'strong', 'em', 'a'])
    ->allowAttributes('a', ['href', 'title'])
    ->sanitize($hostile), "\n";
