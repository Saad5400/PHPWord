# Iter9-A — PHP Arabic-letter list markers + RTL indent raw fallback

Files touched:
- `work/converter/RawXmlIndex.php`
- `work/converter/TipTapConverter.php`

## Task 1: Arabic-letter list markers (already wired by an earlier pass; numFmt detection extended)

Word's `<w:numFmt w:val="arabicAbjad"/>` (and `arabicAlpha`) renders list
markers as ا./ب./ج./د./… (the Arabic abjad/hijai sequence). Browsers
don't support these in `list-style` — `<ol>` defaults to decimal and the
letters go missing.

### Detection
`listLevelNumFmt(ListItemRun, depth): ?string` walks the same numId /
numStyle resolution path as `listTypeFor()` and returns the OOXML numFmt
string ("decimal", "arabicAlpha", "arabicAbjad", "lowerLetter", "bullet",
…) for the level the item sits at.

### Bake-in marker
`arabicAbjadLetter(int $n): string` maps 1→"ا", 2→"ب", 3→"ج", … 28→"غ"
following Word's abjad/hijai ordering.

### List builder
`buildListNodeFor()` detects arabicAlpha/arabicAbjad levels, sets
`attrs.markerStyle = 'none'` on the orderedList, and prepends a text
node ("ا. ", "ب. ", …) to each listItem's first run. Per-list per-depth
counters live on the open-lists stack so sibling lists each restart from 1.

### Result
- **43 orderedLists** in the converted doc carry
  `attrs.markerStyle="none"`.
- Each list item content begins with the correct Arabic letter prefix
  (verified by walking the JSON: prefixes go ا./ب./ج./د./ه./و./z./ح. and
  reset for each new list).

## Task 2: RTL paragraph indent — raw XML fallback

### RawXmlIndex
- New public `numberingDefs[numId][ilvl]` carrying `numFmt`, `lvlText`,
  `start`, `left`, `hanging`. Populated from `numbering.xml` by extending
  `buildHeadingNumbersFromNumbering()` to also map numId → abstractNumId
  → level-def (incl. the level's `<w:ind w:left>` / `<w:ind w:hanging>`).
- New public `arabicAlphaMap` (28-entry letter map).
- New `paragraphRightIndent`, `paragraphLeftIndent`,
  `paragraphHangingIndent` keyed by `<w:p>` index.
- New `paragraphIndentBySig` — joined-text signature → ordered queue of
  `[right,left,hanging]` so the converter can match against its emitted
  nodes the way page-breaks already do.
- New `buildParagraphIndents()` walks every `<w:p>`'s `<w:pPr><w:ind>`
  (handles `w:start`/`w:end` aliases too).
- New `consumeParagraphIndent($sig)` and `getNumberingLevel($numId,$ilvl)`
  accessors.
- Constructor now calls `buildParagraphIndents()`.

### TipTapConverter
- `paragraphAttrs($pStyle, $rawSig = '')` — when PHPWord didn't surface
  `marginRight` / `marginLeft` / `textIndent`, the raw-XML index entry for
  that signature fills it in. RTL paragraphs interpret a positive
  `w:hanging` as a negative `textIndent`.
- `applyNumberingLevelIndent($attrs, $item, $depth)` — for list items
  whose paragraph itself has no inline `<w:ind>`, fall back to the
  numbering level's `left` / `hanging` definition. RTL list items map
  `left → marginRight`, `hanging → -textIndent`. This is what catches the
  arabicAlpha contract-documents list (left=721 → 36.05pt marginRight,
  hanging=360 → -18pt textIndent) plus every other numbered/lettered
  list whose paragraph properties carry only the bullet, not the indent.
- `elementSignature(...)` / `joinElementText(...)` / `normaliseSig(...)`
  helpers produce the joined-text signature; called at every
  `paragraphAttrs()` site.
- `twipsToPt($twips)` — small twips→pt formatter, trimmed.

### Inline `<w:ind w:right>` in this doc
| condition | count |
|---|---|
| inline `<w:ind w:right>` | 9 |
| inline `<w:ind w:left>` | 136 |
| inline `<w:ind w:hanging>` | 80 |
| `paragraphIndentBySig` (unique sigs) | 141 |

PHPWord surfaces 6 of the 9 inline `w:right` directly. The fallback
catches the rest plus the much larger pool of paragraphs that inherit
`<w:ind w:left>` / `<w:ind w:hanging>` from a numbering level.

## Verification

```
php work/converter/run.php testing-documents/التشغيل-والصيانة.docx work/output/iter9a.json
```

| metric | iter8a | iter9a |
|---|---|---|
| top-level children | 711 | 711 |
| heading | 128 | 128 |
| pageBreak | 20 | 20 |
| orderedList | 47 | 47 |
| paragraph | 753 | 753 |
| `markerStyle` occurrences | 0 | **43** |
| `marginRight` occurrences | 6 | **212** |
| `marginLeft` occurrences | 122 | 122 |
| `textIndent` occurrences | 0 | **267** |
| runtime | 0.37s | 0.38s |

Both spec checks satisfied:
- `grep -c '"markerStyle"' work/output/iter9a.json` → **43** (>0).
- `grep -c '"marginRight"' work/output/iter9a.json` → **212** (>20).

`work/sandbox/doc.json` updated.

## Notes
- `arabicIndicNumerals` post-pass is not applied to the Arabic-letter
  prefixes (it only maps Latin digits 0-9 → ٠-٩, so the letters are
  untouched).
- `textIndent` is emitted as a "-Xpt" string (negative). The sandbox
  iter9-B listItem walker maps it to CSS `text-indent`.
- The numbering-level `left` indent applies only when the paragraph
  itself has no inline `<w:ind>` — this is the OOXML inheritance order
  (paragraph properties override numbering-level pPr).
