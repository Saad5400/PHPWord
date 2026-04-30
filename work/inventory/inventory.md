# Test document inventory

**File:** `testing-documents/التشغيل-والصيانة.docx` (10.7 MB)
**Language / direction:** Arabic, RTL — `bidi="ar-SA"` is the document default. 782 of 902 paragraphs carry an explicit `<w:bidi/>` flag; 3,238 individual runs carry `<w:rtl/>`; all 9 tables have `<w:bidiVisual/>`.
**Reference rendering:** 16 PNG pages (`work/reference-pages/page-01.png` ... `page-16.png`).

---

## 1. PHPWord top-level element counts (what the converter actually sees)

| Element        | Count |
|----------------|-------|
| `Text`         | 1967  |
| `TextRun`      | 397   |
| `ListItemRun`  | 338   |
| `TextBreak`    | 40    |
| `Table`        | 9     |
| `PageBreak`    | 3     |
| `Title`        | 1     |
| `Link`         | **0** (see §5 below — PHPWord does not surface the 127 hyperlinks in this document) |
| `Image`        | 0 (document has no inline images at all) |
| `Footnote` / `Endnote` | 0 |

Top-level container is a single `Section`.

---

## 2. Headings

The doc has **128 heading paragraphs** in the body — but PHPWord exposes only **1 `Title` element** (depth=3). The other 127 come through as `ListItemRun` with paragraph styleName `Heading1` or `Heading3`, because the underlying `<w:p>` carries both `<w:pStyle w:val="Heading*"/>` *and* `<w:numPr>`.

| pStyle    | paragraphs in document.xml | path through PHPWord |
|-----------|---------------------------:|-----------------------|
| Heading1  | 16                         | 11+3+2 = **16 ListItemRun**, 0 Title |
| Heading3  | 112                        | 111 ListItemRun + 1 Title (depth=3) |
| Heading2,4-9 | 0                       | — (none used)         |

The current converter already promotes Heading-styled `ListItemRun` to TipTap `heading` nodes via `headingLevelFromStyle()` — that path works.

Note: there is also a **TOC** at the start of the document (1× TOCHeading + 16× TOC1 + 111× TOC3 = 128 TOC entries, mirroring the 128 headings) but **PHPWord drops these entirely** — they don't appear as TextRun, ListItemRun, or anything else in `getSections()`. See gaps.md §1.

---

## 3. Paragraph styles in document.xml

Distinct `<w:pStyle w:val>` values:

| Style          | Para count |
|----------------|-----------:|
| BodyText       | 437        |
| Heading3       | 112        |
| TOC3           | 111 *(dropped by PHPWord)* |
| ListParagraph  | 51         |
| Heading1       | 16         |
| TOC1           | 16  *(dropped)* |
| NormalWeb      | 13         |
| BlockText      | 3          |
| TOCHeading     | 1   *(dropped)* |
| CommentText    | 1          |

Plus 141 paragraphs with no explicit pStyle (Normal / default).

Distinct `<w:rStyle>` (character styles):

| rStyle           | Run count |
|------------------|----------:|
| Hyperlink        | 752 *(applied to every TOC link run; converter doesn't see these)* |
| CommentReference | 2         |
| BodyTextChar     | 1         |

---

## 4. Lists

PHPWord groups consecutive numbered/bulleted paragraphs into `ListItemRun`. Total: 338 (323 at depth 0, 10 at depth 1, 5 at depth 2). 127 of those are styled as headings, leaving ~211 actual list items.

`numbering.xml` contains **83 `<w:num>`** mapping to **81 `<w:abstractNum>`**. Format breakdown across all numbering levels:

| numFmt       | level count |
|--------------|------------:|
| decimal      | 231         |
| lowerLetter  | 166         |
| lowerRoman   | 165         |
| bullet       | 95          |
| arabicAbjad  | 53 *(Arabic alphabet ordering: أ، ب، ج, ...)* |
| arabicAlpha  | 3           |
| none         | 8           |

`PHPWordList48 (numId=48)` is the most-used (68 items — the heading numbering chain).

The converter's `listTypeFor()` correctly reads `numId` → `Numbering` → first level format and decides `bulletList` vs `orderedList`. It does **not** preserve the specific format style (decimal vs arabicAbjad vs roman), and does **not** preserve `<w:lvlText>` patterns like `"%1.%2.%3."` (multi-level numbering shown in headings) — TipTap doesn't natively model these, so the visible numbering may differ.

---

## 5. Hyperlinks

| Source       | Count |
|--------------|------:|
| `<w:hyperlink>` in raw XML | **127** (all internal anchors `_Toc#####`) |
| `Link` elements via PHPWord | **0** |

All 127 hyperlinks live inside the TOC paragraphs (which are themselves dropped by PHPWord). Even when PHPWord *does* surface a paragraph that wraps a hyperlink, the converter only handles `Link` outside complex-field structures — a regular external `<w:hyperlink r:id=...>` would work, but there are zero of those in this doc.

---

## 6. Tables

9 tables, all RTL (`<w:bidiVisual/>`). Per-table dimensions (rows × cells; cell count exceeds (rows × cols) when col-spans exist):

| # | rows × cells | gridCols | gridSpan merges | vMerge merges | Notes |
|---|--------------|---------:|----------------:|--------------:|-------|
| 1 | 4 × 8        | 2        | 0               | 0             | simple 2-col contract-info table |
| 2 | 22 × 44      | 2        | 0               | 0             | longest 2-col table |
| 3 | 7 × 24       | 9        | **9**           | 0             | **only table with merged cells**; one cell spans 8 of 9 columns in several rows |
| 4 | 6 × 24       | 4        | 0               | 0             |       |
| 5 | 7 × 14       | 2        | 0               | 0             |       |
| 6 | 2 × 8        | 4        | 0               | 0             |       |
| 7 | 2 × 8        | 4        | 0               | 0             |       |
| 8 | 2 × 2        | 1        | 0               | 0             |       |
| 9 | 2 × 2        | 1        | 0               | 0             |       |

**Borders**: every table cell has explicit single-line borders + per-cell shading (theme `background2`, white fill). Tables also use `<w:tblLayout w:type="fixed"/>` and per-column `<w:tcW>` widths (in twentieths-of-a-point / dxa).

**Converter coverage**: handles `gridSpan` (colspan) but explicitly leaves `vMerge` rowspan as 1 (irrelevant here — no vMerge usage). Cell width is converted with a 1px ≈ 15 twips approximation, but *no* table border, shading, or `<w:bidiVisual/>` direction propagates to the TipTap output.

---

## 7. Sections, headers, footers

Single `<w:sectPr>` with:
- page size: 11907 × 16839 twips (A4 portrait)
- margins: top 720, right 922, bottom 1267, left 1080, header 288, footer 432 (twips)
- `<w:cols w:space="720"/>` (single column)
- `<w:titlePg/>` — different first-page header
- 3 `headerReference` (even / default / first) and 2 `footerReference` (default / first)

| File                    | Size    | Texts | Drawings | Tables | Notes |
|-------------------------|---------|------:|---------:|-------:|-------|
| `word/header1.xml` (even)    | 15 KB | 4 | 2 | 0 | "DRAFT" watermark drawing + logo  |
| `word/header2.xml` (default) | 12 KB | 5 | 1 | 0 | logo, "شعار الجهة", "المملكة العربية السعودية", form name |
| `word/header3.xml` (first)   | 12 KB | 5 | 1 | 0 | same content as header2 (cover page variant) |
| `word/footer1.xml` (default) | 8 KB  | 7 | 0 | 1 | 1-row table: page number, "من", total pages, issue date placeholder |
| `word/footer2.xml` (first)   | 8 KB  | 8 | 0 | 1 | similar layout |

**Converter coverage**: zero. The converter only walks `phpWord->getSections() → getElements()` which exposes body content only. PHPWord *does* expose headers/footers via `Section::getHeaders()` / `getFooters()`, but the converter never asks for them. Whether to render headers/footers in TipTap is a separate UX question, but right now the "DRAFT" watermark and page-numbering chrome are silently lost.

---

## 8. Page breaks & paging

- 3 `<w:br w:type="page"/>` runs in body — match the 3 PHPWord `PageBreak` elements.
- 0 `<w:pageBreakBefore/>` paragraph properties.
- The reference PDF has 16 pages; only 3 of those breaks come from explicit page breaks, so the other 12 boundaries are implicit (text-flow). That's expected and not something the converter can recover.

The converter renders each `PageBreak` as a TipTap `horizontalRule` (visual stand-in only).

---

## 9. Inline formatting features observed (raw XML)

| Feature                           | Count | Converter handles? |
|-----------------------------------|------:|--------------------|
| bold (`<w:b/>`)                   | 370   | yes |
| italic (`<w:i/>`)                 | 0     | n/a |
| underline (`<w:u w:val="single"/>`) | 181 | yes |
| strike (`<w:strike/>`)            | 2     | yes |
| double-strike (`<w:dstrike/>`)    | 0     | n/a |
| caps (uppercase transform)        | 16    | **no** |
| smallCaps                         | 0     | n/a |
| superscript / subscript (`<w:vertAlign>`) | 0 | n/a |
| run color (`<w:color>`)           | 1,131 distinct color tags; top values: `#000000` (597), `#FF0000` (252 — red callouts), `#0070C0` (174 — blue), `#00B050` (107 — green), `#FFFFFF` (14 white-on-bg) | yes |
| run highlight (`<w:highlight>`)   | **1**  | yes |
| run shading (`<w:shd>` inside `<w:rPr>`) | **245** | **partial** — `getFgColor()` returns the highlight name only; run-level shd fill is ignored |
| run-level character style (rStyle) | 755 (mostly the implicit Hyperlink style on TOC content that PHPWord drops) | **no** — `rStyle` is never inspected |
| `<w:rtl/>` direction marker        | 3,238 | **no** — converter has no `dir="rtl"` output |

---

## 10. Paragraph-level features (raw XML)

| Feature                           | Count | Converter handles? |
|-----------------------------------|------:|--------------------|
| custom tab stops (`<w:tabs>`)     | 124 paragraphs | **no** |
| tab characters in runs (`<w:tab/>`) | 249 | **no** — silently dropped |
| paragraph indentation (`<w:ind>`) | 147   | **no** |
| paragraph border (`<w:pBdr>`)     | 110   | **no** |
| paragraph shading (`<w:shd>` in pPr) | 35  | **no** |
| `<w:bidi/>` (RTL paragraph)       | 782   | **no** — `dir` not emitted |
| `<w:jc>` justification            | both=645, center=99, right=24, lowKashida=8 | yes (lowKashida → justify) |
| `<w:spacing>` (line spacing)      | values 14, 259, 276; lineRule auto / atLeast | **no** |

---

## 11. Fields, content controls, references

| Feature                            | Count | Converter handles? |
|------------------------------------|------:|--------------------|
| `<w:fldChar>` (complex-field markers) | 384 | **no** — characters between `begin`/`end` markers are emitted as plain runs only |
| `<w:instrText>` (field codes)      | 128 (1× `TOC \o "1-3" \h \z \u`, 127× `PAGEREF _Toc##### \h`) | **no** — field instructions are dropped, and the rendered "result" runs leak through as raw text |
| `<w:fldSimple>`                    | 0     | n/a |
| `<w:sdt>` (content control)        | 7 (rich-text controls wrapping cover-page placeholders, e.g. `(وفقًا لمنصة اعتماد)`) | **no** — PHPWord may unwrap some `sdtContent` but the parser is inconsistent |
| `<w:bookmarkStart>`                | 571 (507 `_Toc*`, 4 `_Ref*`, 60 `_Hlk*` / `_GoBack`) | **no** — converter doesn't emit anchor IDs |
| `<w:hyperlink>` (internal anchors) | 127   | **dropped** (see §5) |
| `<w:footnoteReference>`            | 0     | n/a (footnotes.xml only has separators) |
| `<w:endnoteReference>`             | 0     | n/a |
| `<w:commentReference>`             | 0     | n/a |
| tracked changes (`<w:ins>`/`<w:del>`) | 0  | n/a |
| `<w:drawing>` / `<w:pict>`         | 0 in body (only in headers) | n/a for body |
| OLE objects (`<w:object>`)         | 0     | n/a |
| MathML (`<m:oMath>`)               | 0     | n/a |
| `<mc:AlternateContent>`            | 0 in body (used in headers) | n/a |

---

## 12. Embedded fonts

26 obfuscated TTF subsets in `word/fonts/font*.odttf` (DIN Next LT Arabic family). Doesn't affect content conversion, but explains the long list of `<w:rFonts w:ascii="DIN Next LT Arabic" w:cs="DIN Next LT Arabic"/>` references — without these fonts the rendered output won't visually match the reference even if the text is correct.

---

## 13. Quick "what's at risk" snapshot

| Bucket                          | Verdict |
|---------------------------------|---------|
| Body text & paragraphs          | covered |
| Heading levels (1, 3 only)      | covered (ListItemRun-styled-as-Heading path) |
| Numbered / bulleted lists       | covered, but multi-level numbering format (decimal vs Arabic vs roman, `%1.%2.` patterns) not preserved |
| Tables (9, with 1 merged)       | structure covered, borders/shading/widths approximate |
| Hyperlinks                      | **all 127 lost** (PHPWord drops complex-field hyperlinks; converter has no fallback) |
| Table of contents (128 entries) | **all lost** (dropped at the PHPWord layer) |
| Headers / footers               | not extracted at all |
| RTL direction (`bidi`/`rtl`)    | not emitted to TipTap |
| Paragraph borders / shading     | not emitted |
| Tab characters (249 in body)    | dropped |
| Indentation                     | dropped |
| Run shading (245 instances)     | dropped (only highlight is read) |
| `caps` (16 instances)           | not transformed |

See **gaps.md** for ranked impact and **structures.md** for representative XML samples.
