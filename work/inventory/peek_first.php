<?php
require __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpWord\IOFactory;
$phpWord = IOFactory::load(__DIR__ . '/../../testing-documents/التشغيل-والصيانة.docx');
$count = 0;
foreach ($phpWord->getSections() as $section) {
    foreach ($section->getElements() as $el) {
        $cls = preg_replace('/^.*\\\\/','',get_class($el));
        $info = '';
        if (method_exists($el, 'getParagraphStyle')) {
            $ps = $el->getParagraphStyle();
            $name = is_object($ps) && method_exists($ps, 'getStyleName') ? $ps->getStyleName() : '';
            $info .= " pStyle=$name";
        }
        if ($el instanceof \PhpOffice\PhpWord\Element\Title) $info .= ' depth='.$el->getDepth().' text='.json_encode(is_string($el->getText())?$el->getText(): '['.preg_replace('/^.*\\\\/','',get_class($el->getText())).']');
        if ($el instanceof \PhpOffice\PhpWord\Element\TextRun || $el instanceof \PhpOffice\PhpWord\Element\ListItemRun) {
            $kids = [];
            foreach ($el->getElements() as $sub) {
                $kc = preg_replace('/^.*\\\\/','',get_class($sub));
                if ($sub instanceof \PhpOffice\PhpWord\Element\Text) $kc .= '('.mb_substr($sub->getText(),0,15).')';
                $kids[] = $kc;
            }
            $info .= ' children=['.implode(',', array_slice($kids, 0, 8)).(count($kids)>8?',...':'').']';
        }
        echo str_pad($count, 3, ' ', STR_PAD_LEFT) ." $cls$info\n";
        if (++$count >= 30) exit;
    }
}
