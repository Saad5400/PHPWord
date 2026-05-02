# Iter6-A — PHP converter changes

Files touched:
- `work/converter/TipTapConverter.php`
- `work/converter/RawXmlIndex.php`

## 1. Page breaks
- Replaced the `horizontalRule` emit for `PHPWord\Element\PageBreak` with `['type' => 'pageBreak']` in `convertBlock()`.
- TextBreak path is unchanged: PHPWord's `TextBreak` does not distinguish a page break from a line break, so it stays a `hardBreak` inside a run / empty paragraph standalone.
- Output now has 3 `pageBreak` nodes (was 0; the same 3 paragraphs were previously `horizontalRule`). Other `horizontalRule` nodes (111) come from the unrelated paragraph-border separator path and were not touched.

## 2. Default body line-height (1.7)
- Added an `inTableCell` instance flag, pushed/popped around the cell-element loop in `convertTable()`.
- At the end of `paragraphAttrs()`, when no explicit `lineHeight` is resolved, default to `1.7` — but skip when:
  - inside a table cell (`$this->inTableCell`),
  - the paragraph's styleName starts with `TOC` (TOC entries have their own flex layout).
- Headings call `paragraphAttrs()` via `buildHeadingNode()`, so `buildHeadingNode()` strips the defaulted `lineHeight` back out (only honours an explicit one resolved from `<w:spacing>`). Verified: 0 heading or TOC paragraphs carry `lineHeight` in the output; 464 body paragraphs do, all `1.7`.

## 3. Heading underline (XML backup)
- Verified that `convertInlineRun()` → `buildTextNode()` → `buildMarks()` already emits `underline` marks when `getUnderline()` returns a non-null/non-`'none'` value. This path also fires for headings.
- Added a backup XML probe in `RawXmlIndex`:
  - New field `public array $headingUnderlines = []` (heading text → true).
  - New private `extractHeadingUnderlines()` called from the constructor; matches `<w:u w:val="…"/>` (non-`none`) inside `<w:p>` elements with `<w:pStyle w:val="Heading\d|Title">`.
- New helper `applyUnderlineToTextNodes()` in the converter walks the heading content and adds an `underline` mark to text nodes that don't already have one.
- `buildHeadingNode()` consults `RawXmlIndex::$headingUnderlines` keyed by the joined heading text and applies the helper as a fallback for the case where PHPWord drops the underline (e.g. underline only declared on the linked `HeadingNChar` character style).
- Net effect on this docx: this document's heading styles carry no `<w:u>` at all, so the backup map is empty here. The path exists for future docs.

## Verification
- `php work/converter/run.php testing-documents/التشغيل-والصيانة.docx work/output/iter6a.json` ran cleanly (0.34 s).
- `grep -c '"type": "pageBreak"' work/output/iter6a.json` → 3
- `grep -c '"lineHeight": 1.7' work/output/iter6a.json` → 464 (all defaulted)
- Spot check: every heading and every TOC entry paragraph has no `lineHeight` attr.
- Output copied to `work/sandbox/doc.json`.
