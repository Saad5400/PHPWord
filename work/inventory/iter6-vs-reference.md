# Iter6 vs Reference — Visual Diff Report
Generated: 2026-05-02

Compared against `work/inventory/iter5-vs-reference.md` baseline. Captures live in `work/screenshots/iter6/`.

---

## What Iter6 changed (vs Iter5)

Iter6 targeted four specific gaps from the Iter5 review:

1. **Page-break dashed dividers** — confirmed visible in body. `doc.json` carries 3 `pageBreak` nodes (lines 30550, 36654, 38478). The sandbox renders each as a centered dashed-bordered band with the label "— فاصل الصفحة —" (visible in `scroll-30-y31900.png`).
2. **TOC layout — page numbers flush at the left margin** — confirmed across page-01 / page-02 captures. Numbers (٦, ٧, ٨, ١٠, ١١, ١٢…) now anchor at the left edge of the row; dot leaders span the full available width with no floating-mid-row gaps.
3. **Body line-height bumped to ~1.7** — visible on page-09, page-11, page-16. Paragraph rows are noticeably more airy than in Iter5; matches the reference density on page-16 and page-09 reasonably well.
4. **Underline on key headings** — confirmed for "دليل الاستخدام" (page-08), "ملاحظة وتنويه:" (page-08), and "وثيقة العقد الأساسية" (page-09). All three render with `<u>` underline now.

---

## Page-by-page diff (delta vs Iter5)

### Page 01 — Cover
- **No change to the cover-vs-TOC bleed.** Cover content (logo, header, title, fields) still flows directly into "الفهرس" without an intervening blank page. The 3 `pageBreak` nodes are deeper in the body, not at the cover/TOC boundary.
- **TOC layout below the cover is now correctly formatted** (page numbers flush-left, leaders span full row).
- Logo placeholder, header lines, "/ / /" date spacing all unchanged from Iter5.

### Page 02 — TOC start (الفهرس)
- **Major improvement:** every TOC row now has its page number anchored to the left margin and dot leaders fill the entire width. H2 banners (دليل الاستخدام, وثيقة العقد الأساسية, شروط العقد, القسم الأول) render bold with leaders.
- Header/footer band still absent.

### Page 03 — TOC continued
- Anchor still mis-targets to "تعارض المصالح" (body), same as Iter5 — capture-side issue, not a converter regression.
- Within the visible TOC bands the new flush-left layout holds consistently for sub-entries and sub-sub-entries (٤.١, ٤.٢, ٤.٣).

### Page 05 — TOC tail / قياس الأعمال
- Anchor still resolves to body; same Iter5 behavior.

### Page 08 — دليل الاستخدام
- **Underline on "دليل الاستخدام" heading: PRESENT (Iter5 was missing it).**
- **Underline on "ملاحظة وتنويه:" subheading: PRESENT.**
- Numbered color-list (1–5) renders with correct colors as in Iter5.
- No header/footer band; large blank space below content matches reference loosely.

### Page 09 — وثيقة العقد الأساسية
- **Underline on "وثيقة العقد الأساسية" heading: PRESENT (Iter5 was missing it).**
- Body paragraphs visibly more airy thanks to line-height 1.7.
- Inline red placeholder brackets render correctly.

### Page 10 — وثائق العقد
- H3 "٢. وثائق العقد" with HR above — same as Iter5.
- Lettered list (1, 2, 3 …) appears as flat numbered list rather than the reference's ا. ب. ج. … letter labels — **unchanged** from Iter5.
- "أولاً:" / "ثانياً:" / "ثالثاً:" bold inline labels render correctly.

### Page 11 — قيمة العقد / مدة العقد / signature table
- H3 sections each preceded by HR — same as Iter5.
- Two-column signature table (الطرف الأول / الطرف الثاني) renders with cell borders.
- Unchanged behavior.

### Page 12 — شروط العقد section banner
- "شروط العقد" centered banner immediately followed by "القسم الأول: الأحكام العامة" and the definitions table — **no section-divider blank page** (same as Iter5). The 3 page breaks emitted by the converter are not inserted at the section-banner boundaries; they appear later in the body.

### Page 14 — Definitions table
- Renders as in Iter5 — dark-gray header, RTL cells, multi-bullet cells in التَّشغيل row.

### Page 16 — السجلات
- Body paragraphs visibly more airy than Iter5 — closer to reference density.
- No header/footer band.

### Mid-body page-break dividers (scroll-30 etc.)
- The 3 `pageBreak` nodes render as dashed centered bands with the label "— فاصل الصفحة —". This is a visual divider, not a print-style page break — content above and below it still flows in the same scroll. Useful as a marker only; it does not produce reference-style new pages.

---

## Summary — what Iter6 fixed

| Iter5 gap | Iter6 status |
|-----------|---------------|
| Underline missing on دليل الاستخدام, ملاحظة وتنويه:, وثيقة العقد الأساسية | **Fixed** — all three render with underline |
| TOC page numbers float in mid-row instead of anchoring left | **Fixed** — flush-left across the entire TOC |
| Body line-height too tight | **Fixed** — paragraphs at ~1.7, visibly more breathable |
| No page-break separators anywhere in the document | **Partially fixed** — 3 dashed divider bands now render in the mid-body, but the converter is not emitting page breaks at the cover→TOC boundary, the section-banner pages, or any other major structural seam |

---

## Remaining gaps ranked by severity

| Rank | Severity | Gap | Carried from Iter5? |
|------|----------|-----|---------------------|
| 1 | **Critical** | No per-page header band (logo + 3 lines top-right) and no footer band ("X من 58 / تاريخ الإصدار / رقم النسخة / رقم العقد"). Every reference page has both; iter6 has neither. Single biggest visual gap. | Yes |
| 2 | **Critical** | Section-divider blank pages (شروط العقد p.13, الشروط المالية, نطاق العمل المفصل, etc.) are absent. The 3 page-break nodes that exist do not land on these section banners. | Yes |
| 3 | **High** | Cover bleeds into TOC — no page-break between cover and "الفهرس". The converter did not emit a `pageBreak` node at the cover→TOC seam. | Yes |
| 4 | **Medium** | Page-break dashed dividers are visible bands in a continuous scroll, not actual reference-style page boundaries with whitespace. Useful as in-document markers only. | New (introduced by Iter6 design choice) |
| 5 | **Medium** | Lettered list ا./ب./ج./د. … on page-10 still renders as plain numbered list without hanging-indent letter labels. | Yes |
| 6 | **Medium** | Anchor mis-targeting for pages 3 / 5 (capture script lands in body, not TOC continuation). Capture-config issue. | Yes |
| 7 | **Low** | "[BoQ] جدول الكميات والأسعار" cell content not italicized. | Yes |
| 8 | **Low** | TOC off-by-one for الملحقات (showed ٥٩ vs reference ٥٨ in Iter5 — not re-verified in this pass). | Yes |
| 9 | **Low** | Logo placeholder "شعار الجهة" text still flipped LTR inside the black box. | Yes |
| 10 | **Low** | "/ / /" placeholder spacing in cover date field unchanged. | Yes |

---

## Recommendation: Iterate

Do not ship.

Iter6 successfully closed the four targeted gaps (underlines, TOC flush-left, line-height, page-break rendering). The output looks meaningfully better at the medium-zoom level — TOC is finally readable, body breathes, and the targeted headings have the right decoration.

But the two top-ranked Iter5 critical gaps survive untouched:

1. **No header/footer band on any page.** This is the single most visible delta and affects 100% of pages.
2. **No section-divider pages.** The page-break nodes that did land render as dashed in-flow dividers rather than producing the blank title pages the reference uses for شروط العقد, الشروط المالية, etc. The converter is also not emitting page breaks at the cover→TOC or section-banner seams where the reference shows them.

**Suggested Iter7 priorities (in order):**
1. Inject a fixed-position header (logo image + 3 right-aligned lines) and footer (page-number band) into the sandbox renderer. Until the converter can emit them as TipTap nodes, treat this as a sandbox-CSS task.
2. Have the converter emit `pageBreak` nodes at every Word `<w:br w:type="page"/>` and section break — particularly the cover/TOC seam and the section-banner boundaries (شروط العقد, الشروط المالية, نطاق العمل المفصل).
3. Upgrade the sandbox `pageBreak` renderer from a dashed in-flow band to a true page-boundary effect (large vertical whitespace + visible page-edge cue) so dividers read as real page breaks rather than horizontal rules.
4. Fix the lettered-list (ا./ب./ج.) hanging-indent rendering for the contract-documents list on page-10.
5. Fix the page-3 / page-5 capture anchors so future QA passes can validate the TOC tail.
