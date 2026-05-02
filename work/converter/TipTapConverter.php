<?php

namespace PhpWord\TipTap;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Footnote;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\Link;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\NumberFormat;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;
use PhpOffice\PhpWord\Style\Numbering;
use PhpOffice\PhpWord\Style\NumberingLevel;
use PhpOffice\PhpWord\Style\Paragraph as ParagraphStyle;
use PhpOffice\PhpWord\Style\Table as TableStyle;

/**
 * Convert a Word document (loaded via PHPWord) to a TipTap-compatible JSON document.
 *
 * Design goals:
 * - Detect headings via Title elements OR paragraph styleName matching /Heading\d/
 * - Group consecutive ListItemRun into proper bulletList / orderedList based on numFmt
 * - Support nested lists by ListItemRun depth
 * - Convert tables (Table → table/tableRow/tableCell)
 * - Convert images (embedded → base64 data URL)
 * - Convert hyperlinks
 * - Carry alignment, font, size, color, bold/italic/underline/strike marks
 */
class TipTapConverter
{
    /** @var PhpWord */
    private $phpWord;

    /** @var array<string,Numbering> resolved by getNumId() and by name */
    private $numberingById = [];
    private $numberingByName = [];

    /** True while converting elements inside a table cell. Suppresses the
     *  body-paragraph default lineHeight so cells stay compact. */
    private bool $inTableCell = false;

    /** Tunable knobs (font allow-list, RTL default, header/footer injection,
     *  empty-paragraph drop, etc.). Read-only inside the converter — callers
     *  pass an instance via the constructor. */
    public ConverterConfig $config;

    /** @var RawXmlIndex|null populated by convertFile() when raw-XML features are enabled. */
    private ?RawXmlIndex $rawIndex = null;

    public function __construct(?ConverterConfig $config = null)
    {
        $this->config = $config ?? new ConverterConfig();
    }

    /**
     * Apply the configured `nodeTypeMap` to a canonical TipTap node type
     * name. Returns the input unchanged when the map has no entry. Used at
     * every spot where the converter emits `['type' => 'X', ...]` so
     * downstream plugins that rename a node (e.g. `pageBreak` →
     * `customPageBreak`) get the renamed value without a fork.
     */
    private function nodeType(string $type): string
    {
        return $this->config->nodeTypeMap[$type] ?? $type;
    }

    public function convertFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("File not found: $path");
        }
        $this->phpWord = IOFactory::load($path);
        $this->buildNumberingIndex();
        $this->rawIndex = new RawXmlIndex($path);

        $content = [];
        foreach ($this->phpWord->getSections() as $section) {
            $content = array_merge($content, $this->processContainer($section));
        }

        // Inject pageBreak nodes at every <w:br w:type="page"/> /
        // <w:pageBreakBefore/> position the raw XML carries. PHPWord's
        // PageBreak element only surfaces a subset of these; the rest live as
        // mid-paragraph <w:br> or as style-level pageBreakBefore (Heading1).
        $content = $this->injectRawPageBreaks($content);

        if ($this->config->reconstructToc) {
            $tocNodes = $this->buildTocNodes();
            if (!empty($tocNodes)) {
                $insertAt = $this->findTocInsertionIndex($content);
                array_splice($content, $insertAt, 0, $tocNodes);
                // Page break after the last TOC node, before body content.
                array_splice($content, $insertAt + count($tocNodes), 0, [['type' => $this->nodeType('pageBreak')]]);
            }
        }

        if ($this->config->injectHeaderFooter && $this->rawIndex !== null) {
            $headerNodes = $this->buildHeaderNodes();
            if (!empty($headerNodes)) {
                array_splice($content, 0, 0, $headerNodes);
                // Page break between the cover/header band and the TOC/body.
                array_splice($content, count($headerNodes), 0, [['type' => $this->nodeType('pageBreak')]]);
            }
            $footerNodes = $this->buildFooterNodes();
            if (!empty($footerNodes)) {
                $content = array_merge($content, $footerNodes);
            }
        }

        $doc = ['type' => $this->nodeType('doc'), 'content' => $content];
        if ($this->config->defaultRtl) {
            $doc['attrs'] = ['dir' => 'rtl'];
        }
        return $doc;
    }

    /**
     * Build the corp-header band that appears at the top of every reference
     * page: a logo (or "شعار الجهة" placeholder) followed by the country /
     * agency / form-name lines from word/header2.xml. When the source has
     * an embedded picture in any header part, `RawXmlIndex::extractHeaderLogo()`
     * surfaces it on `headerLogo` and the logo line is replaced with a real
     * `image` node; otherwise the dark-background placeholder text is kept.
     */
    private function buildHeaderNodes(): array
    {
        if ($this->rawIndex === null || empty($this->rawIndex->headerLines)) return [];
        $logo = $this->rawIndex->headerLogo;
        $nodes = [];
        foreach ($this->rawIndex->headerLines as $line) {
            $text = $line['text'];
            if ($text === '') continue;
            $isLogoPlaceholder = ($text === 'شعار الجهة');
            if ($isLogoPlaceholder && is_array($logo) && !empty($logo['src'])) {
                $imgAttrs = ['src' => $logo['src'], 'alt' => 'شعار الجهة'];
                if (!empty($logo['width']))  $imgAttrs['width']  = (int) $logo['width'];
                if (!empty($logo['height'])) $imgAttrs['height'] = (int) $logo['height'];
                $nodes[] = [
                    'type' => $this->nodeType('paragraph'),
                    'attrs' => ['textAlign' => 'left', 'dir' => 'rtl'],
                    'content' => [['type' => $this->nodeType('image'), 'attrs' => $imgAttrs]],
                ];
                continue;
            }
            $textNode = ['type' => $this->nodeType('text'), 'text' => $text];
            if ($isLogoPlaceholder) {
                $textNode['marks'] = [[
                    'type' => 'textStyle',
                    'attrs' => ['color' => '#ffffff', 'backgroundColor' => '#222222'],
                ]];
            }
            $nodes[] = [
                'type' => $this->nodeType('paragraph'),
                'attrs' => [
                    'textAlign' => $isLogoPlaceholder ? 'left' : 'right',
                    'dir' => 'rtl',
                ],
                'content' => [$textNode],
            ];
        }
        return $nodes;
    }

    /**
     * Build a footer block (page number, issue date, version, contract number)
     * that appears at the very end of the converted document. PAGE/NUMPAGES
     * fields render as their literal stamped values (Word stamped these at
     * last regen — "1" / "58" on the cover, etc.).
     */
    private function buildFooterNodes(): array
    {
        if ($this->rawIndex === null || empty($this->rawIndex->footerLines)) return [];
        $nodes = [['type' => $this->nodeType('horizontalRule')]];
        foreach ($this->rawIndex->footerLines as $line) {
            $text = $line['text'];
            if ($text === '') continue;
            $nodes[] = [
                'type' => $this->nodeType('paragraph'),
                'attrs' => ['textAlign' => 'right', 'dir' => 'rtl'],
                'content' => [[
                    'type' => $this->nodeType('text'),
                    'text' => $text,
                    'marks' => [[
                        'type' => 'textStyle',
                        'attrs' => ['color' => '#666666', 'fontSize' => '9pt'],
                    ]],
                ]],
            ];
        }
        return $nodes;
    }

    /**
     * Find where in the converted body the TOC should be inserted: just
     * before the first heading whose `attrs.id` is referenced by any TOC
     * entry. The first TOC link points at the first body heading, so this
     * pins the TOC at the cover/body boundary. Falls back to position 0
     * if no match (a defensive edge case — the TOC was generated against
     * heading anchors so they should always be present).
     */
    /**
     * Walk converted content and insert `pageBreak` nodes for every page
     * boundary the raw XML knows about (mid-paragraph `<w:br w:type="page"/>`
     * and style-level `<w:pageBreakBefore/>`). PHPWord's `PageBreak` element
     * only surfaces the subset that lives as a standalone empty paragraph;
     * everything else has to come from the raw-XML signature map.
     *
     * Matching is done by normalised joined text. We avoid duplicate breaks:
     * if the previous (or next) emitted node is already a `pageBreak`, skip.
     */
    private function injectRawPageBreaks(array $content): array
    {
        if ($this->rawIndex === null) return $content;
        $sigs = $this->rawIndex->getPageBreakSignatures();
        $after = $sigs['after'] ?? [];
        $before = $sigs['before'] ?? [];
        if (empty($after) && empty($before)) return $content;

        $out = [];
        $lastEmitted = null;
        foreach ($content as $node) {
            $sig = $this->nodeTextSignature($node);
            if ($sig !== '' && isset($before[$sig])) {
                if ($lastEmitted !== 'pageBreak') {
                    $out[] = ['type' => $this->nodeType('pageBreak')];
                    $lastEmitted = 'pageBreak';
                }
            }
            // Drop empty/hardBreak-only paragraphs adjacent to a page break.
            // These come from `<w:br w:type="page"/>` runs PHPWord surfaced as
            // a hardBreak; the page break itself is already represented.
            if ($lastEmitted === 'pageBreak' && $this->isHardBreakOnlyParagraph($node)) {
                continue;
            }
            $out[] = $node;
            $lastEmitted = is_array($node) ? ($node['type'] ?? null) : null;
            if ($sig !== '' && isset($after[$sig])) {
                if ($lastEmitted !== 'pageBreak') {
                    $out[] = ['type' => $this->nodeType('pageBreak')];
                    $lastEmitted = 'pageBreak';
                }
            }
        }
        return $out;
    }

    /** Normalised joined text of a top-level block node, used to match against
     *  RawXmlIndex page-break signatures. Returns '' for non-text-bearing
     *  nodes (tables, page breaks, horizontal rules). Strips any leading
     *  multi-level number prefix (e.g. "1.", "1.2.3.") that buildHeadingNode
     *  prepends — raw XML carries the heading text only. */
    private function nodeTextSignature($node): string
    {
        if (!is_array($node)) return '';
        $type = $node['type'] ?? null;
        if ($type !== 'paragraph' && $type !== 'heading') return '';
        $content = $node['content'] ?? [];
        if (!is_array($content) || empty($content)) return '';
        $text = $this->joinText($content);
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if ($text === null) return '';
        // Strip leading numbering prefix: "1.", "1.1.", "1.2.3.", "1)", etc.
        $stripped = preg_replace('/^[\d٠-٩]+(?:[.)][\d٠-٩]+)*[.)]?\s+/u', '', $text);
        return $stripped !== null ? $stripped : $text;
    }

    private function findTocInsertionIndex(array $content): int
    {
        if ($this->rawIndex === null || empty($this->rawIndex->tocEntries)) return 0;
        $tocAnchors = [];
        foreach ($this->rawIndex->tocEntries as $e) {
            if (!empty($e['anchor'])) $tocAnchors[$e['anchor']] = true;
        }
        if (empty($tocAnchors)) return 0;
        foreach ($content as $i => $node) {
            if (!is_array($node) || ($node['type'] ?? null) !== 'heading') continue;
            $id = $node['attrs']['id'] ?? null;
            if ($id !== null && isset($tocAnchors[$id])) return $i;
        }
        return 0;
    }

    /**
     * Walk a converted doc tree and replace Latin digits (0-9) in every
     * `text` node's `text` field with Arabic-Indic digits (٠-٩). This is a
     * post-process pass meant to be invoked by callers after convertFile()
     * returns — it deliberately lives outside the conversion pipeline so
     * the converter doesn't need to know about numeral systems and so the
     * substitution can be enabled/disabled per-document by the caller.
     *
     * Substitution is unconditional: heading-number prefixes, TOC page
     * columns, body-text run contents — anything whose `type` is `text`
     * gets digits remapped. The caller decides whether to run this pass
     * based on document language / `arabicIndicNumerals` policy.
     */
    public static function applyArabicIndicNumerals(array $doc): array
    {
        $map = [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ];
        $walk = function (&$n) use (&$walk, $map) {
            if (!is_array($n)) return;
            if (($n['type'] ?? null) === 'text' && isset($n['text']) && is_string($n['text'])) {
                $n['text'] = strtr($n['text'], $map);
            }
            if (isset($n['content']) && is_array($n['content'])) {
                foreach ($n['content'] as &$c) $walk($c);
                unset($c);
            }
        };
        $walk($doc);
        return $doc;
    }

    /**
     * Build TipTap nodes for the TOC the source document carries. Emits a
     * heading for "الفهرس" (or whatever the localised TOCHeading paragraph
     * said) followed by one paragraph per entry. Each entry paragraph carries
     * `attrs.styleName="TOC1"` (or `"TOC3"`) so the sandbox CSS can lay it
     * out as a flex row: number + linked title on the right, growing leader
     * dots in the middle, page number on the left. Indent grows with depth.
     */
    private function buildTocNodes(): array
    {
        if ($this->rawIndex === null || empty($this->rawIndex->tocEntries)) return [];
        $nodes = [];
        $tocHeading = $this->rawIndex->tocHeadingText;
        if ($tocHeading) {
            $nodes[] = [
                'type' => $this->nodeType('heading'),
                'attrs' => ['level' => 1, 'textAlign' => 'right', 'dir' => 'rtl', 'styleName' => 'TOCHeading'],
                'content' => [['type' => $this->nodeType('text'), 'text' => $tocHeading]],
            ];
        }
        foreach ($this->rawIndex->tocEntries as $e) {
            $level = max(1, (int) ($e['level'] ?: 1));
            $styleName = 'TOC' . $level;
            $content = [];
            // Number prefix (e.g. "1.") — bold-styled inline so RTL renders
            // ordered with title; appears on the right edge in flex row.
            if (!empty($e['number'])) {
                $num = $e['number'];
                if (!preg_match('#[.)]\s*$#u', $num)) $num .= '.';
                $content[] = [
                    'type' => $this->nodeType('text'),
                    'text' => $num . ' ',
                    'marks' => [['type' => 'bold']],
                ];
            }
            // Linked title.
            $titleMarks = [];
            if (!empty($e['anchor'])) {
                $titleMarks[] = [
                    'type' => 'link',
                    'attrs' => [
                        'href' => '#' . $e['anchor'],
                        'target' => '_self',
                        'rel' => 'noopener',
                    ],
                ];
            }
            $titleNode = ['type' => $this->nodeType('text'), 'text' => $e['text']];
            if ($titleMarks) $titleNode['marks'] = $titleMarks;
            $content[] = $titleNode;
            // Leader dots — a literal text run tagged via a textStyle mark
            // with a sentinel `fontFamily` value the sandbox recognises as
            // "render this span as a flex-grow leader dot strip". We avoid
            // adding a new node type so the JSON stays vanilla TipTap.
            $content[] = [
                'type' => $this->nodeType('text'),
                'text' => "\u{2009}", // narrow space placeholder; CSS draws the dots
                'marks' => [['type' => 'textStyle', 'attrs' => ['fontFamily' => '__toc_leader__']]],
            ];
            // Page number.
            if (!empty($e['page'])) {
                $content[] = [
                    'type' => $this->nodeType('text'),
                    'text' => $e['page'],
                    'marks' => [['type' => 'textStyle', 'attrs' => ['fontFamily' => '__toc_page__']]],
                ];
            }
            $attrs = [
                'dir' => 'rtl',
                'styleName' => $styleName,
            ];
            if ($level > 1) $attrs['indent'] = ($level - 1) * 24;
            $nodes[] = [
                'type' => $this->nodeType('paragraph'),
                'attrs' => $attrs,
                'content' => $content,
            ];
        }
        return $nodes;
    }

    private function buildNumberingIndex(): void
    {
        foreach (Style::getStyles() as $name => $style) {
            if ($style instanceof Numbering) {
                $this->numberingByName[$name] = $style;
                $id = $style->getNumId();
                if ($id !== null) {
                    $this->numberingById[(int) $id] = $style;
                }
            }
        }
    }

    /** Convert a container's children to a flat list of TipTap block nodes. */
    private function processContainer(AbstractContainer $container): array
    {
        $elements = $container->getElements();
        $out = [];
        $i = 0;
        $n = count($elements);

        while ($i < $n) {
            $el = $elements[$i];

            // ListItemRun groups: only if the style says it's actually a list
            // (some ListItemRun items are styled as headings — promote them).
            if ($el instanceof ListItemRun && !$this->isStyledAsHeading($el)) {
                [$listNode, $consumed] = $this->collectList($elements, $i);
                $i += $consumed;
                if ($listNode !== null) $out[] = $listNode;
                continue;
            }

            // Insert a horizontalRule before paragraphs/headings that carry a
            // top border — Word uses <w:pBdr><w:top/> as a section separator.
            // Skip if the previous emitted node is already a horizontalRule.
            if ($this->elementHasTopBorder($el)) {
                $prev = end($out);
                if (!(is_array($prev) && ($prev['type'] ?? null) === 'horizontalRule')) {
                    $out[] = ['type' => $this->nodeType('horizontalRule')];
                }
            }

            $node = $this->convertBlock($el);
            if ($node !== null) {
                if (is_array($node) && isset($node[0])) {
                    foreach ($node as $sub) $out[] = $sub;
                } else {
                    $out[] = $node;
                }
            }
            $i++;
        }

        if ($this->config->dropEmptyParagraphs) {
            $out = array_values(array_filter($out, fn($n) => !$this->isEmptyParagraph($n)));
        }

        return $out;
    }

    /**
     * True for `type=paragraph` nodes with no content (or an empty content
     * array). Used by the empty-paragraph drop pass — vertical spacing in
     * the converted doc comes from `attrs.marginTop`/`marginBottom` on
     * surrounding non-empty paragraphs, not from empty `<p>` nodes.
     */
    private function isEmptyParagraph($n): bool
    {
        return is_array($n)
            && ($n['type'] ?? null) === 'paragraph'
            && empty($n['content']);
    }

    /** True for `paragraph` nodes whose content is empty or contains only
     *  hardBreak nodes — used to drop the residue of a `<w:br w:type="page"/>`
     *  paragraph PHPWord surfaced as a TextBreak. */
    private function isHardBreakOnlyParagraph($n): bool
    {
        if (!is_array($n) || ($n['type'] ?? null) !== 'paragraph') return false;
        $content = $n['content'] ?? [];
        if (empty($content)) return true;
        foreach ($content as $c) {
            if (!is_array($c)) return false;
            if (($c['type'] ?? null) !== 'hardBreak') return false;
        }
        return true;
    }

    private function elementHasTopBorder($el): bool
    {
        if ($el instanceof TextRun || $el instanceof ListItemRun) {
            return $this->paragraphHasTopBorder($el->getParagraphStyle());
        }
        if ($el instanceof Title) {
            $text = $el->getText();
            if ($text instanceof TextRun) return $this->paragraphHasTopBorder($text->getParagraphStyle());
        }
        return false;
    }

    /** Return a single block node (or array of nodes) for a non-list element. */
    private function convertBlock($el)
    {
        if ($el instanceof Title) {
            $node = $this->convertTitle($el);
            if (($node['type'] ?? null) === 'heading' && !$this->hasTextBearingNode($node['content'] ?? [])) {
                return null;
            }
            return $node;
        }
        if ($el instanceof TextRun) {
            // Heading-styled textruns become headings
            $level = $this->headingLevelFromStyle($el->getParagraphStyle());
            if ($level !== null) {
                $content = $this->convertInlineRun($el);
                if (!$this->hasTextBearingNode($content)) return null;
                return $this->buildHeadingNode($level, $content, $el->getParagraphStyle());
            }
            $content = $this->convertInlineRun($el);
            $content = $this->maybePatchSdt($content);
            return [
                'type' => $this->nodeType('paragraph'),
                'attrs' => $this->paragraphAttrs($el->getParagraphStyle()),
                'content' => $content,
            ];
        }
        if ($el instanceof ListItemRun) {
            // Heading-styled list item: render as heading, dropping the bullet.
            $level = $this->headingLevelFromStyle($el->getParagraphStyle());
            if ($level !== null) {
                $content = $this->convertInlineRun($el);
                if (!$this->hasTextBearingNode($content)) return null;
                return $this->buildHeadingNode($level, $content, $el->getParagraphStyle());
            }
            // Standalone (single-item) list — wrap in a list with proper type
            return $this->buildListNodeFor([$el]);
        }
        if ($el instanceof Table) return $this->convertTable($el);
        if ($el instanceof TextBreak) {
            // empty paragraph for spacing
            return ['type' => $this->nodeType('paragraph'), 'attrs' => ['textAlign' => null, 'dir' => $this->config->defaultRtl ? 'rtl' : null], 'content' => []];
        }
        if ($el instanceof PageBreak) {
            return ['type' => $this->nodeType('pageBreak')];
        }
        if ($el instanceof Image) {
            return [
                'type' => $this->nodeType('paragraph'),
                'attrs' => ['textAlign' => null, 'dir' => $this->config->defaultRtl ? 'rtl' : null],
                'content' => [$this->buildImageNode($el)],
            ];
        }

        return null;
    }

    /**
     * Build a heading node, prefixing the multi-level number if known and
     * attaching an `id` from a matching `_Toc...` bookmark.
     */
    private function buildHeadingNode(int $level, array $content, $pStyle): array
    {
        $headingText = $this->joinText($content);
        $paraAttrs = $this->paragraphAttrs($pStyle);
        // Headings get their line-height from the heading CSS, not the body default.
        $hasExplicitLh = $this->paragraphLineHeight($pStyle) !== null;
        if (!$hasExplicitLh) unset($paraAttrs['lineHeight']);
        $attrs = ['level' => $level] + $paraAttrs;
        if (is_object($pStyle) && method_exists($pStyle, 'getStyleName')) {
            $sn = (string) $pStyle->getStyleName();
            if ($sn !== '') $attrs['styleName'] = $sn;
        }
        $headingColor = $this->dominantTextColor($content);
        if ($headingColor !== null) $attrs['color'] = $headingColor;
        if ($this->config->emitBookmarkIds && $this->rawIndex !== null) {
            $anchor = $this->rawIndex->consumeAnchor($headingText);
            if ($anchor !== null) $attrs['id'] = $anchor;
        }
        if ($this->rawIndex !== null && !empty($this->rawIndex->headingUnderlines[$headingText])) {
            $content = $this->applyUnderlineToTextNodes($content);
        }
        if ($this->config->prefixHeadingNumbers && $this->rawIndex !== null) {
            $number = $this->rawIndex->headingNumbers[$headingText] ?? null;
            if ($number !== null && $number !== '') {
                // Prepend a dotted number + space text node. Keep the same
                // textStyle marks as the existing first text node so the
                // prefix inherits color/size.
                $marks = null;
                foreach ($content as $c) {
                    if (($c['type'] ?? null) === 'text') {
                        $marks = $c['marks'] ?? null;
                        break;
                    }
                }
                $numberSuffix = preg_match('#[.)]\s*$#u', $number) ? ' ' : '. ';
                $prefix = ['type' => $this->nodeType('text'), 'text' => $number . $numberSuffix];
                if ($marks) $prefix['marks'] = $marks;
                array_unshift($content, $prefix);
            }
        }
        return ['type' => $this->nodeType('heading'), 'attrs' => $attrs, 'content' => $content];
    }

    /**
     * Add an `underline` mark to every text node in $content that doesn't
     * already carry one. Used by buildHeadingNode() when RawXmlIndex flagged
     * the heading paragraph as carrying `<w:u>` but PHPWord didn't surface
     * underline at the run level.
     */
    private function applyUnderlineToTextNodes(array $content): array
    {
        foreach ($content as $i => $n) {
            if (($n['type'] ?? null) !== 'text') continue;
            $marks = $n['marks'] ?? [];
            $hasUnderline = false;
            foreach ($marks as $m) {
                if (($m['type'] ?? null) === 'underline') { $hasUnderline = true; break; }
            }
            if ($hasUnderline) continue;
            $marks[] = ['type' => 'underline'];
            $content[$i]['marks'] = $marks;
        }
        return $content;
    }

    /**
     * If a paragraph's joined text matches a known SDT-bearing leader (PHPWord
     * dropped the SDT runs), append the missing text as a new node.
     */
    private function maybePatchSdt(array $content): array
    {
        if (!$this->config->patchSdtPlaceholders || $this->rawIndex === null) return $content;
        if (empty($this->rawIndex->sdtPlaceholders)) return $content;
        $joined = $this->joinText($content);
        $sig = preg_replace('/\s+/u', ' ', trim($joined));
        if ($sig === '') return $content;
        if (!isset($this->rawIndex->sdtPlaceholders[$sig])) return $content;
        $sdt = $this->rawIndex->sdtPlaceholders[$sig];
        // Inherit marks from the last text node so the appended text matches font/color.
        $marks = null;
        for ($i = count($content) - 1; $i >= 0; $i--) {
            if (($content[$i]['type'] ?? null) === 'text') {
                $marks = $content[$i]['marks'] ?? null;
                break;
            }
        }
        $node = ['type' => $this->nodeType('text'), 'text' => $sdt];
        // SDT placeholder text in the source is red — override color to red so it
        // visually distinguishes the placeholder from the surrounding label.
        $sdtMarks = [];
        if ($marks) {
            // copy non-color attrs into a fresh textStyle
            foreach ($marks as $m) {
                if (($m['type'] ?? null) === 'textStyle') {
                    $attrs = $m['attrs'] ?? [];
                    $attrs['color'] = '#ff0000';
                    $sdtMarks[] = ['type' => 'textStyle', 'attrs' => $attrs];
                } else {
                    $sdtMarks[] = $m;
                }
            }
        } else {
            $sdtMarks[] = ['type' => 'textStyle', 'attrs' => ['color' => '#ff0000']];
        }
        $node['marks'] = $sdtMarks;
        $content[] = $node;
        return $content;
    }

    /**
     * True when the inline-node list contains at least one node that produces
     * visible content (non-whitespace text or an image). Used to drop heading
     * paragraphs whose only run was a tab/whitespace, which Word renders as
     * an empty section separator but TipTap renders as a stray empty heading.
     */
    private function hasTextBearingNode(array $nodes): bool
    {
        foreach ($nodes as $n) {
            if (!is_array($n)) continue;
            $type = $n['type'] ?? null;
            if ($type === 'image') return true;
            if ($type === 'text') {
                $t = (string) ($n['text'] ?? '');
                if (trim($t) !== '') return true;
            }
            if (isset($n['content']) && is_array($n['content']) && $this->hasTextBearingNode($n['content'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Inspect the first text-bearing node and return its textStyle.color,
     * lowercased to hex. Returns null when no color is set or when text
     * nodes disagree on color (heading is a mix — leave it inline-only).
     */
    private function dominantTextColor(array $nodes): ?string
    {
        $found = null;
        $stack = $nodes;
        while ($stack) {
            $n = array_shift($stack);
            if (!is_array($n)) continue;
            if (($n['type'] ?? null) === 'text' && trim((string) ($n['text'] ?? '')) !== '') {
                $color = null;
                foreach ($n['marks'] ?? [] as $m) {
                    if (($m['type'] ?? null) === 'textStyle') {
                        $color = $m['attrs']['color'] ?? null;
                        break;
                    }
                }
                if ($color === null) return null;
                $color = strtolower($color);
                if ($found === null) {
                    $found = $color;
                } elseif ($found !== $color) {
                    return null;
                }
            }
            if (isset($n['content']) && is_array($n['content'])) {
                foreach ($n['content'] as $c) $stack[] = $c;
            }
        }
        return $found;
    }

    /** Concatenate all `text` strings in a list of inline nodes. */
    private function joinText(array $nodes): string
    {
        $out = '';
        foreach ($nodes as $n) {
            if (!is_array($n)) continue;
            if (($n['type'] ?? null) === 'text') $out .= $n['text'] ?? '';
            elseif (isset($n['content']) && is_array($n['content'])) $out .= $this->joinText($n['content']);
        }
        return trim($out);
    }

    private function convertTitle(Title $title): array
    {
        $depth = (int) $title->getDepth();
        $level = max(1, min(6, $depth ?: 1));
        $text = $title->getText();
        $pStyle = null;
        $content = [];

        if (is_string($text)) {
            if ($text !== '') $content[] = ['type' => $this->nodeType('text'), 'text' => $this->decodeText($text)];
        } elseif ($text instanceof TextRun) {
            $content = $this->convertInlineRun($text);
            $pStyle = $text->getParagraphStyle();
        }

        $node = $this->buildHeadingNode($level, $content, $pStyle);
        // PHPWord's `Title` element corresponds to Word's "Title" pStyle
        // (cover banner) when no other paragraph styleName surfaced.
        if (!isset($node['attrs']['styleName'])) {
            $node['attrs']['styleName'] = 'Title';
        }
        return $node;
    }

    /** Walk a TextRun's inline children into TipTap text/hardBreak/link/image nodes. */
    private function convertInlineRun(AbstractContainer $run): array
    {
        $nodes = [];
        foreach ($run->getElements() as $sub) {
            if ($sub instanceof Text) {
                $node = $this->buildTextNode($sub);
                if ($node) $nodes[] = $node;
            } elseif ($sub instanceof Link) {
                $node = $this->buildLinkNode($sub);
                if ($node) $nodes[] = $node;
            } elseif ($sub instanceof TextBreak) {
                $nodes[] = ['type' => $this->nodeType('hardBreak')];
            } elseif ($sub instanceof Image) {
                $nodes[] = $this->buildImageNode($sub);
            } elseif ($sub instanceof Footnote) {
                // Inline footnote: render its text then a marker. Best-effort.
                foreach ($sub->getElements() as $fn) {
                    if ($fn instanceof Text) {
                        $tn = $this->buildTextNode($fn);
                        if ($tn) $nodes[] = $tn;
                    }
                }
            } elseif ($sub instanceof TextRun) {
                // Nested text run — recurse and append
                foreach ($this->convertInlineRun($sub) as $n) $nodes[] = $n;
            }
        }
        return $this->mergeAdjacentText($nodes);
    }

    private function buildTextNode(Text $t): ?array
    {
        $text = $this->decodeText((string) $t->getText());
        if ($text === '') return null;
        $fontStyle = $t->getFontStyle();
        if ($this->fontStyleIsAllCaps($fontStyle)) {
            $text = mb_strtoupper($text, 'UTF-8');
        }
        $node = ['type' => $this->nodeType('text'), 'text' => $text];
        $marks = $this->buildMarks($fontStyle);
        if ($marks) $node['marks'] = $marks;
        return $node;
    }

    private function buildLinkNode(Link $l): ?array
    {
        $text = $this->decodeText((string) $l->getText());
        if ($text === '') return null;
        $fontStyle = $l->getFontStyle();
        if ($this->fontStyleIsAllCaps($fontStyle)) {
            $text = mb_strtoupper($text, 'UTF-8');
        }
        $node = ['type' => $this->nodeType('text'), 'text' => $text];
        $marks = $this->buildMarks($fontStyle);
        $href = (string) $l->getSource();
        if ($href !== '' && !$l->isInternal()) {
            $marks[] = ['type' => 'link', 'attrs' => ['href' => $href, 'target' => '_blank', 'rel' => 'noopener noreferrer nofollow']];
        }
        if ($marks) $node['marks'] = $marks;
        return $node;
    }

    /**
     * True when the run carries `<w:caps/>` directly OR inherits it from a
     * `<w:rStyle>` character style. Used to uppercase text at convert time
     * since TipTap has no text-transform mark.
     */
    private function fontStyleIsAllCaps($fontStyle): bool
    {
        if (is_object($fontStyle) && method_exists($fontStyle, 'isAllCaps') && $fontStyle->isAllCaps()) {
            return true;
        }
        $parent = $this->resolveCharStyleFromFont($fontStyle);
        if ($parent !== null && method_exists($parent, 'isAllCaps') && $parent->isAllCaps()) {
            return true;
        }
        return false;
    }

    /**
     * Resolve a Font's `<w:rStyle>` to its registered character style.
     * Returns null when the run has no styleName, the name is unknown, or
     * the registered style isn't a Font (paragraph/table styles aren't
     * mergeable as character formatting).
     *
     * @param mixed $fontStyle Font|string|null
     */
    private function resolveCharStyleFromFont($fontStyle)
    {
        $name = null;
        if (is_string($fontStyle) && $fontStyle !== '') {
            $name = $fontStyle;
        } elseif (is_object($fontStyle) && method_exists($fontStyle, 'getStyleName')) {
            $name = $fontStyle->getStyleName();
        }
        if (!is_string($name) || $name === '') return null;
        $resolved = Style::getStyle($name);
        return ($resolved instanceof \PhpOffice\PhpWord\Style\Font) ? $resolved : null;
    }

    private function buildImageNode(Image $img): array
    {
        $src = '';
        $b64 = $img->getImageStringData(true);
        if ($b64) {
            $type = $img->getImageType() ?: 'image/png';
            $src = 'data:' . $type . ';base64,' . $b64;
        }
        $attrs = ['src' => $src];
        $style = $img->getStyle();
        if ($style && method_exists($style, 'getWidth') && $style->getWidth()) $attrs['width'] = (int) $style->getWidth();
        if ($style && method_exists($style, 'getHeight') && $style->getHeight()) $attrs['height'] = (int) $style->getHeight();
        $alt = $img->getName();
        if ($alt) $attrs['alt'] = $alt;
        return ['type' => $this->nodeType('image'), 'attrs' => $attrs];
    }

    /** Group consecutive ListItemRun, supporting nesting by depth. Returns [node|null, count]. */
    private function collectList(array $elements, int $start): array
    {
        $items = [];
        $i = $start;
        $n = count($elements);
        while ($i < $n && $elements[$i] instanceof ListItemRun && !$this->isStyledAsHeading($elements[$i])) {
            $items[] = $elements[$i];
            $i++;
        }
        $consumed = count($items);
        $node = $this->buildListNodeFor($items);
        return [$node, $consumed];
    }

    /** Build a (possibly nested) bulletList/orderedList from a flat list of items. */
    private function buildListNodeFor(array $items): ?array
    {
        if (empty($items)) return null;

        // Top-level type: format of the first level-0 item determines bullet vs ordered
        $firstTopItem = $items[0];
        $topType = $this->listTypeFor($firstTopItem, 0);

        $rootDepth = 0;
        $rootNode = ['type' => $topType, 'content' => []];
        if ($this->config->defaultRtl || $this->paragraphDir($firstTopItem->getParagraphStyle()) === 'rtl') {
            $rootNode['attrs'] = ['dir' => 'rtl'];
        }
        // Stack of open lists at each depth (index = depth). Each entry is [&listNode, &lastItem]
        $stack = [['list' => &$rootNode, 'lastItemContent' => null]];

        foreach ($items as $item) {
            $depth = (int) $item->getDepth();

            // Pop deeper levels
            while (count($stack) > $depth + 1) {
                array_pop($stack);
            }

            // If we need to open a deeper level, attach a new list to the previous item's content
            while (count($stack) < $depth + 1) {
                $parentDepth = count($stack) - 1;
                if ($parentDepth < 0) break;
                $parent = &$stack[$parentDepth]['list'];
                if (empty($parent['content'])) {
                    // Need a placeholder listItem to hang the deeper list off of
                    $placeholderItem = ['type' => $this->nodeType('listItem'), 'content' => [['type' => $this->nodeType('paragraph'), 'attrs' => ['textAlign' => null, 'dir' => $this->config->defaultRtl ? 'rtl' : null], 'content' => []]]];
                    if ($this->config->defaultRtl) $placeholderItem['attrs'] = ['dir' => 'rtl'];
                    $parent['content'][] = $placeholderItem;
                }
                $lastIdx = count($parent['content']) - 1;
                $childType = $this->listTypeFor($item, $depth);
                $newList = ['type' => $childType, 'content' => []];
                if ($this->config->defaultRtl) $newList['attrs'] = ['dir' => 'rtl'];
                $parent['content'][$lastIdx]['content'][] = &$newList;
                $stack[] = ['list' => &$newList];
                unset($newList);
            }

            // Build the listItem
            $listItemContent = [
                [
                    'type' => $this->nodeType('paragraph'),
                    'attrs' => $this->paragraphAttrs($item->getParagraphStyle()),
                    'content' => $this->convertInlineRun($item),
                ],
            ];
            $current = &$stack[$depth]['list'];
            $listItemNode = ['type' => $this->nodeType('listItem'), 'content' => $listItemContent];
            $itemDir = $this->paragraphDir($item->getParagraphStyle());
            if ($itemDir !== null) $listItemNode['attrs'] = ['dir' => $itemDir];
            $current['content'][] = $listItemNode;
            unset($current);
        }

        return $rootNode;
    }

    /** Determine bulletList / orderedList for a given list item & its level. */
    private function listTypeFor(ListItemRun $item, int $depth): string
    {
        $style = $item->getStyle();
        if (!$style instanceof ListItemStyle) return 'bulletList';

        // Newer numId reference
        $numbering = null;
        $numId = method_exists($style, 'getNumId') ? $style->getNumId() : null;
        if ($numId !== null && isset($this->numberingById[(int) $numId])) {
            $numbering = $this->numberingById[(int) $numId];
        }
        if ($numbering === null) {
            $numStyle = method_exists($style, 'getNumStyle') ? $style->getNumStyle() : null;
            if ($numStyle && isset($this->numberingByName[$numStyle])) {
                $numbering = $this->numberingByName[$numStyle];
            }
        }
        if ($numbering instanceof Numbering) {
            $levels = $numbering->getLevels();
            $lvl = $levels[$depth] ?? $levels[0] ?? null;
            if ($lvl instanceof NumberingLevel) {
                $fmt = $lvl->getFormat();
                if ($fmt === null || $fmt === '' || $fmt === NumberFormat::BULLET || $fmt === NumberFormat::NONE) {
                    return 'bulletList';
                }
                return 'orderedList';
            }
        }

        // Fallback: peek at ListItem.listType
        $type = method_exists($style, 'getListType') ? $style->getListType() : null;
        if ($type !== null) {
            // PHPWord constants like ListItem::TYPE_NUMBER, TYPE_BULLET_FILLED
            if (stripos((string) $type, 'number') !== false) return 'orderedList';
        }
        return 'bulletList';
    }

    /** Convert a Word table to a TipTap table block. */
    private function convertTable(Table $tbl): array
    {
        $tableDir = $this->tableDir($tbl);
        $rowsOut = [];
        foreach ($tbl->getRows() as $row) {
            $cellsOut = [];
            foreach ($row->getCells() as $cell) {
                $cellBg = $this->cellBackgroundColor($cell);
                $cellNodes = [];
                $prevInCell = $this->inTableCell;
                $this->inTableCell = true;
                foreach ($cell->getElements() as $sub) {
                    if ($sub instanceof ListItemRun) {
                        // Single-item list inside a cell (we don't try to merge across cells)
                        $listNode = $this->buildListNodeFor([$sub]);
                        if ($listNode) $cellNodes[] = $listNode;
                        continue;
                    }
                    $node = $this->convertBlock($sub);
                    if ($node === null) continue;
                    if (isset($node[0])) {
                        foreach ($node as $n) $cellNodes[] = $n;
                    } else {
                        $cellNodes[] = $node;
                    }
                }
                $this->inTableCell = $prevInCell;
                if (empty($cellNodes)) {
                    $cellNodes[] = ['type' => $this->nodeType('paragraph'), 'attrs' => ['textAlign' => null, 'dir' => $tableDir ?? ($this->config->defaultRtl ? 'rtl' : null)], 'content' => []];
                }
                // Apply cell shading as a textStyle backgroundColor mark on the
                // first paragraph's text runs as a fallback — the sandbox's
                // RichTextStyle extension renders backgroundColor as inline style.
                if ($cellBg !== null) {
                    $this->applyCellBackgroundFallback($cellNodes, $cellBg);
                }
                $cellAttrs = ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null];
                $cstyle = $cell->getStyle();
                if ($cstyle) {
                    if (method_exists($cstyle, 'getGridSpan') && $cstyle->getGridSpan()) {
                        $cellAttrs['colspan'] = (int) $cstyle->getGridSpan();
                    }
                    if (method_exists($cstyle, 'getVMerge')) {
                        $vm = $cstyle->getVMerge();
                        // PHPWord doesn't expose computed rowspan; leave as 1.
                    }
                }
                $w = $cell->getWidth();
                if ($w) $cellAttrs['colwidth'] = [(int) round($w / 15)]; // twip→px approx (1px ≈ 15 twips)
                if ($cellBg !== null) $cellAttrs['backgroundColor'] = $cellBg;
                $cellsOut[] = ['type' => $this->nodeType('tableCell'), 'attrs' => $cellAttrs, 'content' => $cellNodes];
            }
            $rowsOut[] = ['type' => $this->nodeType('tableRow'), 'content' => $cellsOut];
        }
        $tableNode = ['type' => $this->nodeType('table'), 'content' => $rowsOut];
        if ($tableDir !== null) $tableNode['attrs'] = ['dir' => $tableDir];
        return $tableNode;
    }

    private function tableDir(Table $tbl): ?string
    {
        $style = $tbl->getStyle();
        if ($style instanceof TableStyle && method_exists($style, 'isBidiVisual')) {
            $bv = $style->isBidiVisual();
            if ($bv === true) return 'rtl';
            if ($bv === false) return 'ltr';
        }
        return $this->config->defaultRtl ? 'rtl' : null;
    }

    private function cellBackgroundColor(Cell $cell): ?string
    {
        $style = $cell->getStyle();
        if (!is_object($style)) return null;
        // Prefer Shading::getFill() (the actual <w:shd w:fill>); fall back to BgColor.
        if (method_exists($style, 'getShading')) {
            $shd = $style->getShading();
            if (is_object($shd) && method_exists($shd, 'getFill')) {
                $fill = $shd->getFill();
                if ($fill) {
                    $hex = $this->colorToHex((string) $fill);
                    if ($hex) return $hex;
                }
            }
        }
        if (method_exists($style, 'getBgColor')) {
            $bg = $style->getBgColor();
            if ($bg) {
                $hex = $this->colorToHex((string) $bg);
                if ($hex) return $hex;
            }
        }
        return null;
    }

    /**
     * Sandbox's tableCell extension may not honor attrs.backgroundColor; mirror
     * the color into a textStyle mark on every text node in the cell so the
     * RichTextStyle extension paints inline-block spans.
     */
    private function applyCellBackgroundFallback(array &$nodes, string $hex): void
    {
        foreach ($nodes as &$node) {
            if (!is_array($node)) continue;
            if (($node['type'] ?? null) === 'text') {
                $marks = $node['marks'] ?? [];
                $found = false;
                foreach ($marks as &$m) {
                    if (($m['type'] ?? null) === 'textStyle') {
                        $m['attrs'] = ($m['attrs'] ?? []) + ['backgroundColor' => $hex];
                        if (!isset($m['attrs']['backgroundColor']) || $m['attrs']['backgroundColor'] === null) {
                            $m['attrs']['backgroundColor'] = $hex;
                        }
                        $found = true;
                        break;
                    }
                }
                unset($m);
                if (!$found) {
                    $marks[] = ['type' => 'textStyle', 'attrs' => ['backgroundColor' => $hex]];
                }
                $node['marks'] = $marks;
            }
            if (isset($node['content']) && is_array($node['content'])) {
                $this->applyCellBackgroundFallback($node['content'], $hex);
            }
        }
        unset($node);
    }

    /** Heading level inferred from Word paragraph style name (Heading1..Heading9). */
    private function headingLevelFromStyle($pStyle): ?int
    {
        if (!$pStyle instanceof ParagraphStyle) return null;
        $name = method_exists($pStyle, 'getStyleName') ? (string) $pStyle->getStyleName() : '';
        if ($name === '') return null;
        if (preg_match('/^Heading\s*([1-9])$/i', $name, $m)) {
            return min(6, max(1, (int) $m[1]));
        }
        // "Title" style → h1
        if (strcasecmp($name, 'Title') === 0) return 1;
        // "Subtitle" → h2
        if (strcasecmp($name, 'Subtitle') === 0) return 2;
        return null;
    }

    private function isStyledAsHeading(ListItemRun $li): bool
    {
        return $this->headingLevelFromStyle($li->getParagraphStyle()) !== null;
    }

    /** Resolve dir attribute ("rtl"/"ltr") for a paragraph-style-bearing element. */
    private function paragraphDir($pStyle): ?string
    {
        if (!is_object($pStyle)) return $this->config->defaultRtl ? 'rtl' : null;
        if (method_exists($pStyle, 'isBidi')) {
            $bidi = $pStyle->isBidi();
            if ($bidi === true) return 'rtl';
            if ($bidi === false) return 'ltr';
        }
        return $this->config->defaultRtl ? 'rtl' : null;
    }

    /**
     * Build common paragraph/heading attrs: textAlign, dir, indent,
     * lineHeight, marginTop, marginBottom.
     * `indent` / `marginTop` / `marginBottom` are in CSS pixels
     * (96px = 1in = 1440 twips, 20 twips = 1pt).
     */
    private function paragraphAttrs($pStyle): array
    {
        $attrs = [
            'textAlign' => $this->alignment($pStyle),
            'dir' => $this->paragraphDir($pStyle),
        ];
        $indent = $this->paragraphIndent($pStyle);
        if ($indent !== null) $attrs['indent'] = $indent;
        [$marginLeft, $marginRight] = $this->paragraphSideMargins($pStyle);
        if ($marginLeft !== null) $attrs['marginLeft'] = $marginLeft;
        if ($marginRight !== null) $attrs['marginRight'] = $marginRight;
        $lh = $this->paragraphLineHeight($pStyle);
        if ($lh !== null) $attrs['lineHeight'] = $lh;
        [$mt, $mb] = $this->paragraphMargins($pStyle);
        if ($mt !== null) $attrs['marginTop'] = $mt;
        if ($mb !== null) $attrs['marginBottom'] = $mb;
        // Default body line-height (1.7) for regular paragraphs. Skip for
        // table cells (kept compact) and TOC entries (own layout). Headings
        // strip this back out in buildHeadingNode().
        if (!isset($attrs['lineHeight']) && !$this->inTableCell) {
            $styleName = (is_object($pStyle) && method_exists($pStyle, 'getStyleName'))
                ? (string) $pStyle->getStyleName() : '';
            if (strncasecmp($styleName, 'TOC', 3) !== 0) {
                $attrs['lineHeight'] = 1.7;
            }
        }
        return $attrs;
    }

    /**
     * Resolve `<w:spacing w:line w:lineRule>` to a CSS-friendly lineHeight.
     * - `auto` → unitless multiplier (line / 240). Word stores 240 = single,
     *   360 = 1.5, 480 = double.
     * - `atLeast` / `exact` → "Xpt" (twips/20).
     * Returns null when no spacing is set.
     */
    private function paragraphLineHeight($pStyle)
    {
        if (!is_object($pStyle) || !method_exists($pStyle, 'getSpacing')) return null;
        $line = $pStyle->getSpacing();
        if ($line === null || $line === '' || (float) $line === 0.0) return null;
        $rule = method_exists($pStyle, 'getSpacingLineRule') ? $pStyle->getSpacingLineRule() : null;
        $rule = is_string($rule) ? strtolower($rule) : 'auto';
        if ($rule === 'auto' || $rule === '') {
            $mult = (float) $line / 240.0;
            if ($mult <= 0) return null;
            return (float) rtrim(rtrim(number_format($mult, 3, '.', ''), '0'), '.');
        }
        // atLeast / exact / exactly: line is in twips, 20 twips = 1pt.
        $pt = (float) $line / 20.0;
        if ($pt <= 0) return null;
        return rtrim(rtrim(number_format($pt, 2, '.', ''), '0'), '.') . 'pt';
    }

    /**
     * Read `<w:spacing w:before w:after>` (twips) and return [marginTopPx, marginBottomPx].
     * Each side is null when unset or zero.
     */
    private function paragraphMargins($pStyle): array
    {
        if (!is_object($pStyle)) return [null, null];
        $before = method_exists($pStyle, 'getSpaceBefore') ? $pStyle->getSpaceBefore() : null;
        $after  = method_exists($pStyle, 'getSpaceAfter') ? $pStyle->getSpaceAfter() : null;
        $top = ($before !== null && (float) $before > 0) ? (int) round(((float) $before) / 1440 * 96) : null;
        $bot = ($after  !== null && (float) $after  > 0) ? (int) round(((float) $after)  / 1440 * 96) : null;
        if ($top === 0) $top = null;
        if ($bot === 0) $bot = null;
        return [$top, $bot];
    }

    /** Convert ParagraphStyle indentation (twips) → CSS pixels. */
    private function paragraphIndent($pStyle): ?int
    {
        if (!is_object($pStyle) || !method_exists($pStyle, 'getIndentation')) return null;
        $ind = $pStyle->getIndentation();
        if (!is_object($ind)) return null;
        // Word stores indent in twips (1440 twips = 1 inch = 96 px).
        // Pick whichever side has a positive value; for RTL the left attribute
        // is still authored as "left" in OOXML, so we pass that through. The
        // sandbox just needs a single scalar to apply.
        $left = method_exists($ind, 'getLeft') ? $ind->getLeft() : null;
        $right = method_exists($ind, 'getRight') ? $ind->getRight() : null;
        $val = null;
        if ($left !== null && (float) $left !== 0.0) $val = (float) $left;
        elseif ($right !== null && (float) $right !== 0.0) $val = (float) $right;
        if ($val === null) return null;
        $px = (int) round($val / 1440 * 96);
        if ($px === 0) return null;
        return $px;
    }

    /**
     * Resolve `<w:ind w:left>` / `<w:ind w:right>` (twips) into separate CSS
     * pt values. Returns `[leftPt, rightPt]`, each null when the corresponding
     * side is unset/zero. Twips → pt at 20:1 (20 twips per point).
     *
     * Emitted as `marginLeft` / `marginRight` on the paragraph's attrs so
     * the sandbox walker can apply them as `margin-left`/`margin-right`
     * inline styles. For RTL paragraphs `right` is the start indent (the
     * "before-text" side) and `left` is the end indent.
     */
    private function paragraphSideMargins($pStyle): array
    {
        if (!is_object($pStyle) || !method_exists($pStyle, 'getIndentation')) return [null, null];
        $ind = $pStyle->getIndentation();
        if (!is_object($ind)) return [null, null];
        $left = method_exists($ind, 'getLeft') ? $ind->getLeft() : null;
        $right = method_exists($ind, 'getRight') ? $ind->getRight() : null;
        $leftPt = ($left !== null && (float) $left > 0)
            ? rtrim(rtrim(number_format(((float) $left) / 20.0, 2, '.', ''), '0'), '.') . 'pt'
            : null;
        $rightPt = ($right !== null && (float) $right > 0)
            ? rtrim(rtrim(number_format(((float) $right) / 20.0, 2, '.', ''), '0'), '.') . 'pt'
            : null;
        return [$leftPt, $rightPt];
    }

    /** True when the paragraph carries any non-"none" pBdr top border. */
    private function paragraphHasTopBorder($pStyle): bool
    {
        if (!is_object($pStyle)) return false;
        $size = method_exists($pStyle, 'getBorderTopSize') ? $pStyle->getBorderTopSize() : null;
        $style = method_exists($pStyle, 'getBorderTopStyle') ? $pStyle->getBorderTopStyle() : null;
        if ($size !== null && (float) $size > 0) {
            if ($style === null || $style === '' || strtolower((string) $style) !== 'none') return true;
        }
        if ($style !== null && $style !== '' && strtolower((string) $style) !== 'none') return true;
        return false;
    }

    /** Map Word alignment to TipTap textAlign. */
    private function alignment($pStyle): ?string
    {
        if (!is_object($pStyle)) return null;
        $align = null;
        if (method_exists($pStyle, 'getAlignment')) $align = $pStyle->getAlignment();
        elseif (method_exists($pStyle, 'getAlign')) $align = $pStyle->getAlign();
        if (!$align) return null;
        $align = strtolower($align);
        return match ($align) {
            'center' => 'center',
            'right' => 'right',
            'left' => 'left',
            'both', 'justify', 'lowkashida', 'highkashida', 'mediumkashida', 'distribute' => 'justify',
            'start' => null, // direction-relative; let RTL handle visually
            'end' => null,
            default => null,
        };
    }

    private function buildMarks($fontStyle): array
    {
        $marks = [];
        // When the run is just an rStyle string reference, treat the resolved
        // character style as the effective Font.
        if (is_string($fontStyle) && $fontStyle !== '') {
            $resolved = $this->resolveCharStyleFromFont($fontStyle);
            $fontStyle = $resolved;
        }
        if (!is_object($fontStyle)) return $marks;

        $parent = $this->resolveCharStyleFromFont($fontStyle);

        $name = $this->fontProp($fontStyle, $parent, 'getName');
        $size = $this->fontProp($fontStyle, $parent, 'getSize');
        $color = $this->fontProp($fontStyle, $parent, 'getColor');
        $fgColor = $this->fontProp($fontStyle, $parent, 'getFgColor');
        $bold = $this->fontFlag($fontStyle, $parent, 'isBold');
        $italic = $this->fontFlag($fontStyle, $parent, 'isItalic');
        $underline = $this->fontProp($fontStyle, $parent, 'getUnderline');
        $strike = $this->fontFlag($fontStyle, $parent, 'isStrikethrough')
            || $this->fontFlag($fontStyle, $parent, 'isDoubleStrikethrough');
        $superScript = $this->fontFlag($fontStyle, $parent, 'isSuperScript');
        $subScript = $this->fontFlag($fontStyle, $parent, 'isSubScript');
        $shadingFill = $this->fontShadingFill($fontStyle) ?? $this->fontShadingFill($parent);

        // textStyle for color/font/size/background
        $tsAttrs = [];
        if ($name) $tsAttrs['fontFamily'] = $this->mapFont((string) $name);
        if ($size) {
            // PHPWord's Word2007 reader already halves `<w:sz>` half-points
            // to whole points at READ_SIZE, so getSize() returns pt.
            // (Earlier the converter re-halved here, producing a 2× too small
            //  fontSize like "6pt" for body runs that should be 12pt.)
            $pt = (float) $size;
            $tsAttrs['fontSize'] = $this->config->fontSizeUnit === 'px'
                ? round($pt * 1.3333) . 'px'
                : rtrim(rtrim(number_format($pt, 2, '.', ''), '0'), '.') . 'pt';
        }
        if ($color) {
            $hex = $this->colorToHex((string) $color);
            if ($hex) $tsAttrs['color'] = $hex;
        }
        // Run shading fill (rPr/<w:shd w:fill>) takes precedence over the
        // legacy `fgColor` highlight name when both are set.
        if ($shadingFill) {
            $hex = $this->colorToHex((string) $shadingFill);
            if ($hex) $tsAttrs['backgroundColor'] = $hex;
        }
        if (!isset($tsAttrs['backgroundColor']) && $fgColor) {
            $hl = $this->wordHighlightToHex((string) $fgColor);
            if ($hl) $tsAttrs['backgroundColor'] = $hl;
        }

        if (!empty($tsAttrs)) {
            $marks[] = ['type' => 'textStyle', 'attrs' => $tsAttrs];
        }

        if ($bold) $marks[] = ['type' => 'bold'];
        if ($italic) $marks[] = ['type' => 'italic'];
        if ($underline && strtolower((string) $underline) !== 'none') $marks[] = ['type' => 'underline'];
        if ($strike) $marks[] = ['type' => 'strike'];
        // Superscript/Subscript (TipTap has these as separate marks if loaded)
        if ($superScript) $marks[] = ['type' => 'superscript'];
        if ($subScript) $marks[] = ['type' => 'subscript'];

        return $marks;
    }

    /**
     * Read a getter on the run-local Font, falling back to the resolved
     * character-style Font when the run-local value is null/empty/zero.
     * @param mixed $local Font|null
     * @param mixed $parent Font|null
     */
    private function fontProp($local, $parent, string $getter)
    {
        $val = (is_object($local) && method_exists($local, $getter)) ? $local->$getter() : null;
        if ($val !== null && $val !== '' && $val !== 0 && $val !== '0') return $val;
        if (is_object($parent) && method_exists($parent, $getter)) {
            $pv = $parent->$getter();
            if ($pv !== null && $pv !== '' && $pv !== 0 && $pv !== '0') return $pv;
        }
        return null;
    }

    /**
     * Boolean-flag variant of fontProp: a true on the run or the resolved
     * character style wins. False is treated as a deliberate override
     * (e.g. `<w:b w:val="0"/>` switching off bold from the parent style).
     */
    private function fontFlag($local, $parent, string $getter): bool
    {
        $local_val = null;
        if (is_object($local) && method_exists($local, $getter)) $local_val = $local->$getter();
        if ($local_val === true) return true;
        if ($local_val === false) return false;
        if (is_object($parent) && method_exists($parent, $getter)) {
            return $parent->$getter() === true;
        }
        return false;
    }

    /**
     * Read `<w:shd w:fill>` from a Font's Shading object, if any. PHPWord's
     * Word2007 reader doesn't currently populate run shading from docx, but
     * user-built styles (or future reader changes) may carry it.
     * @param mixed $fontStyle Font|null
     */
    private function fontShadingFill($fontStyle): ?string
    {
        if (!is_object($fontStyle) || !method_exists($fontStyle, 'getShading')) return null;
        $shd = $fontStyle->getShading();
        if (!is_object($shd) || !method_exists($shd, 'getFill')) return null;
        $fill = $shd->getFill();
        return ($fill === null || $fill === '') ? null : (string) $fill;
    }

    private function mapFont(string $name): string
    {
        $name = trim($name, " \t\n\r\0\x0B\"'");
        if (empty($this->config->allowedFonts)) return $name;
        $lower = strtolower($name);
        foreach ($this->config->allowedFonts as $allowed) {
            if (strtolower($allowed) === $lower) return $allowed;
        }
        return $this->config->defaultFont ?: $name;
    }

    private function colorToHex(string $color): ?string
    {
        $c = trim($color);
        if ($c === '' || strtolower($c) === 'auto') return null;
        if ($c[0] === '#') return strtolower($c);
        if (preg_match('/^[0-9a-fA-F]{6}$/', $c)) return '#' . strtolower($c);
        if (preg_match('/^[0-9a-fA-F]{3}$/', $c)) return '#' . strtolower($c[0].$c[0].$c[1].$c[1].$c[2].$c[2]);
        if (preg_match('/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $c, $m)) {
            return sprintf('#%02x%02x%02x', (int)$m[1], (int)$m[2], (int)$m[3]);
        }
        $named = ['black'=>'#000000','white'=>'#ffffff','red'=>'#ff0000','green'=>'#008000','blue'=>'#0000ff','yellow'=>'#ffff00','cyan'=>'#00ffff','magenta'=>'#ff00ff','gray'=>'#808080','grey'=>'#808080'];
        $low = strtolower($c);
        return $named[$low] ?? null;
    }

    private function wordHighlightToHex(string $name): ?string
    {
        $map = [
            'yellow'=>'#ffff00','green'=>'#00ff00','cyan'=>'#00ffff','magenta'=>'#ff00ff',
            'blue'=>'#0000ff','red'=>'#ff0000','darkBlue'=>'#00008b','darkCyan'=>'#008b8b',
            'darkGreen'=>'#006400','darkMagenta'=>'#8b008b','darkRed'=>'#8b0000','darkYellow'=>'#808000',
            'darkGray'=>'#a9a9a9','lightGray'=>'#d3d3d3','black'=>'#000000','white'=>'#ffffff',
            'none'=>null,
        ];
        if (array_key_exists($name, $map)) return $map[$name];
        return $this->colorToHex($name);
    }

    private function decodeText(string $t): string
    {
        return html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Merge consecutive text nodes that have identical marks. */
    private function mergeAdjacentText(array $nodes): array
    {
        $out = [];
        $cur = null;
        foreach ($nodes as $n) {
            if (($n['type'] ?? null) === 'text') {
                if ($cur !== null && ($cur['marks'] ?? null) == ($n['marks'] ?? null)) {
                    $cur['text'] .= $n['text'];
                    continue;
                }
                if ($cur !== null) $out[] = $cur;
                $cur = $n;
            } else {
                if ($cur !== null) { $out[] = $cur; $cur = null; }
                $out[] = $n;
            }
        }
        if ($cur !== null) $out[] = $cur;
        return $out;
    }
}
