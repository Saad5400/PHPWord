# Iter5 vs Reference — Visual Diff Report
Generated: 2026-05-02

---

## Page-by-page diff

### Page 01 — Cover page

**Reference:** Full cover page. Logo placeholder (black box, "شعار الجهة") top-left. Three header lines top-right (المملكة العربية السعودية / اسم الجهة الحكومية / اسم النموذج). Large centered title "نموذج عقد (التَّشغيل والصيانة)". Three labeled fields below (اسم المشروع / رقم العقد / تاريخ التوقيع). Footer band with page number "1 من 58" and version/contract-number fields. **The cover page is the full visible content of page 1; the TOC starts on page 2.**

**Iter5:** Cover fields are rendered but the page is **not paginated**. The cover content (logo + header + title + fields) runs directly into the TOC heading "الفهرس" and then the full TOC — all on one continuous scroll view beginning at y=0. There is no page break between the cover and the TOC.

**Gaps remaining:**
- **No page-break / section separation between the cover and the TOC.** In the reference the cover fills page 1 entirely; the TOC starts on page 2. Iter5 renders them back-to-back in a single flow.
- **Logo box is LTR-flipped.** In the reference the black logo box sits at top-left (correct for RTL documents); in Iter5 it is rendered at top-left of the content area but the text "سعار الجهة" inside it is left-to-right visually (mirrored), likely because the `<img>`/placeholder element is not inside an RTL container.
- **Header lines (المملكة / اسم الجهة / اسم النموذج)** in the reference are right-aligned and sit to the right of the logo box. In Iter5 they are also right-aligned but the vertical spacing between them is tighter, and the top-of-page gap (blank space above the logo) is absent, making it look cramped.
- **Cover fields with mixed Arabic/red text:** "تاريخ توقيع العقد: // / اليوم / التاريخ / المدينة" — in Iter5 the placeholder slashes appear as `/ / /` with extra spaces; reference has "اليوم/ التاريخ/ المدينة" with no space before the slash.
- **Footer (page number, version line)** is entirely absent in Iter5. Reference shows a centered "رقم الصفحة" line and a ruled "1 من 58 تاريخ الإصدار: _____ رقم النسخة: الأولى رقم العقد: ___" footer on every page.

---

### Page 02 — TOC start (الفهرس, entries 1–8 of main TOC sections)

**Reference:** Page starts with the logo/header band, then the red "الفهرس" heading, then TOC entries. Layout is right-to-left: entry text on the right, dot leaders filling the width, page number on the far left (as a standalone right-aligned number on its own line for most entries). H2 section entries (دليل الاستخدام, وثيقة العقد الأساسية, شروط العقد, القسم الأول …) are bold. Sub-entries are indented and numbered (١. تمهيد, ٢. وثائق العقد, etc.) with Arabic-Indic numerals.

**Iter5:** "الفهرس" heading renders correctly in red/large. TOC entries render in RTL order. Dot leaders are present. Arabic-Indic page numbers (٦, ٧, ٨ …) are shown on the left.

**Gaps remaining:**
- **TOC layout model is wrong.** The reference uses a tab-stop model: entry text starts at the right margin, dot leaders fill to a left tab stop, and the page number sits alone at the far left — sometimes on the same line, sometimes pushed to the next line when the entry is long. Iter5 renders each TOC row as a single line with the number flush-left and the entry flush-right, but the dot leaders do not reach all the way across — there is visible white space. On narrow entries the number floats in the middle rather than at the true left margin.
- **Page numbers in TOC are Arabic-Indic (correct per Iter5 notes) but the reference uses Eastern Arabic-Indic (١٢ etc.) while Iter5 shows what appears to be the same glyphs — this needs font verification.**
- **TOC H2 section headings** (دليل الاستخدام, وثيقة العقد الأساسية, شروط العقد, القسم الأول: الأحكام العامة, القسم الثاني: الموقع …) should be **bold** in the reference; in Iter5 they appear bold correctly.
- **Sub-entry indentation:** In the reference sub-entries are indented one level to the right (RTL indent). In Iter5 the indentation is present but shallower, making the visual hierarchy less distinct.
- **No page header/footer band** (logo + three lines top-right, ruled footer) in Iter5.

---

### Page 03 — TOC continued (entries 9–27, end of القسم الأول into القسم الثاني)

**Reference:** Continues TOC in same style. Logo/header top. Entries 9 (السرية وحماية المعلومات) through القسم الثالث: ممثل الجهة entry 28.

**Iter5 (anchor page-03):** The anchor resolves to the body content section for "تعارض المصالح" — this is **body text, not TOC**. The capture script jumped past the remaining TOC pages and landed in the body. The visible content is dense Arabic body paragraphs with no TOC formatting.

**Gaps remaining:**
- **Pages 3–7 of the reference are all TOC.** Iter5's single continuous TOC block is very long (the full 128-entry TOC occupies roughly 3–4 screen heights). The anchor for page-03 is misconfigured and does not land at the correct TOC section. As a result Iter5 pages 3–5 (in the capture) show body content instead of TOC content — the visual comparison is impossible.
- **Within the visible TOC (scroll-02 band):** entries render correctly in structure but the dot-leader width varies — some short entries have a large gap between the leader and the number.

---

### Page 04 — TOC continued (entries 29–47, القسم الرابع and القسم الخامس)

**Reference:** More TOC entries, same style. Section headings القسم الرابع: مسؤوليات المتعاقد and القسم الخامس: تنفيذ الأعمال appear bold with page numbers on the left.

**Iter5:** No named capture for page-04. Scroll-band coverage shows this section of the TOC is rendered (visible in scroll-02 band). Structure appears correct.

**Gaps remaining:**
- Same dot-leader and indentation issues as pages 02–03.
- No header/footer.

---

### Page 05 — TOC continued (قياس الأعمال onwards through end of TOC)

**Reference:** Final TOC pages through entry 128 (الملحقات / ملحق (7)). Includes الشروط المالية, نطاق العمل المفصل, المواصفات, متطلبات المحتوى المحلي, الشروط المفصلة sections.

**Iter5 (anchor page-05):** Anchor resolves to body content — body paragraph text in the "قياس الأعمال" section. Not the TOC.

**Gaps remaining:**
- Same anchor mis-targeting issue as page-03: body content is shown instead of the end-of-TOC pages.
- The TOC's final sections (الملحقات with 7 sub-entries) do render in the scroll bands; their formatting matches the rest of the TOC with the same structural gaps.

---

### Page 06 — TOC final entries (الشروط المالية, نطاق العمل المفصل, المواصفات, etc.)

**Reference:** Tail end of TOC, sub-entries 4.1, 4.2, 4.3 (Fines sub-items) with two-level numbering. Section banners الشروط المالية, نطاق العمل المفصل, المواصفات are bold. Some entries have inline page numbers adjacent to the text (e.g., "44 الغرامات") rather than pushed fully left.

**Iter5:** Rendered within the TOC block. The two-level numbering (٤.١, ٤.٢, ٤.٣) appears in the scroll-band view. The formatting is consistent with the rest of the TOC. No named page capture available.

**Gaps remaining:**
- Sub-sub-entry indentation (two levels deep) is visually similar to one-level entries — reference uses a slightly deeper indent for these.
- No header/footer.

---

### Page 07 — TOC final (متطلبات المحتوى المحلي, الشروط المفصلة, الملحقات)

**Reference:** Last TOC page. Ends with ملحق (7): entry at page 58. Large blank space below (cover of next section starts on a new page).

**Iter5 (scroll-04 band):** The end of the TOC block shows correctly — الملحقات heading bold, 7 sub-entries, page number ٥٩ (vs reference 58 — **page number discrepancy** by 1). Below the TOC the "دليل الاستخدام" section heading appears immediately.

**Gaps remaining:**
- **Page number in TOC for الملحقات sub-entries shows ٥٩ in Iter5 vs ٥٨ in reference** — an off-by-one in the converter's page numbering.
- No visual page break between end of TOC and start of body sections.
- No header/footer.

---

### Page 08 — دليل الاستخدام (Usage guide)

**Reference:** Header band (logo + 3 lines). Centered blue heading "دليل الاستخدام" (underlined). Body text in RTL: color-coded paragraph list (numbered 1–5) explaining color conventions. "ملاحظة وتنويه:" subheading (blue, underlined). Paragraph of black body text. Large blank space below (only the top ~40% of the page has content).

**Iter5 (page-08):** The anchor lands at the "دليل الاستخدام" heading. The heading "دليل الاستخدام" renders centered and blue but **without underline**. The numbered list (1–5) renders correctly in RTL with correct colors (black, green, red, blue items). "ملاحظة وتنويه:" renders but **without the blue underlined style** — it appears as plain blue text without the underline decoration.

**Gaps remaining:**
- **"دليل الاستخدام" heading missing underline.** Reference has underline; Iter5 does not.
- **"ملاحظة وتنويه:" subheading missing underline.**
- **Numbered list color items:** item 2 (اللون الأخضر) should be green — appears green in Iter5 (correct). Item 4 (اللون الأزرق) should be blue — appears blue (correct).
- **List item leading space:** In the reference the numbered items are centered on the page with generous right-side margin. In Iter5 they are left/right padded normally but the visual weight is similar.
- **No header/footer.**

---

### Page 09 — وثيقة العقد الأساسية (Contract intro / signature context)

**Reference:** Header band. Heading "وثيقة العقد الأساسية" (bold, centered). Contract preamble text in RTL with inline red placeholder brackets (الجهة الحكومية, المنصب, الاسم, المملكة العربية السعودية, المدينة, etc.). "ويشار إليها في هذا العقد بـ 'الجهة الحكومية'" lines. Section "١. تمهيد" heading (bold). Numbered sub-items 1–4.

**Iter5 (page-09):** Anchor lands in the right section. Heading "وثيقة العقد الأساسية" is centered and bold (correct). Preamble text renders with red inline placeholders correctly. "١. تمهيد" heading renders correctly.

**Gaps remaining:**
- **Inline placeholder formatting:** reference uses red text inside brackets `[...]`. Iter5 renders these correctly as red. Minor issue: in Iter5 the opening bracket `[` sometimes appears separated from the text by a hair space.
- **"وثيقة العقد الأساسية" heading should be underlined** in the reference style (same as "دليل الاستخدام"). In Iter5 it is not underlined.
- **Section number "١." formatting:** reference puts it as part of the paragraph heading in bold. Iter5 renders it correctly inline.
- **No header/footer.**

---

### Page 10 — وثائق العقد (Contract documents list) — section 2

**Reference:** Header band. H3 heading "٢. وثائق العقد" (bold, with HR line above). Blue annotation paragraph. "أولاً:" bold inline. Lettered list (ا, ب, ج, د, ه, و, ز, ح, ط, ي, يا) of contract document types — each on its own indented line with Arabic letter followed by period and text. "ثانياً:" and "ثالثاً:" bold inline sub-sections. H3 "٣. الغرض من العقد" at bottom.

**Iter5 (page-10):** The "٢. وثائق العقد" H3 heading renders with an HR line above (correct — Iter5 improvement). The blue annotation paragraph renders in blue. The lettered list renders.

**Gaps remaining:**
- **Lettered list indentation and marker style:** Reference shows each letter (ا., ب., ج. …) as a hanging-indent list item centered slightly. In Iter5 the letters appear as plain text at the start of each line without the visual indentation / hanging structure — the items read as inline-continued paragraphs rather than a proper list.
- **"أولاً:" / "ثانياً:" / "ثالثاً:" bold inline labels** render correctly in both.
- **H3 HR separator** above "٢. وثائق العقد": present in Iter5 (correct).
- **No header/footer.**

---

### Page 11 — قيمة العقد / مدة العقد / النظام الواجب التطبيق / حسم النزاعات

**Reference:** Header band. Multiple H3 sections: ٤. قيمة العقد, ٥. مدة العقد, ٦. النظام الواجب التطبيق, ٧. حسم النزاعات, ٨. نسخ العقد, ٩. التوقيع. Each preceded by an HR line. Body text is RTL black with red/green/blue inline brackets. The ٩. التوقيع section contains a two-column signature table (الطرف الأول / الطرف الثاني with الاسم / الصفة / التوقيع rows).

**Iter5 (page-11):** The H3 sections render with HR lines above (correct). The two-column signature table at the end of section ٩ renders visually.

**Gaps remaining:**
- **Signature table layout:** Reference has a clean two-column table with cells clearly delineated and labels centered in cells. In Iter5 the table renders but cell borders may be thinner/missing or the column widths are not equal — the visual balance differs from the reference.
- **H3 heading for "شروط العقد" section banner:** in the reference this is a centered bold title on its own paragraph without an HR — in Iter5 it renders the same way (correct).
- **No header/footer.**

---

### Page 12 — Signature page (blank except for partial sig table) and start of شروط العقد

**Reference:** Page 12 contains only the completion of the signature table (الاسم, الصفة, التوقيع rows) from page 11, then large blank space to the bottom. The "شروط العقد" title appears at the very top of page 13 on a fresh page.

**Iter5 (page-12 anchor):** Anchor lands at a table fragment showing partial rows with "ي:" labels (likely the bottom of the signature table). The "شروط العقد" heading and the "القسم الأول: الأحكام العامة" heading appear below it on the same scroll view (no page break).

**Gaps remaining:**
- **No page break between signature table and شروط العقد.** Reference has شروط العقد on a new page (13); Iter5 flows them together.
- **The reference page 13 has "شروط العقد" as an H1-style centered heading with large vertical whitespace** (it appears as a section divider page — mostly blank). This "section divider" page style is entirely missing in Iter5; the title appears inline.

---

### Page 13 — شروط العقد section divider (mostly blank, just the title)

**Reference:** Nearly blank page. Logo/header top. Centered "شروط العقد" title (large, bold). Remainder of page is white space. Footer at bottom. This is a deliberate Word page-break section divider.

**Iter5:** No equivalent blank section-divider page. "شروط العقد" and "القسم الأول: الأحكام العامة" appear consecutively without the preceding blank page and without a page break.

**Gaps remaining:**
- **Section-divider blank pages are entirely absent.** The Word document uses page breaks to create dedicated title pages for major sections (شروط العقد, الشروط المالية, etc.). These are not converted at all in Iter5.

---

### Page 14 — التعريفات table start (القسم الأول: الأحكام العامة, section 1)

**Reference:** Header band. H2 "القسم الأول: الأحكام العامة" (bold, with dot leaders / horizontal rule in reference style). H3 "١. التَّعريفات". Blue annotation. Body intro sentence. Then a two-column definitions table with dark-gray header row (التعريف | المصطلح), followed by rows for each term.

**Iter5 (page-14):** The definitions table renders. The dark-gray header row is present. Cell content is in RTL. First several rows (نظام المنافسات والمشتريات, اللائحة التنفيذية, ممثل الجهة, الأعمال, المعدات, كتيبات التشغيل, الأعمال المؤقتة, الصيانة العلاجية, الصيانة الوقائية, التَّشغيل, الموقع, ظروف الموقع) are visible.

**Gaps remaining:**
- **Table cell RTL text alignment:** In the reference the right column (المصطلح) is right-aligned and the left column (التعريف) is right-aligned within its cell. In Iter5 the definition text column appears left-aligned or mixed — needs verification across all rows.
- **Table alternating row shading:** Reference has plain white rows (no alternating color). Iter5 matches this (correct).
- **"التعريفات" heading has a decorative tashkeel (التَّعريفات):** renders correctly in both.
- **Multi-bullet cells (التَّشغيل row):** has two bullet points inside one cell. Iter5 renders these as bullet list items within the cell (correct structure, may have minor spacing differences).
- **No header/footer.**

---

### Page 15 — التعريفات table continued (remaining terms) + اللغة المعتمدة, العملة المعتمدة, etc.

**Reference:** Continuation of the definitions table (البيئة الخارجية, المقاولة الباطنة, جدول الكميات والأسعار, يوم/يوما, البوابة). Then H3 sections: ٢. اللغة المعتمدة, ٣. العملة المعتمدة, ٤. الضرائب والرسوم, ٥. الإخطارات والمراسلات with body text.

**Iter5 (scroll-08 / scroll-09):** These sections render in the scroll bands. The table continuation (الملكية الفكرية, جدول الكميات والسعر, يوم/يوما, البوابة rows) is visible. H3 sections ٢–٥ render with HR lines above (correct).

**Gaps remaining:**
- **"جدول الكميات والأسعار [BoQ]" cell:** reference shows it in red italics. Iter5 renders it in plain red without italic — the italic style on this cell content may be dropped.
- **"يوم/يوما" cell:** reference body is in red. Iter5 appears to render it red (correct).
- **H3 section headings ٢. اللغة المعتمدة etc.:** render correctly with HR lines above.
- **No header/footer.**

---

### Page 16 — السجلات section (section 6) and surroundings

**Reference:** Header band. H3 "٦. السجلات" with HR above. Body paragraphs in RTL. Content is dense Arabic text about record-keeping requirements. Then "٧. التراخيص ووثائق التسجيل والتصاريح" H3 section heading.

**Iter5 (page-16):** Anchor lands at section 6 السجلات. Body paragraphs render in RTL. Text content appears correct.

**Gaps remaining:**
- **Text density / line-height:** Reference body paragraphs have noticeably more generous line-height and paragraph spacing than Iter5. Iter5 body paragraphs are rendered with tighter spacing, making long sections harder to read and causing the content to scroll far beyond the expected page count.
- **No header/footer.**

---

## Summary

### What Iter5 gets right (wins vs earlier iterations)
- TOC renders with 128 entries and functional hyperlinks (127/127 working)
- Arabic-Indic numerals in TOC page numbers
- RTL direction applied to paragraph flow
- Three visually distinct H1 heading styles
- H3 headings get HR rule above (paragraph border)
- Tab characters emit text nodes (dot leaders work)
- Empty leading paragraphs dropped
- Red / blue / green inline placeholder text colors preserved
- Two-column definitions table renders with correct dark-gray header
- Signature table renders (two-column)

### Remaining gaps ranked by visibility

| Rank | Severity | Gap |
|------|----------|-----|
| 1 | **Critical** | **No page header/footer band on any page.** Every reference page has the logo + 3-line header top and the ruled "X من 58 / رقم النسخة / رقم العقد" footer bottom. Iter5 has neither. This is the single most visible difference affecting every page. |
| 2 | **Critical** | **Section-divider blank pages missing.** The reference uses Word page breaks to create near-blank pages for major section titles (شروط العقد p.13, and likely الشروط المالية, نطاق العمل المفصل, etc.). None of these exist in Iter5. |
| 3 | **Critical** | **No page-break separation anywhere.** The entire document is one continuous scroll. Reference has hard page breaks between cover, TOC, and all sections. |
| 4 | **High** | **TOC layout — page numbers not flush to the left margin.** Dot leaders do not consistently span the full line width; page numbers float rather than anchoring at the left edge. |
| 5 | **High** | **Underline missing on key headings.** "دليل الاستخدام", "ملاحظة وتنويه:", "وثيقة العقد الأساسية" are underlined in the reference; the underline is absent in Iter5. |
| 6 | **High** | **Cover page layout broken.** Logo and header are present but the cover does not stand alone; it bleeds into the TOC with no separation. The "/ / /" spacing in the date placeholder is wrong. |
| 7 | **Medium** | **Lettered list indentation (ا. ب. ج. …) missing hanging-indent structure.** Items appear as plain text continuations rather than a visually indented list. |
| 8 | **Medium** | **Body text line-height / paragraph spacing too tight** relative to reference, causing the content to appear denser and more compressed. |
| 9 | **Medium** | **Anchor mis-targeting for pages 3 / 5 / 8.** Capture anchors for TOC pages 3 and 5 resolve to body content, making QA comparison impossible for those pages. (Anchor config fix needed, not a converter issue.) |
| 10 | **Low** | **"[BoQ] جدول الكميات والأسعار" cell content missing italic** on the row label. |
| 11 | **Low** | **TOC page-number off-by-one for الملحقات** (Iter5 shows ٥٩, reference shows ٥٨). |
| 12 | **Low** | **Logo text direction inside placeholder box** may be LTR-rendered. |

### Recommendation: Iterate

Do not ship. The two critical gaps — **missing page header/footer band** and **no page-break / section structure** — mean the rendered output looks nothing like the reference document at the macro level. Every single page in the reference has the institutional header and footer; every section starts on a fresh page. These are structural requirements of the contract template, not cosmetic polish.

**Suggested Iter6 priorities:**
1. Implement per-page header (logo image + three right-aligned lines) and footer (page-number line + ruled footer line) — these should be injected as fixed elements in the sandbox renderer if the converter cannot produce them as TipTap nodes.
2. Emit `pageBreak` TipTap nodes at the points where Word has `<w:br w:type="page"/>` or section breaks — this will restore the cover / section-divider pages.
3. Fix the TOC row layout: use a CSS grid or flexbox row with `flex: 1` dot-leader span so the page number always anchors at the far left.
4. Add `text-decoration: underline` to the appropriate heading styles (Usage Guide h2, ملاحظة وتنويه subheading).
5. Fix body paragraph line-height (target ~1.6–1.8 to match the reference).
