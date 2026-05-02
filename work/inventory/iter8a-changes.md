# Iter8-A — PHP heading numbers + paragraph indentation

Files touched:
- `work/converter/RawXmlIndex.php`
- `work/converter/TipTapConverter.php`

## Task A: Multi-level heading numbers (status: already covered, fallback wired)

Investigation outcome — the iter7 converter already covers heading
numbering via the TOC field's rendered runs (`extractTocEntries()` →
`headingNumbers[$text]`). Of 128 heading nodes in the output, **111 already
carried a number prefix before this iteration**. The remaining 17 are
intentionally unnumbered in the source — every one of them has either no
`<w:numPr>` or `<w:numPr><w:numId w:val="0"/>` in `word/document.xml` (Word
treats `numId=0` as "list disabled for this paragraph"). They include:

- "الفهرس" (TOC heading)
- "دليل الاستخدام", "وثيقة العقد الأساسية" (top-level Heading1, no list)
- "شروط العقد", "الشروط المالية", "نطاق العمل المفصل", "المواصفات",
  "متطلبات المحتوى المحلي", "الشروط المفصلة", "الملحقات"
- 7× "القسم …" section banners

So Task A's premise — "127 headings lose their numbering" — was measured
against an older converter; the iter7 baseline already had the numbers.

Wired the existing-but-unused `RawXmlIndex::buildHeadingNumbersFromNumbering()`
as a fallback so docs whose TOC field was never regenerated (no rendered
numbers in the TOC body) still get heading numbers walked from
`word/numbering.xml`. Concrete changes:

- `RawXmlIndex` now caches `word/numbering.xml` at construct time (new private
  `$numberingXmlCached`).
- After populating `headingNumbers` from TOC entries, the constructor calls
  `buildHeadingNumbersFromNumbering($this->numberingXmlCached)`. That method
  was already implemented (walks abstractNum→lvl, numId→abstractNum,
  maintains per-(numId,ilvl) counters, formats `%N` placeholders against the
  numFmt — supports decimal, lowerLetter, lowerRoman, arabicAbjad, etc.) but
  was never invoked. It only writes to `headingNumbers` when the TOC didn't
  already supply a number for that heading text (`if (!isset(...))`), so the
  TOC remains authoritative when both are available.

For this docx the TOC is stamped, so the fallback writes nothing new and
the output of `iter8a.json` is identical (in heading prefixes) to `iter7a.json`.
For docs whose TOC was never regenerated, the fallback now provides numbering.

## Task B: Paragraph indentation → marginLeft / marginRight

`paragraphAttrs()` previously emitted only a single scalar `indent` (CSS
pixels) picking whichever of `<w:ind w:left>` / `<w:ind w:right>` had a
positive value. That collapses paired indents (which a few paragraphs in
this doc carry — e.g. `w:left="691" w:right="709"`) and gives the sandbox
no way to distinguish physical sides.

Added `paragraphSideMargins(ParagraphStyle): [leftPt|null, rightPt|null]`:
- Reads `getIndentation()->getLeft()` / `getRight()` (twips).
- Converts twips → pt at 20:1, formatted with up to 2 decimals.
- Returns null per side when unset/zero.

`paragraphAttrs()` now also emits `marginLeft` and `marginRight` whenever
the corresponding side is positive. The legacy `indent` attr is preserved
unchanged for backwards compatibility — sandbox keeps mapping it to
`padding-inline-start`.

The sandbox already had walker support for `marginLeft`/`marginRight`
(iter8b's prep work — maps to `margin-inline-end`/`margin-inline-start`),
so values flow through end-to-end without further sandbox changes.

### PHPWord coverage caveat
Raw XML carries 147 paragraphs with `<w:ind>` (122 with `w:left>0`, ~140
with `w:right>0` — overlap of 6). PHPWord's reader only surfaces 6 of the
right-side indents through `Indentation::getRight()` (it doesn't populate
`w:right` consistently — appears to be a reader limitation specific to
how `<w:ind>` is normalized for paragraphs styled inside tables / lists /
TOC). Task B emits everything PHPWord exposes; pulling the missing right
indents from raw XML would require threading a paragraph-index map
through the converter and is left for a future iteration.

Result in `iter8a.json`:
- 122 paragraphs now carry `marginLeft` (e.g. "18pt", "60pt").
- 6 paragraphs carry `marginRight` (e.g. "42.55pt" on the color-key list
  before the cover).

## Verification

```
php work/converter/run.php testing-documents/التشغيل-والصيانة.docx work/output/iter8a.json
```

- Runtime: ~0.37 s.
- Heading count: 128. Headings with leading number prefix: 111
  (unchanged from iter7 — confirms the fallback didn't double-number).
- First numbered heading in output: `"١. تمهيد"`, then `"٢. وثائق العقد"`,
  `"٣. الغرض من العقد"`, … (Arabic-Indic numerals applied by the
  post-process in `run.php`).
- 122 paragraphs carry `marginLeft`; 6 carry `marginRight`.
- `pageBreak` count unchanged at 20.
- `work/sandbox/doc.json` updated (cp from `work/output/iter8a.json`).
