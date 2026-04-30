<?php
// Walk the loaded PHPWord document and dump element types + key attrs to understand
// what's available so we can build the better converter.
require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

$src = __DIR__ . '/../../testing-documents/التشغيل-والصيانة.docx';

$phpWord = IOFactory::load($src);

$typeCounts = [];
$listStyles = [];   // numbering refs / counts
$headingStyles = [];
$paragraphStyles = [];
$fontStyles = [];

function describeStyle($s) {
    if (!$s) return '(null)';
    if (is_string($s)) return 'string:' . $s;
    if (is_object($s)) {
        $cls = get_class($s);
        $bits = [$cls];
        foreach (['getStyleName','getName','getAlignment','getAlign'] as $m) {
            if (method_exists($s, $m)) {
                $v = $s->$m();
                if ($v) $bits[] = "$m=" . (is_string($v) ? $v : json_encode($v));
            }
        }
        return implode(' ', $bits);
    }
    return gettype($s);
}

function walk($element, &$ctx, $depth = 0) {
    $cls = get_class($element);
    $short = preg_replace('/^.*\\\\/', '', $cls);
    $ctx['types'][$short] = ($ctx['types'][$short] ?? 0) + 1;

    if ($element instanceof \PhpOffice\PhpWord\Element\Title) {
        $d = $element->getDepth();
        $ctx['titleDepths'][$d] = ($ctx['titleDepths'][$d] ?? 0) + 1;
    }
    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
        $ps = $element->getParagraphStyle();
        $key = describeStyle($ps);
        $ctx['paragraphStyles'][$key] = ($ctx['paragraphStyles'][$key] ?? 0) + 1;
    }
    if ($element instanceof \PhpOffice\PhpWord\Element\ListItemRun) {
        $ps = $element->getParagraphStyle();
        $key = describeStyle($ps);
        $ctx['listParagraphStyles'][$key] = ($ctx['listParagraphStyles'][$key] ?? 0) + 1;
        if (method_exists($element, 'getDepth')) {
            $ctx['listDepths'][$element->getDepth()] = ($ctx['listDepths'][$element->getDepth()] ?? 0) + 1;
        }
        if (method_exists($element, 'getStyle')) {
            $st = $element->getStyle();
            if (is_object($st)) {
                $bits = [];
                foreach (['getNumStyle','getStyleName','getListType','getNumLevel','getNumId'] as $m) {
                    if (method_exists($st, $m)) {
                        $v = $st->$m();
                        if ($v !== null) $bits[] = "$m=" . (is_string($v)||is_int($v)||is_float($v) ? $v : json_encode($v));
                    }
                }
                $ctx['listStyles'][implode(' ', $bits) ?: '(empty)'] = ($ctx['listStyles'][implode(' ', $bits) ?: '(empty)'] ?? 0) + 1;
            } else {
                $ctx['listStyles']['raw:'.json_encode($st)] = ($ctx['listStyles']['raw:'.json_encode($st)] ?? 0) + 1;
            }
        }
    }
    if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
        $ctx['tableCount']++;
        $rowCount = 0; $cellCount = 0;
        foreach ($element->getRows() as $row) {
            $rowCount++;
            foreach ($row->getCells() as $cell) {
                $cellCount++;
                foreach ($cell->getElements() as $sub) walk($sub, $ctx, $depth + 1);
            }
        }
        $ctx['tableRowCells'][] = "$rowCount x $cellCount";
    }
    // Recurse into containers
    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun || $element instanceof \PhpOffice\PhpWord\Element\ListItemRun) {
        // Already counted text content; skip recurse to avoid double counting
    } elseif (method_exists($element, 'getElements')) {
        foreach ($element->getElements() as $sub) {
            walk($sub, $ctx, $depth + 1);
        }
    }
}

$ctx = [
    'types' => [],
    'titleDepths' => [],
    'paragraphStyles' => [],
    'listParagraphStyles' => [],
    'listStyles' => [],
    'listDepths' => [],
    'tableCount' => 0,
    'tableRowCells' => [],
];

foreach ($phpWord->getSections() as $section) {
    foreach ($section->getElements() as $el) walk($el, $ctx);
}

echo "Element types:\n";
arsort($ctx['types']);
foreach ($ctx['types'] as $k => $v) printf("  %-25s %d\n", $k, $v);

echo "\nTitle depths:\n";
foreach ($ctx['titleDepths'] as $k => $v) printf("  depth=%d  %d\n", $k, $v);

echo "\nTables: " . $ctx['tableCount'] . "\n";
echo "Sample table sizes: " . implode(', ', array_slice($ctx['tableRowCells'], 0, 10)) . "\n";

echo "\nList depths:\n";
foreach ($ctx['listDepths'] as $k => $v) printf("  depth=%s  %d\n", $k, $v);

echo "\nTop list styles (first 15):\n";
arsort($ctx['listStyles']);
foreach (array_slice($ctx['listStyles'], 0, 15, true) as $k => $v) printf("  %-80s %d\n", $k, $v);

echo "\nTop paragraph styles on TextRun (first 15):\n";
arsort($ctx['paragraphStyles']);
foreach (array_slice($ctx['paragraphStyles'], 0, 15, true) as $k => $v) printf("  %-80s %d\n", substr($k, 0, 80), $v);

echo "\nTop paragraph styles on ListItemRun (first 10):\n";
arsort($ctx['listParagraphStyles']);
foreach (array_slice($ctx['listParagraphStyles'], 0, 10, true) as $k => $v) printf("  %-80s %d\n", substr($k, 0, 80), $v);

echo "\nNumbering definitions registered in document:\n";
$numbering = \PhpOffice\PhpWord\Style::getStyles();
$found = 0;
foreach ($numbering as $name => $st) {
    if ($st instanceof \PhpOffice\PhpWord\Style\Numbering) {
        $found++;
        echo "  $name: " . get_class($st) . "\n";
        if ($found > 5) { echo "  ... (more)\n"; break; }
    }
}
echo "Total numbering styles: $found\n";

echo "\nAll registered style names (count): " . count($numbering) . "\n";
$byClass = [];
foreach ($numbering as $name => $st) {
    $c = is_object($st) ? get_class($st) : gettype($st);
    $byClass[$c] = ($byClass[$c] ?? 0) + 1;
}
foreach ($byClass as $c => $n) echo "  " . preg_replace('/^.*\\\\/','',$c) . ": $n\n";
