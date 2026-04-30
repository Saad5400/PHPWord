<?php
// Standalone copy of the existing CLM WordToTipTapConverter, stripped of framework dependencies,
// for baseline evaluation. Source: clm/app/Modules/Contracts/src/Services/WordToTipTapConverter.php

namespace Baseline;

use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;

class Converter
{
    public function convertFile($file): array
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException("File not found: {$file}");
        }
        $phpWord = IOFactory::load($file);

        $tipTapContent = ['type' => 'doc', 'content' => []];
        $sections = $phpWord->getSections();
        if (empty($sections)) {
            return $tipTapContent;
        }
        $section = $sections[0];
        $elements = $section->getElements();
        $tipTapContent['content'] = $this->processElements($elements);
        return $tipTapContent;
    }

    protected function processElements(array $elements): array
    {
        $result = [];
        $i = 0;
        $totalElements = count($elements);
        while ($i < $totalElements) {
            $element = $elements[$i];
            if ($element instanceof ListItemRun) {
                $listItems = [];
                while ($i < $totalElements && $elements[$i] instanceof ListItemRun) {
                    $listItems[] = $elements[$i];
                    $i++;
                }
                $list = $this->convertListItemsToList($listItems);
                if ($list) $result[] = $list;
            } else {
                $converted = $this->convertElement($element);
                if ($converted !== null) $result[] = $converted;
                $i++;
            }
        }
        return $result;
    }

    protected function convertElement($element): ?array
    {
        if ($element instanceof Title) return $this->convertTitle($element);
        if ($element instanceof TextRun) return $this->convertTextRun($element);
        if ($element instanceof ListItemRun) return $this->convertListItem($element);
        return null;
    }

    protected function convertTitle(Title $title): array
    {
        $depth = $title->getDepth();
        $level = min(max($depth, 1), 6);
        $titleText = $title->getText();
        $textAlign = null;
        $content = [];
        if (is_string($titleText)) {
            $content[] = ['type' => 'text', 'text' => $titleText];
        } elseif ($titleText instanceof TextRun) {
            $content = $this->convertTextRunContent($titleText);
            $textAlign = $this->getTextAlignment($titleText);
        }
        return [
            'type' => 'heading',
            'attrs' => ['level' => (int)$level, 'textAlign' => $textAlign],
            'content' => $content,
        ];
    }

    protected function convertTextRun(TextRun $textRun): array
    {
        $content = $this->convertTextRunContent($textRun);
        $textAlign = $this->getTextAlignment($textRun);
        return [
            'type' => 'paragraph',
            'attrs' => ['textAlign' => $textAlign],
            'content' => $content,
        ];
    }

    protected function convertTextRunContent(TextRun $textRun): array
    {
        $content = [];
        foreach ($textRun->getElements() as $element) {
            if ($element instanceof Text) {
                $textNode = $this->convertTextElement($element);
                if ($textNode) $content[] = $textNode;
            } elseif ($element instanceof TextBreak) {
                $content[] = ['type' => 'hardBreak'];
            }
        }
        return $this->mergeConsecutiveTextNodes($content);
    }

    protected function convertTextElement(Text $textElement): ?array
    {
        $text = $textElement->getText();
        if (empty($text)) return null;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $node = ['type' => 'text', 'text' => $text];
        $marks = $this->getTextMarks($textElement);
        if (!empty($marks)) $node['marks'] = $marks;
        return $node;
    }

    protected function convertListItemsToList(array $listItems): ?array
    {
        if (empty($listItems)) return null;
        $listType = 'bulletList';
        $listContent = [];
        foreach ($listItems as $listItem) {
            $itemContent = $this->convertListItemContent($listItem);
            if (!empty($itemContent)) {
                $listContent[] = ['type' => 'listItem', 'content' => $itemContent];
            }
        }
        if (empty($listContent)) return null;
        return ['type' => $listType, 'content' => $listContent];
    }

    protected function convertListItemContent(ListItemRun $listItem): array
    {
        $paragraphContent = [];
        foreach ($listItem->getElements() as $element) {
            if ($element instanceof Text) {
                $textNode = $this->convertTextElement($element);
                if ($textNode) $paragraphContent[] = $textNode;
            }
        }
        if (empty($paragraphContent)) return [];
        return [[
            'type' => 'paragraph',
            'attrs' => ['textAlign' => null],
            'content' => $this->mergeConsecutiveTextNodes($paragraphContent),
        ]];
    }

    protected function convertListItem(ListItemRun $listItem): array
    {
        $content = [];
        foreach ($listItem->getElements() as $element) {
            if ($element instanceof Text) {
                $textNode = $this->convertTextElement($element);
                if ($textNode) $content[] = $textNode;
            }
        }
        return ['type' => 'paragraph', 'attrs' => ['textAlign' => null], 'content' => $content];
    }

    protected function getTextAlignment(TextRun $textRun): ?string
    {
        $paragraphStyle = $textRun->getParagraphStyle();
        if ($paragraphStyle && is_object($paragraphStyle)) {
            if (method_exists($paragraphStyle, 'getAlignment')) $alignment = $paragraphStyle->getAlignment();
            elseif (method_exists($paragraphStyle, 'getAlign')) $alignment = $paragraphStyle->getAlign();
            else return null;
            return in_array($alignment, ['center','right','left','justify'], true) ? $alignment : null;
        }
        return null;
    }

    protected function getTextMarks(Text $textElement): array
    {
        $marks = [];
        $fontStyle = $textElement->getFontStyle();
        if (!$fontStyle || !is_object($fontStyle)) return $marks;

        $textStyleAttrs = ['color'=>null,'fontSize'=>null,'fontFamily'=>null,'lineHeight'=>null,'backgroundColor'=>null];
        $hasTextStyle = false;
        if (method_exists($fontStyle, 'getName') && $fontStyle->getName()) {
            $textStyleAttrs['fontFamily'] = $fontStyle->getName();
            $hasTextStyle = true;
        }
        if (method_exists($fontStyle, 'getSize') && $fontStyle->getSize()) {
            $textStyleAttrs['fontSize'] = $fontStyle->getSize().'px';
            $hasTextStyle = true;
        }
        if (method_exists($fontStyle, 'getColor') && $fontStyle->getColor()) {
            $color = $fontStyle->getColor();
            $hex = $this->convertColorToHex($color);
            if ($hex) { $textStyleAttrs['color'] = $hex; $hasTextStyle = true; }
        }
        if ($hasTextStyle) {
            $cleanAttrs = [];
            foreach ($textStyleAttrs as $k=>$v) {
                if ($v !== null) $cleanAttrs[$k] = $v;
            }
            if (!empty($cleanAttrs)) $marks[] = ['type'=>'textStyle','attrs'=>$cleanAttrs];
        }
        if (method_exists($fontStyle, 'isBold') && $fontStyle->isBold()) $marks[] = ['type'=>'bold'];
        if (method_exists($fontStyle, 'isItalic') && $fontStyle->isItalic()) $marks[] = ['type'=>'italic'];
        if (method_exists($fontStyle, 'getUnderline')) {
            $u = $fontStyle->getUnderline();
            if ($u && $u !== 'none') $marks[] = ['type'=>'underline'];
        }
        if (method_exists($fontStyle, 'isStrikethrough') && $fontStyle->isStrikethrough()) $marks[] = ['type'=>'strike'];
        return $marks;
    }

    protected function convertColorToHex(string $color): ?string
    {
        if (empty($color)) return null;
        if (str_starts_with($color, '#')) return $color;
        if (preg_match('/^[0-9a-fA-F]{6}$/', $color)) return '#'.$color;
        if (preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/i', $color, $m)) {
            return sprintf('#%02x%02x%02x', (int)$m[1], (int)$m[2], (int)$m[3]);
        }
        $named = ['black'=>'#000000','white'=>'#ffffff','red'=>'#ff0000','green'=>'#008000','blue'=>'#0000ff','yellow'=>'#ffff00'];
        $low = strtolower($color);
        if (isset($named[$low])) return $named[$low];
        if (preg_match('/^[0-9a-fA-F]+$/', $color)) {
            $color = str_pad($color, 6, '0', STR_PAD_LEFT);
            return '#'.substr($color, 0, 6);
        }
        return null;
    }

    protected function mergeConsecutiveTextNodes(array $nodes): array
    {
        if (empty($nodes)) return $nodes;
        $merged = [];
        $cur = null;
        foreach ($nodes as $node) {
            if ($node['type'] !== 'text') {
                if ($cur) { $merged[] = $cur; $cur = null; }
                $merged[] = $node;
                continue;
            }
            if ($cur && $this->canMerge($cur, $node)) $cur['text'] .= $node['text'];
            else { if ($cur) $merged[] = $cur; $cur = $node; }
        }
        if ($cur) $merged[] = $cur;
        return $merged;
    }

    protected function canMerge(array $a, array $b): bool
    {
        $ma = $a['marks'] ?? []; $mb = $b['marks'] ?? [];
        if (count($ma) !== count($mb)) return false;
        usort($ma, fn($x,$y)=>$x['type']<=>$y['type']);
        usort($mb, fn($x,$y)=>$x['type']<=>$y['type']);
        for ($i = 0; $i < count($ma); $i++) if ($ma[$i] !== $mb[$i]) return false;
        return true;
    }
}
