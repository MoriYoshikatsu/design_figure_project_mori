#!/usr/bin/env python3

from __future__ import annotations

import html
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parent
SOURCE = ROOT / "17_Webアプリ説明書.md"
OUTPUT = ROOT / "17_Webアプリ説明書_Word用.html"

HEADING_RE = re.compile(r"^(#{1,3})\s+(.*)$")
ORDERED_RE = re.compile(r"^\d+\.\s+(.*)$")
UNORDERED_RE = re.compile(r"^-\s+(.*)$")
IMAGE_RE = re.compile(r"^!\[(.*)\]\((.*)\)$")
INLINE_RE = re.compile(r"`([^`]+)`|\[([^\]]+)\]\(([^)]+)\)|\*\*([^*]+)\*\*")


def slugify(text: str) -> str:
    slug = text.strip().lower().replace(".", "")
    slug = slug.replace(" ", "-")
    slug = re.sub(r"[^0-9a-zA-Zぁ-んァ-ヶ一-龠ー\-_]", "", slug)
    return slug


def render_inline(text: str) -> str:
    parts: list[str] = []
    pos = 0
    for match in INLINE_RE.finditer(text):
        parts.append(html.escape(text[pos:match.start()]))
        if match.group(1) is not None:
            parts.append(f"<code>{html.escape(match.group(1))}</code>")
        elif match.group(2) is not None and match.group(3) is not None:
            label = html.escape(match.group(2))
            href = html.escape(match.group(3), quote=True)
            parts.append(f'<a href="{href}">{label}</a>')
        else:
            parts.append(f"<strong>{html.escape(match.group(4))}</strong>")
        pos = match.end()
    parts.append(html.escape(text[pos:]))
    return "".join(parts)


def render_paragraph(lines: list[str]) -> str:
    fragments: list[str] = []
    for index, raw_line in enumerate(lines):
        text = raw_line.rstrip()
        if index > 0:
            if lines[index - 1].endswith("  "):
                fragments.append("<br>")
            else:
                fragments.append(" ")
        fragments.append(render_inline(text))
    return f"<p>{''.join(fragments)}</p>"


def render_table(table_lines: list[str]) -> str:
    rows = []
    for line in table_lines:
        if not line.strip():
            continue
        cells = [cell.strip() for cell in line.strip().strip("|").split("|")]
        if cells and all(re.fullmatch(r"[-: ]+", cell or "") for cell in cells):
            continue
        rows.append(cells)
    if not rows:
        return ""

    header = rows[0]
    body = rows[1:]
    header_html = "".join(f"<th>{render_inline(cell)}</th>" for cell in header)
    body_html = []
    for row in body:
        cells = "".join(f"<td>{render_inline(cell)}</td>" for cell in row)
        body_html.append(f"<tr>{cells}</tr>")
    return (
        "<table>"
        f"<thead><tr>{header_html}</tr></thead>"
        f"<tbody>{''.join(body_html)}</tbody>"
        "</table>"
    )


def build_html(markdown_text: str) -> str:
    lines = markdown_text.splitlines()
    blocks: list[str] = []
    i = 0
    title = "Webアプリ説明書"

    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        if not stripped:
            i += 1
            continue

        heading = HEADING_RE.match(line)
        if heading:
            level = len(heading.group(1))
            text = heading.group(2).strip()
            if level == 1:
                title = text
            tag = f"h{level}"
            element_id = slugify(text)
            is_chapter = level == 2 and re.match(r"^\d+\.\s", text)
            extra_class = ' class="chapter"' if is_chapter else ""
            blocks.append(
                f'<{tag} id="{html.escape(element_id, quote=True)}"{extra_class}>{render_inline(text)}</{tag}>'
            )
            i += 1
            continue

        if stripped == "---":
            blocks.append("<hr>")
            i += 1
            continue

        image = IMAGE_RE.match(stripped)
        if image:
            alt_text = image.group(1).strip()
            src = html.escape(image.group(2).strip(), quote=True)
            caption = render_inline(alt_text)
            blocks.append(
                '<figure>'
                f'<img src="{src}" alt="{html.escape(alt_text, quote=True)}">'
                f"<figcaption>{caption}</figcaption>"
                "</figure>"
            )
            i += 1
            continue

        if stripped.startswith("|"):
            table_lines: list[str] = []
            while i < len(lines) and lines[i].strip().startswith("|"):
                table_lines.append(lines[i])
                i += 1
            blocks.append(render_table(table_lines))
            continue

        ordered = ORDERED_RE.match(line)
        if ordered:
            items: list[str] = []
            while i < len(lines):
                current = ORDERED_RE.match(lines[i])
                if not current:
                    break
                items.append(f"<li>{render_inline(current.group(1).strip())}</li>")
                i += 1
            blocks.append(f"<ol>{''.join(items)}</ol>")
            continue

        unordered = UNORDERED_RE.match(line)
        if unordered:
            items = []
            while i < len(lines):
                current = UNORDERED_RE.match(lines[i])
                if not current:
                    break
                items.append(f"<li>{render_inline(current.group(1).strip())}</li>")
                i += 1
            blocks.append(f"<ul>{''.join(items)}</ul>")
            continue

        paragraph_lines = []
        while i < len(lines):
            current = lines[i]
            current_stripped = current.strip()
            if not current_stripped:
                break
            if (
                HEADING_RE.match(current)
                or current_stripped == "---"
                or IMAGE_RE.match(current_stripped)
                or current_stripped.startswith("|")
                or ORDERED_RE.match(current)
                or UNORDERED_RE.match(current)
            ):
                break
            paragraph_lines.append(current)
            i += 1
        blocks.append(render_paragraph(paragraph_lines))

    content = "\n".join(blocks)

    return f"""<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{html.escape(title)}</title>
  <style>
    @page {{
      size: A4;
      margin: 22mm 18mm 22mm 18mm;
    }}
    body {{
      margin: 0 auto;
      padding: 24px 32px 48px;
      max-width: 980px;
      color: #1f2937;
      background: #ffffff;
      font-family: "Yu Gothic", "Meiryo", "Hiragino Kaku Gothic ProN", sans-serif;
      font-size: 11pt;
      line-height: 1.75;
    }}
    h1, h2, h3 {{
      color: #111827;
      line-height: 1.35;
      page-break-after: avoid;
    }}
    h1 {{
      font-size: 24pt;
      margin: 0 0 16px;
      padding-bottom: 10px;
      border-bottom: 2px solid #0f172a;
    }}
    h2 {{
      font-size: 16pt;
      margin: 32px 0 12px;
      padding-bottom: 4px;
      border-bottom: 1px solid #e2e8f0;
    }}
    h2.chapter {{
      font-size: 18pt;
      margin: 42px 0 14px;
      padding-bottom: 6px;
      border-bottom-color: #cbd5e1;
      page-break-before: always;
    }}
    h2.chapter:first-of-type {{
      page-break-before: auto;
    }}
    h3 {{
      font-size: 14pt;
      margin: 26px 0 10px;
    }}
    p, ul, ol, table, figure {{
      margin: 0 0 14px;
    }}
    ul, ol {{
      padding-left: 24px;
    }}
    li {{
      margin: 0 0 6px;
    }}
    hr {{
      border: none;
      border-top: 1px solid #cbd5e1;
      margin: 22px 0;
    }}
    table {{
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      font-size: 10.5pt;
    }}
    th, td {{
      border: 1px solid #94a3b8;
      padding: 7px 8px;
      vertical-align: top;
      word-break: break-word;
    }}
    th {{
      background: #e2e8f0;
      font-weight: 700;
    }}
    code {{
      padding: 0 4px;
      border-radius: 4px;
      background: #f1f5f9;
      font-family: "Consolas", "Cascadia Mono", monospace;
      font-size: 0.96em;
    }}
    a {{
      color: #1d4ed8;
      text-decoration: none;
    }}
    figure {{
      page-break-inside: avoid;
    }}
    img {{
      display: block;
      max-width: 100%;
      height: auto;
      margin: 0 auto;
      border: 1px solid #cbd5e1;
      background: #ffffff;
    }}
    figcaption {{
      margin-top: 8px;
      text-align: center;
      color: #475569;
      font-size: 9.5pt;
    }}
    strong {{
      color: #0f172a;
    }}
  </style>
</head>
<body>
{content}
</body>
</html>
"""


def main() -> None:
    markdown_text = SOURCE.read_text(encoding="utf-8")
    OUTPUT.write_text(build_html(markdown_text), encoding="utf-8")
    print(f"Wrote {OUTPUT}")


if __name__ == "__main__":
    main()
