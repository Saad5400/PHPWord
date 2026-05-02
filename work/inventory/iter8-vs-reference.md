---
name: Iter8 vs Reference Visual Diff
description: QA diff for Iter8 — multi-level heading numbers, paragraph indent, logo direction, lettered list indent, section-divider spacing
type: project
---

# Iter8 vs Reference — Visual Diff Report
Generated: 2026-05-02

Compared against `work/inventory/iter7-vs-reference.md` baseline. Captures live in `work/screenshots/iter8/`. Iter7 captures retained in `work/screenshots/iter7/` for delta comparison.

---

## Headline numbers

| Metric | Iter6 | Iter7 | Iter8 |
|---|---|---|---|
| Document scroll-height (px) | 49,805 | 51,612 | **52,135** |
| Top-level nodes rendered | — | — | 711 |
| Logo "شعار الجهة" reads RTL | No | No | **Yes** |
| Body heading numbers in Arabic-Indic | No | No | **Yes (top-level)** |
| Multi-level heading numbers (١.١, ١.٢) | No | No | **No (still single-level)** |
| Lettered list ا./ب./ج. on docs list | No | No | **No (still 1./2./3.)** |
| Paragraph block indent on indented paragraphs | No | No | **No (still flush)** |
| Section-divider vertical breathing room | Tight | Tight | **Slightly improved** |

---

## Iter8-A claims (PHP — multi-level heading numbers + paragraph indentation)

### 1. Multi-level heading numbers — PARTIALLY DONE

**Top-level (single-digit) headings now render with Arabic-Indic numerals in the body.**
- Page-09 (`page-09.png`): "١. تمهيد" / "٢. وثائق العقد" — Arabic-Indic. Iter7 showed Western "1." / "2.".
- Page-11: "٤. قيمة العقد" / "٥. مدة العقد" / "٦. النظام الواجب التطبيق" / "٧. حسم النزاعات" / "٨. نسخ العقد" / "٩. التوقيع" — all Arabic-Indic.
- Page-12: "١. التَّعريفات" — Arabic-Indic.

**Multi-level numbers (١.١, ١.٢, ٢.١) are NOT visible anywhere in the captured pages.** Either:
- (a) the source document's numbered headings are all top-level (W:numId points at a 1-level abstract list), and there is no real "١.١" in the original to render — in which case the implementer's claim is functionally complete; or
- (b) there are sub-headings in the body that should render multi-level and the converter is collapsing them to single-level.

The reference pages I sampled (1, 9, 10, 11, 14) only show single-level numbered headings, so I lean toward (a). However, I cannot confirm from the captures alone that any second-level numbered heading exists in the reference at all — pages 1–16 all use single-level "n. عنوان". **Recommendation: spot-check the source `<w:numPr>`/`<w:ilvl>` to confirm before declaring this complete.**

### 2. Paragraph indentation — NOT VISIBLE

The QA brief asks whether "indented paragraphs" now render with visible block indent. Across page-09 / 10 / 11 captures, body paragraphs (e.g., the "بعون الله وتوفيقه..." block on page-09, the "ثانيًا/ثالثًا" paragraphs on page-10, the "أولًا/ثانيًا" paragraphs on page-11) all start flush with the right edge of the column — same as Iter7. No first-line indent and no left-margin block indent visible.

If the PHP change emits indent metadata in `doc.json`, the sandbox CSS may not be styling it. If the change is purely on the converter side without sandbox-side rendering rules, the visual outcome is unchanged.

---

## Iter8-B claims (Sandbox — logo direction + lettered list indent + section-divider spacing)

### 3. Logo direction — FIXED ✓

**Page-01 logo box** (`page-01.png`) reads "شعار الجهة" in correct RTL order. Iter7 showed it LTR-mirrored ("شعار الجهة" with reversed glyph order — looked like Latin-LTR text with Arabic glyphs).

The white horizontal band at the top of every page-band capture (the per-page header) also shows the small black logo cell rendering "شعار الجهة" correctly.

**This is the most visible change in Iter8 and it is unambiguously a fix.**

### 4. Lettered list ا./ب./ج. indent — NOT FIXED

**Page-10** (`page-10.png`) — the contract-documents list under "أولًا: يتكون العقد من الوثائق التالية":

- **Reference** (`work/reference-pages/page-10.png`): list items are labeled ا. ب. ج. د. ه. و. ز. ح. ط. ي. يا. — Arabic letters as list markers, with hanging indent so labels align in a column on the right and item text wraps under itself.
- **Iter8**: list items are labeled 1. 2. 3. 4. 5. 6. 7. 8. 9. 10. 11. — Western digits, no ا./ب./ج. labels at all. Items appear hanging-indented but flush against the right column edge — **labels are wrong, indentation is approximately right**.

**The lettered-list rendering itself is not implemented.** Iter7 noted the same gap; Iter8 does not address the marker style. (It is possible the sandbox change focused only on indent CSS for already-correct markers, but the underlying `doc.json` still emits Western-digit `<ol>` for this list.)

### 5. Section-divider vertical spacing — MARGINALLY IMPROVED

**Page-08→page-09 boundary** and **page-12 boundary** (شروط العقد → القسم الأول):
- Iter7: page-break separator + ~30px gap + next heading.
- Iter8: page-break separator + ~50–60px gap + next heading.

The improvement is real but subtle. Major section banners ("شروط العقد", "القسم الأول: الأحكام العامة") have visibly more breathing room before the next content block than they did in Iter7. It does not replicate the reference's "near-blank section title page" but the boundary reads more clearly as a section transition rather than a flat divider.

---

## Page-by-page delta (vs Iter7)

| Page | Delta |
|---|---|
| 01 — Cover | Logo direction fixed. Otherwise identical (header band, two page-break separators, TOC start at bottom). |
| 02 — TOC | Identical layout. TOC numbers were already Arabic-Indic in Iter7. |
| 03 / 05 — TOC tail | Anchors still mis-target (capture-config issue, not converter). |
| 08 — دليل الاستخدام | Numbered list 1–5 still Western digits. Page-break separator before "وثيقة العقد الأساسية" has marginally more vertical space below it. |
| 09 — وثيقة العقد | Body heading numerals flipped Western → Arabic-Indic (".١ تمهيد"). Numbered list items inside paragraph (1. لما كانت / 2. ولما كانت...) still Western. |
| 10 — Contract docs | Body heading numerals Arabic-Indic ("٢. وثائق العقد", "٣. الغرض من العقد", "٤. قيمة العقد", "٥. مدة العقد"). Documents list a–k still rendered as 1.–11. with Western digits. |
| 11 — Body | All section headings ٤.–٩. now Arabic-Indic. |
| 12 — شروط العقد section banner | More vertical space between page-break separator and "القسم الأول: الأحكام العامة". "١. التَّعريفات" Arabic-Indic. |
| 14 — Definitions table | Identical to Iter7. |
| 16 — السجلات | Identical to Iter7. |

---

## Summary of the 5 QA-brief checkpoints

| # | Checkpoint | Status |
|---|---|---|
| 1 | Multi-level heading numbers (١., ٢., ١.١.) | **Partial** — top-level Arabic-Indic ✓; multi-level (١.١) not observed (likely no source instances on captured pages) |
| 2 | Paragraph indent visible on indented paragraphs | **Not visible** — body paragraphs still flush right, same as Iter7 |
| 3 | Logo "شعار الجهة" reads RTL | **Fixed** ✓ |
| 4 | Lettered list ا./ب./ج. indented | **Not fixed** — still 1./2./3. Western digits, no Arabic letter markers |
| 5 | Section-divider spacing after page breaks | **Marginally improved** — extra vertical gap before major section banners |

---

## Remaining gaps (carrying over from Iter7)

| Rank | Severity | Gap |
|---|---|---|
| 1 | **High** | **Lettered list (ا./ب./ج.) on contract-documents list still missing** — Iter8-B did not change list-marker rendering; sandbox CSS only affects already-emitted markers. Needs `doc.json` to carry letter-style or the sandbox to detect Arabic-letter list marks. |
| 2 | **Medium** | **Paragraph indent invisible** — either the converter does not emit indent metadata or the sandbox CSS is not honoring it. Needs inspection of `doc.json` to disambiguate. |
| 3 | **Medium** | **Numbered list items (1.–11.) inside body paragraphs still use Western digits** — the heading-numeral fix did not extend to inline numbered lists. |
| 4 | **Medium** | Header/footer bands appear once, not per page (carried from Iter7 — out of converter scope). |
| 5 | **Low** | Cover-date placeholder spacing (`/ / /`) still off. |
| 6 | **Low** | TOC off-by-one for الملحقات (٥٩ vs ref ٥٨). |
| 7 | **Low** | Anchor mis-targeting for page-03 / page-05 captures. |

---

## Recommendation: **Iterate (one more pass)**

Iter8 makes one clearly visible improvement (logo direction) and one structurally correct improvement (top-level heading numerals in Arabic-Indic). However, of the 5 QA-brief checkpoints, only 1.5 are unambiguously addressed:

- ✓ Logo direction
- ◐ Heading numerals (top-level only; cannot confirm multi-level coverage from captures)
- ✗ Paragraph indent
- ✗ Lettered list ا./ب./ج.
- ◐ Section-divider spacing (improved but not on par with reference)

**Two of the five named checkpoints (paragraph indent, lettered list) are not visibly different from Iter7.** Shipping now would close the iteration with two high-visibility regressions still showing on page-10 (the contract-documents list reads as a generic numbered list rather than the reference's lettered list, which is the most prominent list in the document).

**Suggested Iter9 scope:**
1. Detect Arabic-letter lists (`<w:numFmt w:val="arabicAlpha"/>` or similar) in the converter and emit them with `data-marker-style="arabic-letters"`; sandbox renders ا./ب./ج. with hanging indent.
2. Verify paragraph-indent metadata is emitted in `doc.json` and add CSS rule (`p[data-indent="..."] { margin-inline-start: ... }`) in the sandbox.
3. Spot-check a paragraph that should be indented (e.g., the "ثانيًا/ثالثًا" blocks on page-10) and confirm `doc.json` carries the indent value.
4. Optional: verify whether the source has any second-level numbered headings to render (`grep ilvl="1"` in the docx XML); if yes, address; if no, declare multi-level done.
