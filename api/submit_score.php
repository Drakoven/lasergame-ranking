<?php
header("Content-Type: application/json; charset=utf-8");
require __DIR__ . "/config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["ok"=>false, "error"=>"Méthode non autorisée"]);
  exit;
}

function clean($s) { return trim((string)$s); }

$email = clean($_POST["email"] ?? "");
$partyDateTime = clean($_POST["partyDateTime"] ?? "");
$vestPseudo = clean($_POST["vestPseudo"] ?? "");
$playerPseudo = clean($_POST["playerPseudo"] ?? "");
$score = $_POST["score"] ?? "";
$partyCode = strtoupper(clean($_POST["partyCode"] ?? ""));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Email invalide"]);
  exit;
}
if ($partyDateTime === "") {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Date de partie manquante"]);
  exit;
}
if (mb_strlen($vestPseudo) < 3 || mb_strlen($playerPseudo) < 3) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Pseudo trop court"]);
  exit;
}

$scoreVal = (int)$score;
if (!is_numeric($score) || $scoreVal < 0) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Score invalide"]);
  exit;
}

// Vérifie le code de partie + récupère le center_id
$now = date("Y-m-d H:i:s");
$stmt = $pdo->prepare("
  SELECT id, center_id
  FROM party_codes
  WHERE code = :c
    AND valid_from <= :now
    AND valid_to >= :now
  ORDER BY id DESC
  LIMIT 1
");
$stmt->execute([":c"=>$partyCode, ":now"=>$now]);
$codeRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$codeRow) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Code invalide ou expiré. Demande un code au staff."]);
  exit;
}

$centerId = (int)$codeRow["center_id"];

// Photo
if (empty($_FILES["photo"]) || $_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Photo manquante"]);
  exit;
}

$photo = $_FILES["photo"];
$allowed = ["image/jpeg"=>"jpg", "image/png"=>"png"];
$mime = mime_content_type($photo["tmp_name"]);

if (!isset($allowed[$mime])) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Photo invalide : JPG ou PNG uniquement"]);
  exit;
}

$maxBytes = 5 * 1024 * 1024;
if ($photo["size"] > $maxBytes) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Photo trop lourde (max 5 Mo)"]);
  exit;
}

// Vrai check image (anti fake)
$imgInfo = @getimagesize($photo["tmp_name"]);
if ($imgInfo === false) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Fichier invalide (image non reconnue)."]);
  exit;
}
if ($imgInfo[0] > 6000 || $imgInfo[1] > 6000) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Image trop grande (dimensions)."]);
  exit;
}

$uploadsDir = realpath(__DIR__ . "/../uploads");
if (!$uploadsDir) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Dossier uploads introuvable"]);
  exit;
}

$ext = $allowed[$mime];
$filename = bin2hex(random_bytes(16)) . "." . $ext;
$destAbs = $uploadsDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($photo["tmp_name"], $destAbs)) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Impossible d'enregistrer la photo"]);
  exit;
}

$photoPath = "/lasergame/uploads/" . $filename;

$stmt = $pdo->prepare("
  INSERT INTO scores (
    center_id,
    email, party_datetime, vest_pseudo, player_pseudo,
    score, party_code,
    photo_path, photo_mime, photo_size,
    verified, status, created_at
  )
  VALUES (
    :center_id,
    :email, :pdt, :vp, :pp,
    :score, :pc,
    :path, :mime, :size,
    0, 0, NOW()
  )
");
$stmt->execute([
  ":center_id"=>$centerId,
  ":email"=>$email,
  ":pdt"=>$partyDateTime,
  ":vp"=>$vestPseudo,
  ":pp"=>$playerPseudo,
  ":score"=>$scoreVal,
  ":pc"=>$partyCode,
  ":path"=>$photoPath,
  ":mime"=>$mime,
  ":size"=>(int)$photo["size"],
]);

echo json_encode([
  "ok"=>true,
  "id"=>(int)$pdo->lastInsertId(),
  "center_id"=>$centerId,
  "photo_path"=>$photoPath
]);
exit;
