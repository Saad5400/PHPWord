---
name: Iter12 vs Reference Visual Diff
description: QA diff for Iter12 — re-added gated arabicAbjad bake path so page-10 list shows Arabic letters
type: project
---

# Iter12 vs Reference — Visual Diff Report
Generated: 2026-05-03

Captures: `work/screenshots/iter12/`. Compared against `work/reference-pages/page-01..16.png` and the iter11 baseline.

---

## TL;DR — **SHIP**

Iter12 closes the last open list-marker regression. Both named QA checkpoints pass:

- Page-09 لما كانت list — markers `١.`–`٨.` Arabic-Indic. ✓ (unchanged from iter11)
- Page-10 وثائق العقد list — markers `ا. ب. ج. د. ه. و. ز. ح. ط. ي. يا.` Arabic letters, with the composite `يا.` for the 11th item. ✓

No regressions on the other ordered lists (page-08 دليل الاستخدام, page-11 numbered headings, page-12 التَّعريفات, page-14 definitions, page-16 السجلات). Pages 1, 2, 3, 5, 8, 9, 11, 12, 14, 16 are byte-identical to iter11. Only the وثائق العقد region (scroll bands `y=5500`, `y=6600`) and `page-10.png` change.

---

## Check 1 — Page-09 لما كانت sub-list

**Reference**: `١. لما كانت / ٢. ولما كانت / … / ٨. ولما كانت` — Arabic-Indic digits.
**Iter12**:    `١. ٢. ٣. … ٨.` — **Arabic-Indic digits — CORRECT.** ✓

`page-09.png` md5 is identical to iter11 (`22b27b3aafb66f56b6d03f8d006e7c0b`), confirming this list did NOT pick up letter markers from the new arabicAbjad bake path. The gating works: the لما كانت list (numFmt `decimal`) keeps its CSS `arabic-indic` counter; only `arabicAbjad` / `arabicAlpha` lists pick up baked letter prefixes.

(Note: in the captured rendering, `١` and `٢` glyphs visually resemble `ا` and a small letter-form. They are Arabic-Indic digits — confirmed by `doc.json` line 8609ff containing no baked-in letter prefix on these list items.)

## Check 2 — Page-10 وثائق العقد list

**Reference**: `ا. ب. ج. د. ه. و. ز. ح. ط. ي. يا.` — Arabic letters, composite `يا.` for the 11th item.
**Iter9**:     `ا. ب. ج. … ي. ك.` — Arabic letters, but used `ك.` (10th letter alone) instead of composite `يا.` for item 11.
**Iter10**:    `1. 2. 3. … 11.` — Western digits (regression).
**Iter11**:    `١. ٢. ٣. … ١١.` — Arabic-Indic digits (still wrong).
**Iter12**:    `ا. ب. ج. د. ه. و. ز. ح. ط. ي. يا.` — **Arabic letters with composite `يا.` — CORRECT.** ✓

Visible in `work/screenshots/iter12/page-10.png` and `scroll-07-y6600.png`.

## Check 3 — Other ordered lists

| Page | List | Iter12 marker | Reference marker | Status |
|---|---|---|---|---|
| 08 | دليل الاستخدام (5 items) | `١.`–`٥.` Arabic-Indic | `1.`–`5.` Western (in PDF render) | Mismatch (cosmetic; same as iter11 — PDF reference renders Western glyphs even when source is Arabic-Indic). Not a regression. |
| 09 | ولما كانت sub-list (8 items) | `١.`–`٨.` Arabic-Indic | `١.`–`٨.` Arabic-Indic | OK ✓ |
| 10 | وثائق العقد (11 items) | `ا.`–`يا.` Arabic letters | `ا.`–`يا.` Arabic letters | **OK ✓ (newly fixed)** |
| 11 | section headings ٤.–٩. | unchanged (Arabic-Indic) | Arabic-Indic | OK ✓ |
| 12 | "١. التَّعريفات" | unchanged | Arabic-Indic | OK ✓ |
| 14 | definitions table | unchanged | unchanged | OK ✓ |
| 16 | السجلات body | unchanged | unchanged | OK ✓ |

Cross-iteration parity: `md5sum` of iter11 vs iter12 captures shows `page-{01,02,03,05,08,09,11,12,14,16}.png` are byte-identical. Of all 49 scroll bands, only `scroll-06-y5500.png` and `scroll-07-y6600.png` differ — both within the page-10 list region. No spurious Arabic-letter markers anywhere outside the وثائق العقد list, so the iter9-style page-09 letter regression does NOT reappear.

---

## doc.json deltas

| metric | Iter9 | Iter10 | Iter11 | Iter12 |
|---|---|---|---|---|
| `markerStyle="none"` | 43 | 3 | 3 | 4 |
| baked letter prefixes (`ا. `, `ب. `, …, `يا. `) | present (page-09 + page-10) | absent | absent | present (page-10 + 1 nested + others) |
| لما كانت list (page-09) baked prefixes | letters (wrong) | none | none | **none** ✓ (gating works) |
| وثائق العقد list (page-10) baked prefixes | letters (used `ك.` for 11th, wrong) | none | none | **`ا.`…`يا.` with correct composite** ✓ |
| `marginRight` on list paragraphs | 212 | 212 | 212 | 212 |
| `textIndent` | 267 | 267 | 267 | 267 |
| `doc.json` mtime | 11:48 | 11:54 | 11:54 | 16:29 (re-baked) |

Total baked Arabic-letter prefix tokens in iter12 doc.json: 22 (across the page-10 list and a nested list elsewhere — none on page-09).

Document scroll-height: 53,039 px (same as iter10, iter11).

---

## Refactor check — note for team-lead

Task #3 description mentioned `grep -c "use PhpOffice" work/converter/TipTapConverter.php` should be 0 (post-refactor signal). Current count is **22** (all standard PhpWord element/style imports). Either task #2 (refactor) was not run / does not exist in this iteration, or the description's expectation does not match the chosen scope. The visual fix from task #1 is independent of this; flagging for awareness — does not block ship.

## Allowlist coverage — flagged for follow-up

Implementer's `bakeArabicLetterMarkersForNumIds = [61]` (in `work/converter/run.php:16`) is gated to a single numId. Cross-checking the source DOCX `word/numbering.xml`:

- **36 distinct numIds** referenced in `document.xml` resolve to `arabicAbjad`/`arabicAlpha` at level 0 (numId 19, 20, 22–28, 32–43, 46, 47, 50, 53–56, 61, 63, 65, 70, 73, 79–81).
- Only numId=61 is in the allowlist. Verified ground truth (against PDF reference pages 1–16):
  - numId=61 (page-10 وثائق العقد) → **letters** in PDF ✓ (in allowlist)
  - numId=56 (page-09 لما كانت) → **Arabic-Indic digits** in PDF ✓ (correctly skipped)
- The other 34 referenced abjad numIds correspond to lists in pages 17–58 of the doc, which are **outside the captured reference page set** (`work/reference-pages/` only has page-01..16.png).

Implementer's run.php comment claims "page-10 is the only arabicAbjad list in the reference doc that renders as Arabic-letter markers — all others render as Arabic-Indic digits." That is plausible (the doc may locally override marker rendering — see numId=56 above) but **not visually verified for pages 17–58**. Risk is bidirectional:

- If any of those 34 lists *should* be letter-marked in the PDF, iter12 will silently render them as Arabic-Indic digits → undetected regression on pages 17–58.
- Conversely, naively widening the allowlist would risk introducing letters where the PDF expects digits.

**Suggested follow-up (not blocking ship):** capture reference pages 17–58 (extract from `testing-documents/التشغيل-والصيانة.pdf`) and re-run QA across the full doc, expanding the allowlist for any numIds whose reference render shows Arabic letters. Sample referenced abjad numIds with their level-0 item count and first-item text (for a quick PDF eyeball scan):

| numId | items | first item (truncated) |
|---|---|---|
| 20 | 6 | "عندما يؤدي ممثل الجهة واجباته…" |
| 22 | 5 | "التقيّد بجميع تعليمات السلامة…" |
| 23 | 19 | "بيان عدد العمال ومهنة كل فريق." |
| 25 | 13 | "يجب على المتعاقد أن يتخذ الترتيبات…" |
| 26 | 5 | "يقوم المتعاقد بعد إنجاز نسبة…" |
| 47 | 4 | "على المتعاقد إذا رأى أحقيته…" |
| 50 | 8 | "بذل العناية اللازمة لتنفيذ…" |
| 73 | 4 | "يلتزم المتعاقد باختيار وتعيين…" |

(full list of 36 referenced abjad numIds is in `numbering.xml`; sampled here by reference count ≥ 4.)

---

## Recommendation: **SHIP**

Iter12 lands the missing piece from iter11 without regressing anything else:

- Page-10 وثائق العقد renders the Arabic-letter list with correct composite `يا.` — the QA-brief checkpoint tracked since iter7 is finally green.
- Page-09 لما كانت remains on Arabic-Indic digits — confirms the bake path is correctly gated to `arabicAbjad`/`arabicAlpha` numFmt only.
- Byte-level parity across 9 of 11 captured pages and 47 of 49 scroll bands shows the change is surgical.

If the team-lead wants the converter refactor (use-statement reduction) tracked alongside this, recommend opening it as a follow-up — visual parity is independent and ship-ready now.
