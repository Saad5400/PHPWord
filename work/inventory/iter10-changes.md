# Iter10 — arabicAbjad letter-marker regression fix

## Problem
iter9-A's letter-marker path treated `arabicAlpha` and `arabicAbjad` numFmts
identically, baking ا./ب./ج./… into every list item from either format. The
reference renders only `arabicAlpha` lists with letter markers; `arabicAbjad`
lists are intended to read as decimal counters that the
`applyArabicIndicNumerals` post-pass converts to ١./٢./٣./….

In `numbering.xml` for the test doc:
- `arabicAlpha`: 3 occurrences (real letter lists — وثائق العقد, etc.)
- `arabicAbjad`: 53 occurrences (decimal lists with abjad-typed ordering).

Result: 43 lists were getting markerStyle=none + Arabic-letter prefixes
when only 3 should.

## Fix
File: `work/converter/TipTapConverter.php`, function `buildListNodeFor()`.

Three OR-conditions narrowed from `arabicAlpha || arabicAbjad` to
`arabicAlpha` only:

1. Top-level list: `if ($topFmt === 'arabicAlpha')` (was OR'd with
   `arabicAbjad`).
2. Child list (when opening a deeper level):
   `if ($childFmt === 'arabicAlpha')`.
3. Per-item prefix injection:
   `if ($levelFmt === 'arabicAlpha')`.

The arabicAbjad branch now flows through the standard `orderedList`
default-counter path; the existing `applyArabicIndicNumerals` post-process
turns the rendered "1.", "2.", "3." into "١.", "٢.", "٣." for visible
RTL output.

The `listLevelNumFmt()` helper, the per-list per-depth abjad counter on
the open-lists stack, and `arabicAbjadLetter()` are unchanged — they
still service `arabicAlpha` lists correctly.

## Verification
```
php work/converter/run.php testing-documents/التشغيل-والصيانة.docx work/output/iter10.json
```

| metric | iter9a | iter10 |
|---|---|---|
| top-level children | 711 | 711 |
| heading | 128 | 128 |
| pageBreak | 20 | 20 |
| orderedList | 47 | 47 |
| `markerStyle` occurrences | 43 | **3** |
| `marginRight` occurrences | 212 | 212 |
| `marginLeft` occurrences | 122 | 122 |
| `textIndent` occurrences | 267 | 267 |
| `text` nodes | 1994 | 1819 (-175 baked prefixes removed) |

`grep -c '"markerStyle"' work/output/iter10.json` → **3** (< 43 ✓).

### Spot checks (re-verified)
- Genuine arabicAlpha list — "تعويض" / تعديل التعرفة الجمركية
  (numId=43 → abstractNumId=6, ilvl=0, numFmt=arabicAlpha) at
  `root.content[548]`: emitted as `orderedList` with
  `markerStyle: none` + baked prefixes ا./ب./ج./د./ه./و. ✓
- Page-09 "لما كانت" list (numId=56 → abstractNumId=0, ilvl=0,
  numFmt=**arabicAbjad**) at `root.content[154]`: `attrs: {dir:'rtl'}`
  only, no markerStyle, no baked prefix. First items read
  "لما كانت الجهة الحكومية…", "ولما كان المتعاقد قد اطلع…",
  "ولما كان المتعاقد قد عاين…" — ready for the post-pass to stamp
  ١./٢./٣..
- Page-10 "وثائق العقد" body list (numId=61 → abstractNumId=56,
  ilvl=0, numFmt=**arabicAbjad**) at `root.content[160]`: also a
  default `orderedList` (no markerStyle, no prefix). Contrary to the
  initial regression brief, this list's underlying numFmt is
  arabicAbjad, so it correctly renders as ١./٢./٣. via the post-pass.

### markerStyle=none locations (3 total)
1. `root.content[548]` — top-level تعويض list (genuine arabicAlpha).
2. `root.content[530]…[1]` — nested arabicAlpha list (placeholder
   parent-item).
3. `root.content[530]…[1].content[0].content[1]` — nested arabicAlpha
   list with `ا./ب.` prefixed items (إنهاء العقد).

`work/sandbox/doc.json` updated.
