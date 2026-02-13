<?php
require __DIR__ . "/auth.php";
session_destroy();
header("Location: /lasergame/admin/login.php");
exit;
