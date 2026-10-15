<?php
// SessionGuard demo — must run behind a web server (sessions need HTTP).
// This file documents the intended flow; open it in a browser, don't run via CLI.
require __DIR__ . '/../src/SessionGuard.php';

SessionGuard::start(array(
    'idle_timeout'       => 1800,   // log out after 30 min idle
    'absolute_timeout'   => 28800,  // max 8 h per session
    'rotate_probability' => 5,      // 5% chance per request to rotate the ID
    'bind_ip'            => 'loose',// none | loose | strict
));

if (!SessionGuard::validate()) {
    SessionGuard::destroy();
    header('Location: /login.php');
    exit;
}

SessionGuard::touch();

// After a successful password check, always rotate (kills session fixation):
// SessionGuard::rotate();
$_SESSION['user_id'] = 42;

echo 'Session OK. ID: ' . htmlspecialchars(session_id());
