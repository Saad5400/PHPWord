# Representative XML structures from `word/document.xml`

`word/document.xml` is 1,457,459 bytes. Byte offsets below are within the *uncompressed* `document.xml` extracted from the docx (`unzip -p TESTFILE word/document.xml`).

To re-extract:
```bash
unzip -p testing-documents/التشغيل-والصيانة.docx word/document.xml > /tmp/document.xml
```

Then `dd if=/tmp/document.xml bs=1 skip=<offset> count=<length>` (or just open in an editor that shows byte offsets).

---

## 1. Numbered Heading1 paragraph (depth 1, with bookmark anchor)

**Byte offset**: 383,861 (length ≈ 880 B)

```xml
<w:p ... w:rsidP="009160B1">
  <w:pPr>
    <w:pStyle w:val="Heading1"/>
    <w:numPr>
      <w:ilvl w:val="0"/>
      <w:numId w:val="0"/>      <!-- "no list / disable inherited numbering" -->
    </w:numPr>
    <w:bidi/>
    <w:spacing w:before="240" w:after="0"/>
    <w:ind w:left="360"/>
    <w:contextualSpacing w:val="0"/>
    <w:jc w:val="center"/>
    <w:rPr>
      <w:rFonts w:ascii="DIN Next LT Arabic" w:hAnsi="DIN Next LT Arabic" w:cs="DIN Next LT Arabic"/>
      <w:color w:val="0070C0"/>
      <w:sz w:val="24"/><w:szCs w:val="24"/>
      <w:rtl/>
    </w:rPr>
  </w:pPr>
  <w:bookmarkStart w:id="4" w:name="_Toc38563304"/>
  <w:r ...>
    <w:rPr>...same fonts/color/sz/rtl...</w:rPr>
    <w:t>دليل الاستخدام</w:t>
  </w:r>
  <w:bookmarkEnd w:id="4"/>
</w:p>
```

Notes for the implementer:
- PHPWord parses this as **`ListItemRun`** with `paragraphStyle.styleName = "Heading1"`, depth=0.
- `bookmarkStart`/`bookmarkEnd` (`_Toc38563304`) is what the corresponding TOC hyperlink points to (see §6).
- The numId=0 is Word's "list-disabling" sentinel; the converter currently still treats this as a list item but `headingLevelFromStyle()` correctly promotes it to `heading` (level 1) before the list grouping kicks in.

---

## 2. Numbered Heading3 paragraph (with paragraph border)

**Byte offset**: 417,137 (length ≈ 950 B)

```xml
<w:p ...>
  <w:pPr>
    <w:pStyle w:val="Heading3"/>
    <w:numPr>
      <w:ilvl w:val="0"/>
      <w:numId w:val="18"/>          <!-- references numbering.xml -->
    </w:numPr>
    <w:pBdr>
      <w:top w:val="single" w:sz="4" w:space="1" w:color="auto"/>   <!-- visual separator -->
    </w:pBdr>
    <w:bidi/>
    <w:spacing w:before="240" w:after="0"/>
    <w:ind w:left="432" w:hanging="432"/>      <!-- hanging indent for the heading number -->
    <w:jc w:val="both"/>
    <w:rPr>...rtl...</w:rPr>
  </w:pPr>
  <w:bookmarkStart w:id="9" w:name="_Toc32151357"/>
  <w:bookmarkStart w:id="10" w:name="_Toc38563306"/>
  <w:r>
    <w:rPr>...rtl...</w:rPr>
    <w:t>تمهيد</w:t>
  </w:r>
  <w:bookmarkEnd w:id="9"/>
  <w:bookmarkEnd w:id="10"/>
</w:p>
```

Things lost by the current converter on this single paragraph: the top border (gap #9), the hanging indent (gap #6), the bookmark anchor (gap #16), and the leading multi-level number from `numId=18` (gap #7).

---

## 3. Nested list item (depth 2 inside `BodyText` ListParagraph)

**Byte offset**: 1,027,985 (length ≈ 750 B)

Surrounding context (from byte 1,024,447):
```
  ilvl=0  دفع قيمة اللوازم والمواد...
  ilvl=0  الإفراج عن ضمان الدفعة...
  ilvl=.  ثانيًا :        <- a non-numbered intro paragraph
> ilvl=2  يجوز للجهة الحكومية...     <- the depth-2 item
  ilvl=2  في حالة إنهاء العقد...
  ilvl=0  الشروط المالية              <- jumps back to depth 0
```

The depth-2 paragraph itself:
```xml
<w:p ...>
  <w:pPr>
    <w:pStyle w:val="ListParagraph"/>
    <w:numPr>
      <w:ilvl w:val="2"/>
      <w:numId w:val="19"/>
    </w:numPr>
    <w:bidi/>
    <w:spacing w:before="240"/>
    <w:ind w:left="644"/>
    <w:jc w:val="both"/>
  </w:pPr>
  <w:r>
    <w:rPr>...DIN Next LT Arabic, sz 24, rtl...</w:rPr>
    <w:t>يجوز للجهة الحكومية إذا أنهت العقد بناءً على توصية...</w:t>
  </w:r>
</w:p>
```

The converter's `collectList()` correctly groups these into nested `bulletList`/`orderedList` based on depth — but note the depth jump from 0 → 2 (skipping 1) which the existing nesting code handles by inserting empty placeholder `listItem`s.

---

## 4. Table with `gridSpan` column-merge (Table 3)

**Byte offset**: 1,153,529 (length 28,442 B; ends 1,181,971)

The table opens with a `<w:tblGrid>` declaring **9 columns**, then the first body row (row 2) has its second cell span 8 of them via `<w:gridSpan w:val="8"/>`:

```xml
<w:tbl>
  <w:tblPr>
    <w:bidiVisual/>                            <!-- RTL table -->
    <w:tblW w:w="..." w:type="dxa"/>
    <w:tblBorders>
      <w:top    w:val="single" w:sz="4" w:color="auto"/>
      <w:bottom w:val="single" w:sz="4" w:color="auto"/>
      ... (left, right, insideH, insideV)
    </w:tblBorders>
    <w:tblLayout w:type="fixed"/>
  </w:tblPr>
  <w:tblGrid>
    <w:gridCol w:w="..."/> × 9
  </w:tblGrid>
  <w:tr>...header row, 9 cells, all single-width...</w:tr>
  <w:tr>
    <w:tc>
      <w:tcPr>
        <w:tcW w:w="3032" w:type="dxa"/>
        <w:tcBorders>...single sz=4 all sides...</w:tcBorders>
        <w:shd w:val="clear" w:color="auto" w:fill="FFFFFF" w:themeFill="background2"/>
        <w:vAlign w:val="bottom"/>
      </w:tcPr>
      <w:p>...<w:r><w:t>الإداري</w:t></w:r>...</w:p>
    </w:tc>
    <w:tc>
      <w:tcPr>
        <w:tcW w:w="6321" w:type="dxa"/>
        <w:gridSpan w:val="8"/>                 <!-- ← merged cell -->
        <w:tcBorders>...</w:tcBorders>
        ...
      </w:tcPr>
      <w:p>...content for the merged cell...</w:p>
    </w:tc>
  </w:tr>
  ... 5 more rows ...
</w:tbl>
```

The converter reads `gridSpan` correctly into `colspan`. It does **not** propagate `<w:bidiVisual/>` (so column order will appear LTR in the output), nor table borders / cell shading (so the result is an unstyled grid).

---

## 5. Table-of-contents paragraph (TOC1) — complex field with hyperlink, tab leader, page number

**Byte offset**: 17,637 (length ≈ 2,800 B). This is the first TOC entry; the following 127 TOC3 entries follow the same pattern.

```xml
<w:p>
  <w:pPr>
    <w:pStyle w:val="TOC1"/>
    <w:bidi/>
    <w:rPr>...</w:rPr>
  </w:pPr>

  <!-- field begin marker -->
  <w:r><w:rPr>...</w:rPr><w:fldChar w:fldCharType="begin"/></w:r>
  <w:r><w:rPr>...</w:rPr><w:instrText xml:space="preserve"> TOC \o "1-3" \h \z \u </w:instrText></w:r>
  <w:r><w:rPr>...</w:rPr><w:fldChar w:fldCharType="separate"/></w:r>

  <!-- the rendered TOC entry: hyperlink wrapping a heading text + tab + page-ref field -->
  <w:hyperlink w:anchor="_Toc38563304" w:history="1">
    <w:r>
      <w:rPr><w:rStyle w:val="Hyperlink"/><w:rtl/></w:rPr>
      <w:t>دليل الاستخدام</w:t>
    </w:r>
    <w:r><w:rPr><w:webHidden/></w:rPr><w:tab/></w:r>      <!-- dotted-leader tab -->
    <w:r>
      <w:rPr><w:rStyle w:val="Hyperlink"/><w:rtl/></w:rPr>
      <w:fldChar w:fldCharType="begin"/>
    </w:r>
    <w:r>
      <w:rPr><w:webHidden/></w:rPr>
      <w:instrText xml:space="preserve"> PAGEREF _Toc38563304 \h </w:instrText>
    </w:r>
    <w:r>...separate...</w:r>
    <w:r>
      <w:rPr><w:webHidden/><w:rtl/></w:rPr>
      <w:t>6</w:t>                               <!-- the rendered page number -->
    </w:r>
    <w:r>...end fldChar...</w:r>
  </w:hyperlink>

  <!-- TOC field end -->
  <w:r>...<w:fldChar w:fldCharType="end"/>...</w:r>
</w:p>
```

PHPWord's docx reader does **not** surface this paragraph at all in `Section::getElements()` — it's silently dropped. To handle this the converter needs to parse `word/document.xml` directly for any paragraph with `pStyle ∈ {TOC1, TOC2, TOC3, TOCHeading}` and emit an appropriate node tree.

The 127 sibling TOC3 entries (starting at byte ≈ 19,000 onward) follow the same structure but with `<w:pStyle w:val="TOC3"/>` and indented `<w:ind w:left="...">`.

---

## 6. Bookmark target (corresponding to the link in §5)

The TOC link `w:anchor="_Toc38563304"` lands on the bookmark in §1 — `<w:bookmarkStart w:id="4" w:name="_Toc38563304"/>` at byte 383,861.

Total bookmarks: 571. Distribution:
- `_Toc*` — 507 (one for every Heading paragraph, occasionally duplicated)
- `_Hlk*` / `_GoBack` — 60 (Word's autosave / cursor-position markers; safe to ignore)
- `_Ref*` — 4 (cross-reference targets)

To make internal links functional, every paragraph that contains `<w:bookmarkStart>` needs to surface its `w:name` as an `id` (or anchor) on the corresponding TipTap node.

---

## 7. SDT (content control) wrapping placeholder text

**Byte offset**: 6,926 (the first of 7 SDTs, all on the cover page)

```xml
<w:sdt>
  <w:sdtPr>
    <w:rPr>...DIN Next LT Arabic, sz 28, rtl...</w:rPr>
    <w:id w:val="481423982"/>
    <w:placeholder>
      <w:docPart w:val="4205E44ADD7743A5938E2E00B41410DE"/>
    </w:placeholder>
  </w:sdtPr>
  <w:sdtEndPr/>
  <w:sdtContent>
    <w:sdt>                                     <!-- nested SDT -->
      ...same structure...
      <w:sdtContent>
        <w:r>...<w:t xml:space="preserve"> </w:t></w:r>
        <w:r>
          <w:rPr><w:color w:val="FF0000"/>...</w:rPr>
          <w:t xml:space="preserve">(وفقًا لمنصة اعتماد)  </w:t>
        </w:r>
      </w:sdtContent>
    </w:sdt>
  </w:sdtContent>
</w:sdt>
```

PHPWord generally unwraps `<w:sdtContent>` and surfaces the inner runs through the normal `TextRun`/`ListItemRun` path — so in this document the cover-page placeholder text *does* reach the converter. But the unwrapping is brittle; if a future docx uses a checkbox or date-picker SDT (`<w:sdtPr><w:checkbox/>` etc.), PHPWord may skip it entirely.

---

## 8. Header file structure (`word/header2.xml`, default header)

The default body header is referenced by `<w:headerReference w:type="default" r:id="rId12"/>` in the section's `<w:sectPr>`. Its body:

```xml
<w:hdr>
  <w:p>
    <w:pPr>
      <w:pStyle w:val="Header"/>
      <w:tabs>
        <w:tab w:val="clear" w:pos="4680"/>
        <w:tab w:val="clear" w:pos="9360"/>
        <w:tab w:val="right" w:pos="9905"/>
      </w:tabs>
      <w:bidi/>
      <w:rPr>...DIN Next LT Arabic, rtl...</w:rPr>
    </w:pPr>
    <w:r>
      <w:rPr><w:noProof/><w:rtl/></w:rPr>
      <mc:AlternateContent>
        <mc:Choice Requires="wps">
          <w:drawing>
            <wp:anchor ...>
              <!-- positioned shape group containing the logo + Arabic text "شعار الجهة"/"المملكة العربية السعودية"/"اسم الجهة الحكومية"/"اسم النموذج" -->
            </wp:anchor>
          </w:drawing>
        </mc:Choice>
        <mc:Fallback>
          <w:pict>...VML fallback...</w:pict>
        </mc:Fallback>
      </mc:AlternateContent>
    </w:r>
  </w:p>
</w:hdr>
```

Key points:
- The header is mostly a positioned drawing (`<wp:anchor>`) — extracting it well needs DrawingML support, which the converter currently doesn't have.
- The first-page header (`header1.xml`) contains the same logo block plus a "DRAFT" watermark drawing.
- Footer1/footer2 contain a one-row `<w:tbl>` with literal page-numbering text — those *do* extract cleanly via PHPWord's table reader, the converter just never asks for them.

---

## 9. Section properties (single section, end of body)

**Byte offset**: 1,455,841 (very near the end of `document.xml`)

```xml
<w:sectPr w:rsidR="00D62E85" w:rsidRPr="00FC5458" w:rsidSect="0011181B">
  <w:headerReference w:type="even"    r:id="rId11"/>     <!-- header1.xml -->
  <w:headerReference w:type="default" r:id="rId12"/>     <!-- header2.xml -->
  <w:footerReference w:type="default" r:id="rId13"/>     <!-- footer1.xml -->
  <w:headerReference w:type="first"   r:id="rId14"/>     <!-- header3.xml -->
  <w:footerReference w:type="first"   r:id="rId15"/>     <!-- footer2.xml -->
  <w:pgSz w:w="11907" w:h="16839" w:code="9"/>           <!-- A4 portrait -->
  <w:pgMar w:top="720" w:right="922" w:bottom="1267" w:left="1080" w:header="288" w:footer="432" w:gutter="0"/>
  <w:cols w:space="720"/>                                 <!-- single column -->
  <w:titlePg/>                                            <!-- different first-page header -->
  <w:docGrid w:linePitch="360"/>
</w:sectPr>
```

---

## 10. Field types in this document

Two distinct complex-field instructions appear:

| Instruction                          | Count | Where |
|--------------------------------------|------:|-------|
| `TOC \o "1-3" \h \z \u`              | 1     | inside the TOC1 paragraph at byte 17,637 |
| `PAGEREF _Toc##### \h`               | 127   | inside each TOC entry, supplies the page number |

No `<w:fldSimple>` is used — every field is wrapped in the multi-run `begin`/`instrText`/`separate`/`...result runs.../end` complex-field idiom. The converter's `convertInlineRun()` does not recognise these markers; the *result* runs (the rendered page numbers and link text) leak through as plain text only when they happen to be inside paragraphs PHPWord exposes — which TOC paragraphs are not.

---

## How to find more in the raw XML

```bash
# Count any feature
grep -oE '<w:tabs>' /tmp/document.xml | wc -l

# Find the byte offset of the first match
python3 -c "import re,sys; d=open('/tmp/document.xml').read(); m=re.search(r'<w:tbl>',d); print(m.start() if m else 'none')"

# Extract a slice
python3 -c "d=open('/tmp/document.xml').read(); print(d[1153529:1156000])"

# The full analyser output
php work/inventory/analyse.php > work/inventory/analyse.out
```
