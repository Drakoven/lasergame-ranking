<?php
// admin/auth.php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    "httponly" => true,
    "secure"   => !empty($_SERVER["HTTPS"]),
    "samesite" => "Lax",
  ]);
  session_start();
}

function require_admin() {
  if (empty($_SESSION["is_admin"])) {
    header("Location: /lasergame/admin/login.php");
    exit;
  }
}
