<?php
// read_xlsx_rows_robust.php  (remplace ta fonction existante)
function read_xlsx_rows($path, $sheetName = 'Fixtures') {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception("Impossible d'ouvrir le fichier xlsx ($path) avec ZipArchive");
    }

    // sharedStrings (optionnel)
    $shared = [];
    if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $sxml = @simplexml_load_string($zip->getFromIndex($idx));
        if ($sxml !== false) {
            foreach ($sxml->si as $si) {
                $txt = '';
                if (isset($si->t)) $txt = (string)$si->t;
                else {
                    foreach ($si->xpath('.//t') as $t) $txt .= (string)$t;
                }
                $shared[] = $txt;
            }
        }
    }

    // workbook.xml
    $wbXml = null;
    if (($ix = $zip->locateName('xl/workbook.xml')) !== false) {
        $wbXml = @simplexml_load_string($zip->getFromIndex($ix));
        if ($wbXml === false) {
            $zip->close();
            throw new Exception("Erreur: xl/workbook.xml illisible.");
        }
    } else {
        $zip->close();
        throw new Exception("Erreur: xl/workbook.xml absent.");
    }

    // map sheet name -> r:id
    $sheetNameToRid = [];
    foreach ($wbXml->sheets->sheet as $s) {
        $name = (string)$s['name'];
        $rId = '';
        // try relationships namespace first
        $relsNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $attr = $s->attributes($relsNs);
        if ($attr && isset($attr['id'])) $rId = (string)$attr['id'];
        else {
            // fallback: search any attribute that contains 'id'
            foreach ($s->attributes() as $k => $v) {
                if (stripos($k, 'id') !== false) { $rId = (string)$v; break; }
            }
        }
        $sheetNameToRid[trim($name)] = $rId;
    }

    // read rels to map rId -> target
    $ridToTarget = [];
    if (($ix = $zip->locateName('xl/_rels/workbook.xml.rels')) !== false) {
        $relsXml = @simplexml_load_string($zip->getFromIndex($ix));
        if ($relsXml !== false) {
            foreach ($relsXml->Relationship as $rel) {
                $id = (string)$rel['Id'];
                $target = (string)$rel['Target'];
                $ridToTarget[$id] = $target;
            }
        }
    }

    // determine target path
    $targetPath = null;
    if (isset($sheetNameToRid[$sheetName]) && $sheetNameToRid[$sheetName] !== '' && isset($ridToTarget[$sheetNameToRid[$sheetName]])) {
        $targetPath = 'xl/' . ltrim($ridToTarget[$sheetNameToRid[$sheetName]], '/');
    } else {
        // fallback: try to find first worksheets/* in rels
        foreach ($ridToTarget as $t) {
            if (stripos($t, 'worksheets/') !== false) { $targetPath = 'xl/' . ltrim($t, '/'); break; }
        }
        // ultimate fallback: common default
        $candidates = ['xl/worksheets/sheet1.xml','xl/worksheets/sheet.xml'];
        foreach ($candidates as $c) if ($zip->locateName($c) !== false) { $targetPath = $c; break; }
    }

    if ($targetPath === null || $zip->locateName($targetPath) === false) {
        $zip->close();
        throw new Exception("Feuille '{$sheetName}' introuvable (targetPath null ou absent).");
    }

    $sheetXml = @simplexml_load_string($zip->getFromName($targetPath));
    if ($sheetXml === false) {
        $zip->close();
        throw new Exception("Impossible de parser le XML de la feuille ($targetPath).");
    }

    // namespace
    $ns = ['ns' => 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'];
    $rows = [];
    $allCols = [];
    foreach ($sheetXml->sheetData->row as $row) {
        $rowCells = [];
        foreach ($row->c as $c) {
            $coord = (string)$c['r'];
            if (!preg_match('/^([A-Z]+)(\d+)$/', $coord, $m)) continue;
            $col = $m[1];
            $t = isset($c['t']) ? (string)$c['t'] : '';
            $v = isset($c->v) ? (string)$c->v : '';
            $val = '';
            if ($t === 's') {
                $idx = intval($v);
                $val = isset($shared[$idx]) ? $shared[$idx] : $v;
            } elseif ($t === 'inlineStr') {
                // inline string store in <is><t>...
                $val = '';
                foreach ($c->is->t as $tt) $val .= (string)$tt;
            } else {
                $val = $v;
            }
            $rowCells[$col] = trim(str_replace("\xC2\xA0", ' ', $val));
            $allCols[$col] = true;
        }
        $rows[] = $rowCells;
    }

    if (empty($rows)) {
        $zip->close();
        // no rows found
        return [];
    }

    // build full ordered columns A..max
    $maxIndex = 0;
    foreach (array_keys($allCols) as $c) {
        $n = 0;
        $s = $c;
        for ($i=0;$i<strlen($s);$i++) $n = $n*26 + (ord($s[$i]) - 64);
        if ($n > $maxIndex) $maxIndex = $n;
    }
    $ordered = [];
    for ($i=1;$i<=$maxIndex;$i++){
        $col='';
        $num = $i;
        while ($num>0) {
            $mod = ($num-1)%26;
            $col = chr(65 + $mod) . $col;
            $num = intval(($num - $mod -1)/26);
        }
        $ordered[] = $col;
    }

    // transform rows to arrays indexed by column letters and return
    $out=[];
    foreach ($rows as $r) {
        $rowAssoc = [];
        foreach ($ordered as $col) $rowAssoc[$col] = isset($r[$col]) ? $r[$col] : '';
        $out[] = $rowAssoc;
    }

    $zip->close();
    return $out;
}
