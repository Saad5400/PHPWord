<?php
// Combined PHPWord + raw-XML feature inventory for the test document.
// Reads via PHPWord, then probes the raw word/document.xml for things
// PHPWord drops (field codes, SDTs, page borders, run-level shading, etc.)
require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;

$docx = __DIR__ . '/../../testing-documents/التشغيل-والصيانة.docx';

// ---------- PHPWord-side ----------
$phpWord = IOFactory::load($docx);
$ctx = [
    'types' => [],
    'titleDepths' => [],
    'paragraphStyles' => [],
    'listParagraphStyles' => [],
    'listDepths' => [],
    'listFmts' => [],
    'tableSizes' => [],
    'tableMerges' => [],
    'fontStyles' => [],
    'colors' => [],
    'links' => ['internal' => 0, 'external' => 0, 'samples_internal' => [], 'samples_external' => []],
];

function describePStyle($s): string {
    if (!is_object($s)) return '(none)';
    $bits = [];
    foreach (['getStyleName', 'getAlignment'] as $m) {
        if (method_exists($s, $m)) {
            $v = $s->$m();
            if ($v) $bits[] = "$m=$v";
        }
    }
    return implode(' ', $bits) ?: '(empty)';
}

function walk($el, array &$ctx) {
    $cls = preg_replace('/^.*\\\\/', '', get_class($el));
    $ctx['types'][$cls] = ($ctx['types'][$cls] ?? 0) + 1;

    if ($el instanceof \PhpOffice\PhpWord\Element\Title) {
        $d = $el->getDepth();
        $ctx['titleDepths'][$d] = ($ctx['titleDepths'][$d] ?? 0) + 1;
    }
    if ($el instanceof \PhpOffice\PhpWord\Element\TextRun) {
        $key = describePStyle($el->getParagraphStyle());
        $ctx['paragraphStyles'][$key] = ($ctx['paragraphStyles'][$key] ?? 0) + 1;
    }
    if ($el instanceof \PhpOffice\PhpWord\Element\ListItemRun) {
        $key = describePStyle($el->getParagraphStyle());
        $ctx['listParagraphStyles'][$key] = ($ctx['listParagraphStyles'][$key] ?? 0) + 1;
        $depth = (int) $el->getDepth();
        $ctx['listDepths'][$depth] = ($ctx['listDepths'][$depth] ?? 0) + 1;
        $st = $el->getStyle();
        if (is_object($st)) {
            $numStyle = method_exists($st, 'getNumStyle') ? $st->getNumStyle() : null;
            $numId = method_exists($st, 'getNumId') ? $st->getNumId() : null;
            $ctx['listFmts'][($numStyle ?? '?') . " (numId=$numId)"] =
                ($ctx['listFmts'][($numStyle ?? '?') . " (numId=$numId)"] ?? 0) + 1;
        }
    }
    if ($el instanceof \PhpOffice\PhpWord\Element\Table) {
        $r = 0; $c = 0; $merges = 0;
        foreach ($el->getRows() as $row) {
            $r++;
            foreach ($row->getCells() as $cell) {
                $c++;
                $cs = $cell->getStyle();
                if ($cs && method_exists($cs, 'getGridSpan') && $cs->getGridSpan() && $cs->getGridSpan() > 1) {
                    $merges++;
                }
                foreach ($cell->getElements() as $sub) walk($sub, $ctx);
            }
        }
        $ctx['tableSizes'][] = $r . 'x' . $c;
        $ctx['tableMerges'][] = $merges;
    }
    if ($el instanceof \PhpOffice\PhpWord\Element\Link) {
        if ($el->isInternal()) {
            $ctx['links']['internal']++;
            if (count($ctx['links']['samples_internal']) < 3) $ctx['links']['samples_internal'][] = $el->getSource();
        } else {
            $ctx['links']['external']++;
            if (count($ctx['links']['samples_external']) < 3) $ctx['links']['samples_external'][] = $el->getSource();
        }
    }
    if ($el instanceof \PhpOffice\PhpWord\Element\Text) {
        $f = $el->getFontStyle();
        if (is_object($f) && method_exists($f, 'getColor')) {
            $col = $f->getColor();
            if ($col && strtolower($col) !== 'auto') $ctx['colors'][$col] = ($ctx['colors'][$col] ?? 0) + 1;
        }
    }
    if ($el instanceof \PhpOffice\PhpWord\Element\TextRun || $el instanceof \PhpOffice\PhpWord\Element\ListItemRun) {
        // recurse into inline children too, so we count Links and Texts
        if (method_exists($el, 'getElements')) {
            foreach ($el->getElements() as $sub) walk($sub, $ctx);
        }
        return;
    }
    if (method_exists($el, 'getElements')) {
        foreach ($el->getElements() as $sub) walk($sub, $ctx);
    }
}

foreach ($phpWord->getSections() as $section) {
    foreach ($section->getElements() as $el) walk($el, $ctx);
}

echo "## PHPWord element counts\n";
arsort($ctx['types']);
foreach ($ctx['types'] as $k => $v) printf("  %-20s %d\n", $k, $v);

echo "\n## Title depths (PHPWord Title element only — most headings come through as ListItemRun with Heading style)\n";
foreach ($ctx['titleDepths'] as $k => $v) printf("  depth=%d  %d\n", $k, $v);

echo "\n## Paragraph styles seen on TextRun\n";
arsort($ctx['paragraphStyles']);
foreach ($ctx['paragraphStyles'] as $k => $v) printf("  %-70s %d\n", substr($k, 0, 70), $v);

echo "\n## Paragraph styles seen on ListItemRun\n";
arsort($ctx['listParagraphStyles']);
foreach ($ctx['listParagraphStyles'] as $k => $v) printf("  %-70s %d\n", substr($k, 0, 70), $v);

echo "\n## ListItemRun depths\n";
ksort($ctx['listDepths']);
foreach ($ctx['listDepths'] as $k => $v) printf("  depth=%d  %d\n", $k, $v);

echo "\n## numbering refs used (top 20)\n";
arsort($ctx['listFmts']);
foreach (array_slice($ctx['listFmts'], 0, 20, true) as $k => $v) printf("  %-50s %d\n", $k, $v);

echo "\n## Tables ({$ctx['types']['Table']}): rows×cells (cells>cols when merges exist)\n";
foreach ($ctx['tableSizes'] as $i => $s) {
    printf("  Table %d: %-10s   gridSpan-merges=%d\n", $i+1, $s, $ctx['tableMerges'][$i]);
}

echo "\n## Hyperlinks via PHPWord Link element\n";
echo "  internal: {$ctx['links']['internal']}  external: {$ctx['links']['external']}\n";
echo "  samples internal: " . implode(', ', $ctx['links']['samples_internal']) . "\n";
echo "  samples external: " . implode(', ', $ctx['links']['samples_external']) . "\n";

echo "\n## Run colors (top 10)\n";
arsort($ctx['colors']);
foreach (array_slice($ctx['colors'], 0, 10, true) as $c => $n) printf("  #%-8s %d\n", $c, $n);

// ---------- raw XML probe ----------
$zip = new ZipArchive();
$zip->open($docx);
$xml = $zip->getFromName('word/document.xml');

echo "\n## Raw document.xml feature counts\n";
$probes = [
    'paragraphs' => '/<w:p[ >]/',
    'tables' => '/<w:tbl>/',
    'rows' => '/<w:tr[ >]/',
    'cells' => '/<w:tc>/',
    'gridSpan (col-merge)' => '/<w:gridSpan /',
    'vMerge (row-merge)' => '/<w:vMerge/',
    'hyperlinks' => '/<w:hyperlink/',
    'bookmarks' => '/<w:bookmarkStart/',
    'page-break runs (br type=page)' => '/<w:br w:type="page"/',
    'page-break-before (pPr)' => '/<w:pageBreakBefore/',
    'paragraph borders pBdr' => '/<w:pBdr>/',
    'shading shd' => '/<w:shd /',
    'tabs definitions' => '/<w:tabs>/',
    'tab characters in runs' => '/<w:tab\/>/',
    'highlight' => '/<w:highlight/',
    'indentation' => '/<w:ind /',
    'numId references' => '/<w:numId /',
    'ilvl references' => '/<w:ilvl /',
    'rtl runs' => '/<w:rtl\/>/',
    'bidi paragraphs' => '/<w:bidi\/>/',
    'sectPr (sections)' => '/<w:sectPr/',
    'headerReference' => '/<w:headerReference/',
    'footerReference' => '/<w:footerReference/',
    'sdt (content control)' => '/<w:sdt>/',
    'fldSimple (simple field)' => '/<w:fldSimple/',
    'fldChar (complex field)' => '/<w:fldChar/',
    'instrText (field code)' => '/<w:instrText/',
    'footnoteReference' => '/<w:footnoteReference/',
    'endnoteReference' => '/<w:endnoteReference/',
    'commentReference' => '/<w:commentReference/',
    'tracked-change ins' => '/<w:ins[ >]/',
    'tracked-change del' => '/<w:del[ >]/',
    'drawings (DrawingML)' => '/<w:drawing[ >]/',
    'pict (legacy VML)' => '/<w:pict[ >]/',
    'OLE objects' => '/<w:object[ >]/',
    'AlternateContent' => '/<mc:AlternateContent/',
    'mathML m:oMath' => '/<m:oMath[ >]/',
    'caps (uppercase)' => '/<w:caps[ \/]/',
    'smallCaps' => '/<w:smallCaps[ \/]/',
    'strike' => '/<w:strike[ \/]/',
    'dstrike (double-strike)' => '/<w:dstrike[ \/]/',
    'underline u' => '/<w:u w:val=/',
    'vertAlign (sup/sub)' => '/<w:vertAlign /',
    'rStyle (char style)' => '/<w:rStyle /',
    'pStyle (para style)' => '/<w:pStyle /',
    'bidiVisual (RTL table)' => '/<w:bidiVisual/',
    'titlePg (different first-page header)' => '/<w:titlePg/',
];
foreach ($probes as $label => $rx) {
    preg_match_all($rx, $xml, $m);
    printf("  %-40s %d\n", $label, count($m[0]));
}

echo "\n## Distinct pStyle values (in document.xml)\n";
preg_match_all('/<w:pStyle w:val="([^"]+)"\/>/', $xml, $m);
$counts = array_count_values($m[1]);
arsort($counts);
foreach ($counts as $k => $v) printf("  %-25s %d\n", $k, $v);

echo "\n## Distinct rStyle values\n";
preg_match_all('/<w:rStyle w:val="([^"]+)"\/>/', $xml, $m);
$counts = array_count_values($m[1]);
arsort($counts);
foreach ($counts as $k => $v) printf("  %-25s %d\n", $k, $v);

echo "\n## Distinct jc (justification) values\n";
preg_match_all('/<w:jc w:val="([^"]+)"\/>/', $xml, $m);
$counts = array_count_values($m[1]);
arsort($counts);
foreach ($counts as $k => $v) printf("  %-25s %d\n", $k, $v);

echo "\n## Distinct field instructions\n";
preg_match_all('/<w:instrText[^>]*>([^<]*)<\/w:instrText>/', $xml, $m);
$norm = array_map(fn($s) => trim(preg_replace('/_Toc\d+|\b\d+\b/', '#', $s)), $m[1]);
$counts = array_count_values($norm);
arsort($counts);
foreach ($counts as $k => $v) printf("  %-50s %d\n", substr($k, 0, 50), $v);

// ---------- header / footer / footnotes summary ----------
echo "\n## Header / footer parts present\n";
foreach ($zip->numFiles ? range(0, $zip->numFiles - 1) : [] as $i) {
    $name = $zip->getNameIndex($i);
    if (preg_match('#^word/(header|footer|footnotes|endnotes)\d*\.xml$#', $name)) {
        $size = $zip->statIndex($i)['size'];
        $body = $zip->getFromName($name);
        $textCount = preg_match_all('/<w:t[ >][^<]*<\/w:t>/', $body);
        $drawings = preg_match_all('/<w:drawing[ >]/', $body);
        $tables = preg_match_all('/<w:tbl>/', $body);
        printf("  %-25s %6d B  texts=%d drawings=%d tables=%d\n", $name, $size, $textCount, $drawings, $tables);
    }
}

echo "\n## Embedded fonts (word/fonts/*.odttf)\n";
$ff = 0;
foreach (range(0, $zip->numFiles - 1) as $i) {
    $name = $zip->getNameIndex($i);
    if (str_starts_with($name, 'word/fonts/')) $ff++;
}
echo "  count: $ff (these are obfuscated embedded subsets, not relevant for converter content but explain why styles request specific font names)\n";

$zip->close();
