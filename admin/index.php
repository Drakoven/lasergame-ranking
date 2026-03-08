<?php
require __DIR__ . "/auth.php";
require_admin();
require __DIR__ . "/../api/config.php";

// Vérif centre en session
if (empty($_SESSION["center_id"])) {
  http_response_code(400);
  exit("Centre manquant en session. Reconnecte-toi.");
}
$centerId = (int)$_SESSION["center_id"];

// CSRF token
if (empty($_SESSION["csrf"])) {
  $_SESSION["csrf"] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION["csrf"];

// Helpers
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
function fmt_dt($s) { return date("d/m/Y H:i", strtotime($s)); }
function fmt_bytes($b) {
  $mb = ((int)$b) / (1024 * 1024);
  return number_format($mb, 2, ",", " ") . " Mo";
}

// Filtres
$ym = $_GET["ym"] ?? date("Y-m");
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date("Y-m");

$start = $ym . "-01 00:00:00";
$end   = date("Y-m-d H:i:s", strtotime($start . " +1 month"));

$q = trim($_GET["q"] ?? "");
$status = $_GET["status"] ?? "all"; // all | 0 | 1 | 2
$sort = $_GET["sort"] ?? "scoreDesc";
$limit = 300;

$orderBy = "s.score DESC, s.party_datetime DESC";
if ($sort === "scoreAsc") $orderBy = "s.score ASC, s.party_datetime DESC";
if ($sort === "dateDesc") $orderBy = "s.party_datetime DESC, s.score DESC";
if ($sort === "dateAsc")  $orderBy = "s.party_datetime ASC, s.score DESC";

// Query principale : scores DU CENTRE connecté uniquement
$sql = "
  SELECT
    s.id, s.email, s.party_datetime,
    s.vest_pseudo, s.player_pseudo,
    s.score, s.party_code,
    s.photo_path, s.photo_mime, s.photo_size,
    s.status, s.created_at,
    s.verified_at, s.verified_by_admin_id,
    a.username AS verified_by_username
  FROM scores s
  LEFT JOIN admins a ON a.id = s.verified_by_admin_id
  WHERE s.center_id = :center_id
    AND s.party_datetime >= :start
    AND s.party_datetime < :end
";
$params = [
  ":center_id" => $centerId,
  ":start" => $start,
  ":end" => $end
];

if ($q !== "") {
  $sql .= " AND (
    s.email LIKE :q OR
    s.vest_pseudo LIKE :q OR
    s.player_pseudo LIKE :q OR
    s.party_code LIKE :q
  )";
  $params[":q"] = "%" . $q . "%";
}

if (in_array($status, ["0","1","2"], true)) {
  $sql .= " AND s.status = :st";
  $params[":st"] = (int)$status;
}

$sql .= " ORDER BY $orderBy LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// Stats PRO (indépendant du tri)
// ===============================

// Total affiché dans la liste
$total = count($rows);

// Meilleur score ACCEPTÉ du mois pour CE CENTRE
$stmtBestApproved = $pdo->prepare("
  SELECT id, score, vest_pseudo, player_pseudo
  FROM scores
  WHERE center_id = :center_id
    AND party_datetime >= :start
    AND party_datetime < :end
    AND status = 1
  ORDER BY score DESC, party_datetime DESC
  LIMIT 1
");
$stmtBestApproved->execute([
  ":center_id"=>$centerId,
  ":start"=>$start,
  ":end"=>$end
]);
$bestApproved = $stmtBestApproved->fetch(PDO::FETCH_ASSOC);

// Fallback : meilleur score tout statut pour CE CENTRE
$stmtBestAny = $pdo->prepare("
  SELECT id, score, vest_pseudo, player_pseudo
  FROM scores
  WHERE center_id = :center_id
    AND party_datetime >= :start
    AND party_datetime < :end
  ORDER BY score DESC, party_datetime DESC
  LIMIT 1
");
$stmtBestAny->execute([
  ":center_id"=>$centerId,
  ":start"=>$start,
  ":end"=>$end
]);
$bestAny = $stmtBestAny->fetch(PDO::FETCH_ASSOC);

$best = $bestApproved ?: $bestAny;

// Dernier envoi du mois pour CE CENTRE
$stmtLast = $pdo->prepare("
  SELECT id, created_at, player_pseudo
  FROM scores
  WHERE center_id = :center_id
    AND party_datetime >= :start
    AND party_datetime < :end
  ORDER BY created_at DESC
  LIMIT 1
");
$stmtLast->execute([
  ":center_id"=>$centerId,
  ":start"=>$start,
  ":end"=>$end
]);
$last = $stmtLast->fetch(PDO::FETCH_ASSOC);

// Pending (en attente) du mois pour CE CENTRE
$stmtCount = $pdo->prepare("
  SELECT COUNT(*)
  FROM scores
  WHERE center_id = :center_id
    AND party_datetime >= :start
    AND party_datetime < :end
    AND status = 0
");
$stmtCount->execute([
  ":center_id"=>$centerId,
  ":start"=>$start,
  ":end"=>$end
]);
$pendingCount = (int)$stmtCount->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin — Classement LaserGame</title>
  <link rel="stylesheet" href="admin.css" />
  <meta name="csrf-token" content="<?= h($csrf) ?>">
</head>
<body>

<header class="topbar">
  <div class="topbar__left">
    <div class="topbar__title">
      <h1>Admin — Classement</h1>
      <span id="pendingBadge" class="badge badge--danger" <?= $pendingCount ? "" : "hidden" ?>>
        <?= $pendingCount ? h($pendingCount . " à vérifier") : "" ?>
      </span>
    </div>
    <p class="muted">Suivi des scores et vainqueur mensuel</p>
  </div>

  <div class="topbar__right">
    <button id="adminThemeToggle" class="btn btn--pill" type="button" aria-label="Basculer le thème">🌙</button>

    <button id="newCodeBtn" class="btn" type="button">Nouveau code</button>
    <span id="codeBox" class="badge badge--gold" hidden>—</span>

    <a href="qrcode.php" class="btn btn--ghost">QR Code</a>
    <a class="btn btn--ghost" href="./logout.php">Déconnexion</a>
  </div>
</header>

<main class="container">

  <section class="cards">
    <article class="card">
      <div class="card__label">Scores (mois)</div>
      <div class="card__value"><?= (int)$total ?></div>
    </article>

    <article class="card">
      <div class="card__label">Meilleur score <span class="badge badge--gold">#1</span></div>
      <div class="card__value"><?= $best ? (int)$best["score"] : "—" ?></div>
      <div class="card__sub">
        <?= $best ? h($best["player_pseudo"] . " (gilet " . $best["vest_pseudo"] . ")") : "—" ?>
      </div>
    </article>

    <article class="card">
      <div class="card__label">Dernier envoi</div>
      <div class="card__value"><?= $last ? h(fmt_dt($last["created_at"])) : "—" ?></div>
      <div class="card__sub"><?= $last ? h($last["player_pseudo"]) : "—" ?></div>
    </article>
  </section>

  <section class="panel">
    <div class="panel__header">
      <h2>Scores</h2>
      <p class="muted">Filtrer, vérifier et contrôler les photos.</p>
    </div>

    <form id="filtersForm" class="controls" method="get">
      <label class="control">
        <span>Mois</span>
        <input name="ym" type="month" value="<?= h($ym) ?>" />
      </label>

      <label class="control">
        <span>Recherche</span>
        <input name="q" type="search" placeholder="Joueur, gilet, email, code..." value="<?= h($q) ?>" />
      </label>

      <label class="control">
        <span>Statut</span>
        <select id="statusSelect" name="status">
          <option value="all" <?= $status==="all" ? "selected" : "" ?>>Tous</option>
          <option value="0"   <?= $status==="0" ? "selected" : "" ?>>En attente</option>
          <option value="1"   <?= $status==="1" ? "selected" : "" ?>>Accepté</option>
          <option value="2"   <?= $status==="2" ? "selected" : "" ?>>Rejeté</option>
        </select>
      </label>

      <label class="control">
        <span>Trier</span>
        <select name="sort">
          <option value="scoreDesc" <?= $sort==="scoreDesc" ? "selected" : "" ?>>Score (desc)</option>
          <option value="scoreAsc"  <?= $sort==="scoreAsc" ? "selected" : "" ?>>Score (asc)</option>
          <option value="dateDesc"  <?= $sort==="dateDesc" ? "selected" : "" ?>>Date (récent)</option>
          <option value="dateAsc"   <?= $sort==="dateAsc" ? "selected" : "" ?>>Date (ancien)</option>
        </select>
      </label>

      <div class="controls__actions">
        <button class="btn" type="submit">Appliquer</button>
        <button type="button" id="onlyPendingBtn" class="btn btn--ghost">À vérifier</button>
      </div>
    </form>

    <div class="tableWrap">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Joueur</th>
            <th>Gilet</th>
            <th>Score</th>
            <th>Code</th>
            <th>Photo</th>
            <th>Statut</th>
            <th>Audit</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody id="scoresTbody">
          <?php if (!$rows): ?>
            <tr><td colspan="10" class="muted" style="text-align:center; padding:18px;">Aucun résultat avec ces filtres.</td></tr>
          <?php endif; ?>

          <?php foreach ($rows as $r): ?>
            <?php
              $st = (int)$r["status"];
              $isPending  = ($st === 0);
              $isApproved = ($st === 1);
              $isRejected = ($st === 2);

              $pillText = $isPending ? "En attente" : ($isApproved ? "Accepté" : "Rejeté");
              $pillClass = $isPending ? "pill--wait" : ($isApproved ? "pill--ok" : "pill--no");
            ?>
            <tr>
              <td><?= (int)$r["id"] ?></td>
              <td><?= h(fmt_dt($r["party_datetime"])) ?></td>
              <td><?= h($r["player_pseudo"]) ?></td>
              <td><?= h($r["vest_pseudo"]) ?></td>
              <td><strong><?= (int)$r["score"] ?></strong></td>
              <td><?= h($r["party_code"]) ?></td>

              <td>
                <?php if (!empty($r["photo_path"])): ?>
                  <button
                    type="button"
                    class="btn btn--sm js-view-photo"
                    data-src="<?= h($r["photo_path"]) ?>"
                    data-meta="<?= h($r["player_pseudo"]) ?>"
                    data-size="<?= h(fmt_bytes($r["photo_size"])) ?>"
                  >Voir</button>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>

              <td>
                <span data-pill class="pill <?= $pillClass ?>">
                  <?= h($pillText) ?>
                </span>
              </td>

              <td>
                <?php if (!$isPending && !empty($r["verified_at"])): ?>
                  <small>
                    <?= h($r["verified_by_username"] ?: "admin") ?><br>
                    <?= h(fmt_dt($r["verified_at"])) ?>
                  </small>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>

              <td>
                <button
                  type="button"
                  class="btn btn--sm js-set-status"
                  data-id="<?= (int)$r["id"] ?>"
                  data-status="1"
                  <?= $isApproved ? "disabled" : "" ?>
                >Valider</button>

                <button
                  type="button"
                  class="btn btn--ghost btn--sm js-set-status"
                  data-id="<?= (int)$r["id"] ?>"
                  data-status="2"
                  <?= $isRejected ? "disabled" : "" ?>
                >Refuser</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<div class="modal" id="photoModal" hidden>
  <div class="modal__overlay" id="photoOverlay"></div>

  <div class="modal__content" role="dialog" aria-modal="true" aria-labelledby="photoTitle">
    <div class="modal__header">
      <h2 id="photoTitle">Photo du classement</h2>
      <button class="modal__close" id="photoClose" type="button" aria-label="Fermer">✕</button>
    </div>

    <p class="muted" id="photoMeta"></p>
    <p class="muted" id="photoSize"></p>

    <img id="photoImg" class="photo" alt="Photo du classement" />

    <div class="modal__actions">
      <a id="photoOpen" class="btn btn--ghost" href="#" target="_blank" rel="noopener">Ouvrir en grand</a>
      <button class="btn" id="photoOk" type="button">OK</button>
    </div>
  </div>
</div>

<script src="admin.js"></script>
</body>
</html>
