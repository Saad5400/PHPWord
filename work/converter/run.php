<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/RawXmlIndex.php';
require __DIR__ . '/TipTapConverter.php';

$src = $argv[1] ?? __DIR__ . '/../../testing-documents/التشغيل-والصيانة.docx';
$out = $argv[2] ?? __DIR__ . '/../output/v2.json';

$start = microtime(true);
$conv = new \PhpWord\TipTap\TipTapConverter();
$doc = $conv->convertFile($src);
if ($conv->arabicIndicNumerals) {
    $doc = \PhpWord\TipTap\TipTapConverter::applyArabicIndicNumerals($doc);
}
$elapsed = microtime(true) - $start;

file_put_contents($out, json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$counts = [];
$walk = function ($n) use (&$walk, &$counts) {
    if (!is_array($n)) return;
    if (isset($n['type'])) $counts[$n['type']] = ($counts[$n['type']] ?? 0) + 1;
    if (isset($n['marks'])) foreach ($n['marks'] as $m) $counts['mark:'.$m['type']] = ($counts['mark:'.$m['type']] ?? 0) + 1;
    if (isset($n['content']) && is_array($n['content'])) foreach ($n['content'] as $c) $walk($c);
};
$walk($doc);
ksort($counts);

fprintf(STDERR, "Output: %s\n", $out);
fprintf(STDERR, "Elapsed: %.2fs\n", $elapsed);
fprintf(STDERR, "Top-level children: %d\n", count($doc['content']));
fprintf(STDERR, "Counts:\n");
foreach ($counts as $k => $v) fprintf(STDERR, "  %-25s %d\n", $k, $v);
