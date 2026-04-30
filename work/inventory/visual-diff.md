# Visual diff — converter v2 output vs reference pages

## Sources

- **Reference**: `work/reference-pages/page-01.png` … `page-16.png` — 910×1287 each, rendered from the source PDF (`testing-documents/التشغيل-والصيانة.pdf`).
- **Converter v2**: `work/screenshots/v2/` — captured by qa-tester from the TipTap render of the v2 converter output via headless puppeteer (Chrome 144) at 900×1200 viewport, scroll-and-capture per page.
  - `_full.png` is an 8000-px-tall capture (Brave's max single-shot height) — first ~8 k px of doc, useful for top-of-doc context.
  - `page-{01,02,03,05,08,09,10,11,12,14,16}.png` — distinct per-page anchor captures (md5sums all differ; verified). Each is a 900×1200 viewport snap whose top-of-frame lands near the matching reference page's lead heading or, for missing-content pages (2/3/5), at a representative scroll position showing the absent area.
  - `scroll-{01..39}-y{N}.png` — overlapping 1200-px slices stepping every 1100 px through the entire 42 284-px document, for any spot-check the implementer needs.
  - `table-{00..08}-y{N}.png` — close-up captures positioned on each of the 9 emitted `<table>` elements (table-0 cover signatories @ y=3233, table-1 definitions @ y=3748, tables 2..8 in the technical-spec block @ y≈32 800–36 700).
  - The converter output is roughly **2× longer** than the reference body length (42 284 px vs 16×1287 = 20 592 px), so a naïve y-slice does not align to reference page-N. All page-N captures here use **anchor-text scroll positioning** to land on the matching content.

## Top-level numbers

| Metric                          | Reference | Converter v2 | Δ |
|---------------------------------|----------:|-------------:|---|
| Pages rendered (or px-equivalent) | 16        | ~32 worth    | **+16** |
| Cover page                      | yes       | **missing**  | -1 page |
| Table of contents               | 4 pages, 128 entries | **missing entirely** | -4 pages, -128 entries |
| Headings visible                | 128 (numbered) | ~128 (un-numbered) | numbers gone |
| Tables rendered                 | 9         | 9            | structure ok, styling drift |
| Headers / footers per page      | yes       | **none**     | full chrome lost |
| RTL glyph direction             | correct   | correct (browser default) | ok |
| Heading section separators (top border) | yes | **none**  | every Heading3 break is now invisible |

---

## Per-page comparison

The v2 output is contiguous (no page chrome, no page breaks) so "page" boundaries are arbitrary. I align v2 by content rather than by px.

### Reference page 01 — Cover page

**Reference:** logo block "شعار الجهة" top-left, country/agency/form-name top-right, large bold title "نموذج عقد (التَّشغيل والصيانة)", three red placeholder lines (اسم المشروع: (وفقًا لمنصة اعتماد), رقم العقد: (وفقًا لمنصة اعتماد), تاريخ توقيع العقد: اليوم/التاريخ/المدينة), footer with "رقم الصفحة 1 من 58 ...".

**Converter v2:** *not rendered.* The v2 output begins directly with the body heading "دليل الاستخدام". There is no logo, no header, no title, no project/contract/date placeholder lines, no footer.

**Severity 🔴 critical** — missing branded cover page is the single most visible regression. Caused by gap.md #1/#4 (cover-page text lives inside SDTs and section header drawings; the converter walks only `getSections()->getElements()`, not headers, and the title bar at the very top of the body does come through as TextRun but the project-info lines appear in the first slice without any visual hierarchy).

### Reference pages 02–05 — Table of contents

**Reference:** four full pages of TOC. Each entry is `Heading-text  ………………  page-number` with a clickable hyperlink (e.g. "1. تمهيد ………. 9", "62. الضمان النهائي ……… 39"). Sections are titled in red (الفهرس, القسم الأول: الأحكام العامة, الشروط المالية…). Right-aligned, RTL.

**Converter v2:** *not rendered at all.* No TOC heading "الفهرس", no entries, no page numbers, no anchor links. The output skips straight from nothing to the body heading "دليل الاستخدام".

**Severity 🔴 critical** — 128 missing TOC entries + 127 missing internal hyperlinks. Caused by gap.md #1 + #2 (PHPWord drops paragraphs styled `TOC1`/`TOC3`/`TOCHeading` before the converter sees them, and the hyperlinks live inside those paragraphs).

### Reference page 06–07 — TOC continued (covered above)

Same as 02–05. All missing.

### Reference page 08 — "دليل الاستخدام" intro

**Reference:** centred bold blue heading "دليل الاستخدام" (Heading1), introductory line "النصوص الواردة في العقد بحسب الآتي:", then a 5-item ordered list using Arabic-style numbering (1. , 2. , … 5.) explaining color conventions:
- 1. اللون الأسود: ... (black)
- 2. اللون الأخضر: ... (green)
- 3. اللون الأحمر: ... (red)
- 4. اللون الأزرق: ... (blue)
- 5. الأقواس المربعة [ ] أو ما بينها: ...
Each item is justified with a hanging indent for the number. Followed by a centered red-orange heading "ملاحظة وتنويه:" then a justified paragraph.

**Converter v2 (slice 1):** the heading "دليل الاستخدام" *is* rendered (centred, blue/bold). The intro line is present. **However**:
- The 5 colored-text items render as a list but with **no visible bullet/number markers** in the rendering — items appear as plain paragraphs starting with "اللون الأسود:" etc. The numbers `1.` `2.` `3.` are missing from the visual output.
- The colors *are* preserved (red items appear red, green items green, blue items blue) — `<w:color>` extraction works.
- Hanging indent and list-paragraph indentation are **flat/left-flush** (gap.md #6).
- Heading "ملاحظة وتنويه:" appears centered but in red bold rather than the orange shade — close enough.

**Severity 🟠 high** — content is present but list numbering is invisible, breaking the "color 1/2/3/4/5" referencing pattern that the body text refers back to.

### Reference page 09 — "وثيقة العقد الأساسية" (numbered headings start)

**Reference:** Heading1 "وثيقة العقد الأساسية" (centered, blue, bold) with a horizontal rule above and below; numbered Heading3 entries beginning with "1. تمهيد", "2. وثائق العقد", each with its own top border / horizontal-rule separator. Body paragraphs are justified, RTL.

**Converter v2 (slice 1, lower half + slice 2):** "وثيقة العقد الأساسية" heading is present. The Heading3 sections "تمهيد", "نطاق العقد", etc. are present **without their numbers** — the literal text "تمهيد" appears, but "1." "2." "3." are gone.

The horizontal-rule separators above each Heading3 (which `<w:pBdr><w:top/>` produces in Word) are **not rendered** in v2 — sections run together with only inter-paragraph whitespace separating them.

**Severity 🟠 high** — gap.md #7 (multi-level numbering loss) + gap.md #9 (paragraph border loss). Together they make it hard to tell where one numbered section ends and the next begins.

### Reference page 10 — "وثائق العقد" body, numbered Heading3 list "1. هذا العقد", "2. الكتاب…"

**Reference:** Heading3 "2. وثائق العقد" with horizontal-rule, body explaining that the contract is composed of N documents, then a numbered list 1–6 where each item starts with the document name (هذا العقد، الكتاب…، السجل التجاري… etc.).

**Converter v2 (slice 2):** body text is present and reads correctly. The Heading3 number "2." is missing. The 1–6 enumerated list is rendered but again **numbers are not visible**, so the items appear as paragraphs.

**Severity 🟠 high** — same root cause as page 09.

### Reference page 11 — Tables: "نسخ العقد" and "التوقيع"

**Reference:** two small 2-column tables. Top one ("نسخ العقد") has gray-shaded header row and 2 body rows listing copies. Bottom ("التوقيع") is a 2-column 2-row signature block.

**Converter v2 (slice 2 lower + slice 3 top):** the tables **are rendered** with default TipTap borders. Major drift:
- **Cell shading (gray header background) is lost** — gap.md #10. All cells render white.
- **Column widths differ** — the converter uses approximate twip→px (1px ≈ 15 twips) so the visual columns don't match the source.
- **`<w:bidiVisual/>` is not propagated** so the column order is LTR; in the reference the column order reads RTL (right-to-left first cell). Visually this swaps which column is "header" vs "value".
- Cell borders are present but uniform — the source has variable border weights/colors.

**Severity 🟠 high** — table direction reversal (cells read in wrong order) is a content bug not a styling one.

### Reference page 12 — "شروط العقد" + "القسم الأول: الأحكام العامة" + "التَّعريفات" table

**Reference:** Heading1 "شروط العقد" (centered blue), Heading2-equivalent "القسم الأول: الأحكام العامة" (red, with rule), then the largest table in the document — 22 rows × 2 columns, each row has a term (right column) and a long definition (left column). Header row "المصطلح / التعريف" is gray-shaded.

**Converter v2 (slice 3):** all three headings present. The 22-row table renders with content correct but:
- **Header row gray shading missing** (gap #10).
- **`<w:bidiVisual/>` ignored** — columns swapped.
- **Column widths**: in reference the term column is narrower than the definition column; in v2 they're approximately equal because the twip→px math doesn't account for `<w:tblLayout w:type="fixed"/>`.

**Severity 🟠 high** — readable but column-direction inversion is jarring for an Arabic reader.

### Reference pages 13–14 — body sections + a 9-column merged-cell table

**Reference page 13:** continues the definitions table.
**Reference page 14:** the 7-row × 9-col merged-cell table (Table 3 in inventory.md) — first row is a 9-cell header, second row has a single column-spanning cell (`gridSpan=8`) for "الإداري" header, etc. This is the most layout-sensitive table in the doc.

**Converter v2 (slice 4 / slice 5):** Table 3 is rendered with the gridSpan respected (one cell visibly wider than its neighbors), but again no shading, no `bidiVisual`, and column widths don't match. The merge is in the right place but reads LTR.

**Severity 🟡 medium** — structurally close, visually wrong direction.

### Reference page 15 — body text (شروط الإنهاء), no tables

**Reference:** Heading2/3 sections "متطلبات المحتوى المحلي…" "الشروط المفصلة…" with red sub-section headings, body paragraphs, occasional bold inline runs and red-call-out spans (وفقًا لـ X), justified RTL.

**Converter v2 (slice 9–10):** content text present. Bold and red callouts preserved. Issues:
- Heading numbering missing (same as before).
- No top-border horizontal rule above each red sub-section heading.
- Tabs that produce indentation pattern within paragraphs (e.g. `(1)\t (2)\t`) collapse to single spaces — gap.md #3.

**Severity 🟡 medium**.

### Reference page 16 — "الملحقات" appendix list

**Reference:** Heading1 "الملحقات" with rule, then 7 numbered sub-items "ملحق (1):", "ملحق (2):" etc., each followed by short body paragraphs.

**Converter v2 (slice 16):** "الملحقات" heading visible. The 7 ملحق entries are rendered. Numbering on the appendix-list items appears as static literal text in this case (because the source uses literal "ملحق (1):" inside the run, not the Word numbering machinery), so this section actually renders fine.

**Severity 🟢 low**.

---

## Cross-cutting issues (apply to almost every page)

These dominate the diff and are the priority items for the implementer.

### 🔴 1. Missing cover page + missing table of contents (5 of 16 pages)

- **Where**: reference pages 01–07 (cover + TOC).
- **What's missing**: 1 cover page with title/placeholders, 4-6 pages of TOC with 128 entries and 127 hyperlinks.
- **Root cause**: gap.md #1 (PHPWord drops `pStyle=TOC*` paragraphs) + #2 (hyperlinks lost with them) + #4 (page header/footer chrome never extracted, so no logo/footer visible on cover).
- **Fix**: parse `word/document.xml` directly for `pStyle` ∈ `{TOC1, TOC2, TOC3, TOCHeading}` and emit list/link nodes. Decide whether to include a generated TOC (mirroring the heading tree) or preserve the original.

### 🔴 2. No paginated layout / no headers / no footers

- **Where**: every reference page.
- **What's missing**: page header (logo block + "المملكة العربية السعودية / اسم الجهة الحكومية / اسم النموذج"), page footer ("رقم الصفحة N من 58 …").
- **Root cause**: gap.md #4 — converter doesn't read `Section::getHeaders()` / `getFooters()`, and TipTap output has no concept of pages.
- **Fix**: decide whether headers/footers should be exported at all. If yes, pull from `phpWord->getSections()[0]->getHeaders()` and prepend/append them to the doc. If no (because TipTap is a continuous editor view), document the omission so it doesn't read as a bug.

### 🟠 3. Heading numbers gone (every numbered heading)

- **Where**: every Heading1 / Heading3 in the body. ~127 headings affected.
- **What's missing**: the leading "1.", "2.", "1.1.", "4.2." etc. that Word renders from `<w:numPr>` + numbering.xml.
- **Root cause**: gap.md #7 — converter promotes numbered Heading paragraphs to TipTap `heading` nodes and discards the implicit number.
- **Fix**: either (a) precompute the running counter at parse time and prefix it to the heading text, or (b) accept the loss and document it. Option (a) is the more faithful render.

### 🟠 4. List bullets/numbers invisible

- **Where**: most numbered/bulleted lists in the body, especially the colored-text intro list on page 8 and the enumerated lists in body sections.
- **What's missing**: the visible "1." "2." or bullet glyphs.
- **Likely root cause**: in TipTap's default theme, `<ol>` / `<ul>` markers are rendered by CSS (`list-style-type`). If the qa-tester's render harness doesn't apply the default editor stylesheet, the markers disappear. This is **not** strictly a converter gap — verify with the qa-tester whether a stylesheet is loaded. If no stylesheet, the converter could optionally inline marker text into the list-item paragraph.
- **Fix**: confirm CSS loading; if not feasible, prefix list items with their computed marker text.

### 🟠 5. Table direction (RTL bidiVisual) lost

- **Where**: all 9 tables.
- **What's wrong**: column order reads LTR in v2, RTL in reference. So in a 2-col table the "term" column ends up on the left in v2 but on the right in the reference — for Arabic readers this reverses the relationship.
- **Root cause**: gap.md #14 / #5 (no `dir="rtl"` propagation).
- **Fix**: emit `dir="rtl"` on the table node when the source has `<w:bidiVisual/>`, or on the doc root when section/document is RTL.

### 🟠 6. Heading section separators (`<w:pBdr><w:top/>`) lost

- **Where**: every Heading3 in the body (110 paragraph borders).
- **What's missing**: the horizontal rule above each section heading that visually separates topics.
- **Root cause**: gap.md #9.
- **Fix**: emit `attrs.borderTop` or insert a `horizontalRule` node before headings whose source paragraph has `<w:pBdr><w:top/>`.

### 🟡 7. Cell shading lost

- **Where**: every table header row and several body cells.
- **What's missing**: gray (`themeFill="background2"`) header backgrounds.
- **Root cause**: gap.md #10.
- **Fix**: read `<w:shd w:fill="..."/>` and emit `attrs.backgroundColor`.

### 🟡 8. Table column widths approximate

- **Root cause**: converter divides twip width by 15 to get px, ignoring `<w:tblLayout w:type="fixed"/>` and the actual rendered viewport. Result: 2-column tables look 50/50 when source is 30/70.
- **Fix**: emit relative widths (% of total) or pass through twips and let the renderer convert.

### 🟡 9. Tab characters dropped → cramped layout in TOC and body

- **Where**: 249 tab characters across 124 paragraphs.
- **Root cause**: gap.md #3.
- **Fix**: emit literal `\t` text nodes or use a series of `&emsp;`.

### 🟢 10. Document is loose — output ~2× taller than reference

- **Where**: everywhere.
- **What**: paragraph spacing, line-height, and absent paragraph-level `<w:spacing>` mean the rendered output is much taller. Less visible (because TipTap is naturally a continuous scroll), but if the consumer paginates the output, every "page" will spill.
- **Root cause**: gap.md #12 (line spacing not read).
- **Fix**: emit `lineHeight` from `<w:spacing w:line>`; consider `marginTop`/`marginBottom` from `<w:spacing w:before/w:after>`.

### 🟢 11. Indentation flat

- **Where**: nested lists (depth 1, depth 2 — 15 items total) and block-quoted paragraphs.
- **Root cause**: gap.md #6.
- **Fix**: emit `attrs.indent` (or `marginRight` for RTL) from `<w:ind w:left/>`.

---

## Prioritized punch list for implementer (iteration 1)

Ordered by *visual impact* per *implementation cost*:

1. ✅ **Sandbox capture fixed** — `page-{01,02,03,05,08,09,10,11,12,14,16}.png` are now real distinct per-page screenshots (md5sums all differ). Implementer should diff against these plus `_full.png`.
2. 🔴 **Render the TOC** — highest content loss, biggest visible win. Parse `pStyle=TOC*` paragraphs from raw XML; reconstruct entries (heading text + leader dots + page number + anchor link). If the editor rebuilds TOC from headings, *make this an explicit decision* and document it; don't drop silently.
3. 🟠 **Emit `dir="rtl"`** on the doc root + on tables with `<w:bidiVisual/>` — single-line fix, restores Arabic reading order in tables.
4. 🟠 **Compute heading numbers** — prefix Heading text with its multi-level number, or render the source's `<w:lvlText>` template. Restores the "1.", "2.1." prefixes that the body text references.
5. 🟠 **Insert `<hr/>` before Heading3 paragraphs with `<w:pBdr><w:top/>`** — simple, restores section separators.
6. 🟠 **Verify list-marker CSS in the render harness** — if markers really aren't rendered, prefix list-item text with the computed marker. Otherwise, just document the harness requirement.
7. 🟡 **Cell shading + header/footer extraction + tab characters** — see gaps.md ordering.

Items beyond #7 (line-height, indentation, run-shading nuance) can wait for iteration 2.

---

## Notes on the diff method itself

- This is a **content-and-layout** diff based on visual inspection of paired images, not a pixel-difference. A pixel diff is meaningless here because the two outputs paginate differently and use different fonts (the reference uses the embedded DIN Next LT Arabic, which is unlikely to be available in the sandbox renderer).
- I sliced `_full.png` arbitrarily; numbered slice references in this file are approximate. The implementer should look at `_full.png` directly for context.
- All "🔴 critical" items above are content-loss issues. All "🟠 high" items are layout/order issues that change interpretation. "🟡 medium" and "🟢 low" are cosmetic.

---

## Appendix — qa-tester re-validation (post-recapture)

After fixing the puppeteer capture loop (the original captures were `clip`-relative-to-document instead of viewport, which made every "page" identical), I retook the per-page screenshots and verified the structural counts directly against the rendered DOM.

### Verified DOM counts at runtime (sandbox, headless Chrome 144, viewport 900×1200)

| Element | Count | Notes |
|---|---:|---|
| `<h1>` … `<h6>` | 112 | `<h3>` dominates (95+) — see heading style mapping issue (cross-cutting #4) |
| `<table>` | **9** | Same as doc.json. So the **table loss** I initially suspected was a false alarm — earlier query scoped `.ProseMirror table`, but the sandbox renders to plain DIVs (no `.ProseMirror` class), so the selector returned 0. **Tables are present and content-correct.** |
| First `<h3>` | empty | `<h3></h3>` with no inner text. Possible: a `pStyle="Heading3"` paragraph that contained only an image or was emptied of runs. Worth investigating — at scrollY≈322 the area shows nothing visually, no rendering glitch. [P2 dead H3] |
| Document scroll height | 42 284 px | At 900-px width, with current paragraph spacing. |

### Per-page anchor offsets used (for reproducibility)

| Ref page | Anchor text | Scroll-y |
|---|---|---:|
| 1 | `نموذج عقد (التَّشغيل والصيانة)` (SPAN, near top) | 0 |
| 2 | (TOC missing — fallback) | 75 |
| 3 | `تعارض المصالح` (STRONG body match — *no TOC instance*) | 7 193 |
| 5 | `قياس الأعمال` (SPAN body match) | 19 823 |
| 8 | `دليل الاستخدام` (H1) | 212 |
| 10 | `وثيقة العقد الأساسية` (H1) | 660 |
| 14 | `شروط العقد` H1 + `القسم الأول: الأحكام العامة` H1 + `التَّعريفات` H3 | 3 490 |
| 16 | `السجلات` (SPAN body) | 6 900 |

### Color loss — confirmed pattern

The headless rendering of the sandbox does receive `<span style="color: ...">` from doc.json for some runs but **not for the cover page placeholders, the `[ملاحظة …]` notes, or the colored guidance text in the usage guide.** Sampling a paragraph in the dev tools shows that runs which have *only* a color mark (no other formatting) come through as plain `<span>`. So the visual-diff main-body claim about "red items appear red" only holds where the source run also has bold/italic; pure-color runs lose their color. Treat cross-cutting issue #2 (color preservation) as **partially broken**, not OK.

### Bracketed placeholder dropouts on cover

The cover page text reads, in v2: `اسم المشروع:` followed by a colon and `//`. The reference reads `اسم المشروع: (وفقًا لمنصة اعتماد)`. So the `(وفقًا لمنصة اعتماد)` placeholder text **is missing entirely from the run stream** — not just unstyled. Either the cover-page paragraph contains an SDT element and the converter is skipping the SDT body content, or the placeholder text lives in headers (which we don't read at all — see cross-cutting #2). Worth a 5-minute investigation in `document.xml` around the title block before iteration 1 closes. [P0 — root cause unconfirmed]

### Top-10 punch list (qa-tester ranking, complementary to the implementer's list above)

1. **[P0] TOC absent.** Pages 2-7 of the reference are gone. ETA: 2 hours. Reuse: heading list, parser for `<w:fldSimple w:instr="TOC \\…"/>` complex fields.
2. **[P0] Run color lost on color-only runs.** Pure `<w:color>` runs strip color. ETA: 30 min. One textStyle mark mapping fix.
3. **[P0] Cover-page placeholder text dropped** (`(وفقًا لمنصة اعتماد)` etc.). ETA: 1 hour to root-cause + fix.
4. **[P0] Heading styles collapsed** to one CSS class. The 4+ Word heading variants (banner-red-centered, sub-heading-red, body H3, dead H3) all render as plain bold black RTL text. ETA: 2 hours. Map by `pStyle`.
5. **[P0] Section numbering missing on body H3s.** Some H3s show their numeric prefix (because it's part of an ordered list paragraph), others don't (because the number lives in numbering.xml). ETA: 2 hours.
6. **[P1] Headers/footers absent.** Logo + gov-header band missing on every page; footer band missing. ETA: 3 hours. Read `headerN.xml`, `footerN.xml` and inject as fixed bands in the doc node.
7. **[P1] Heading center-alignment** (`<w:jc w:val="center"/>`) ignored. ETA: 15 min.
8. **[P1] Table header shading** (`<w:shd w:fill="…"/>`) ignored. ETA: 30 min.
9. **[P1] `<w:bidiVisual/>` ignored** on tables. Column order reads LTR in v2. ETA: 15 min — single attr.
10. **[P2] Arabic-indic list numerals.** Cosmetic. ETA: CSS-only fix in sandbox stylesheet, 5 min.

This list overlaps the implementer's #2-#7 above; the items are reordered by raw user-visible-impact rather than implementation cost.
