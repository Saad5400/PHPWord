---
name: Iter10 vs Reference Visual Diff
description: QA diff for Iter10 — fix arabicAbjad regression on page-09 لما كانت list
type: project
---

# Iter10 vs Reference — Visual Diff Report
Generated: 2026-05-02

Captures: `work/screenshots/iter10/`. Compared against Iter9 captures and reference pages.

---

## TL;DR — **ITERATE**

The Iter10 fix overshot: it stripped the Arabic-letter prefixes from **all** lists (not just the page-09 لما كانت list that should not have had them). Page-10's وثائق العقد list — which DID render correctly with Arabic letters in Iter9 — has regressed to Western digits.

`doc.json` evidence: `markerStyle="none"` count fell **43 → 3**; the Arabic-letter text prefixes ("ا.", "ب.", …) are no longer present anywhere in the JSON.

---

## Check 1 — Page-09 لما كانت sub-list

**Reference**: `١. لما كانت / ٢. ولما كانت / … / ٨. ولما كانت` — Arabic-Indic digits.
**Iter9**:     `ا.  لما كانت / ب. ولما كانت / … / ح. ولما كانت` — Arabic letters (wrong: regression).
**Iter10**:    `1.  لما كانت / 2.  ولما كانت / … / 8.  ولما كانت` — Western digits.

**Status: PARTIAL FIX.** The Arabic letters are gone (good), but the post-pass that converts decimal → Arabic-Indic numerals is not running on this list. Reference wants `١.`, Iter10 emits `1.`.

## Check 2 — Page-10 وثائق العقد list

**Reference**: `ا. ب. ج. د. ه. و. ز. ح. ط. ي. يا.` — Arabic letters (abjad).
**Iter9**:     `ا. ب. ج. د. ه. و. ز. ح. ط. ي. ك.`  — Arabic letters (10/11 correct).
**Iter10**:    `1. 2. 3. 4. 5. 6. 7. 8. 9. 10. 11.` — **Western digits — REGRESSION.**

**Status: REGRESSED.** This list correctly rendered Arabic letters in Iter9 and is now back to Iter8's Western-digit state. The Arabic-letter bake-in was removed wholesale rather than gated on the OOXML numFmt of the specific list.

---

## Other observations

| Page | Status |
|---|---|
| 01 — Cover | Identical to Iter9 (logo RTL OK, TOC Arabic-Indic OK). |
| 02 — TOC | Identical. |
| 08 — دليل الاستخدام | Numbered list 1.–5. Western digits (matches reference, unchanged). |
| 09 — وثيقة العقد | Headings ".١ تمهيد" Arabic-Indic OK. لما كانت sub-list now Western 1.–8. (see Check 1). |
| 10 — Contract docs | Heading "٢. وثائق العقد" Arabic-Indic OK. Documents list now Western 1.–11. (see Check 2). |
| 11 — Body | Section headings ٤.–٩. Arabic-Indic, identical to Iter9. |
| 12 — شروط العقد | Identical to Iter9. |
| 14 — Definitions table | Identical to Iter9. |
| 16 — السجلات | Identical to Iter9. |

Document scroll-height: 53,101 (Iter9) → **53,039 (Iter10)** — minor shift, no content lost.

`doc.json` deltas vs Iter9:
- `markerStyle` count: 43 → **3** (-40).
- `marginRight` count: 212 → 212 (unchanged).
- `textIndent` count: 267 → 267 (unchanged).
- `arabicAbjad` references: 0 (none in either; never serialized as numFmt name).
- Arabic letter prefix text ("ا.", "ب.") in JSON: present in Iter9, **absent in Iter10**.

The indent infrastructure (marginRight / textIndent) is intact. Only the marker-emission path changed.

---

## Recommendation: **ITERATE**

The fix needs to be conditional on the level's OOXML numFmt:

- `arabicAbjad` / `arabicAlpha` levels → emit Arabic-letter prefix + `markerStyle="none"` (current Iter9 behavior).
- `decimal` levels in RTL paragraphs → emit no prefix; let the browser's `<ol>` counter render the digit, and apply the existing Arabic-Indic post-pass that already converts `1` → `١` in body text.

Iter10 chose the simplest possible fix (strip all letter prefixes) which removes the regression on page-09 but creates a larger regression on page-10. The page-09 لما كانت list also still shows Western `1.` instead of Arabic-Indic `١.`, so even the targeted fix is incomplete.

**Suggested Iter11 scope:**
1. Restore the Arabic-letter prefix bake-in for orderedLists where the resolved numFmt is `arabicAbjad` or `arabicAlpha`.
2. For lists where the numFmt is `decimal` and the paragraph is RTL, ensure the Arabic-Indic post-pass runs on the auto-generated counter (either by emitting Arabic-Indic digit prefixes inline with `markerStyle="none"`, or by using CSS `list-style-type: arabic-indic` which has good browser support).
3. Verify both page-09 لما كانت (should show ١.–٨.) and page-10 وثائق العقد (should show ا.–يا.) in the same render.
