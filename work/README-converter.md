# Word → TipTap Converter

A standalone library that converts a Word document (`.docx`) into a TipTap-compatible JSON document, with a matching JS renderer that walks the JSON and produces HTML. Both halves are designed to be dropped into any downstream PHP / browser project; the `work/sandbox/` files are just one example consumer.

## Quick start

```php
require __DIR__ . '/work/converter/ConverterConfig.php';
require __DIR__ . '/work/converter/RawXmlIndex.php';
require __DIR__ . '/work/converter/TipTapConverter.php';

$conv = new \PhpWord\TipTap\TipTapConverter();              // defaults
$doc  = $conv->convertFile('/path/to/file.docx');
if ($conv->config->arabicIndicNumerals) {
    $doc = \PhpWord\TipTap\TipTapConverter::applyArabicIndicNumerals($doc);
}
file_put_contents('doc.json', json_encode($doc, JSON_UNESCAPED_UNICODE));
```

## ConverterConfig options

Pass a `ConverterConfig` instance to the constructor to override any defaults. All fields are public; mutate them directly.

| Property                | Type     | Default | Description                                                                          |
|-------------------------|----------|---------|--------------------------------------------------------------------------------------|
| `allowedFonts`          | `array`  | `[]`    | Allow-list of font families to map run fontFamily values to. Empty = pass-through.   |
| `defaultFont`           | `string` | `''`    | Family substituted when a run's font isn't in `allowedFonts`.                        |
| `fontSizeUnit`          | `string` | `'pt'`  | CSS unit emitted on font sizes (`'pt'` or `'px'`).                                   |
| `defaultRtl`            | `bool`   | `true`  | Default `dir="rtl"` on paragraphs/headings without an explicit `<w:bidi/>` flag.     |
| `reconstructToc`        | `bool`   | `true`  | Reconstruct the TOC paragraphs PHPWord drops, by parsing `word/document.xml`.        |
| `prefixHeadingNumbers`  | `bool`   | `true`  | Prepend computed multi-level numbers (`"1.1.2."`) on headings.                       |
| `emitBookmarkIds`       | `bool`   | `true`  | Attach `attrs.id` from `<w:bookmarkStart w:name="_Toc...">` so TOC links resolve.    |
| `patchSdtPlaceholders`  | `bool`   | `true`  | Patch SDT placeholder text PHPWord drops, on paragraphs whose runs match a leader.   |
| `injectHeaderFooter`    | `bool`   | `true`  | Prepend section header lines + logo from `header2.xml`; append `footer1.xml` lines.  |
| `arabicIndicNumerals`   | `bool`   | `true`  | Caller-side hint: should `applyArabicIndicNumerals()` be invoked on the doc tree.    |
| `dropEmptyParagraphs`   | `bool`   | `true`  | Drop paragraphs with no inline content. Vertical rhythm comes from `marginTop/Bottom`.|
| `nodeTypeMap`           | `array`  | `[]`    | Rename emitted TipTap node types (e.g. `['pageBreak' => 'customPageBreak']`).        |

### Renaming emitted node types

`nodeTypeMap` lets a downstream project that uses a plugin with a different schema name swap the emitted type without forking:

```php
$cfg = new \PhpWord\TipTap\ConverterConfig();
$cfg->nodeTypeMap = [
    'pageBreak' => 'customPageBreak',  // your plugin's node name
    // 'table'   => 'pluginTable',     // alias others as needed
];
$conv = new \PhpWord\TipTap\TipTapConverter($cfg);
```

The map is consulted at every emission site. Defaults are an empty map — i.e. canonical TipTap names — which already match `tiptap-pagination-plus` and `tiptap-table-plus` (see below). Note: a few internal helpers (TOC anchor lookup, empty-paragraph drop) match against canonical names; remapping `paragraph`, `heading`, `text`, or `pageBreak` is supported, but the rest of the pipeline expects the *output* under those mapped names — internal walks pre-emission still operate canonically.

## Integration with `tiptap-pagination-plus` and `tiptap-table-plus`

The converter's default output is **drop-in compatible** with the [clm](../clm) project's TipTap plugins — no schema changes or `nodeTypeMap` overrides needed:

- `pageBreak` nodes are natively handled by `tiptap-pagination-plus`.
- `table`, `tableRow`, `tableCell`, `tableHeader` names are preserved by `tiptap-table-plus` (it extends the base TipTap table classes, keeping the same node names).

Example TipTap editor setup (Vue 3) that consumes the converter output directly:

```js
import { useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import { PaginationPlus, A4_PAGE_SIZE } from 'tiptap-pagination-plus'
import { PaginationTable } from 'tiptap-table-plus'

useEditor({
  extensions: [
    StarterKit,
    PaginationPlus.configure({ pageSize: A4_PAGE_SIZE }),
    ...Object.values(PaginationTable),
    // ... other extensions
  ],
  content: converterOutput, // direct TipTap JSON from the PHP converter
})
```

If a future plugin renames a node, set `ConverterConfig::$nodeTypeMap` accordingly and the converter's emitted JSON adapts without touching library internals.

## JS renderer overrides

The `TipTapRenderer` class in `work/sandbox/index.html` exposes per-node and per-mark overrides via constructor options. Defaults live on `TipTapRenderer.DEFAULT_NODE_RENDERERS` / `TipTapRenderer.DEFAULT_MARK_RENDERERS` and are merged with whatever you pass in.

```js
const renderer = new TipTapRenderer({
  nodeRenderers: {
    pageBreak: () => '<hr class="my-break" />',
    table: (node, ctx) => myTablePlugin.render(node),
  },
  markRenderers: {
    link: (m) => ({ tag: 'a', attrs: { href: m.attrs.href, class: 'ext-link' } }),
  },
});
document.getElementById('page').innerHTML = renderer.render(doc);
```

Each renderer receives `(node, ctx)` (or `(mark, ctx)` for marks); `ctx` is the renderer instance, so override implementations can call `ctx._renderChildren(node.content)` or `ctx._blockAttrs(node)` to delegate back to the defaults.

## Node types emitted

The converter emits a vanilla TipTap document. Node types you may encounter:

- `doc`
- `paragraph`
- `heading`
- `bulletList`, `orderedList`, `listItem`
- `table`, `tableRow`, `tableCell`, `tableHeader`
- `hardBreak`
- `horizontalRule`
- `pageBreak` (custom node — render as a visual divider; not part of stock TipTap)
- `image`
- `text`

Marks: `bold`, `italic`, `underline`, `strike`, `superscript`, `subscript`, `code`, `link`, `textStyle` (carries `color`, `fontSize`, `fontFamily`, `backgroundColor`, `lineHeight`).

## Extending for a specific project

Drop `ConverterConfig.php`, `RawXmlIndex.php`, `TipTapConverter.php` into your PHP project (any PSR-4 autoloader picks up the `PhpWord\TipTap` namespace) and instantiate with whatever config your document set needs. On the browser side, copy the `TipTapRenderer` class out of the sandbox HTML into your own JS module and override `nodeRenderers` / `markRenderers` to match your design system — no fork required.
