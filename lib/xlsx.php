<?php
function readXlsx($filePath) {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return [];
    }
    
    $xmlString = $zip->getFromName('xl/sharedStrings.xml');
    $sharedStrings = [];
    if ($xmlString) {
        $xml = simplexml_load_string($xmlString);
        if ($xml !== false) {
            foreach ($xml->si as $si) {
                $sharedStrings[] = (string)$si->t;
            }
        }
    }
    
    $xmlString = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    
    if (!$xmlString) {
        return [];
    }
    
    $xml = simplexml_load_string($xmlString);
    if ($xml === false) {
        return [];
    }
    
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        foreach ($row->c as $cell) {
            $v = (string)$cell->v;
            $attr = $cell->attributes();
            $type = (string)$attr['t'];
            if ($type == 's' && isset($sharedStrings[$v])) {
                $rowData[] = $sharedStrings[$v];
            } else {
                $rowData[] = $v;
            }
        }
        $rows[] = $rowData;
    }
    
    return $rows;
}
?>