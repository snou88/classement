<?php

$simana = 0;
require_once 'config.php';

$verification = false;

// debug_xlsx_full.php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// --- Fichier à diagnostiquer ---
$file = __DIR__ . '/uploads/Results_V2_updated.xlsx'; // <-- METTRE LE NOM EXACT ICI

echo "<h2>Debug XLSX — diagnostic</h2>";
echo "<p><strong>PHP version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Zip extension loaded:</strong> " . (extension_loaded('zip') ? 'yes' : 'NO') . "</p>";
echo "<p><strong>libxml available:</strong> " . (function_exists('simplexml_load_string') ? 'yes' : 'NO') . "</p>";

if (!file_exists($file)) {
    echo "<h3 style='color:darkred'>Fichier introuvable:</h3><pre>" . htmlentities($file) . "</pre>";
    exit;
}
echo "<p>Fichier trouvé: <code>" . htmlentities($file) . "</code></p>";

try {
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) throw new Exception("Impossible d'ouvrir l'archive xlsx avec ZipArchive.");
    echo "<h3>Contenu de l'archive (liste)</h3><pre>";
    for ($i = 0; $i < $zip->numFiles; $i++) {
        echo htmlentities($zip->getNameIndex($i)) . "\n";
    }
    echo "</pre>";

    // workbook.xml excerpt
    $ix = $zip->locateName('xl/workbook.xml');
    if ($ix !== false) {
        $wb = $zip->getFromIndex($ix);
        echo "<h3>xl/workbook.xml (extrait)</h3><pre>" . nl2br(htmlentities(substr($wb, 0, 2000))) . "</pre>";
    }

    // workbook.xml.rels excerpt
    $ix = $zip->locateName('xl/_rels/workbook.xml.rels');
    if ($ix !== false) {
        $rels = $zip->getFromIndex($ix);
        echo "<h3>xl/_rels/workbook.xml.rels (extrait)</h3><pre>" . nl2br(htmlentities(substr($rels, 0, 2000))) . "</pre>";
    }

    // sharedStrings presence
    $ix = $zip->locateName('xl/sharedStrings.xml');
    if ($ix !== false) {
        $ss = $zip->getFromIndex($ix);
        echo "<h3>xl/sharedStrings.xml (extrait)</h3><pre>" . nl2br(htmlentities(substr($ss, 0, 2000))) . "</pre>";
    } else {
        echo "<p>xl/sharedStrings.xml absent — cellules prob. en inlineStr (OK)</p>";
    }

    // --- lecture feuille Fixtures
    echo "<h3>Tentative de lecture de la feuille 'Fixtures' (super-robust)</h3>";
    $rows = read_xlsx_rows_superrobust($file, 'Fixtures');
    if (empty($rows)) {
        echo "<p style='color:orange'>Aucune ligne récupérée (tableau vide retourné).</p>";
    } else {
        echo "<p>Nombre de lignes récupérées: " . count($rows) . "</p>";
        echo "<table border='1' cellpadding='6' style='border-collapse:collapse;font-family:Arial'><tr style='background:#efefef'>";
        foreach (array_keys($rows[0]) as $h) echo "<th>" . htmlentities($h) . "</th>";
        echo "</tr>";
        $n = 0;
        foreach ($rows as $r) {
            echo "<tr>";
            foreach ($r as $v) {
                echo "<td>" . htmlentities($v) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";


        // $rows is the array you already have
        // If you want missing teams to be created automatically, set 3rd arg true:
        $result = store_matches($pdo, $rows, false);
        echo '<script>
        fetch("confirm_results.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json" // or "application/x-www-form-urlencoded"
            },
            body: JSON.stringify({ week: ' . $simana . '})
        })
        .then(response => response.text())  // or .json() if the page returns JSON
        .then(data => {
            window.location.href = "../index.html?msg=succes";
        })
        .catch(error => {
            console.error("Error:", error);
        });
        </script>';
    }

    $zip->close();
} catch (Throwable $e) {
    echo "<h3 style='color:red'>Exception attrapée :</h3>";
    echo "<pre>" . htmlentities($e->getMessage()) . "\n\n" . htmlentities($e->getTraceAsString()) . "</pre>";
}

// -------------------------
// Fonction super-robust
// -------------------------
function read_xlsx_rows_superrobust($path, $preferredSheetName = 'Fixtures')
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new Exception("Impossible d'ouvrir le xlsx avec ZipArchive.");

    // sharedStrings
    $shared = [];
    if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $sxml = @simplexml_load_string($zip->getFromIndex($idx));
        if ($sxml !== false) {
            foreach ($sxml->si as $si) {
                $txt = '';
                if (isset($si->t)) $txt = (string)$si->t;
                else foreach ($si->xpath('.//t') as $t) $txt .= (string)$t;
                $shared[] = $txt;
            }
        }
    }

    // workbook + rels
    $wbXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $relsXml = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));

    $relsNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $sheetNameToRid = [];
    foreach ($wbXml->sheets->sheet as $s) {
        $name = (string)$s['name'];
        $attr = $s->attributes($relsNs);
        $rId = $attr ? (string)$attr['id'] : '';
        $sheetNameToRid[$name] = $rId;
    }

    $ridToTarget = [];
    foreach ($relsXml->Relationship as $rel) {
        $id = (string)$rel['Id'];
        $target = ltrim((string)$rel['Target'], '/');
        $ridToTarget[$id] = $target;
    }

    $pname = trim($preferredSheetName);
    $targetPath = null;

    if (isset($sheetNameToRid[$pname]) && isset($ridToTarget[$sheetNameToRid[$pname]])) {
        $target = $ridToTarget[$sheetNameToRid[$pname]];
        // enlève un éventuel slash au début
        $targetPath = ltrim($target, '/');
    }

    // DEBUG
    echo "<pre>";
    echo "Preferred sheet: $pname\n";
    echo "Target trouvé dans workbook.xml.rels: " . ($targetPath ?? 'NON TROUVÉ') . "\n";
    echo "</pre>";

    // Si pas de target trouvé
    if (!$targetPath) {
        throw new Exception("Impossible de trouver le chemin de la feuille '$pname' dans workbook.xml.rels");
    }

    // Correction chemin : supprimer ./ au début, enlever / au début
    $targetPath = ltrim($targetPath, '/');
    $targetPath = preg_replace('#^xl/#', '', $targetPath); // sécurité

    // Vérifier si le chemin existe dans le ZIP
    if ($zip->locateName($targetPath) === false) {
        // Essayer avec "xl/" devant (certains fichiers l'ont)
        $altPath = "xl/$targetPath";
        if ($zip->locateName($altPath) !== false) {
            $targetPath = $altPath;
        } else {
            // Dernier fallback : chercher un fichier sheet*.xml
            $candidates = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (preg_match('#xl/worksheets/sheet\d+\.xml$#', $stat['name'])) {
                    $candidates[] = $stat['name'];
                }
            }
            echo "⚠️ Aucun match direct, candidats possibles :\n";
            print_r($candidates);
            throw new Exception("Feuille '$pname' introuvable dans l'archive (targetPath=$targetPath)");
        }
    }

    // Lecture du contenu
    $xmlContent = $zip->getFromName($targetPath);
    if ($xmlContent === false) {
        throw new Exception("Impossible de lire le contenu de $targetPath");
    }

    $sheetXml = simplexml_load_string($xmlContent);
    if (!$sheetXml) {
        throw new Exception("Erreur XML lors du chargement de $targetPath");
    }

    // Extraction des lignes
    $rows = [];
    foreach ($sheetXml->sheetData->row as $row) {
        $r = [];
        foreach ($row->c as $c) {
            $t = (string)$c['t'];
            $v = (string)$c->v;
            if ($t === 's') {
                $idx = intval($v);
                $val = $shared[$idx] ?? $v;
            } elseif ($t === 'inlineStr') {
                $val = (string)$c->is->t;
            } else {
                $val = $v;
            }
            $r[] = $val;
        }
        $rows[] = $r;
    }

    $zip->close();
    return $rows;
}
/**
 * Store match rows into the matches table.
 *
 * @param PDO $pdo
 * @param array $rows Each $row is a numeric-indexed array: [week, team, opponent, points_for, points_against, ...]
 * @param bool $create_missing_teams If true, missing teams will be inserted into teams table. Default: false.
 * @return array summary with counts and errors
 * @throws Exception on fatal DB error
 */
function store_matches(PDO $pdo, array $rows, bool $create_missing_teams = false): array
{
    // ensure PDO throws exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // helper to normalize a team name for case-insensitive matching
    $normalize = function (string $s): string {
        // trim and reduce multiple spaces, leave punctuation intact
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_strtolower($s);
    };

    // Load all teams into memory mapping normalized_name => id
    $teamMap = [];
    $stmt = $pdo->query("SELECT id, name FROM teams");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teamMap[$normalize($row['name'])] = (int)$row['id'];
    }

    // prepared statements
    $insertTeamStmt = $pdo->prepare("INSERT INTO teams (name) VALUES (:name)");
    $findMatchStmt = $pdo->prepare("SELECT id FROM matches WHERE week = :week AND home_team_id = :home AND away_team_id = :away LIMIT 1");
    $insertMatchStmt = $pdo->prepare("
        INSERT INTO matches (week, home_team_id, away_team_id, home_goals, away_goals, status)
        VALUES (:week, :home, :away, :home_goals, :away_goals, :status)
    ");

    $summary = [
        'rows_total' => count($rows),
        'inserted' => 0,
        'skipped_existing' => 0,
        'skipped_invalid' => 0,
        'errors' => []
    ];

    try {
        $pdo->beginTransaction();

        foreach ($rows as $idx => $r) {
            // Expect numeric-indexed array; require at least 5 columns
            if (!is_array($r) || count($r) < 5) {
                $summary['skipped_invalid']++;
                $summary['errors'][] = "Row {$idx} invalid format or too few columns.";
                continue;
            }

            // Parse fields by position:
            // 0 => GW (week), 1 => Team (home), 2 => Opponent (away), 3 => Points For (home_goals), 4 => Points Against (away_goals)
            $weekRaw = trim((string)$r[0]);
            // skip header rows where week is not numeric, e.g. 'GW'
            if (!is_numeric($weekRaw)) {
                // it's a header or otherwise invalid row
                continue;
            }
            $week = (int)$weekRaw;

            global $verification;
            $verification = true;
            if (!$verification) {
                // Vérifier si la semaine existe déjà
                $checkWeekStmt = $pdo->prepare("SELECT id FROM matches WHERE week = :week LIMIT 1");
                $checkWeekStmt->execute(['week' => $week]);
                $existingWeek = $checkWeekStmt->fetch();

                if ($existingWeek) {
                    // Si la semaine existe → renvoyer un script JS qui redirige
                    echo "<script>
        window.location.href = '../index.html?msg=exist';
    </script>";
                    exit;
                }
                $verification = true;
            }

            $teamRaw = trim((string)$r[1]);
            $oppRaw  = trim((string)$r[2]);
            global $simana;
            $simana = $week;
            if ($teamRaw === '' || $oppRaw === '') {
                $summary['skipped_invalid']++;
                $summary['errors'][] = "Row {$idx} missing team/opponent.";
                continue;
            }
            /*             $last = (count($rows) + 1) / 2;
            if ($idx >= $last) break; */
            $tens = floor(($idx - 1) / 10) % 10;
            if ($tens % 2 == 1) {
                continue;
            }
            echo '<h1> id: ' . $idx . '</h1>';
            // extract integer from points strings (handles extraneous chars)
            $extractInt = function ($s) {
                if (preg_match('/-?\d+/', (string)$s, $m)) {
                    return (int)$m[0];
                }
                return null;
            };

            $homeGoals = $extractInt($r[3]);
            $awayGoals = $extractInt($r[4]);

            if ($homeGoals === null || $awayGoals === null) {
                $summary['skipped_invalid']++;
                $summary['errors'][] = "Row {$idx} cannot parse goals: '{$r[3]}','{$r[4]}'.";
                continue;
            }

            // find or create team ids
            $nteam = $normalize($teamRaw);
            $nopp  = $normalize($oppRaw);

            // helper to get id, optionally create
            $getOrCreateId = function (string $normName, string $originalName) use (&$teamMap, $pdo, $insertTeamStmt, $create_missing_teams, $normalize) {
                if (isset($teamMap[$normName])) {
                    return $teamMap[$normName];
                }
                if (!$create_missing_teams) {
                    return null;
                }
                // create new team
                $insertTeamStmt->execute([':name' => $originalName]);
                $newId = (int)$pdo->lastInsertId();
                // store mapping; normalized by the database-stored name (originalName is used)
                $teamMap[$normalize($originalName)] = $newId;
                return $newId;
            };

            $homeId = $getOrCreateId($nteam, $teamRaw);
            $awayId = $getOrCreateId($nopp, $oppRaw);

            if (!$homeId || !$awayId) {
                $summary['skipped_invalid']++;
                $summary['errors'][] = "Row {$idx}: team '{$teamRaw}' or opponent '{$oppRaw}' not found in teams table.";
                continue;
            }

            // skip if identical match already exists
            $findMatchStmt->execute([':week' => $week, ':home' => $homeId, ':away' => $awayId]);
            if ($findMatchStmt->fetchColumn()) {
                $summary['skipped_existing']++;
                continue;
            }

            // insert match (status = 'completed' because goals are present)
            $insertMatchStmt->execute([
                ':week' => $week,
                ':home' => $homeId,
                ':away' => $awayId,
                ':home_goals' => $homeGoals,
                ':away_goals' => $awayGoals,
                ':status' => 'completed'
            ]);
            $summary['inserted']++;
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $summary;
}
