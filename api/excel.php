<?php
// upload_fixtures_nolib_fixed.php
// Version corrigée : évite les warnings liés aux r:id manquants / ltrim(null,...)
// Supporte .csv et .xlsx (lecture basique, enough for Fixtures export)
// Edit DB credentials below.

$dbHost = '127.0.0.1';
$dbName = 'football_league';
$dbUser = 'your_db_user';
$dbPass = 'your_db_password';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

/* ---------------- CSV reader ---------------- */
function read_csv_rows($path, $delimiter = ",") {
    $fh = fopen($path, 'r');
    if (!$fh) return [];
    $rows = [];
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        if (count($rows) === 0 && isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]); // remove BOM
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

/* ------------- helpers for column letters ------------- */
function colToIndex($col) {
    $col = strtoupper($col);
    $n = 0;
    $len = strlen($col);
    for ($i = 0; $i < $len; $i++) {
        $n = $n * 26 + (ord($col[$i]) - 64);
    }
    return $n;
}
function indexToCol($index) {
    $s = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $s = chr(65 + $mod) . $s;
        $index = intval(($index - $mod) / 26);
    }
    return $s;
}

/* ----------------- XLSX reader (robusté) ----------------
 - lecture de sharedStrings si présente
 - lecture de xl/workbook.xml et xl/_rels/workbook.xml.rels
 - localisation de la feuille demandée (Fixtures) sinon fallback à la première
 - retourne un tableau de lignes où chaque ligne est ['A'=>val,'B'=>val,...]
-------------------------------------------------------- */
function read_xlsx_rows($path, $sheetName = 'Fixtures') {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];

    // shared strings
    $shared = [];
    if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $sxml = simplexml_load_string($zip->getFromIndex($idx));
        if ($sxml !== false) {
            foreach ($sxml->si as $si) {
                $txt = '';
                if (isset($si->t)) {
                    $txt = (string)$si->t;
                } else {
                    foreach ($si->xpath('.//t') as $t) $txt .= (string)$t;
                }
                $shared[] = $txt;
            }
        }
    }

    // workbook.xml
    $workbookIndex = $zip->locateName('xl/workbook.xml');
    if ($workbookIndex === false) {
        $zip->close();
        return [];
    }
    $wbXml = simplexml_load_string($zip->getFromIndex($workbookIndex));
    if ($wbXml === false) {
        $zip->close();
        return [];
    }

    // build sheet name -> rId mapping
    $sheetRidToName = [];
    foreach ($wbXml->sheets->sheet as $s) {
        $name = (string)$s['name'];
        // try to retrieve r:id in the relationships namespace
        $rId = '';
        $relsNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $attr = $s->attributes($relsNs);
        if ($attr && isset($attr['id'])) {
            $rId = (string)$attr['id'];
        } else {
            // fallback: try attributes without namespace (rare)
            $allAttrs = $s->attributes();
            if ($allAttrs) {
                foreach ($allAttrs as $k => $v) {
                    $ks = (string)$k;
                    if (stripos($ks, 'id') !== false) { $rId = (string)$v; break; }
                }
            }
        }
        // only add when we have a name (we always have) and optionally rId
        $sheetRidToName[$rId] = $name; // rId may be '' (we still keep mapping key '')
    }

    // workbook rels
    $relsIndex = $zip->locateName('xl/_rels/workbook.xml.rels');
    $ridToTarget = [];
    if ($relsIndex !== false) {
        $relsXml = simplexml_load_string($zip->getFromIndex($relsIndex));
        if ($relsXml !== false) {
            foreach ($relsXml->Relationship as $rel) {
                $id = (string)$rel['Id'];
                $target = (string)$rel['Target'];
                $ridToTarget[$id] = $target;
            }
        }
    }

    // determine target path for desired sheetName
    $targetPath = null;
    $foundRid = null;
    foreach ($sheetRidToName as $rid => $name) {
        if (trim($name) === trim($sheetName)) { $foundRid = $rid; break; }
    }
    if ($foundRid !== null && $foundRid !== '' && isset($ridToTarget[$foundRid])) {
        $targetPath = 'xl/' . ltrim($ridToTarget[$foundRid], '/');
    } else {
        // either sheetName not found or mapping missing => fallback:
        // try to find by value match in ridToTarget if rId='' used earlier
        // else pick first target for a worksheet (first relation pointing to worksheets/)
        foreach ($ridToTarget as $rid => $t) {
            if (stripos($t, 'worksheets/') !== false) { $targetPath = 'xl/' . ltrim($t, '/'); break; }
        }
        // ultimate fallback: try common default path
        if ($targetPath === null) {
            $possible = [
                'xl/worksheets/sheet1.xml',
                'xl/worksheets/sheet.xml',
            ];
            foreach ($possible as $p) if ($zip->locateName($p) !== false) { $targetPath = $p; break; }
        }
    }

    if ($targetPath === null || $zip->locateName($targetPath) === false) {
        $zip->close();
        return []; // sheet not found
    }

    $sheetXml = simplexml_load_string($zip->getFromName($targetPath));
    if ($sheetXml === false) { $zip->close(); return []; }

    $rows = [];
    $allCols = [];

    foreach ($sheetXml->sheetData->row as $row) {
        $rowCells = [];
        foreach ($row->c as $c) {
            $coord = (string)$c['r']; // A1
            if (!preg_match('/^([A-Z]+)(\d+)$/', $coord, $m)) continue;
            $col = $m[1];
            $v = isset($c->v) ? (string)$c->v : '';
            $t = isset($c['t']) ? (string)$c['t'] : '';
            $val = '';
            if ($t === 's') {
                $idx = (int)$v;
                $val = $shared[$idx] ?? '';
            } elseif ($t === 'inlineStr') {
                $val = (string)$c->is->t;
            } else {
                $val = $v;
            }
            $val = trim(str_replace("\xC2\xA0", ' ', $val));
            $rowCells[$col] = $val;
            $allCols[$col] = true;
        }
        $rows[] = $rowCells;
    }

    // create ordered column list A..max
    if (empty($allCols)) { $zip->close(); return []; }
    $colLetters = array_keys($allCols);
    // compute numeric max index
    $maxIndex = 0;
    foreach ($colLetters as $c) $maxIndex = max($maxIndex, colToIndex($c));
    $orderedCols = [];
    for ($i = 1; $i <= $maxIndex; $i++) $orderedCols[] = indexToCol($i);

    // reformat rows: fill missing columns with ''
    $out = [];
    foreach ($rows as $r) {
        $rowAssoc = [];
        foreach ($orderedCols as $col) {
            $rowAssoc[$col] = isset($r[$col]) ? $r[$col] : '';
        }
        $out[] = $rowAssoc;
    }

    $zip->close();
    return $out; // 0-based: first row = header row (cols A,B,...)
}

/* ---------------- convert rows (A..columns) to associative with header names ----- */
function rows_to_assoc($rows) {
    if (empty($rows)) return [];
    $headerRow = $rows[0];
    $headers = [];
    foreach ($headerRow as $col => $val) {
        $h = trim((string)$val);
        if ($h === '') $h = $col; // fallback header name = column letter if empty
        $headers[$col] = $h;
    }
    $data = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $assoc = [];
        foreach ($headers as $col => $hdr) {
            $assoc[$hdr] = isset($row[$col]) ? $row[$col] : '';
        }
        $data[] = $assoc;
    }
    return $data;
}

/* ---------------- find header helper ---------------- */
function find_header_key($headers, $candidates) {
    foreach ($headers as $h) {
        foreach ($candidates as $c) {
            if (mb_strtolower(trim($h)) === mb_strtolower(trim($c))) return $h;
        }
    }
    foreach ($headers as $h) {
        foreach ($candidates as $c) {
            if (mb_stripos(mb_strtolower($h), mb_strtolower($c)) !== false) return $h;
        }
    }
    return null;
}

/* ---------------- Main (UI + processing) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Upload Fixtures (fix)</title>
    <style>body{font-family:system-ui,Arial;margin:28px} .card{max-width:820px;border:1px solid #ddd;padding:16px;border-radius:8px} input[type=file]{display:block;margin:12px 0} button{background:#0b74de;color:#fff;padding:8px 12px;border-radius:6px;border:0;cursor:pointer}</style>
    </head><body>
    <div class="card">
      <h2>Upload Fixtures (.csv ou .xlsx) — version corrigée</h2>
      <p>Nom de feuille attendu : <strong>Fixtures</strong> (sinon première feuille utilisée). Colonnes attendues : <code>GW, Team, Opponent, Points For, Points Against, Result, BEST, WORST</code></p>
      <form method="post" enctype="multipart/form-data">
        <input type="file" name="file" accept=".csv,.xlsx" required>
        <label><input type="checkbox" name="insert_missing" checked> Insérer les équipes manquantes automatiquement</label><br><br>
        <button type="submit">Uploader et appliquer</button>
      </form>
    </div>
    </body></html>
    <?php
    exit;
}

// POST
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); echo "Aucun fichier ou erreur d'upload."; exit;
}
$uploadDir = __DIR__ . '/uploads'; if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
$orig = basename($_FILES['file']['name']);
$target = $uploadDir . '/' . uniqid('upload_') . '_' . preg_replace('/[^A-Za-z0-9_.-]/','_', $orig);
if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) { echo "Impossible de déplacer le fichier."; exit; }
$insertMissing = !empty($_POST['insert_missing']);

$ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
$rows = [];
if ($ext === 'csv' || $ext === 'txt') {
    $csvRows = read_csv_rows($target, ",");
    if (empty($csvRows)) { echo "CSV vide."; exit; }
    $headers = $csvRows[0]; $data = [];
    foreach ($headers as $k => $h) $headers[$k] = preg_replace('/^\xEF\xBB\xBF/', '', trim($h));
    for ($i = 1; $i < count($csvRows); $i++) {
        $r = $csvRows[$i]; $assoc = [];
        for ($j = 0; $j < count($headers); $j++) {
            $h = $headers[$j] !== '' ? $headers[$j] : "col$j";
            $assoc[$h] = isset($r[$j]) ? $r[$j] : '';
        }
        $data[] = $assoc;
    }
    $rows = $data;
} elseif ($ext === 'xlsx') {
    $raw = read_xlsx_rows($target, 'Fixtures');
    if (empty($raw)) { echo "No rows found in xlsx (sheet missing or unreadable)."; exit; }
    $rows = rows_to_assoc($raw);
} else { echo "Type non supporté: $ext"; exit; }

if (empty($rows)) { echo "Aucune ligne de données."; exit; }

// find headers keys
$hdrs = array_keys($rows[0]);
$colGW = find_header_key($hdrs, ['GW','Week']);
$colTeam = find_header_key($hdrs, ['Team','Equipe','Team Name']);
$colOpponent = find_header_key($hdrs, ['Opponent','Opp']);
$colPF = find_header_key($hdrs, ['Points For','PointsFor','Points']);
$colPA = find_header_key($hdrs, ['Points Against','PointsAgainst','Points_Against']);
$colResult = find_header_key($hdrs, ['Result','Res']);
$colBEST = find_header_key($hdrs, ['BEST','Best']);
$colWORST = find_header_key($hdrs, ['WORST','Worst']);

if (!$colTeam || !$colPF || !$colPA) { echo "Colonnes requises manquantes: Team, Points For, Points Against"; exit; }

// DB
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
} catch (Exception $e) { echo "DB connect error: ".htmlentities($e->getMessage()); exit; }

$updateStmt = $pdo->prepare("
    UPDATE teams SET
      played = played + 1,
      won = won + :won,
      drawn = drawn + :drawn,
      lost = lost + :lost,
      goals_for = goals_for + :gf,
      goals_against = goals_against + :ga,
      goal_difference = goal_difference + :diff,
      points = points + :pt,
      best = best + :best,
      worst = worst + :worst
    WHERE name = :name
");
$insertStmt = $pdo->prepare("
    INSERT INTO teams
    (name, played, won, drawn, lost, goals_for, goals_against, goal_difference, points, best, worst)
    VALUES (:name, :played, :won, :drawn, :lost, :gf, :ga, :diff, :pt, :best, :worst)
");

$pdo->beginTransaction();
$processed = $updated = $inserted = 0; $errors = [];

foreach ($rows as $i => $row) {
    $team = trim((string)($row[$colTeam] ?? '')); if ($team === '') continue;
    $pf = (int) round(floatval(str_replace(',', '.', ($row[$colPF] ?? 0))));
    $pa = (int) round(floatval(str_replace(',', '.', ($row[$colPA] ?? 0))));
    $res = mb_strtolower(trim((string)($row[$colResult] ?? '')));
    $won = ($res === 'win' || $res === 'w') ? 1 : 0;
    $drawn = ($res === 'draw' || $res === 'd') ? 1 : 0;
    $lost = (!$won && !$drawn) ? 1 : 0;
    $ptAdd = $won ? 3 : ($drawn ? 1 : 0);
    $bestCell = trim((string)($row[$colBEST] ?? '')); $worstCell = trim((string)($row[$colWORST] ?? ''));
    $bestAdd = (mb_strpos($bestCell, '✅') !== false || mb_strtolower($bestCell) === 'yes') ? 1 : 0;
    $worstAdd = (mb_strpos($worstCell, '❌') !== false || mb_strtolower($worstCell) === 'yes') ? 1 : 0;
    $diff = $pf - $pa;

    try {
        $updateStmt->execute([
            ':won'=>$won,':drawn'=>$drawn,':lost'=>$lost,':gf'=>$pf,':ga'=>$pa,
            ':diff'=>$diff,':pt'=>$ptAdd,':best'=>$bestAdd,':worst'=>$worstAdd,':name'=>$team
        ]);
        if ($updateStmt->rowCount() === 0) {
            if ($insertMissing) {
                $insertStmt->execute([
                    ':name'=>$team,':played'=>1,':won'=>$won,':drawn'=>$drawn,':lost'=>$lost,
                    ':gf'=>$pf,':ga'=>$pa,':diff'=>$diff,':pt'=>$ptAdd,':best'=>$bestAdd,':worst'=>$worstAdd
                ]);
                $inserted++;
            } else {
                $errors[] = "Team not found: $team";
            }
        } else $updated++;
    } catch (Exception $ex) {
        $errors[] = "Ligne ".($i+2)." erreur pour $team: ". $ex->getMessage();
    }
    $processed++;
}

if (!empty($errors)) {
    $pdo->rollBack();
    echo "<h3>Erreurs — aucun changement appliqué</h3><ul>";
    foreach ($errors as $e) echo "<li>".htmlentities($e)."</li>";
    echo "</ul>";
    exit;
}
$pdo->commit();

echo "<h2>Import terminé</h2><ul>";
echo "<li>Fichier: ".htmlentities($orig)."</li>";
echo "<li>Lignes traitées: $processed</li>";
echo "<li>Équipes mises à jour: $updated</li>";
echo "<li>Équipes insérées: $inserted</li>";
echo "</ul>";
echo "<p><a href=\"upload_fixtures_nolib_fixed.php\">Importer un autre fichier</a></p>";
