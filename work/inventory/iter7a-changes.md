# Iter7-A — PHP raw-XML page breaks + cover/TOC separator

Files touched:
- `work/converter/RawXmlIndex.php`
- `work/converter/TipTapConverter.php`

## RawXmlIndex changes
- New private field `$stylesXmlCached` populated from `word/styles.xml` at construct time.
- New `getPageBreakSignatures()` returning `['after' => […], 'before' => […]]` — both keyed by normalised joined-text signatures so the converter can match against its emitted node tree without having to track raw paragraph indexes through tables/lists/SDT-dropped paragraphs.
  - `after`: signatures of paragraphs that contain `<w:br w:type="page"/>` (page break sits at the end of that paragraph).
  - `before`: signatures of paragraphs that
    - sit immediately AFTER an empty `<w:br>`-only paragraph, OR
    - have an inline `<w:pPr><w:pageBreakBefore/>`, OR
    - use a paragraph style whose definition (in `word/styles.xml`) carries `<w:pageBreakBefore/>` (e.g. `Heading1` in this docx).
- New `getPageBreakParagraphIndexes()` (raw indexes of paragraphs containing `<w:br w:type="page"/>`) kept as a public helper per the task spec.
- New private `stylesWithPageBreakBefore()` parses the cached styles.xml once and returns the set of `styleId`s whose `<w:pPr>` carries `<w:pageBreakBefore/>`. For this docx that's `Heading1` (16 occurrences).

## TipTapConverter changes
- New `injectRawPageBreaks(array $content): array` — runs after `processContainer()` in `convertFile()`, walks top-level content and inserts `['type' => 'pageBreak']` nodes before/after paragraphs whose normalised joined text matches a signature from `RawXmlIndex::getPageBreakSignatures()`. Dedupes against an immediately-preceding `pageBreak` to avoid stacking.
- New `nodeTextSignature($node)` builds a normalised signature for a top-level paragraph/heading; strips a leading multi-level number prefix (`"1."`, `"1.2.3."`, …) so that headings carrying the converter-injected number prefix still match the raw-XML signature.
- New `isHardBreakOnlyParagraph($n)` helper. The dedup loop in `injectRawPageBreaks` drops empty/hardBreak-only paragraphs sitting immediately after a page break — these are the residue of `<w:br w:type="page"/>` paragraphs that PHPWord parsed as a TextRun containing a TextBreak (rendered as `hardBreak`); the page break itself is already represented.
- `convertFile()` now also injects:
  - a `pageBreak` directly after the last header node (cover band → TOC),
  - a `pageBreak` directly after the last TOC node (TOC → body),
  - both via `array_splice` once the corresponding nodes are placed.

## Verification
- `php work/converter/run.php testing-documents/التشغيل-والصيانة.docx work/output/iter7a.json` runs in ~0.36 s.
- `grep -c '"type": "pageBreak"' work/output/iter7a.json` → **20** (was 3 in iter6a; target was > 10).
- pageBreaks land at the right positions: cover→TOC, TOC→body, before each top-level section heading (شروط العقد, الشروط المالية, نطاق العمل المفصل, المواصفات, متطلبات المحتوى المحلي, الشروط المفصلة, الملحقات, دليل الاستخدام, وثيقة العقد الأساسية), and before each "القسم …" Heading1.
- Output copied to `work/sandbox/doc.json`.
