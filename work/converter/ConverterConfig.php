<?php

namespace PhpWord\TipTap;

/**
 * Tunable knobs for {@see TipTapConverter}. Pass an instance into the
 * converter constructor to override any default; the converter consumes the
 * config purely as read-only data, so a single config instance can be reused
 * across many conversions.
 *
 * Every option here used to live as a public property on TipTapConverter;
 * grouping them in a dedicated value object keeps the converter's surface
 * small and makes downstream projects' configuration explicit.
 */
class ConverterConfig
{
    /** Allow-list of font families to map run fontFamily values to. Empty = pass-through. */
    public array $allowedFonts = [];

    /** Family substituted when a run's font isn't in $allowedFonts. */
    public string $defaultFont = '';

    /** CSS unit for emitted font sizes ("pt" or "px"). */
    public string $fontSizeUnit = 'pt';

    /**
     * Treat the document as RTL by default. When true, paragraphs/headings
     * whose ParagraphStyle::isBidi() returns null still receive dir="rtl".
     */
    public bool $defaultRtl = true;

    /** Reconstruct the TOC paragraphs PHPWord drops, by parsing word/document.xml. */
    public bool $reconstructToc = true;

    /** Inject computed heading numbers ("1.", "2.") as a prefix on heading nodes. */
    public bool $prefixHeadingNumbers = true;

    /** Attach `attrs.id` to heading nodes from `<w:bookmarkStart w:name="_Toc...">`. */
    public bool $emitBookmarkIds = true;

    /** Patch SDT placeholder text into paragraphs whose plain runs match a known leader. */
    public bool $patchSdtPlaceholders = true;

    /**
     * Prepend section header lines (corp band: country / agency / form name)
     * extracted from word/header2.xml, plus a logo placeholder. Append footer
     * lines from word/footer1.xml.
     */
    public bool $injectHeaderFooter = true;

    /**
     * Documents this converter's preferred numeral system. Not consulted
     * inside convertFile() — substitution is done as a post-process pass via
     * {@see TipTapConverter::applyArabicIndicNumerals()}, which the caller
     * invokes against the returned doc tree based on this flag.
     */
    public bool $arabicIndicNumerals = true;

    /**
     * Drop all empty paragraphs from the converter's output. Word documents
     * pad with empty paragraphs for vertical layout; vertical rhythm is meant
     * to come from `attrs.marginTop`/`marginBottom` on surrounding non-empty
     * paragraphs. Set to false to preserve them.
     */
    public bool $dropEmptyParagraphs = true;

    /**
     * Word numIds whose `arabicAbjad` lists should bake Arabic-letter markers
     * (ا./ب./ج./...) into each item's text content. Other arabicAbjad lists
     * are treated as numeric and rendered via the Arabic-Indic post-pass.
     *
     * Why opt-in: `arabicAbjad` lists in our corpus often render as
     * Arabic-Indic digits in the source PDF despite the OOXML name implying
     * letters, so the safe default is numeric. List the numIds that genuinely
     * want letter markers per the source document's reference rendering.
     *
     * `arabicAlpha` lists always bake letters (no opt-in needed).
     *
     * @var int[]
     */
    public array $bakeArabicLetterMarkersForNumIds = [];

    /**
     * Rename emitted TipTap node types. Useful when a downstream plugin
     * expects a different schema name — e.g. swap `pageBreak` for
     * `customPageBreak`, or alias `table` to a fork's renamed node type.
     *
     * Defaults are an empty map (each type is emitted under its canonical
     * name). The defaults are already compatible with `tiptap-pagination-plus`
     * (uses `pageBreak`) and `tiptap-table-plus` (preserves `table`,
     * `tableRow`, `tableCell`, `tableHeader`), so override only when needed.
     *
     * @var array<string,string>
     */
    public array $nodeTypeMap = [];
}
