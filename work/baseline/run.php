<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/Converter.php';

$src = __DIR__ . '/../../testing-documents/التشغيل-والصيانة.docx';
$out = __DIR__ . '/../output/baseline.json';

$start = microtime(true);
$converter = new \Baseline\Converter();
$doc = $converter->convertFile($src);
$elapsed = microtime(true) - $start;

file_put_contents($out, json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$counts = [];
$walk = function($node) use (&$walk, &$counts) {
    if (!is_array($node)) return;
    if (isset($node['type'])) $counts[$node['type']] = ($counts[$node['type']] ?? 0) + 1;
    if (isset($node['marks'])) foreach ($node['marks'] as $m) $counts['mark:'.$m['type']] = ($counts['mark:'.$m['type']] ?? 0) + 1;
    if (isset($node['content']) && is_array($node['content'])) foreach ($node['content'] as $c) $walk($c);
};
$walk($doc);
ksort($counts);

echo "Output: $out\n";
echo "Elapsed: " . number_format($elapsed, 2) . "s\n";
echo "Top-level children: " . count($doc['content']) . "\n";
echo "Node/mark counts:\n";
foreach ($counts as $k => $v) echo sprintf("  %-25s %d\n", $k, $v);
