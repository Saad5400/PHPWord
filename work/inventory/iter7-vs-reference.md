# Iter7 vs Reference — Visual Diff Report
Generated: 2026-05-02

Compared against `work/inventory/iter6-vs-reference.md` baseline. Captures live in `work/screenshots/iter7/`.

---

## Headline numbers

| Metric | Iter5 | Iter6 | Iter7 |
|---|---|---|---|
| `pageBreak` nodes in `doc.json` | 0 | 3 | **20** |
| Document scroll-height (px) | — | 49,805 | 51,612 |
| Header band on page 1 | No | No | **Yes** |
| Footer band at doc end | No | No | **Yes** |
| Cover/TOC page-break separator | No | No | **Yes** (two separators around the cover block) |
| Heading underlines on key headings | No | Yes | Yes |
| TOC page numbers flush at left | No | Yes | Yes |
| Body line-height ~1.7 | No | Yes | Yes |

---

## What Iter7 changed (vs Iter6)

1. **Header band rendered.** First 4 nodes of the document are auto-grouped into a `.doc-header` wrapper — black logo cell ("شعار الجهة") on the left edge, three header lines (المملكة العربية السعودية / اسم الجهة الحكومية / اسم النموذج) right-aligned, with a bottom border (`work/sandbox/index.html:164-189`).
2. **Footer band rendered** at the end of the document — "رقم الصفحة ٤ من ٥٩ / تاريخ الإصدار: ____ / رقم النسخة: الأولى / رقم العقد: ____" inside `.doc-footer` with top border (`index.html:192-201`). Synthesized from the final trailing paragraphs.
3. **Page-break node count jumped 3→20.** Raw-XML page-break detection now emits `pageBreak` nodes at every Word `<w:br w:type="page"/>` and section break.
4. **Cover→TOC separator.** Two `pageBreak` dividers now visible around the cover block — one between header band and the "نموذج عقد …" title, one between the date-fields paragraph and "الفهرس". The cover is no longer continuous with the TOC.
5. **Page-break visual upgrade.** The dashed band was replaced with a more substantial striped band (light-blue fill + double horizontal rules + centered "— فاصل الصفحة —" label). Reads more clearly as a page boundary in the long scroll.

---

## Page-by-page diff (delta vs Iter6)

### Page 01 — Cover
- **Header band: PRESENT.** Logo box, 3 header lines, bottom border — visible at the very top of the document.
- **Two page-break separators visible** after the header band and after the contract date-fields block.
- "نموذج عقد (التَّشغيل والصيانة)" and field paragraphs render between the two separators — closer to "cover sits alone" structure than Iter6.
- The cover block no longer bleeds directly into "الفهرس"; the second separator sits between them.
- Logo placeholder text "شعار الجهة" still flipped LTR inside the box (unchanged from Iter6).

### Page 02 — TOC start
- TOC layout unchanged (already correct in Iter6).
- Header band visible at the top of the scroll-band view above الفهرس.

### Page 03 / 05 — TOC tail
- Anchors still mis-target to body — same capture-config issue.

### Page 08 — دليل الاستخدام
- Underlines on "دليل الاستخدام" and "ملاحظة وتنويه:" — present (carried from Iter6).
- A page-break separator now sits between the bottom of "دليل الاستخدام" content and "وثيقة العقد الأساسية" — section boundary now visually marked.

### Page 09 — وثيقة العقد الأساسية
- Underline on heading: present.
- Body line-height comfortable.

### Page 10–11 — وثائق العقد / قيمة العقد
- H3 sections with HR rules above — unchanged.
- Two-column signature table renders as in Iter6.

### Page 12 — شروط العقد section banner
- **Page-break separator now between "شروط العقد" and "القسم الأول: الأحكام العامة"** — section-divider behavior implemented (was missing in Iter6).
- Header band visible at the top of the scroll view.

### Page 14 — Definitions table
- Same as Iter6.

### Page 16 — السجلات
- Body comfortable, similar density to Iter6.

### Mid-body section dividers
- Multiple section dividers visible (e.g., القسم السادس: الضمانات in `scroll-30-y31900.png`). Each major section now reads as a separate visual block.

### Document tail
- Footer band visible after the appendices (الملحقات 1–8). Includes page-number line, version line, contract-number line.

---

## Summary — what Iter7 fixed

| Iter6 critical gap | Iter7 status |
|---|---|
| No header band on any page | **Fixed** — synthetic header band rendered once at document top |
| No footer band on any page | **Fixed** — synthetic footer band rendered once at document end |
| No section-divider separators | **Fixed (mostly)** — 20 page-break nodes now mark cover/TOC boundary, section banners (شروط العقد, القسم السادس, etc.), دليل الاستخدام/وثيقة العقد boundary |
| Cover→TOC bleed | **Fixed** — two page-break separators around cover block |
| Page-break rendering as flimsy dashed line | **Improved** — striped band with double rules reads more like a real page boundary |

---

## Remaining gaps ranked by severity

| Rank | Severity | Gap |
|---|---|---|
| 1 | **High** | **Header/footer bands appear once (not per page).** Reference shows them on every page. Achieving per-page repetition would require a paginated render model (CSS paged media or splitting content into discrete page divs). The current single-band approach reads as "document chrome" rather than per-page chrome. |
| 2 | **Medium** | **Section-divider blank pages still missing.** The 20 page-breaks separate sections inline but no near-blank "section title only" pages (reference p.13 for شروط العقد, similar for الشروط المالية, نطاق العمل المفصل) are rendered. Page-break dividers run consecutive content; they don't introduce vertical whitespace equivalent to a blank page. |
| 3 | **Medium** | **Lettered list ا./ب./ج. on page-10** still renders as plain numbered list without hanging-indent letter labels (carried unchanged from Iter5/Iter6). |
| 4 | **Low** | **Logo placeholder "شعار الجهة" text is LTR-mirrored inside the black box** — unchanged. |
| 5 | **Low** | **"/ / /" cover date placeholder spacing** still off vs reference. |
| 6 | **Low** | **TOC off-by-one for الملحقات** (Iter5 noted ٥٩ vs ref ٥٨). Footer also says "٤ من ٥٩" — likely the same numbering source. |
| 7 | **Low** | **"[BoQ] جدول الكميات والأسعار" cell italic** still missing. |
| 8 | **Low** | Anchor mis-targeting for page-03 / page-05 captures (capture script issue, not converter). |

---

## Recommendation: Ship (with caveats)

Iter7 is shippable for the current deliverable goal — visualizing the contract template with structural fidelity to the reference. The four critical gaps from Iter5/Iter6 (no headers, no footers, no section breaks, cover→TOC bleed) are all addressed.

The remaining gap that would prevent "pixel parity" with the reference is the per-page header/footer repetition, which is qualitatively different work — it requires a paginated render model rather than continuous-flow HTML, and is materially outside the converter's scope unless the goal becomes print-fidelity.

**Suggested follow-ups (lower priority):**
1. If true print parity is needed: add a CSS paged-media layer (`@page` + `position: running()` for header/footer) so the bands repeat on every printed page.
2. Improve the cover separator spacing so the cover reads as "one page of content" rather than two adjacent blocks split by a divider.
3. Fix the lettered-list rendering (ا./ب./ج. hanging indent) on the contract-documents list.
4. Re-verify the off-by-one page numbering (الملحقات ٥٩ vs ref ٥٨) — likely a TOC field-result issue.
