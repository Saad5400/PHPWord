# Visual diff v3 — iter1 acceptance (vs v2 baseline)

Captured 2026-04-30 by qa-tester after iteration 1 (RTL + tabs + borders + shading + indent) AND iteration 2 (TOC + heading numbers + header/footer) landed AND task #9 sandbox-attr-rendering fixes also landed.

Sources
- v2 baseline: `work/screenshots/v2/` and `work/inventory/visual-diff.md`
- v3 capture: `work/screenshots/v3/` (rerun via `work/qa/capture.sh v3`)
- Manifest: `work/screenshots/v3/_manifest.json`
- Sandbox URL: `http://127.0.0.1:8765/?cb=v3`

NOTE: my v3 capture was taken after iter2 + task #9 also landed in parallel, so wins below combine all three streams of work. Where I can disambiguate, I do.

## Headline counts (DOM probes against v3)

| Metric                                | v2 | v3 | Δ |
|---------------------------------------|------:|------:|---|
| Document scroll-height (px)           | 42 284 | 32 781 | **−9 503** (sparser indent now compresses content) |
| Top-level doc nodes (banner)          | 480 | **592** | +112 (TOC entries) |
| `<table>` count                       | 9 | 9 | 0 |
| `<hr>` (top-border → horizontalRule)  | 0  | **113** | **+113** ✅ |
| `<hr>` immediately followed by H3     | 0  | 113 | ✅ matches expected count |
| Cells with non-transparent background | 0 | **14** | ✅ (header rows shaded) |
| Cells with bg via attr/inline-style   | 0 | **31** | ✅ |
| `<span style*="color">` (inline color)| ~0 | **835** | ✅ (color-only runs preserved) |
| Paragraphs with computed indent       | 0 | **172** | ✅ (indent attrs honored) |
| Paragraphs with inline indent style   | 0 | **182** | ✅ |
| `<p dir="rtl">` paragraphs            | unknown | **777 / 777** | ✅ 100% RTL coverage |
| `<p dir="ltr">` paragraphs            | unknown | **0** | no LTR contamination |
| Literal tab characters in body text   | unknown | 80 | ✅ tabs preserved in body |
| `<a>` anchor links (TOC)              | 0 | 67+ | ✅ (TOC fully rendered) |

## Iter 1 acceptance — five-item check

### 1. RTL — ✅ pass

- `<p dir="rtl">` on every paragraph in the doc (777/777). No `<p dir="ltr">` slipped through.
- Arabic punctuation renders correctly throughout v3 captures: `،` `:` `()` `[]` all in correct visual positions.
- No regressions vs v2 (v2 already had per-paragraph RTL coming from default browser behavior; v3 makes it explicit on every `<p>`).

### 2. Tabs — ⚠ partial pass

- 80 literal tab characters present in body text (per `innerText.match(/\t/g)`).
- TOC entries (e.g. `1 تمهيد ... 7`) render leader-dots correctly even without explicit tab elements — TOC reads OK.
- Body paragraphs that originally had embedded tabs (e.g. clause numbering `(1)\t (2)\t`): not visually verified at sample-level. Implementer's note that "most TOC tabs die at PHPWord, body tabs should now show" matches what I observe.
- No regressions vs v2.

### 3. Top borders → `<hr>` above each H3 — ✅ pass

- **113 `<hr>` elements**, exactly matching the implementer's expected count (113 vs 3 in v2).
- All 113 are positioned as `previousElementSibling` of an H3 — verified via DOM probe.
- Visible in page-08, page-09, page-10, page-12, page-14 captures: every `1. تمهيد`, `2. وثائق العقد`, `1. التَّعريفات`, etc. is preceded by a thin horizontal rule. Strong visual win.

### 4. Cell shading on table headers — ✅ pass

- 14 cells with computed-style non-transparent background, 31 cells with bg via attr/inline-style.
- The definitions-table header row (`المصطلح | التَّعريف`) renders with **clear dark-grey shading** in page-12 and page-14 captures — visually matches the reference DOCX exactly.
- *I had incorrectly claimed earlier this was unfixed; that was based on an earlier sandbox state. The current sandbox (post-task-#9) shows shading correctly.* Withdrawing my earlier ❌ — it's a ✅.

### 5. Indent — ✅ pass

- 172 paragraphs with computed-style indent (paddingInlineStart / paddingRight / margin).
- 182 paragraphs with inline `style` containing indent properties.
- Nested lists structurally present (5 nested `ol`/`ul` combos at depth ≥ 1).
- Visible in page-08 (color-coded list items 1-5 with proper hanging indent), page-09 (numbered enumerated list with hanging indent), page-12 (clause body paragraphs with right-side indent for their indented sub-points).

## Per-page visual delta

### Page 1 — Cover page area
- v2: cover content `نموذج عقد` was at top of doc.
- v3: at y=0 the **TOC heading `الفهرس`** is now first. Cover content (`نموذج عقد`, project/contract/date placeholders) sits between TOC end and body start (visible above `وثيقة العقد الأساسية` heading on page-09 capture). Red placeholder text in `[]` brackets and `()` now renders in red. ✅ Placeholders restored, ✅ colors restored.
- 🔴 NEW REGRESSION (still open): cover/TOC reading order is inverted vs reference. Reference: Cover → TOC → Body. v3: TOC at y=0 → Cover ≈ y=3658 → Body. Tracked as task #11.

### Page 2 — TOC
- v2: TOC entirely missing. P0.
- v3: TOC fully rendered. `الفهرس` H1 heading, ~67 entries each as a list item with `<a>` anchor + page number + leader dots. Bullets visible per-item. ✅ Massive win.

### Pages 3 / 5 — TOC continued
- v2: TOC missing.
- v3: TOC continues across the whole list. ✅

### Page 8 — Usage guide
- v2: `دليل الاستخدام` H1 + 5-item color-coded list, but markers invisible.
- v3: heading is bold black, color-coded list items 1-5 visible WITH numerical markers and proper hanging indent. **Red placeholder bracket text now renders red.** `ملاحظة وتنويه:` sub-heading present, bold + underlined (still underlined — minor cosmetic open).
- Still open: H1 heading not centered or red banner-style, just black bold (P1 cosmetic).

### Pages 9 / 10 / 11 — Body (تمهيد, وثائق العقد, …)
- v2: H3 numbers missing; bare `تمهيد`, `وثائق العقد`.
- v3: ✅ **All H3 numbered correctly:** `1. تمهيد`, `2. وثائق العقد`, `3. الغرض من العقد`, `4. قيمة العقد`, `5. مدة العقد`, etc. Each preceded by an `<hr>`.
- Body paragraphs have proper indent. Red bracketed placeholders (`[وصف الأعمال]`, `[ملاحظة: …]`, etc.) render in red.

### Page 12 — Section banner + Definitions
- v2: `شروط العقد` and `القسم الأول: الأحكام العامة` plain bold black; definitions table with no header shading.
- v3: same H1 banners (still bold black, not red — P1 cosmetic open) BUT now `1. التَّعريفات` H3 is preceded by an `<hr>`. Definitions table directly below shows **header row with proper dark-grey shading**. ✅

### Page 14 — Definitions table
- v2: 22-row 2-col table with content correct, white header row.
- v3: **Header row dark-grey-shaded** (`المصطلح / التَّعريف` cells have full bg color). Body cells have alternating fine borders. Some rows have body text in red (the entries that reference colored placeholders). RTL column order matches reference (`المصطلح` on the right, `التَّعريف` on the left). ✅

### Page 16 — Records section
- v2: section H3s like `السجلات` had no number.
- v3: ✅ `5. الإخطارات والمراسلات`, `6. السجلات`, `7. التراخيص ووثائق التسجيل والتصاريح`, `8. تعارض المصالح` — all numbered. Each preceded by an `<hr>`.

## v2 punch-list status

| #  | Item                                        | v3 status |
|----|---------------------------------------------|-----------|
| 1  | Render the TOC                              | ✅ Done |
| 2  | Preserve color marks on color-only runs     | ✅ Done (835 inline-color spans) |
| 3  | Restore body H3 section numbering           | ✅ Done |
| 4  | Distinct heading styles (red vs black H1)   | ❌ Open — all H1 still black |
| 5  | Cover-page placeholder values               | ✅ Done |
| 6  | Render headers / footers (logo + footer)    | ❌ Open — `شعار الجهة` and `رقم الصفحة` still absent |
| 7  | Heading center alignment for banner-class   | ❌ Open — `text-align: start` |
| 8  | Apply table cell shading                    | ✅ Done |
| 9  | Arabic-indic list numerals                  | ❌ Open (cosmetic) |
| 10 | Spurious underline on ملاحظة وتنويه         | ⚠ verify (still appears underlined) |

## NEW regressions in v3 (vs v2)

| Sev | Item | Notes |
|-----|------|-------|
| 🔴 P1 | **Cover/TOC ordering inverted** | Cover content moved from y=0 (v2) to ≈y=3658 (v3, after TOC). Reference: Cover → TOC → Body. v3: TOC → Cover → Body. Likely an iter2 side-effect when TOC was prepended. Tracked as task #11. |
| 🟢 P3 | Empty/dead first `<h3></h3>` | Was already in v2; not fixed in v3. Cosmetic. |

## Reproduction
```
cd /home/saad/phpstorm-projects/phpword
work/qa/capture.sh v3
```

## Recommendation to team-lead

**Iteration 1 acceptance: ✅ PASS** — all 5 items land in the rendered DOM:
- RTL ✅ • Tabs ⚠ partial (no regression) • Top borders ✅ (113 hr) • Cell shading ✅ (14+31 cells) • Indent ✅ (172 paras)

Open items for iter3+ (or post-iter2 polish):
- Heading-style mapping (banner-red vs body-black H1)
- Heading center alignment for banner-class headings
- Headers/footers (logo box + page-number footer)
- Cover/TOC ordering regression (task #11)

Ready to capture v4 once #9 + #11 close.
