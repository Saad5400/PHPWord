# Converter gap analysis — `work/converter/TipTapConverter.php`

Each gap is scored by *frequency in this document* × *user-visibility in the rendered output*. "Visibility" is judged against the reference page renders (`work/reference-pages/page-01.png` … `page-16.png`).

Severity legend:
- 🔴 **critical**: fundamentally breaks the document (data loss or unreadable layout)
- 🟠 **high**: very visible style/layout drift
- 🟡 **medium**: noticeable but not data-loss
- 🟢 **low**: cosmetic / edge case

---

## 🔴 1. Table of contents is silently dropped (128 entries)

- **Frequency**: 128 paragraphs (16 TOC1 + 111 TOC3 + 1 TOCHeading) at the start of the body.
- **Visibility**: pages 2–4 of the reference renders are **entirely TOC**. The converter omits all of them.
- **Root cause**: PHPWord's docx reader does not surface paragraphs with style `TOC1`/`TOC3`/`TOCHeading` as `TextRun`/`ListItemRun` — they don't appear in `Section::getElements()` at all. The converter never has a chance to see them.
- **Fix direction**: either (a) re-parse the raw `<w:p>` for TOC-styled paragraphs and inject them as headings/list items, or (b) skip rendering the TOC and let TipTap rebuild it from the heading tree (preferred for an editable TipTap doc, but means dropping the TOC content silently — must be a deliberate decision, not the current accidental loss).

## 🔴 2. Hyperlinks are dropped (127 internal links)

- **Frequency**: 127 `<w:hyperlink w:anchor="_Toc#####">` in the body XML. PHPWord exposes 0 of them as `Link` elements.
- **Visibility**: the entire TOC is clickable in the reference; in the converter output there's nothing to click.
- **Root cause**: same as #1 — these hyperlinks are *inside* TOC paragraphs that PHPWord drops. There are also zero external links in this doc, so the `Link` branch in the converter never fires.
- **Fix direction**: tied to #1 — handling the TOC paragraphs revives the links. For external `<w:hyperlink r:id="...">` (none in this doc but common elsewhere) PHPWord *does* surface them, and the converter already handles them.

## 🟠 3. Tab characters are dropped (249 occurrences)

- **Frequency**: 249 `<w:tab/>` runs across 124 paragraphs that define custom tab stops.
- **Visibility**: most are inside the TOC ("title …………… page-number" pattern) — combined with #1, the dotted leaders + page numbers vanish. But there are also tabs in body paragraphs and table cells.
- **Root cause**: PHPWord exposes tabs as a flow element the converter doesn't handle; `convertInlineRun()` only inspects `Text`, `Link`, `TextBreak`, `Image`, `Footnote`, `TextRun` types.
- **Fix direction**: emit a literal `\t` text node (TipTap doesn't model tab stops, but at least the visual gap is preserved), or render an em-space sequence.

## 🟠 4. Headers and footers are not extracted at all

- **Frequency**: 3 headers (incl. "DRAFT" watermark drawing on first page) + 2 footers (each containing a 1-row table with page numbering text "رقم الصفحة … من …").
- **Visibility**: every reference page shows the header/footer chrome.
- **Root cause**: the converter walks `phpWord->getSections()` but never asks for `Section::getHeaders()` / `getFooters()`. PHPWord *does* expose these.
- **Fix direction**: explicitly out of scope for an editable TipTap document body, but the omission should be deliberate. If the consumer wants header/footer captured (e.g. metadata fields for "form name", "issue date"), the converter needs an opt-in.

## 🟠 5. RTL direction is never emitted

- **Frequency**: 782 paragraphs flagged `<w:bidi/>`, 3,238 runs flagged `<w:rtl/>`, 9 tables flagged `<w:bidiVisual/>` — i.e. essentially the entire document.
- **Visibility**: without `dir="rtl"` on the rendered TipTap nodes (or wrapping document), Arabic punctuation (`،`, `؛`, brackets, parentheses) renders in the wrong place. List bullets / numbers also end up on the wrong side.
- **Root cause**: the converter's TipTap output has no `dir` attribute. `alignment()` even maps `start`/`end` to `null` to "let RTL handle visually", but RTL never gets handled.
- **Fix direction**: either set a doc-level `dir` attr on the TipTap doc, or add per-paragraph `dir` attrs when `<w:bidi/>` is present. Default-to-RTL based on document language (`ar-SA` in `docDefaults`) is a defensible global toggle.

## 🟠 6. Paragraph indentation is dropped (147 paragraphs)

- **Frequency**: 147 `<w:ind>` declarations.
- **Visibility**: nested-list indentation (depth 1 / depth 2 items), block quotes, indented intro paragraphs all become left-flush.
- **Root cause**: not read.
- **Fix direction**: emit `attrs.indent` (or `marginRight` for RTL) on `paragraph` and `heading` nodes.

## 🟠 7. Multi-level numbered headings lose their numbers

- **Frequency**: 127 numbered Heading paragraphs use numbering chains like `%1.%2.%3.` that produce visible labels (e.g. "1.2.3 خطوات الإجراء").
- **Visibility**: in the reference, every Heading3 has a multi-level number prefix. In the converter output the heading text appears with no number.
- **Root cause**: the converter promotes `ListItemRun` styled as Heading to a TipTap `heading` node (correct) and discards the `ListItemRun`'s implicit numbering. There's no mechanism to compute the running counter.
- **Fix direction**: either (a) accept the loss (TipTap can't render Word-style multi-level numbering) and document it, or (b) precompute the number for each heading at parse time and prefix it as text. Option (b) requires implementing Word's level-restart / continueNumbering rules — non-trivial.

## 🟠 8. Run-level character styles (`<w:rStyle>`) are ignored — 755 occurrences

- **Frequency**: 752 `Hyperlink` rStyle (in dropped TOC), 2 `CommentReference`, 1 `BodyTextChar`.
- **Visibility**: in this doc the impact is small once #1/#2 are fixed — but the converter has *no* path that inspects `rStyle` at all, so any character-style-based formatting (e.g., a custom "Caution" character style with red bold) is lost on every document.
- **Fix direction**: when building marks, also resolve the `<w:rStyle>` to the corresponding character style in `styles.xml` and apply its rPr.

## 🟡 9. Paragraph borders are dropped (110 occurrences)

- **Frequency**: 110 `<w:pBdr>` — most are top-border on Heading3 paragraphs, used as visual section separators.
- **Visibility**: the reference shows a horizontal rule above each Heading3; converter output has none.
- **Root cause**: not read.
- **Fix direction**: emit `attrs.borderTop` on the heading node, or insert a `horizontalRule` before headings that carry a top border.

## 🟡 10. Paragraph & run shading are dropped

- **Frequency**: 35 paragraph-level `<w:shd>` (in pPr) + 245 run-level `<w:shd>` (in rPr).
- **Visibility**: highlighted callout paragraphs and shaded inline runs lose their background. The converter does read `getFgColor()` (which is the *highlight name* in PHPWord) but not the run-level fill from `<w:shd>`.
- **Fix direction**: read `<w:shd w:fill>` from the run's rPr, fall back to highlight name.

## 🟡 11. Caps / smallCaps / vertAlign

- **Frequency**: 16 `<w:caps/>` (uppercase), 0 smallCaps, 0 vertAlign.
- **Visibility**: 16 capitalized text runs render lowercase.
- **Fix direction**: when `<w:caps/>` is set, emit `text-transform: uppercase` via a textStyle mark, or pre-uppercase the text.

## 🟡 12. Line spacing is dropped

- **Frequency**: spacing values `14`, `259`, `276` with rule `auto`/`atLeast`.
- **Visibility**: vertical density of paragraphs differs subtly from reference.
- **Fix direction**: `attrs.lineHeight` on paragraph nodes.

## 🟡 13. SDT (content controls) — 7 occurrences

- **Frequency**: 7 rich-text content controls on the cover page (placeholder text like "(وفقًا لمنصة اعتماد)").
- **Visibility**: in this doc, PHPWord *does* unwrap most sdtContent so the inner runs are surfaced via TextRun — but it's inconsistent. Some text inside SDTs may be missing depending on PHPWord's reader version.
- **Fix direction**: if any SDT content goes missing, parse `<w:sdtContent>` directly from raw XML.

## 🟢 14. Table borders / cell shading / direction

- **Frequency**: every cell in all 9 tables has explicit borders and shading.
- **Visibility**: tables render unstyled (no borders) in the converter output.
- **Fix direction**: emit `attrs.style` on tableCell / table with borders + background. Also propagate `<w:bidiVisual/>` to a table-level `dir="rtl"` so column order matches.

## 🟢 15. vMerge (rowspan)

- **Frequency**: 0 in this document.
- **Visibility**: n/a here — but the converter has a comment acknowledging vMerge is left as 1. If the next test document uses it, table layout breaks.
- **Fix direction**: track vMerge `restart`/`continue` across rows and compute rowspan.

## 🟢 16. Bookmarks (anchor IDs)

- **Frequency**: 571 `<w:bookmarkStart>` (507 `_Toc*`, 4 `_Ref*`, 60 `_Hlk*` / `_GoBack`).
- **Visibility**: zero direct visibility, but #2 cannot fully work without these — internal links resolve to bookmark anchors.
- **Fix direction**: when emitting headings/paragraphs that contain a `<w:bookmarkStart>`, attach an `id` attribute so internal hyperlinks can target them.

## 🟢 17. Page-numbering fields in footers

- **Frequency**: 1 PAGE + 1 NUMPAGES per footer (×2 footers).
- **Visibility**: only relevant if headers/footers are exported (#4).
- **Fix direction**: render as static text (`{page}`, `{totalPages}`) or evaluate to `?` placeholders.

---

## Out of scope for this document (no instances found)

- Drawings / shapes / SmartArt in the body (0)
- Embedded images (0 — even though the converter has Image handling)
- Footnotes / endnotes (separators only, 0 references)
- Comments (0)
- Tracked changes (0)
- OLE objects (0)
- MathML / equations (0)
- `<w:fldSimple>` simple fields (0 — all fields are complex `fldChar`-wrapped)
- Multi-column layout (single column)
- `<w:pageBreakBefore/>` (0)
- Italic / superscript / subscript / smallCaps / dstrike (0)

These will surface in other test docs, but tackling them now without a representative example is premature.

---

## Suggested ordering for iteration 1

1. **#5 RTL direction** — global flip, single attr, biggest visual win.
2. **#3 Tab characters** — one-line fix, restores TOC alignment + body tabs.
3. **#1 + #2 TOC + hyperlinks** — joint fix; needs raw XML fallback for TOC paragraphs.
4. **#6 Indentation** — small, restores nested-list indent.
5. **#9 Paragraph borders** — restores heading separators.
6. **#10 Shading** — completes the heading-callout look.

Items 7, 11–17 can be deferred without major visual loss given the current test document.
