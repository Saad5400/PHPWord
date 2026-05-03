# Iter12 — task #1: re-add arabicAbjad to letter-marker bake path

## numFmt trace findings

Both target lists in `testing-documents/التشغيل-والصيانة.docx`
resolve to *identical* OOXML numbering definitions (same `tmpl="A8846DEC"`,
both `numFmt=arabicAbjad`, `lvlText="%1."`), but render with different
markers in the source PDF:

| List | numId | abstractNumId | numFmt | lvlText | reference markers |
|---|---|---|---|---|---|
| Page-09 لما كانت | 56 | 0 | arabicAbjad | `%1.` | ١.–٨. (Arabic-Indic digits) |
| Page-10 وثائق العقد | 61 | 56 | arabicAbjad | `%1.` | ا.–يا. (Arabic letters) |

There is **no signal in the source XML** to disambiguate the two — same
numFmt, same lvlText, same indent, no `numStyleLink`/`styleLink` referenced
from either abstractNum. Word renders them differently anyway (likely a
quirk of how the document was authored / saved).

Disambiguation strategy: **per-numId opt-in via config**. The default
numFmt-driven path keeps `arabicAbjad` as numeric (matches the page-09
case), and a list of `numId`s opts specific lists into the
Arabic-letter bake path (matches the page-10 case).

## Code changes

### 1. New config option — `ConverterConfig::$bakeArabicLetterMarkersForNumIds`
- Type: `int[]`. Default `[]`.
- Documented as opt-in for `arabicAbjad` lists that should bake
  Arabic-letter markers (`arabicAlpha` always bakes — no opt-in needed).

### 2. New helper — `TipTapConverter::shouldBakeArabicLetters(ListItemRun, int)`
- Returns true when:
  - level numFmt is `arabicAlpha`, OR
  - level numFmt is `arabicAbjad` AND the item's numId is in
    `$config->bakeArabicLetterMarkersForNumIds`
- Replaces the previous three `=== 'arabicAlpha'` checks scattered
  through `buildListNodeFor()`.

### 3. Three call-sites updated in `buildListNodeFor()`
- Top-level `markerStyle: none` suppression — uses `shouldBakeArabicLetters`.
- Child-list `markerStyle: none` suppression — same.
- Per-item letter prefix bake — same; the stack frame now carries a
  `bakeLetters` bool alongside `fmt`/`counter`, set when each list level
  is opened.

### 4. `arabicAbjadLetter()` extended for n ≥ 11
- `n ≤ 10` → single letter from `[ا,ب,ج,د,ه,و,ز,ح,ط,ي]`.
- `11 ≤ n < 20` → `'ي' . arabicAbjadLetter(n - 10)` (يا, يب, يج, …يط).
- `20 ≤ n < 30` → `'ك' . arabicAbjadLetter(n - 20)` (ك, كا, كب, …كط).
- `n ≥ 30` falls back to decimal.
- Page-10 needs n=11 → `يا`; tested correct.

### 5. `work/converter/run.php`
- Sets `$config->bakeArabicLetterMarkersForNumIds = [61]` so the test doc
  renders the page-10 list with letters out of the box.

## Verification (against `work/output/iter12.json`)

- `markerStyle="none"` count: **4** (was 3 in Iter10/Iter11). The new entry
  is the orderedList wrapping the page-10 وثائق العقد list (numId=61).
- Page-10 list — first item's paragraph content begins with text node
  `"ا. "`; the 11 items in document order produce:
  `ا. / ب. / ج. / د. / ه. / و. / ز. / ح. / ط. / ي. / يا.` — matches the
  reference exactly.
- Page-09 لما كانت list — first item's paragraph content begins with
  `"لما كانت الجهة الحكومية..."`. **No baked letter prefix.** Stays on
  the default ordered-list path so the Arabic-Indic post-pass renders
  ١.–٨. as before.
- Other arabicAlpha letter-baked lists (Definitions block at JSON line
  ~28235, top-level letter list at ~29650) unaffected.

## Files touched
- `work/converter/ConverterConfig.php`
- `work/converter/TipTapConverter.php`
- `work/converter/run.php`
