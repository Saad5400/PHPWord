---
name: Iter9 vs Reference Visual Diff
description: QA diff for Iter9 — Arabic-letter list markers + RTL paragraph indent (raw XML fallback) + sandbox marker suppression
type: project
---

# Iter9 vs Reference — Visual Diff Report
Generated: 2026-05-02

Compared against Iter8 baseline (`work/inventory/iter8-vs-reference.md`). Captures live in `work/screenshots/iter9/`. Iter8 captures retained for delta comparison.

---

## Headline numbers

| Metric | Iter7 | Iter8 | Iter9 |
|---|---|---|---|
| Document scroll-height (px) | 51,612 | 52,135 | **53,101** |
| Top-level nodes rendered | — | 711 | 711 |
| Logo "شعار الجهة" reads RTL | No | Yes | **Yes** |
| Body heading numbers in Arabic-Indic | No | Yes (top-level) | **Yes (top-level)** |
| Lettered list ا./ب./ج. on docs list | No | No | **Yes (with caveat)** |
| Paragraph block indent on indented paragraphs | No | No | **Yes (now visible)** |
| `markerStyle="none"` orderedLists | 0 | 0 | **43** |
| `marginRight` occurrences in doc.json | ~6 | ~6 | **212** |
| `textIndent` occurrences in doc.json | 0 | 0 | **267** |

---

## QA-brief checkpoints (Iter9 focus)

### 1. Page-10 contract-documents list — Arabic letters? **YES (with one off-by-letter)**

**Page-10** (`page-10.png`) — list under "أولًا: يتكون العقد من الوثائق التالية":

- **Reference**: ا. ب. ج. د. ه. و. ز. ح. ط. ي. **يا.** — Arabic abjad with composite letter for 11.
- **Iter9**: ا. ب. ج. د. ه. و. ز. ح. ط. ي. **ك.** — Arabic letters; the 11th item is "ك." (next letter alphabetically), where reference uses the abjad-composite "يا.".
- **Iter8**: 1. 2. 3. 4. 5. 6. 7. 8. 9. 10. 11. — Western digits.

**This is a major win**: 10 of 11 markers match the reference exactly. The 11th differs because the converter's `arabicAbjadLetter()` walks single letters straight through (ا→ي is 1–10, then ك, ل, م, …) rather than producing composite forms (يا = 11, يب = 12, …) once the count exceeds 10. Iter9-A's notes call out a 28-entry mapping (1→ا … 28→غ) — pure single-letter, no composites. For this document only the 11th item exposes the difference; lists with >10 items are rare elsewhere in the doc.

### 2. Indented paragraphs visually indented from right margin? **YES**

Looking at page-10:
- "[ملاحظة: تقوم الجهة الحكومية بإضافة الوثائق المرفقة...]" — visibly indented from the right edge.
- "أولًا: يتكون العقد من الوثائق التالية:" — visibly indented from the right edge.
- "ثانيًا: تُشكِّل هذه الوثائق وحدة متكاملة..." — flush right (correct — reference also flush).
- "ثالثًا: في حال وجود تعارض بين أحكام..." — flush right (correct — reference also flush).

The selective indentation pattern (some paragraphs indented, others flush) now matches the reference. Iter8 had everything flush right.

Page-09 also shows the introductory paragraph "بعون الله وتوفيقه..." with appropriate margin, matching reference.

### 3. List items with hanging indent show letter with hanging text? **PARTIAL**

The list items on page-10 in iter9 visibly start with the Arabic letter and a period ("ا. وثيقة العقد الأساسية."). All items in this list happen to be short single-line text, so wrap behavior cannot be confirmed from page-10 alone. CSS-side, Iter9-B emits `text-indent: -Xpt` on listItems with `marginRight: Xpt`, which is the standard hanging-indent pattern.

**Visually**: list items are clearly hanging from the right margin (aligned in a column further left than the body paragraphs above/below), and the letter sits at the right edge of that column. This matches the reference layout. The implementation is structurally correct; long wrapping items elsewhere in the doc would be needed to fully exercise the hanging-indent CSS.

### 4. Regressions vs Iter8?

**None observed.** Spot-checks across pages 1, 2, 3, 5, 8, 9, 10, 11, 12, 14, 16:

| Page | Status |
|---|---|
| 01 — Cover | Identical to Iter8. Logo RTL OK. TOC Arabic-Indic OK. |
| 02 — TOC | Identical. |
| 03 / 05 — TOC tail | Anchor mis-target (capture-config issue, not converter — same as Iter8). |
| 08 — دليل الاستخدام | Numbered list 1–5 still Western (matches reference; not a regression). |
| 09 — وثيقة العقد | Heading numerals Arabic-Indic. Lettered list (a–i) under "ولما كانت..." now shows ا./ب./ج./د./ه./و./ز./ح./ط. — was 1.–9. in Iter8. Matches reference. |
| 10 — Contract docs | **Lettered list now ا./ب./ج./د./ه./و./ز./ح./ط./ي./ك.** (was 1.–11. in Iter8). Indented paragraphs now visibly indented (was flush in Iter8). |
| 11 — Body | All section headings ٤.–٩. Arabic-Indic (same as Iter8). |
| 12 — شروط العقد section banner | Identical to Iter8. "١. التَّعريفات" Arabic-Indic. |
| 14 — Definitions table | Identical to Iter8. |
| 16 — السجلات | Identical to Iter8. |

Document scroll-height grew from 52,135 → 53,101 px (+966 px ≈ +1.9%) — consistent with paragraph indent / hanging indent now consuming inline space and pushing some items to wrap, but no content was dropped (top-level node count unchanged at 711).

---

## Summary of the 5 QA-brief checkpoints (running)

| # | Checkpoint | Status |
|---|---|---|
| 1 | Multi-level heading numbers (١., ٢., ١.١.) | **OK** — top-level Arabic-Indic; no source instances of multi-level on captured pages |
| 2 | Paragraph indent visible on indented paragraphs | **Fixed in Iter9** OK |
| 3 | Logo "شعار الجهة" reads RTL | **Fixed in Iter8** OK |
| 4 | Lettered list ا./ب./ج. with hanging indent | **Fixed in Iter9** OK (modulo "يا." vs "ك." for 11th) |
| 5 | Section-divider spacing after page breaks | **Marginally improved in Iter8** partial |

---

## Remaining gaps

| Rank | Severity | Gap |
|---|---|---|
| 1 | **Low** | 11th list item on page-10 reads "ك." instead of reference's "يا." (composite abjad letter for 11). `arabicAbjadLetter()` is single-letter only; would need composite-letter logic for n≥11. Affects only this one item in the captured corpus. |
| 2 | **Low** | Header/footer bands appear once, not per page (carried from Iter7 — out of converter scope). |
| 3 | **Low** | Cover-date placeholder spacing (`/ / /`) still off. |
| 4 | **Low** | TOC off-by-one for الملحقات (٥٩ vs ref ٥٨). |
| 5 | **Low** | Anchor mis-targeting for page-03 / page-05 captures (capture config, not converter). |

---

## Recommendation: **SHIP**

Iter9 closes the two highest-priority gaps from Iter8:
- Lettered list ا./ب./ج. now renders (was the most prominent visible regression on page-10)
- Paragraph indentation now visible (was flush-right across the doc in Iter8)
- Hanging indent structurally implemented (`marginRight` + negative `textIndent`)
- No regressions vs Iter8

The single remaining substantive defect is cosmetic and bounded: one list-item label ("ك." instead of "يا.") on a single page. All other captured pages match or improve on Iter8. Headline metrics confirm the implementation is broad: 43 markerStyle="none" lists, 212 marginRight values, 267 textIndent values — the raw-XML fallback is producing indent metadata at scale, not just for the page-10 hero list.

**Suggested follow-up (not blocking ship):**
1. Extend `arabicAbjadLetter()` to handle composite letters (يا=11, يب=12, …) for lists with >10 items.
2. (Stretch) Verify on a longer test document that no list with wrapped items breaks the hanging-indent column alignment.
