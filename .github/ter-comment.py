#!/usr/bin/env python3
"""Build the TER upload comment for a version from CHANGELOG.md.

TER shows the comment as plain text, so the Markdown of the changelog section is
flattened: headings become "Added:", code ticks and emphasis are removed, links
become "text (url)" and hard-wrapped lines are joined again. The comment ends with
a link to the full changelog on GitHub.

Usage: python3 .github/ter-comment.py 2.2.1
"""
import json
import re
import sys
from pathlib import Path

MAX_LENGTH = 1500


def section(changelog: str, version: str) -> list[str]:
    lines = changelog.splitlines()
    start = None
    for i, line in enumerate(lines):
        if re.match(r"^## \[?" + re.escape(version) + r"\]?(\s|$)", line):
            start = i + 1
        elif start is not None and line.startswith("## "):
            return lines[start:i]
    return lines[start:] if start is not None else []


def inline(text: str) -> str:
    text = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r"\1 (\2)", text)
    text = text.replace("`", "")
    text = re.sub(r"\*\*([^*]+)\*\*", r"\1", text)
    text = re.sub(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])", r"\1", text)
    text = re.sub(r"(?<!\w)_([^_\n]+)_(?!\w)", r"\1", text)
    return text


def flatten(lines: list[str]) -> str:
    # Join wrapped lines into blocks first, then strip inline Markdown, so that
    # emphasis spanning a line break is removed as well.
    blocks: list[str] = []
    for raw in lines:
        stripped = raw.strip()
        if not stripped:
            if blocks and blocks[-1] != "":
                blocks.append("")
            continue
        heading = re.match(r"^#{1,6}\s+(.*)$", stripped)
        if heading:
            if blocks and blocks[-1] != "":
                blocks.append("")
            blocks.append("\x00" + heading.group(1))
            continue
        bullet = re.match(r"^[-*+]\s+(.*)$", stripped)
        if bullet:
            blocks.append("- " + bullet.group(1))
            continue
        if blocks and blocks[-1] != "" and not blocks[-1].startswith("\x00"):
            blocks[-1] += " " + stripped
        else:
            blocks.append(stripped)
    out = []
    for block in blocks:
        if block.startswith("\x00"):
            out.append(inline(block[1:]).rstrip(":") + ":")
        else:
            out.append(inline(block))
    while out and out[-1] == "":
        out.pop()
    while out and out[0] == "":
        out.pop(0)
    return "\n".join(out)


def main() -> int:
    version = sys.argv[1].lstrip("v")
    root = Path(__file__).resolve().parent.parent
    composer = json.loads((root / "composer.json").read_text(encoding="utf-8"))
    source = composer.get("support", {}).get("source", "").rstrip("/")
    footer = f"Full changelog: {source}/blob/main/CHANGELOG.md" if source else ""

    body = flatten(section((root / "CHANGELOG.md").read_text(encoding="utf-8"), version))
    if not body:
        body = f"Release {version}"
    limit = MAX_LENGTH - len(footer) - 4
    if len(body) > limit:
        cut = body[:limit]
        cut = cut[: cut.rfind("\n")] if "\n" in cut else cut
        kept = cut.rstrip().split("\n")
        # Do not end on an empty line or a heading without entries
        while kept and (kept[-1] == "" or kept[-1].endswith(":")):
            kept.pop()
        body = "\n".join(kept) + "\n..."
    print(body + ("\n\n" + footer if footer else ""))
    return 0


if __name__ == "__main__":
    sys.exit(main())
