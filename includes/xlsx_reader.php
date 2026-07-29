<?php
// Lecteur .xlsx minimaliste (sans dépendance externe) : lit la première
// feuille d'un classeur Excel et renvoie un tableau de lignes (tableaux de
// cellules texte). Suffisant pour importer une liste de questions simple.

function xlsx_to_rows($filePath) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException("Impossible d'ouvrir le fichier .xlsx");
    }

    // ---- Chaînes partagées ----
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sx = new SimpleXMLElement($sharedXml);
        foreach ($sx->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string)$si->t;
            } else {
                // Cas des textes avec runs multiples (<r><t>...</t></r>)
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
                $sharedStrings[] = $text;
            }
        }
    }

    // ---- Première feuille ----
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        throw new RuntimeException('Feuille de calcul introuvable dans le classeur');
    }
    $sheet = new SimpleXMLElement($sheetXml);
    $zip->close();

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        $colIndex = 0;
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $targetIndex = $ref !== '' ? col_letters_to_index($ref) : $colIndex;
            while ($colIndex < $targetIndex) {
                $cells[$colIndex] = '';
                $colIndex++;
            }

            $type = (string)$c['t'];
            $value = isset($c->v) ? (string)$c->v : '';

            if ($type === 's') {
                $value = $sharedStrings[(int)$value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($c->is->t) ? (string)$c->is->t : '';
            }

            $cells[$colIndex] = $value;
            $colIndex++;
        }
        $rows[] = $cells;
    }

    return $rows;
}

function col_letters_to_index($ref) {
    preg_match('/^([A-Z]+)/', $ref, $m);
    $letters = $m[1] ?? 'A';
    $index = 0;
    foreach (str_split($letters) as $ch) {
        $index = $index * 26 + (ord($ch) - ord('A') + 1);
    }
    return $index - 1;
}
