<?php
// api/config.example.php
// Copie ce fichier en api/config.php et adapte les identifiants (ne jamais commit config.php)

$host = "localhost";
$db   = "lasergame";
$user = "root";
$pass = ""; // WAMP : souvent vide si root
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
  // En prod : log plutôt que afficher
  http_response_code(500);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode(["ok" => false, "error" => "DB connection failed"]);
  exit;
}
