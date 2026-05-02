<?php

namespace PhpWord\TipTap;

/**
 * Raw-XML companion to PHPWord. Reads word/document.xml directly and surfaces
 * the things PHPWord drops or doesn't expose: TOC entries, bookmarks anchored
 * to headings, SDT placeholder runs, and multi-level heading numbers from
 * numbering.xml.
 *
 * Parsing is deliberately regex-based — DOMDocument over a 1.4 MB body
 * triples conversion time and we only need a handful of features.
 */
class RawXmlIndex
{
    /** @var string Raw document.xml contents. */
    public string $xml = '';

    /** @var array<int,array{xml:string,start:int,end:int}> One entry per <w:p> in document order. */
    public array $paragraphs = [];

    /** @var array<int,array{level:int,anchor:?string,text:string,page:?string}> TOC entries in source order. */
    public array $tocEntries = [];

    /** @var array<string,string> bookmark name (e.g. "_Toc38563304") → joined heading text. */
    public array $bookmarkText = [];

    /** @var array<string,string> heading text (joined) → bookmark anchor name. */
    public array $textToAnchor = [];

    /**
     * @var array<string,bool> heading text → true when any run inside that
     * heading paragraph carries `<w:u>` with a non-"none" value. Backup for
     * cases where PHPWord's reader misses the underline at the run level.
     */
    public array $headingUnderlines = [];

    /** Cached `word/styles.xml` (read at construct time) — used to discover
     *  paragraph styles whose definition carries `<w:pageBreakBefore/>`. */
    private string $stylesXmlCached = '';

    /** Cached `word/numbering.xml` (read at construct time). */
    private string $numberingXmlCached = '';

    /**
     * @var array<string,list<string>> heading text → ordered list of anchors.
     * When the same heading text appears twice (e.g. "موقع العمل" appears as
     * Heading3 in two different sections), the TOC has two distinct entries
     * pointing to two distinct anchors. The converter consumes from this
     * list in document order so the Nth occurrence resolves to the Nth anchor.
     */
    public array $textToAnchors = [];

    /** @var array<string,string> heading text → computed multi-level number ("1.", "1.2.", "2.1.3."). */
    public array $headingNumbers = [];

    /** @var array<string,string> joined-paragraph-signature → trailing SDT placeholder text. */
    public array $sdtPlaceholders = [];

    /** @var string|null TOC heading paragraph text (e.g. "الفهرس"). */
    public ?string $tocHeadingText = null;

    /**
     * @var array<int,array{text:string,style:?string}> Cover-page section header
     * lines extracted from word/header2.xml (the default header). Each entry is
     * a single visible text line in document order, e.g. "المملكة العربية السعودية".
     */
    public array $headerLines = [];

    /**
     * @var array<int,array{text:string,style:?string}> Footer lines from
     * word/footer1.xml. Page-number fields render as their literal "1"/"58".
     */
    public array $footerLines = [];

    /**
     * The first embedded image found in any header part, encoded as a TipTap-
     * ready data URL plus pixel dimensions. When the source's "logo box" is a
     * real image (`<a:blip r:embed>`) this carries it; when the box is a
     * DrawingML shape with no embedded image (as in التشغيل-والصيانة.docx),
     * this stays null and the converter falls back to a text placeholder.
     *
     * @var array{src:string,width:?int,height:?int}|null
     */
    public ?array $headerLogo = null;

    public function __construct(string $docxPath)
    {
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException("Cannot open docx: $docxPath");
        }
        $this->xml = (string) $zip->getFromName('word/document.xml');
        $this->stylesXmlCached = (string) ($zip->getFromName('word/styles.xml') ?: '');
        $this->numberingXmlCached = (string) ($zip->getFromName('word/numbering.xml') ?: '');
        // Header/footer parts. We prefer the default (non-first/non-even) variant
        // because that's what every page after the cover uses; for this doc all
        // three header variants share the same logo block. Footer1.xml is the
        // default footer.
        $headerName = null;
        foreach (['word/header2.xml', 'word/header1.xml', 'word/header3.xml'] as $candidate) {
            if ($zip->locateName($candidate) !== false) { $headerName = $candidate; break; }
        }
        $headerXml = $headerName !== null ? (string) $zip->getFromName($headerName) : '';
        $footerXml = (string) ($zip->getFromName('word/footer1.xml') ?: '');
        if ($headerName !== null && $headerXml !== '') {
            $this->headerLogo = $this->extractHeaderLogo($zip, $headerName, $headerXml);
        }
        $zip->close();

        $this->splitParagraphs();
        $this->extractTocEntries();
        $this->extractBookmarks();
        $this->extractSdtPlaceholders();
        $this->extractHeadingUnderlines();
        if ($headerXml !== '') $this->headerLines = $this->extractPartLines($headerXml, true);
        if ($footerXml !== '') $this->footerLines = $this->extractPartLines($footerXml, false);
        // Populate heading numbers from the TOC field's rendered output
        // (Word stamped the multi-level numbers there at last regen).
        // The TOC is the AUTHORITATIVE source for which anchor a heading
        // resolves to: heading paragraphs often carry multiple stale _Toc*
        // bookmarks from older TOC regens, and extractBookmarks() keeps the
        // first-seen one — which may not be the one the current TOC field
        // actually links to. Override textToAnchor with the TOC's anchor.
        foreach ($this->tocEntries as $e) {
            if (empty($e['text'])) continue;
            if (!empty($e['number']) && !isset($this->headingNumbers[$e['text']])) {
                $this->headingNumbers[$e['text']] = $e['number'];
            }
            if (!empty($e['anchor'])) {
                $this->textToAnchor[$e['text']] = $e['anchor'];
                $this->textToAnchors[$e['text']][] = $e['anchor'];
                if (!isset($this->bookmarkText[$e['anchor']])) {
                    $this->bookmarkText[$e['anchor']] = $e['text'];
                }
            }
        }
        // Fallback: walk numbering.xml for any heading whose text the TOC
        // didn't supply a number for (e.g. doc with stale TOC field).
        if ($this->numberingXmlCached !== '') {
            $this->buildHeadingNumbersFromNumbering($this->numberingXmlCached);
        }
    }

    /**
     * Indexes (into `$this->paragraphs`) where a hard page break should land,
     * along with where the break sits relative to the paragraph. Sources:
     *  - `<w:br w:type="page"/>` inside a paragraph → page break AFTER that
     *    paragraph (PHPWord can't surface this through TextBreak).
     *  - `<w:pageBreakBefore/>` inline in `<w:pPr>` → page break BEFORE it.
     *  - Paragraphs whose pStyle's definition in styles.xml carries
     *    `<w:pageBreakBefore/>` (e.g. Heading1) → page break BEFORE.
     *
     * Returns text signatures (normalized joined text) so the converter can
     * match against its emitted node tree without having to track raw indexes
     * past elements that PHPWord groups (tables, lists) or drops (SDT, TOC).
     *
     * @return array{after:array<string,bool>,before:array<string,bool>}
     *   `after`  — text signatures of paragraphs after which a pageBreak goes
     *   `before` — text signatures of paragraphs before which a pageBreak goes
     */
    public function getPageBreakSignatures(): array
    {
        $stylesWithPbb = $this->stylesWithPageBreakBefore();
        $after = [];
        $before = [];
        foreach ($this->paragraphs as $idx => $p) {
            $body = $p['xml'];
            $sig = $this->signature($this->extractAllText($body));
            // <w:br w:type="page"/> inside the paragraph — break after it
            if (preg_match('#<w:br[^>]*w:type="page"#', $body)) {
                if ($sig !== '') {
                    $after[$sig] = true;
                } elseif ($idx + 1 < count($this->paragraphs)) {
                    // Empty para containing only the br — fall through to the next.
                    $nextSig = $this->signature($this->extractAllText($this->paragraphs[$idx + 1]['xml']));
                    if ($nextSig !== '') $before[$nextSig] = true;
                }
            }
            // Inline <w:pageBreakBefore/> in pPr — break before
            if (preg_match('#<w:pPr\b[^>]*>.*?<w:pageBreakBefore\b#s', $body)) {
                if ($sig !== '') $before[$sig] = true;
            }
            // Paragraph style carries pageBreakBefore (e.g. Heading1)
            if (preg_match('#<w:pStyle w:val="([^"]+)"#', $body, $m)) {
                if (isset($stylesWithPbb[$m[1]]) && $sig !== '') {
                    $before[$sig] = true;
                }
            }
        }
        return ['after' => $after, 'before' => $before];
    }

    /** Returns paragraph indexes that contain a `<w:br w:type="page"/>`. */
    public function getPageBreakParagraphIndexes(): array
    {
        $idxs = [];
        foreach ($this->paragraphs as $idx => $p) {
            if (preg_match('#<w:br[^>]*w:type="page"#', $p['xml'])) {
                $idxs[] = $idx;
            }
        }
        return $idxs;
    }

    /** Read styles.xml once; return paragraph styles whose definition carries
     *  `<w:pageBreakBefore/>` directly in their `<w:pPr>`. */
    private function stylesWithPageBreakBefore(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = [];
        $stylesXml = '';
        // Already-closed zip — re-open via the docx path tracked in the constructor.
        // We didn't keep the path; instead, peek at headerLogo's metadata isn't
        // available, so do this from $this->xml relationships? Simplest: capture
        // styles.xml at construct time. Fall through with an empty cache when
        // styles aren't reachable.
        if (isset($this->stylesXmlCached)) {
            $stylesXml = $this->stylesXmlCached;
        }
        if ($stylesXml === '') return $cache;
        if (!preg_match_all('#<w:style[^>]*w:styleId="([^"]+)"[^>]*>(.*?)</w:style>#s', $stylesXml, $m, PREG_SET_ORDER)) {
            return $cache;
        }
        foreach ($m as $hit) {
            if (preg_match('#<w:pageBreakBefore\b#', $hit[2])) {
                $cache[$hit[1]] = true;
            }
        }
        return $cache;
    }

    /** Normalise paragraph text to a stable signature for cross-source matching. */
    private function signature(string $text): string
    {
        $sig = preg_replace('/\s+/u', ' ', trim($text));
        return $sig === null ? '' : $sig;
    }

    /**
     * Pop the next anchor for a heading text. Used by the converter to walk
     * TOC entries in document order — when a heading text appears N times,
     * the Nth converted heading resolves to the Nth TOC anchor.
     */
    public function consumeAnchor(string $text): ?string
    {
        if (!isset($this->textToAnchors[$text]) || empty($this->textToAnchors[$text])) {
            return $this->textToAnchor[$text] ?? null;
        }
        return array_shift($this->textToAnchors[$text]);
    }

    private function splitParagraphs(): void
    {
        // Capture every <w:p ...> ... </w:p> in document order.
        if (!preg_match_all('#<w:p\b[^>]*>.*?</w:p>#s', $this->xml, $m, PREG_OFFSET_CAPTURE)) return;
        foreach ($m[0] as $hit) {
            $this->paragraphs[] = ['xml' => $hit[0], 'start' => $hit[1], 'end' => $hit[1] + strlen($hit[0])];
        }
    }

    private function extractTocEntries(): void
    {
        foreach ($this->paragraphs as $p) {
            if (!preg_match('#<w:pStyle w:val="(TOC[A-Za-z0-9]*)"#', $p['xml'], $sm)) continue;
            $style = $sm[1];
            if ($style === 'TOCHeading') {
                $this->tocHeadingText = $this->extractAllText($p['xml']);
                continue;
            }
            $level = 1;
            if (preg_match('#TOC(\d+)$#', $style, $lm)) $level = (int) $lm[1];
            // Each TOC entry has a single hyperlink containing the heading text + tab + page-number.
            // The heading-text run is the first non-empty <w:t> inside the hyperlink.
            $anchor = null;
            $text = '';
            $page = null;
            $headingNumber = null;
            if (preg_match('#<w:hyperlink\s+w:anchor="([^"]+)"[^>]*>(.*?)</w:hyperlink>#s', $p['xml'], $hm)) {
                $anchor = $hm[1];
                $body = $hm[2];
                // Walk runs in order. For each <w:r>, classify by its rPr:
                // - <w:webHidden/> after a fldChar separate → page number (or PAGEREF result)
                // - first non-webHidden run with a digit-only <w:t> → heading number prefix
                // - everything else, joined → heading text
                $runs = [];
                if (preg_match_all('#<w:r\b[^>]*>(.*?)</w:r>#s', $body, $rms, PREG_SET_ORDER)) {
                    $afterSeparate = false;
                    foreach ($rms as $rm) {
                        $rbody = $rm[1];
                        if (preg_match('#<w:fldChar\s+w:fldCharType="separate"#', $rbody)) {
                            $afterSeparate = true;
                            continue;
                        }
                        if (preg_match('#<w:fldChar\s+w:fldCharType="end"#', $rbody)) {
                            $afterSeparate = false;
                            continue;
                        }
                        if (preg_match('#<w:fldChar#', $rbody) || preg_match('#<w:instrText#', $rbody)) continue;
                        // Walk the run's children in order: <w:t> bytes plus <w:tab/> markers.
                        $rText = '';
                        $hasTab = false;
                        if (preg_match_all('#<w:(t|tab)\b[^/>]*(?:/>|>([^<]*)</w:\1>)#', $rbody, $tm, PREG_SET_ORDER)) {
                            foreach ($tm as $piece) {
                                if ($piece[1] === 'tab') { $rText .= "\t"; $hasTab = true; }
                                else $rText .= html_entity_decode($piece[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            }
                        }
                        if ($rText === '' && !$hasTab) continue;
                        $isWebHidden = (bool) preg_match('#<w:webHidden/>#', $rbody);
                        $runs[] = ['text' => $rText, 'webHidden' => $isWebHidden, 'afterSep' => $afterSeparate];
                    }
                }
                for ($i = count($runs) - 1; $i >= 0; $i--) {
                    if ($runs[$i]['afterSep']) {
                        $page = trim($runs[$i]['text']);
                        unset($runs[$i]);
                        break;
                    }
                }
                $runs = array_values($runs);
                $textPieces = [];
                foreach ($runs as $r) {
                    if ($r['webHidden']) continue;
                    $textPieces[] = $r['text'];
                }
                $combined = implode('', $textPieces);
                // Lead pattern for numbered headings: "<number>\t<heading-text>".
                // Split on the FIRST tab — if the lead is a number-ish prefix, set headingNumber.
                if (strpos($combined, "\t") !== false) {
                    [$lead, $rest] = explode("\t", $combined, 2);
                    $lead = trim($lead);
                    $rest = trim(str_replace("\t", '', $rest));
                    if ($lead !== '' && preg_match('#^[0-9A-Za-zأ-ي.\-]+\.?$#u', $lead)) {
                        $headingNumber = $lead;
                        $text = $rest;
                    } else {
                        $text = trim(str_replace("\t", ' ', $combined));
                    }
                } else {
                    $text = trim($combined);
                }
            } else {
                $text = trim($this->extractAllText($p['xml']));
            }
            if ($text === '') continue;
            $this->tocEntries[] = [
                'level' => $level,
                'anchor' => $anchor,
                'text' => $text,
                'page' => $page,
                'number' => $headingNumber,
            ];
        }
    }

    private function extractBookmarks(): void
    {
        foreach ($this->paragraphs as $p) {
            $body = $p['xml'];
            // Skip TOC paragraphs — their bookmarks are not the targets we care about.
            if (preg_match('#<w:pStyle w:val="TOC[A-Za-z0-9]*"#', $body)) continue;
            // Only headings/titles host the _Toc* anchors that the TOC links resolve to.
            // Other paragraphs may have stale _Toc bookmarks left over from old TOCs.
            $isHeading = (bool) preg_match('#<w:pStyle w:val="(Heading\d|Title)"#', $body);
            if (!$isHeading) continue;
            if (!preg_match_all('#<w:bookmarkStart\s+[^>]*w:name="(_Toc[^"]+)"#', $body, $bms)) continue;
            $text = trim($this->extractAllText($body));
            if ($text === '') continue;
            foreach ($bms[1] as $name) {
                $this->bookmarkText[$name] = $text;
                if (!isset($this->textToAnchor[$text])) {
                    $this->textToAnchor[$text] = $name;
                }
            }
        }
    }

    /**
     * Map heading text → true when the heading paragraph carries an inline
     * `<w:u>` with a non-"none" value. Linked character styles (HeadingNChar)
     * sometimes carry underline that PHPWord doesn't surface on each run; this
     * is the backup signal for `buildHeadingNode()`.
     */
    private function extractHeadingUnderlines(): void
    {
        foreach ($this->paragraphs as $p) {
            $body = $p['xml'];
            if (!preg_match('#<w:pStyle w:val="(Heading\d|Title)"#', $body)) continue;
            if (!preg_match('#<w:u\s[^/>]*w:val="([^"]+)"#', $body, $m)) continue;
            if (strcasecmp($m[1], 'none') === 0) continue;
            $text = trim($this->extractAllText($body));
            if ($text === '') continue;
            $this->headingUnderlines[$text] = true;
        }
    }

    /**
     * SDT (content control) text drops at the PHPWord layer. Paragraphs that
     * mix plain runs and an SDT need the SDT's <w:t> content stitched into the
     * rendered text. We key by the joined plain text BEFORE the SDT, plus the
     * SDT text, so the converter can insert it at the right location.
     */
    private function extractSdtPlaceholders(): void
    {
        foreach ($this->paragraphs as $p) {
            $body = $p['xml'];
            if (strpos($body, '<w:sdt>') === false) continue;
            // Extract plain-run text (outside any <w:sdt>) and SDT-content text (inside).
            $plain = $this->extractTextOutsideSdt($body);
            $sdtText = $this->extractTextInsideSdt($body);
            if ($sdtText === '') continue;
            // signature: joined plain text trimmed, normalized whitespace
            $sig = preg_replace('/\s+/u', ' ', trim($plain));
            if ($sig === '') {
                // no plain text leader — key by full signature
                $sig = '__SDT_ONLY__:' . substr(preg_replace('/\s+/u', ' ', $sdtText), 0, 80);
            }
            // Only keep the first occurrence per signature
            if (!isset($this->sdtPlaceholders[$sig])) {
                $this->sdtPlaceholders[$sig] = $sdtText;
            }
        }
    }

    private function extractTextOutsideSdt(string $xml): string
    {
        // Strip every <w:sdt>...</w:sdt> (handle nested by repeated removal).
        $cleaned = $xml;
        while (preg_match('#<w:sdt>.*?</w:sdt>#s', $cleaned)) {
            $cleaned = preg_replace('#<w:sdt>(?:(?!<w:sdt>).)*?</w:sdt>#s', '', $cleaned, 1);
        }
        return $this->extractAllText($cleaned);
    }

    private function extractTextInsideSdt(string $xml): string
    {
        // Iterate top-level <w:sdt>...</w:sdt> blocks, taking the LEAF text of
        // each (innermost <w:sdtContent>'s <w:t> runs) and joining placeholders
        // with " / " — paragraphs typically have format-like layouts (DD/MM/YY,
        // X/Y/Z) and the slash separator preserves readability.
        $sdtBlocks = $this->findTopLevelSdt($xml);
        if (empty($sdtBlocks)) return '';
        $placeholders = [];
        foreach ($sdtBlocks as $block) {
            $inner = $this->innermostSdtContent($block);
            if ($inner === '') continue;
            $text = '';
            if (preg_match_all('#<w:t[^>]*>([^<]*)</w:t>#', $inner, $tm)) {
                foreach ($tm[1] as $t) $text .= html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $text = trim($text);
            if ($text !== '') $placeholders[] = $text;
        }
        if (empty($placeholders)) return '';
        if (count($placeholders) === 1) return ' ' . $placeholders[0];
        return ' ' . implode(' / ', $placeholders);
    }

    /** Return top-level <w:sdt>...</w:sdt> chunks (no nesting depth tracking — we accept the outermost). */
    private function findTopLevelSdt(string $xml): array
    {
        $blocks = [];
        $offset = 0;
        $len = strlen($xml);
        while (($start = strpos($xml, '<w:sdt>', $offset)) !== false) {
            // Walk forward, tracking <w:sdt> open/close balance.
            $depth = 1;
            $pos = $start + 7;
            while ($pos < $len && $depth > 0) {
                $nextOpen = strpos($xml, '<w:sdt>', $pos);
                $nextClose = strpos($xml, '</w:sdt>', $pos);
                if ($nextClose === false) break;
                if ($nextOpen !== false && $nextOpen < $nextClose) {
                    $depth++;
                    $pos = $nextOpen + 7;
                } else {
                    $depth--;
                    $pos = $nextClose + 8;
                }
            }
            if ($depth === 0) {
                $blocks[] = substr($xml, $start, $pos - $start);
                $offset = $pos;
            } else {
                break;
            }
        }
        return $blocks;
    }

    /** Strip nested <w:sdt> wrappers and return the deepest <w:sdtContent>. */
    private function innermostSdtContent(string $sdtBlock): string
    {
        // Repeatedly drill into sdtContent that immediately wraps another sdt.
        $body = $sdtBlock;
        while (preg_match('#<w:sdtContent>(.*)</w:sdtContent>#s', $body, $cm)) {
            $inner = $cm[1];
            // If the inner content is itself another sdt, recurse into it.
            if (strpos($inner, '<w:sdt>') !== false) {
                $body = $inner;
                continue;
            }
            return $inner;
        }
        return '';
    }

    private function extractAllText(string $xml): string
    {
        if (!preg_match_all('#<w:t[^>]*>([^<]*)</w:t>#', $xml, $m)) return '';
        $out = '';
        foreach ($m[1] as $t) {
            $out .= html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $out;
    }

    /**
     * Walk a header/footer part's XML and extract one entry per visible line.
     * - Each text-bearing element (run / drawing-text-box paragraph / cell-paragraph)
     *   that appears in document order produces one line.
     * - Consecutive identical text runs (a docx artefact: DrawingML stamps shape
     *   text twice — once in the wp:anchor body and once in the wrapping <w:p>)
     *   are deduped to a single line.
     * - PAGE/NUMPAGES result text leaks through verbatim (Word stamps "1"/"58"
     *   as the rendered field result).
     */
    private function extractPartLines(string $xml, bool $isHeader): array
    {
        // Strip <w:rPr>...</w:rPr>, <w:pPr>...</w:pPr>, and the field code
        // blocks so we don't accidentally pick up text out of paragraph
        // properties (rare, but defensive).
        $clean = preg_replace('#<w:rPr>.*?</w:rPr>#s', '', $xml) ?? $xml;
        $clean = preg_replace('#<w:pPr>.*?</w:pPr>#s', '', $clean) ?? $clean;
        $clean = preg_replace('#<w:instrText[^>]*>.*?</w:instrText>#s', '', $clean) ?? $clean;
        // Walk every <w:t>…</w:t> in document order, grouping into "lines"
        // separated by paragraph boundaries (`</w:p>`) or cell boundaries (`</w:tc>`).
        // We tokenize: split on </w:p> | </w:tc> markers so each chunk holds
        // text for one paragraph.
        $chunks = preg_split('#</w:(?:p|tc)>#', $clean);
        $lines = [];
        foreach ($chunks as $chunk) {
            // Walk runs in order. Insert a space between runs that cross a
            // <w:fldChar> boundary so PAGE/NUMPAGES results don't collide
            // with their surrounding label text ("رقم الصفحة40" → "رقم الصفحة 40").
            $text = '';
            $fragments = preg_split('#(<w:t[^>]*>[^<]*</w:t>|<w:fldChar[^/]*/>)#', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE);
            $sawSeparator = false;
            $lastWasText = false;
            foreach ($fragments as $frag) {
                if (preg_match('#<w:fldChar\s+w:fldCharType="(begin|separate|end)"#', $frag, $fcm)) {
                    if ($fcm[1] === 'separate' || $fcm[1] === 'end') $sawSeparator = true;
                    continue;
                }
                if (preg_match('#<w:t[^>]*>([^<]*)</w:t>#', $frag, $tm)) {
                    $tval = html_entity_decode($tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($sawSeparator && $lastWasText && $text !== '' && substr($text, -1) !== ' ' && (!isset($tval[0]) || $tval[0] !== ' ')) {
                        $text .= ' ';
                    }
                    $text .= $tval;
                    $lastWasText = true;
                    $sawSeparator = false;
                }
            }
            $text = trim($text);
            if ($text !== '') $lines[] = ['text' => $text, 'style' => null];
        }
        // Dedup consecutive identical lines (DrawingML shape-text duplicate).
        $deduped = [];
        $prev = null;
        foreach ($lines as $line) {
            if ($line['text'] === $prev) continue;
            $deduped[] = $line;
            $prev = $line['text'];
        }
        return $deduped;
    }

    /**
     * Find the first DrawingML picture (`<a:blip r:embed="rIdN"/>`) in a
     * header part, resolve the rId via the part's sibling .rels file, read
     * the media file out of the docx zip, and return a TipTap image-node
     * shape (`src` data URL plus optional pixel `width`/`height`). Returns
     * null when the header has no embedded picture — in this test doc the
     * "logo box" is a DrawingML rounded rectangle with no `<a:blip>`, so
     * this correctly falls through to the text placeholder.
     *
     * @return array{src:string,width:?int,height:?int}|null
     */
    private function extractHeaderLogo(\ZipArchive $zip, string $headerName, string $headerXml): ?array
    {
        if (!preg_match('#<a:blip\b[^>]*\br:embed="([^"]+)"#', $headerXml, $bm)) {
            return null;
        }
        $rId = $bm[1];

        // Header part name is e.g. "word/header2.xml"; sibling rels is "word/_rels/header2.xml.rels".
        $relsName = preg_replace('#^(.*/)([^/]+)$#', '$1_rels/$2.rels', $headerName);
        if ($relsName === null || $zip->locateName($relsName) === false) return null;
        $relsXml = (string) $zip->getFromName($relsName);
        if ($relsXml === '') return null;

        $target = null;
        if (preg_match_all('#<Relationship\b[^>]*?Id="([^"]+)"[^>]*?Target="([^"]+)"#', $relsXml, $rm, PREG_SET_ORDER)) {
            foreach ($rm as $r) {
                if ($r[1] === $rId) { $target = $r[2]; break; }
            }
        }
        if ($target === null) return null;

        // `Target` is relative to the part's directory (word/). It usually looks
        // like "media/image1.png"; resolve to "word/media/image1.png".
        $base = preg_replace('#/[^/]*$#', '/', $headerName);
        $mediaName = $this->normalisePath(($base ?? '') . $target);
        if ($zip->locateName($mediaName) === false) return null;
        $bytes = (string) $zip->getFromName($mediaName);
        if ($bytes === '') return null;

        $mime = $this->guessMimeFromName($mediaName);
        $src = 'data:' . $mime . ';base64,' . base64_encode($bytes);

        // Pull pixel dimensions from <wp:extent cx="..." cy="..."/>. EMU units;
        // 914400 EMU = 1 inch = 96 px → 9525 EMU per px.
        $width = $height = null;
        if (preg_match('#<wp:extent\s+cx="(\d+)"\s+cy="(\d+)"#', $headerXml, $em)) {
            $width = (int) round(((int) $em[1]) / 9525);
            $height = (int) round(((int) $em[2]) / 9525);
            if ($width <= 0) $width = null;
            if ($height <= 0) $height = null;
        }

        return ['src' => $src, 'width' => $width, 'height' => $height];
    }

    /** Resolve "../foo/bar" / "./baz" segments in a zip-relative path. */
    private function normalisePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') { array_pop($parts); continue; }
            $parts[] = $seg;
        }
        return implode('/', $parts);
    }

    /** Map a media file's extension to a MIME type, defaulting to image/png. */
    private function guessMimeFromName(string $name): string
    {
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            default => 'image/png',
        };
    }

    /**
     * Multi-level heading numbers from numbering.xml — kept as a fallback for
     * documents whose TOC field was never regenerated (so the rendered TOC
     * has no numbers). Walks paragraphs in document order, maintaining per-
     * (numId, ilvl) counters.
     */
    public function buildHeadingNumbersFromNumbering(string $numberingXml): void
    {
        // Map (abstractNumId, ilvl) → ['fmt'=>..., 'lvlText'=>..., 'start'=>...]
        $abstractLevels = [];
        if (preg_match_all('#<w:abstractNum\s+w:abstractNumId="(\d+)"[^>]*>(.*?)</w:abstractNum>#s', $numberingXml, $am, PREG_SET_ORDER)) {
            foreach ($am as $a) {
                $aid = (int) $a[1];
                $body = $a[2];
                if (preg_match_all('#<w:lvl\s+w:ilvl="(\d+)"[^>]*>(.*?)</w:lvl>#s', $body, $lm, PREG_SET_ORDER)) {
                    foreach ($lm as $l) {
                        $lvl = (int) $l[1];
                        $lbody = $l[2];
                        $start = 1;
                        if (preg_match('#<w:start w:val="(\d+)"#', $lbody, $sm)) $start = (int) $sm[1];
                        $fmt = 'decimal';
                        if (preg_match('#<w:numFmt w:val="([^"]+)"#', $lbody, $fm)) $fmt = $fm[1];
                        $lvlText = '';
                        if (preg_match('#<w:lvlText w:val="([^"]*)"#', $lbody, $lt)) $lvlText = $lt[1];
                        $abstractLevels[$aid][$lvl] = ['fmt' => $fmt, 'lvlText' => $lvlText, 'start' => $start];
                    }
                }
            }
        }
        // Map numId → abstractNumId
        $numToAbstract = [];
        if (preg_match_all('#<w:num\s+w:numId="(\d+)"[^>]*>(.*?)</w:num>#s', $numberingXml, $nm, PREG_SET_ORDER)) {
            foreach ($nm as $n) {
                $numId = (int) $n[1];
                if (preg_match('#<w:abstractNumId w:val="(\d+)"#', $n[2], $am2)) {
                    $numToAbstract[$numId] = (int) $am2[1];
                }
            }
        }

        // Walk paragraphs in order. For each Heading-styled paragraph with numId+ilvl,
        // increment the counter. numId=0 means "list disabled" — emit empty number.
        $counters = []; // [numId][ilvl] => current count
        foreach ($this->paragraphs as $p) {
            $body = $p['xml'];
            if (!preg_match('#<w:pStyle w:val="(Heading\d)"#', $body, $sm)) continue;
            $headingLevel = (int) substr($sm[1], -1);
            if (!preg_match('#<w:numPr>(.*?)</w:numPr>#s', $body, $npm)) continue;
            $numIdMatch = preg_match('#<w:numId w:val="(\d+)"#', $npm[1], $im);
            $ilvlMatch = preg_match('#<w:ilvl w:val="(\d+)"#', $npm[1], $ilm);
            if (!$numIdMatch) continue;
            $numId = (int) $im[1];
            $ilvl = $ilvlMatch ? (int) $ilm[1] : 0;
            if ($numId === 0) continue; // numbering disabled for this paragraph
            $abstractId = $numToAbstract[$numId] ?? null;
            if ($abstractId === null) continue;
            $levelDef = $abstractLevels[$abstractId][$ilvl] ?? null;
            if ($levelDef === null) continue;
            // Increment ilvl, reset deeper levels
            $counters[$numId][$ilvl] = ($counters[$numId][$ilvl] ?? ($levelDef['start'] - 1)) + 1;
            foreach (array_keys($counters[$numId] ?? []) as $deeper) {
                if ($deeper > $ilvl) unset($counters[$numId][$deeper]);
            }
            // Build label from lvlText template — replace %N with the counter at level N-1
            $lvlText = $levelDef['lvlText'] ?: '%' . ($ilvl + 1) . '.';
            $label = preg_replace_callback('#%(\d+)#', function ($m) use ($numId, $abstractId, $abstractLevels, $counters, $levelDef) {
                $n = (int) $m[1];
                $lvlIdx = $n - 1;
                $val = $counters[$numId][$lvlIdx] ?? null;
                if ($val === null) {
                    // Use start from def
                    $start = $abstractLevels[$abstractId][$lvlIdx]['start'] ?? 1;
                    $val = $start;
                }
                $fmt = $abstractLevels[$abstractId][$lvlIdx]['fmt'] ?? 'decimal';
                return $this->formatCounter($val, $fmt);
            }, $lvlText);
            $headingText = trim($this->extractAllText($body));
            if ($headingText === '') continue;
            // Map the trimmed heading text → label
            if (!isset($this->headingNumbers[$headingText])) {
                $this->headingNumbers[$headingText] = $label;
            }
        }
    }

    private function formatCounter(int $n, string $fmt): string
    {
        switch ($fmt) {
            case 'decimal': return (string) $n;
            case 'lowerLetter': return $this->letterCounter($n, false);
            case 'upperLetter': return $this->letterCounter($n, true);
            case 'lowerRoman': return strtolower($this->romanCounter($n));
            case 'upperRoman': return $this->romanCounter($n);
            case 'arabicAlpha':
            case 'arabicAbjad': return $this->abjadCounter($n);
            case 'none': return '';
            default: return (string) $n;
        }
    }

    private function letterCounter(int $n, bool $upper): string
    {
        $letters = '';
        $base = $upper ? 65 : 97;
        while ($n > 0) {
            $n--;
            $letters = chr($base + ($n % 26)) . $letters;
            $n = intdiv($n, 26);
        }
        return $letters ?: ($upper ? 'A' : 'a');
    }

    private function romanCounter(int $n): string
    {
        $map = [1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
        $out = '';
        foreach ($map as $v => $s) while ($n >= $v) { $out .= $s; $n -= $v; }
        return $out;
    }

    private function abjadCounter(int $n): string
    {
        // Arabic alphabet ordering used by abjad numbering: أ ب ج د ه و ز ح ط ي ...
        $alphabet = ['أ','ب','ج','د','ه','و','ز','ح','ط','ي','ك','ل','م','ن','س','ع','ف','ص','ق','ر','ش','ت','ث','خ','ذ','ض','ظ','غ'];
        if ($n <= 0) return '';
        if ($n <= count($alphabet)) return $alphabet[$n - 1];
        return (string) $n;
    }
}
