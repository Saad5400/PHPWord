---
name: Iter11 vs Reference Visual Diff
description: QA diff for Iter11 — sandbox arabic-indic CSS list counter for RTL ordered lists
type: project
---

# Iter11 vs Reference — Visual Diff Report
Generated: 2026-05-02

Captures: `work/screenshots/iter11/`. Compared against Iter9, Iter10 captures and reference pages.

---

## TL;DR — **ITERATE**

Iter11 fixes page-09 (✓ Arabic-Indic digits ١.–٨.) but does not restore page-10 to its reference state (Arabic letters ا.–يا.). Page-10 now shows Arabic-Indic digits ١.–١١. — a different mismatch than Iter10's Western digits, but still mismatched.

The fix used a global CSS `list-style-type: arabic-indic` for RTL ordered lists. That is the right call for `decimal`-numFmt lists in RTL contexts, but it doesn't address `arabicAbjad`/`arabicAlpha` lists which need Arabic-letter markers.

`doc.json` is unchanged from Iter10: `markerStyle="none"` count = 3, no Arabic-letter prefix text in JSON. The Iter9 letter-baked-into-content path is still removed.

---

## Check 1 — Page-09 لما كانت sub-list

**Reference**: `١. لما كانت / ٢. ولما كانت / … / ٨. ولما كانت` — Arabic-Indic digits.
**Iter9**:     `ا. ب. ج. … ح.` — Arabic letters (wrong).
**Iter10**:    `1. 2. 3. … 8.` — Western digits (wrong).
**Iter11**:    `١. ٢. ٣. … ٨.` — **Arabic-Indic digits — CORRECT.** ✓

## Check 2 — Page-10 وثائق العقد list

**Reference**: `ا. ب. ج. د. ه. و. ز. ح. ط. ي. يا.` — Arabic letters.
**Iter9**:     `ا. ب. ج. … ي. ك.` — Arabic letters (10/11 correct).
**Iter10**:    `1. 2. 3. … 11.` — Western digits (regression).
**Iter11**:    `١. ٢. ٣. … ١١.` — **Arabic-Indic digits — STILL WRONG** (should be Arabic letters).

## Check 3 — Other orderedLists

| Page | List | Iter11 marker | Reference marker | Status |
|---|---|---|---|---|
| 08 | دليل الاستخدام (5 items) | ١.–٥. Arabic-Indic | 1.–5. Western (in PDF render) | Mismatch (cosmetic; PDF font may render Western glyphs even when source is Arabic-Indic) |
| 09 | ولما كانت sub-list | ١.–٨. Arabic-Indic | ١.–٨. Arabic-Indic | OK ✓ |
| 10 | وثائق العقد | ١.–١١. Arabic-Indic | ا.–يا. Arabic letters | Mismatch (numFmt-specific) |
| 11 | section headings ٤.–٩. | unchanged (Arabic-Indic) | Arabic-Indic | OK ✓ |
| 12 | "١. التَّعريفات" | unchanged | Arabic-Indic | OK ✓ |
| 14 | definitions table | unchanged | unchanged | OK ✓ |
| 16 | السجلات body | unchanged | unchanged | OK ✓ |

No spurious Arabic-letter markers observed anywhere — Iter10's wholesale strip is still in effect, so the Arabic-letter regression on page-09 doesn't reappear.

---

## doc.json deltas

| metric | Iter9 | Iter10 | Iter11 |
|---|---|---|---|
| `markerStyle="none"` | 43 | 3 | 3 |
| `marginRight` | 212 | 212 | 212 |
| `textIndent` | 267 | 267 | 267 |
| `ا.` / `ب.` letter prefixes baked into content | present | absent | absent |
| doc.json mtime | 11:48 | 11:54 | 11:54 (unchanged from Iter10) |

Iter11 is a sandbox-only change (`work/sandbox/index.html` CSS), confirmed by doc.json being byte-identical to Iter10.

Document scroll-height: 53,039 px (same as Iter10).

---

## Recommendation: **ITERATE**

Iter11 closes 1 of the 2 named regressions. Page-09 is correct. Page-10 — the most prominent list in the document, on the QA-brief checkpoint that has been tracked since Iter7 — still does not match the reference's Arabic-letter markers.

A pure-CSS counter approach can't solve page-10:

- `list-style-type: arabic-indic` produces ١, ٢, ٣, … (digits, not letters).
- CSS doesn't have a built-in `arabic-letters` / `arabic-abjad` counter style. A `@counter-style` rule with explicit symbols (ا, ب, ج, د, ه, و, ز, ح, ط, ي) and `additive-symbols` for composites (يا, يب, …) would be needed, OR the Iter9 PHP-side bake-in path needs to be conditionally restored.

**Suggested Iter12 scope:**
1. Either:
   - (a) Restore the Iter9 PHP bake-in path, but gate it strictly on the resolved level numFmt being `arabicAbjad` or `arabicAlpha` (so the page-09 list — `decimal` numFmt — doesn't accidentally pick up letters); OR
   - (b) Define a CSS `@counter-style` for arabic-abjad with explicit `symbols` and `additive-symbols`, and have the converter emit `data-list-style="arabic-abjad"` when numFmt matches.
2. Verify in the same render: page-09 = ١.–٨. (Arabic-Indic, currently working) **and** page-10 = ا.–يا. (Arabic letters).

Approach (a) reuses code already proven to work in Iter9. Approach (b) is more declarative but requires browser support for `@counter-style additive-symbols` (Firefox + Chromium since 2022; Safari 16.4+).
