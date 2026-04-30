<?php
require __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpWord\IOFactory;
$phpWord = IOFactory::load(__DIR__ . '/../../testing-documents/التشغيل-والصيانة.docx');
$count = 0;
foreach ($phpWord->getSections() as $section) {
    foreach ($section->getElements() as $el) {
        if ($el instanceof \PhpOffice\PhpWord\Element\TextRun) {
            $ps = $el->getParagraphStyle();
            $name = is_object($ps) && method_exists($ps, 'getStyleName') ? $ps->getStyleName() : '';
            if (in_array($name, ['TOC1','TOC2','TOC3','TOCHeading'])) {
                echo "==== TextRun (pStyle=$name) ====\n";
                foreach ($el->getElements() as $sub) {
                    $cls = preg_replace('/^.*\\\\/','',get_class($sub));
                    $extra = '';
                    if ($sub instanceof \PhpOffice\PhpWord\Element\Text) $extra = json_encode(mb_substr($sub->getText(), 0, 40));
                    if ($sub instanceof \PhpOffice\PhpWord\Element\Link) $extra = 'src='.$sub->getSource().' internal='.($sub->isInternal()?'1':'0').' text='.json_encode($sub->getText());
                    echo "  - $cls $extra\n";
                }
                if (++$count >= 3) exit;
            }
        }
    }
}
