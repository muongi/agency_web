#!/usr/bin/env python3
"""
Build Word (.docx) deliverables from the SUNU markdown documentation set.

Handles the constructs actually used in these documents: ATX headings, GFM
tables, fenced code blocks (the ASCII architecture diagrams), block quotes,
bullet/numbered/checkbox lists, horizontal rules and inline bold/italic/code/links.

Usage:  python3 build_docx.py [--outdir DIR]
"""

import os
import re
import argparse

from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK, WD_TAB_ALIGNMENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.section import WD_SECTION
from docx.oxml.ns import qn, nsdecls
from docx.oxml import parse_xml, OxmlElement

HERE = os.path.dirname(os.path.abspath(__file__))

# House palette — matches docs/generate_docs.py so every deliverable reads as one set.
DARK_BLUE = RGBColor(0x1B, 0x3A, 0x5C)
MEDIUM_BLUE = RGBColor(0x2C, 0x5F, 0x8A)
DARK_GRAY = RGBColor(0x33, 0x33, 0x33)
GRAY = RGBColor(0x66, 0x66, 0x66)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)

HDR_FILL = "1B3A5C"      # table header row
ALT_FILL = "F2F6FA"      # banded table row
CODE_FILL = "F4F5F2"     # code / diagram block
QUOTE_FILL = "FBF3E2"    # block quote

BODY_FONT = "Calibri"
MONO_FONT = "Consolas"

DOCS_EN = [
    {
        "src": "SUNU_01_ARCHITECTURE_SHORT_EN.md",
        "out": "SUNU_01_Solution_Architecture_EN.docx",
        "number": "Document 1",
        "title": "Solution Architecture",
        "subject": "Solution Architecture",
        "version": "2.0",
        "date": "17 September 2026",
    },
    {
        "src": "SUNU_01_TECHNICAL_ARCHITECTURE.md",
        "out": "SUNU_01_Technical_Architecture_EN.docx",
        "number": "Document 1",
        "title": "Overall and Detailed Technical Architecture",
        "subject": "Technical Architecture Specification",
        "version": "1.1 — adds pilot as-built status (§14)",
        "date": "17 September 2026",
    },
    {
        "src": "SUNU_02_SECURITY.md",
        "out": "SUNU_02_Security_EN.docx",
        "number": "Document 2",
        "title": "Security Architecture, Procedures and Controls",
        "subject": "Security Architecture & Procedures",
    },
    {
        "src": "SUNU_03_USER_GUIDE.md",
        "out": "SUNU_03_User_Guide_EN.docx",
        "number": "Document 3",
        "title": "User Guide — All Profiles",
        "subject": "User Guide",
    },
    {
        "src": "SUNU_04_DEPLOYMENT_PLAN.md",
        "out": "SUNU_04_Deployment_Plan_EN.docx",
        "number": "Document 4",
        "title": "Deployment Plan and Schedule",
        "subject": "Deployment Plan & Schedule",
    },
    {
        "src": "SUNU_05_DATA_IMPORT_FORMATS.md",
        "out": "SUNU_05_Data_Import_Formats_EN.docx",
        "section_breaks": False,
        "number": "Document 5",
        "title": "Data Import and Exchange Formats",
        "subject": "Data Import and Exchange Formats Specification",
        "version": "1.0",
        "date": "17 September 2026",
    },
]

DOCS_FR = [
    {
        "src": "SUNU_01_ARCHITECTURE_SHORT_FR.md",
        "out": "SUNU_01_Architecture_Solution_FR.docx",
        "number": "Document 1",
        "title": "Architecture de la solution",
        "subject": "Architecture de la solution",
        "version": "2.0",
        "date": "17 septembre 2026",
    },
    {
        "src": "SUNU_01_ARCHITECTURE_TECHNIQUE_FR.md",
        "out": "SUNU_01_Architecture_Technique_FR.docx",
        "number": "Document 1",
        "title": "Architecture technique globale et détaillée",
        "subject": "Spécification d\u2019architecture technique",
        "version": "1.1 — ajout de l\u2019état réel du pilote (§14)",
        "date": "17 septembre 2026",
    },
    {
        "src": "SUNU_02_SECURITE_FR.md",
        "out": "SUNU_02_Securite_FR.docx",
        "number": "Document 2",
        "title": "Architecture, procédures et dispositifs de sécurité",
        "subject": "Architecture et procédures de sécurité",
    },
    {
        "src": "SUNU_03_GUIDE_UTILISATEUR_FR.md",
        "out": "SUNU_03_Guide_Utilisateur_FR.docx",
        "number": "Document 3",
        "title": "Guide utilisateur — tous profils",
        "subject": "Guide utilisateur",
    },
    {
        "src": "SUNU_04_PLAN_DEPLOIEMENT_FR.md",
        "out": "SUNU_04_Plan_Deploiement_FR.docx",
        "number": "Document 4",
        "title": "Plan et calendrier de déploiement",
        "subject": "Plan et calendrier de déploiement",
    },
    {
        "src": "SUNU_05_FORMATS_IMPORT_DONNEES_FR.md",
        "out": "SUNU_05_Formats_Import_Donnees_FR.docx",
        "section_breaks": False,
        "number": "Document 5",
        "title": "Formats d\u2019import et d\u2019échange de données",
        "subject": "Spécification des formats d\u2019import et d\u2019échange de données",
        "version": "1.0",
        "date": "17 septembre 2026",
    },
]

STRINGS = {
    "en": {
        "programme": "Mobile Premium Collection Solution on POS Terminals",
        "client": "SUNU Assurances Vie Burkina Faso",
        "dept": " — Central Operations Department",
        "date": "31 August 2026",
        "classification": "Confidential — SUNU Assurances Vie and DBS SA",
        "subject": "Subject",
        "version": "Version",
        "version_val": "1.0 — draft for review",
        "date_label": "Date",
        "for": "Prepared for",
        "by": "Prepared by",
        "class_label": "Classification",
        "contents": "Contents",
        "toc_hint": "Right-click the field below and choose \u201cUpdate field\u201d to build the contents list.",
        "sources": ("Source requirements: Terms of Reference \u201cAcquisition d\u2019une solution digitale "
                    "de collecte de primes via TPE\u201d, 12 August 2026, and the SUNU requirements e-mail "
                    "of 21 August 2026."),
        "page": "Page ",
        "of": " of ",
    },
    "fr": {
        "programme": "Solution mobile de collecte de primes sur terminaux TPE",
        "client": "SUNU Assurances Vie Burkina Faso",
        "dept": " — Direction Centrale des Opérations",
        "date": "31 août 2026",
        "classification": "Confidentiel — SUNU Assurances Vie et DBS SA",
        "subject": "Objet",
        "version": "Version",
        "version_val": "1.0 — projet soumis à revue",
        "date_label": "Date",
        "for": "Destinataire",
        "by": "Émetteur",
        "class_label": "Classification",
        "contents": "Sommaire",
        "toc_hint": ("Faites un clic droit sur le champ ci-dessous et choisissez « Mettre à jour les "
                     "champs » pour générer le sommaire."),
        "sources": ("Exigences sources : Termes de Référence « Acquisition d\u2019une solution digitale "
                    "de collecte de primes via TPE », 12 août 2026, et courriel d\u2019exigences SUNU "
                    "du 21 août 2026."),
        "page": "Page ",
        "of": " sur ",
    },
}

AUTHOR = "DBS SA — Digital Business Services"
LOGO = os.path.join(HERE, "branding", "dbs_logo.png")
L = STRINGS["en"]          # rebound per language in main()


# --------------------------------------------------------------------------- helpers

def shade(element, fill):
    """Apply background shading to a cell or paragraph."""
    element.append(parse_xml(r'<w:shd {} w:val="clear" w:fill="{}"/>'.format(nsdecls("w"), fill)))


def cell_shading(cell, fill):
    shade(cell._tc.get_or_add_tcPr(), fill)


def para_shading(paragraph, fill):
    shade(paragraph._p.get_or_add_pPr(), fill)


def para_border(paragraph, edges=("left",), color="1B3A5C", size=18):
    pPr = paragraph._p.get_or_add_pPr()
    borders = OxmlElement("w:pBdr")
    for edge in edges:
        el = OxmlElement("w:" + edge)
        el.set(qn("w:val"), "single")
        el.set(qn("w:sz"), str(size))
        el.set(qn("w:space"), "6")
        el.set(qn("w:color"), color)
        borders.append(el)
    pPr.append(borders)


def table_borders(table, color="C9D4E0", size=4):
    tbl = table._tbl
    tblPr = tbl.tblPr
    borders = OxmlElement("w:tblBorders")
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        el = OxmlElement("w:" + edge)
        el.set(qn("w:val"), "single")
        el.set(qn("w:sz"), str(size))
        el.set(qn("w:space"), "0")
        el.set(qn("w:color"), color)
        borders.append(el)
    tblPr.append(borders)


def repeat_header(row):
    """Mark a table row as a header row that repeats across page breaks."""
    trPr = row._tr.get_or_add_trPr()
    el = OxmlElement("w:tblHeader")
    el.set(qn("w:val"), "true")
    trPr.append(el)


def keep_with_next(paragraph):
    pPr = paragraph._p.get_or_add_pPr()
    el = OxmlElement("w:keepNext")
    pPr.append(el)


def add_field(paragraph, instruction):
    """Insert a Word field code (used for TOC and page numbers)."""
    run = paragraph.add_run()
    begin = OxmlElement("w:fldChar"); begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText"); instr.set(qn("xml:space"), "preserve")
    instr.text = instruction
    sep = OxmlElement("w:fldChar"); sep.set(qn("w:fldCharType"), "separate")
    end = OxmlElement("w:fldChar"); end.set(qn("w:fldCharType"), "end")
    for el in (begin, instr, sep, end):
        run._r.append(el)
    return run


# --------------------------------------------------------------------------- inline

INLINE_RE = re.compile(
    r"(\*\*.+?\*\*"          # bold
    r"|`[^`]+`"              # inline code
    r"|\[[^\]]+\]\([^)]+\)"  # link
    r"|\*[^*\s][^*]*?\*)"    # italic
)


def add_inline(paragraph, text, size=10, color=DARK_GRAY, bold=False, italic=False, mono=False):
    """Render markdown inline formatting into a paragraph.

    Recurses so nested constructs such as **`table_name`** keep both the bold
    and the monospace treatment instead of leaking their delimiters.
    """
    for part in INLINE_RE.split(text):
        if not part:
            continue
        if part.startswith("**") and part.endswith("**") and len(part) > 4:
            add_inline(paragraph, part[2:-2], size, color, True, italic, mono)
            continue
        if part.startswith("`") and part.endswith("`") and len(part) > 2:
            add_inline(paragraph, part[1:-1], size, color, bold, italic, True)
            continue
        if part.startswith("[") and "](" in part:
            m = re.match(r"\[([^\]]+)\]\(([^)]+)\)", part)
            if m:
                add_inline(paragraph, m.group(1), size, color, bold, italic, mono)
                continue
        if part.startswith("*") and part.endswith("*") and len(part) > 2:
            add_inline(paragraph, part[1:-1], size, color, bold, True, mono)
            continue

        run = paragraph.add_run(part)
        run.bold = bold
        run.italic = italic
        run.font.size = Pt(size - 0.5 if mono else size)
        run.font.name = MONO_FONT if mono else BODY_FONT
        run.font.color.rgb = DARK_BLUE if mono else color
        if mono:
            rpr = run._r.get_or_add_rPr()
            rf = rpr.get_or_add_rFonts()
            rf.set(qn("w:ascii"), MONO_FONT)
            rf.set(qn("w:hAnsi"), MONO_FONT)
    return paragraph


# --------------------------------------------------------------------------- parser

def parse_blocks(lines):
    """Turn markdown lines into a flat list of typed blocks."""
    blocks = []
    i = 0
    n = len(lines)
    while i < n:
        line = lines[i]

        m = re.match(r"^<!--\s*figure:\s*(.+?)\s*-->\s*$", line)
        if m:
            bits = [b.strip() for b in m.group(1).split("|")]
            name = bits[0]
            caption = bits[1] if len(bits) > 1 else ""
            replace = len(bits) > 2 and bits[2] == "replace"
            i += 1
            if replace:
                # skip blank lines, then drop the ASCII block the figure supersedes
                while i < n and not lines[i].strip():
                    i += 1
                if i < n and lines[i].startswith("```"):
                    i += 1
                    while i < n and not lines[i].startswith("```"):
                        i += 1
                    i += 1
            blocks.append(("figure", (name, caption)))
            continue

        if line.startswith("```"):
            i += 1
            body = []
            while i < n and not lines[i].startswith("```"):
                body.append(lines[i])
                i += 1
            i += 1
            blocks.append(("code", body))
            continue

        if not line.strip():
            i += 1
            continue

        if re.match(r"^(-{3,}|\*{3,})\s*$", line):
            blocks.append(("rule", None))
            i += 1
            continue

        m = re.match(r"^(#{1,6})\s+(.*)$", line)
        if m:
            blocks.append(("h" + str(len(m.group(1))), m.group(2).strip()))
            i += 1
            continue

        if line.startswith("|"):
            rows = []
            while i < n and lines[i].startswith("|"):
                rows.append(lines[i])
                i += 1
            blocks.append(("table", rows))
            continue

        if line.startswith(">"):
            body = []
            while i < n and lines[i].startswith(">"):
                body.append(re.sub(r"^>\s?", "", lines[i]))
                i += 1
            blocks.append(("quote", body))
            continue

        m = re.match(r"^(\s*)([-*+]|\d+\.)\s+(.*)$", line)
        if m:
            items = []
            ordered = not m.group(2) in ("-", "*", "+")
            while i < n:
                mm = re.match(r"^(\s*)([-*+]|\d+\.)\s+(.*)$", lines[i])
                if not mm:
                    # continuation line belonging to the previous item
                    if items and lines[i].startswith("  ") and lines[i].strip():
                        items[-1] = (items[-1][0], items[-1][1] + " " + lines[i].strip())
                        i += 1
                        continue
                    break
                level = len(mm.group(1)) // 2
                items.append((level, mm.group(3).strip()))
                i += 1
            blocks.append(("olist" if ordered else "ulist", items))
            continue

        # plain paragraph
        body = []
        while i < n and lines[i].strip() and not re.match(
                r"^(#{1,6}\s|\||>|```|\s*([-*+]|\d+\.)\s|-{3,}\s*$)", lines[i]):
            body.append(lines[i].strip())
            i += 1
        if body:
            blocks.append(("para", " ".join(body)))
        else:
            i += 1
    return blocks


def split_row(row):
    cells = row.strip().strip("|").split("|")
    return [c.strip() for c in cells]


def is_separator(row):
    return bool(re.match(r"^\|[\s:\-|]+\|$", row.strip()))


# --------------------------------------------------------------------------- rendering

def setup_styles(doc):
    normal = doc.styles["Normal"]
    normal.font.name = BODY_FONT
    normal.font.size = Pt(10)
    normal.font.color.rgb = DARK_GRAY
    normal.paragraph_format.space_after = Pt(6)
    normal.paragraph_format.line_spacing = 1.12
    # East Asian font mapping so Word does not substitute
    rpr = normal.element.get_or_add_rPr()
    rfonts = rpr.get_or_add_rFonts()
    rfonts.set(qn("w:eastAsia"), BODY_FONT)


def setup_page(doc, meta):
    section = doc.sections[0]
    section.top_margin = Cm(2.2)
    section.bottom_margin = Cm(2.0)
    section.left_margin = Cm(2.2)
    section.right_margin = Cm(2.2)
    section.header_distance = Cm(1.1)
    section.footer_distance = Cm(1.0)

    hp = section.header.paragraphs[0]
    hp.text = ""
    hp.paragraph_format.tab_stops.add_tab_stop(Cm(16.6), WD_TAB_ALIGNMENT.RIGHT)
    if os.path.exists(LOGO):
        hp.add_run().add_picture(LOGO, height=Cm(0.75))
    run = hp.add_run("\t{} · {}".format(meta["number"], meta["title"]))
    run.font.size = Pt(8)
    run.font.name = BODY_FONT
    run.font.color.rgb = GRAY
    hp.alignment = WD_ALIGN_PARAGRAPH.LEFT
    para_border(hp, edges=("bottom",), color="C9D4E0", size=6)

    # No footer: SUNU asked for clean pages.
    section.footer.is_linked_to_previous = False


def cover(doc, meta, toc=()):
    if os.path.exists(LOGO):
        p = doc.add_paragraph()
        p.paragraph_format.space_after = Pt(28)
        p.add_run().add_picture(LOGO, width=Cm(5.2))
    else:
        for _ in range(3):
            doc.add_paragraph()

    p = doc.add_paragraph()
    r = p.add_run(L["client"].upper())
    r.font.size = Pt(13)
    r.font.name = BODY_FONT
    r.bold = True
    r.font.color.rgb = MEDIUM_BLUE

    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(22)
    r = p.add_run(L["programme"])
    r.font.size = Pt(11)
    r.font.name = BODY_FONT
    r.font.color.rgb = GRAY

    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(2)
    r = p.add_run(meta["number"].upper())
    r.font.size = Pt(10)
    r.bold = True
    r.font.name = BODY_FONT
    r.font.color.rgb = MEDIUM_BLUE

    p = doc.add_paragraph()
    para_border(p, edges=("bottom",), color="1B3A5C", size=12)
    p.paragraph_format.space_after = Pt(26)
    r = p.add_run(meta["title"])
    r.font.size = Pt(26)
    r.bold = True
    r.font.name = BODY_FONT
    r.font.color.rgb = DARK_BLUE

    rows = [
        (L["subject"], meta["subject"]),
        (L["version"], meta.get("version", L["version_val"])),
        (L["date_label"], meta.get("date", L["date"])),
        (L["for"], L["client"] + L["dept"]),
        (L["by"], AUTHOR),
        (L["class_label"], L["classification"]),
    ]
    table = doc.add_table(rows=0, cols=2)
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    for label, value in rows:
        cells = table.add_row().cells
        rp = cells[0].paragraphs[0]
        rr = rp.add_run(label)
        rr.bold = True
        rr.font.size = Pt(9)
        rr.font.name = BODY_FONT
        rr.font.color.rgb = MEDIUM_BLUE
        vp = cells[1].paragraphs[0]
        vr = vp.add_run(value)
        vr.font.size = Pt(9)
        vr.font.name = BODY_FONT
        vr.font.color.rgb = DARK_GRAY
    table.columns[0].width = Cm(4.0)
    table.columns[1].width = Cm(12.0)
    for row in table.rows:
        row.cells[0].width = Cm(4.0)
        row.cells[1].width = Cm(12.0)


    doc.add_page_break()

    # Table of contents
    p = doc.add_paragraph()
    r = p.add_run(L["contents"])
    r.font.size = Pt(16)
    r.bold = True
    r.font.name = BODY_FONT
    r.font.color.rgb = DARK_BLUE
    p.paragraph_format.space_after = Pt(4)

    render_toc(doc, toc)

    doc.add_page_break()


def fld_char(run, kind):
    el = OxmlElement("w:fldChar")
    el.set(qn("w:fldCharType"), kind)
    run._r.append(el)


def render_toc(doc, entries):
    """Write the contents list as real entries, linked to each heading.

    The entries sit inside a Word TOC field, so "Update field" in Word still
    rebuilds the list with page numbers, but the list is readable as delivered
    (Word, LibreOffice, Google Docs, phone viewers) without anyone updating it.
    """
    if not entries:
        return
    last = len(entries) - 1
    for n, (level, text, anchor) in enumerate(entries):
        p = doc.add_paragraph()
        p.paragraph_format.left_indent = Cm(0.6 * (level - 1))
        p.paragraph_format.space_before = Pt(5 if level == 1 else 0)
        p.paragraph_format.space_after = Pt(1.5)
        if n == 0:
            r = p.add_run()
            fld_char(r, "begin")
            instr = OxmlElement("w:instrText")
            instr.set(qn("xml:space"), "preserve")
            instr.text = ' TOC \\o "1-2" \\h \\z \\u '
            r._r.append(instr)
            fld_char(p.add_run(), "separate")
        link = OxmlElement("w:hyperlink")
        link.set(qn("w:anchor"), anchor)
        link.set(qn("w:history"), "1")
        run = OxmlElement("w:r")
        rpr = OxmlElement("w:rPr")
        fonts = OxmlElement("w:rFonts")
        fonts.set(qn("w:ascii"), BODY_FONT)
        fonts.set(qn("w:hAnsi"), BODY_FONT)
        rpr.append(fonts)
        if level == 1:
            rpr.append(OxmlElement("w:b"))
        color = OxmlElement("w:color")
        color.set(qn("w:val"), "1B3A5C" if level == 1 else "333333")
        rpr.append(color)
        size = OxmlElement("w:sz")
        size.set(qn("w:val"), "21" if level == 1 else "19")
        rpr.append(size)
        run.append(rpr)
        wt = OxmlElement("w:t")
        wt.set(qn("xml:space"), "preserve")
        wt.text = text
        run.append(wt)
        link.append(run)
        p._p.append(link)
        if n == last:
            fld_char(p.add_run(), "end")


def add_bookmark(paragraph, name, bid):
    start = OxmlElement("w:bookmarkStart")
    start.set(qn("w:id"), str(bid))
    start.set(qn("w:name"), name)
    end = OxmlElement("w:bookmarkEnd")
    end.set(qn("w:id"), str(bid))
    paragraph._p.insert(1 if paragraph._p.pPr is not None else 0, start)
    paragraph._p.append(end)


def heading(doc, text, level, part_break=False, bookmark=None):
    sizes = {1: 15, 2: 12, 3: 10.5, 4: 10}
    colors = {1: DARK_BLUE, 2: DARK_BLUE, 3: MEDIUM_BLUE, 4: DARK_GRAY}
    p = doc.add_paragraph(style="Heading %d" % min(level, 4))
    p.paragraph_format.space_before = Pt({1: 18, 2: 14, 3: 10, 4: 8}[min(level, 4)])
    p.paragraph_format.space_after = Pt(4)
    keep_with_next(p)
    if part_break:
        p.paragraph_format.page_break_before = True
    text = re.sub(r"\*\*(.+?)\*\*", r"\1", text)
    r = p.add_run(text)
    r.font.size = Pt(sizes[min(level, 4)])
    r.font.name = BODY_FONT
    r.bold = True
    r.font.color.rgb = colors[min(level, 4)]
    if level == 1:
        para_border(p, edges=("bottom",), color="1B3A5C", size=8)
    if bookmark:
        add_bookmark(p, bookmark[0], bookmark[1])
    return p


def render_table(doc, rows):
    parsed = [split_row(r) for r in rows if not is_separator(r)]
    if not parsed:
        return
    ncols = max(len(r) for r in parsed)
    parsed = [r + [""] * (ncols - len(r)) for r in parsed]

    table = doc.add_table(rows=0, cols=ncols)
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    table.autofit = True
    table_borders(table)

    header, body = parsed[0], parsed[1:]
    hrow = table.add_row()
    for idx, text in enumerate(header):
        cell = hrow.cells[idx]
        cell_shading(cell, HDR_FILL)
        p = cell.paragraphs[0]
        p.paragraph_format.space_before = Pt(3)
        p.paragraph_format.space_after = Pt(3)
        clean = re.sub(r"\*\*(.+?)\*\*", r"\1", text)
        r = p.add_run(clean)
        r.bold = True
        r.font.size = Pt(8.5)
        r.font.name = BODY_FONT
        r.font.color.rgb = WHITE
    repeat_header(hrow)

    for n, cells in enumerate(body):
        row = table.add_row()
        for idx, text in enumerate(cells):
            cell = row.cells[idx]
            if n % 2 == 1:
                cell_shading(cell, ALT_FILL)
            p = cell.paragraphs[0]
            p.paragraph_format.space_before = Pt(2)
            p.paragraph_format.space_after = Pt(2)
            text = text.replace("<br>", " ")
            add_inline(p, text, size=8.5)

    # Narrow a leading identifier column (#, R1, OP-3, T12 …) so prose gets the room.
    first_col = [r[0] for r in parsed]
    if all(len(v) <= 8 for v in first_col):
        width = Cm(1.6)
        rest = (Cm(16.6) - width) / max(ncols - 1, 1)
        for row in table.rows:
            row.cells[0].width = width
            for c in row.cells[1:]:
                c.width = rest

    doc.add_paragraph().paragraph_format.space_after = Pt(2)


def render_figure(doc, name, caption, width_cm=16.4):
    """Place a rendered architecture diagram, with its caption beneath."""
    png = os.path.join(HERE, "diagrams", name + ".png")
    if not os.path.exists(png):
        return
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(10)
    p.paragraph_format.space_after = Pt(4)
    p.add_run().add_picture(png, width=Cm(width_cm))

    if caption:
        cp = doc.add_paragraph()
        cp.alignment = WD_ALIGN_PARAGRAPH.LEFT
        cp.paragraph_format.space_after = Pt(12)
        cp.paragraph_format.left_indent = Cm(0.2)
        para_border(cp, edges=("top",), color="C9D4E0", size=6)
        r = cp.add_run(caption)
        r.font.size = Pt(8.5)
        r.italic = True
        r.font.name = BODY_FONT
        r.font.color.rgb = GRAY


def render_code(doc, body):
    # Trim blank lines at the edges of the block
    while body and not body[0].strip():
        body.pop(0)
    while body and not body[-1].strip():
        body.pop()
    if not body:
        return
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(8)
    p.paragraph_format.left_indent = Cm(0.2)
    p.paragraph_format.line_spacing = 1.0
    para_shading(p, CODE_FILL)
    para_border(p, edges=("left",), color="1B3A5C", size=12)
    for n, line in enumerate(body):
        run = p.add_run(line)
        run.font.name = MONO_FONT
        run.font.size = Pt(7.5)
        run.font.color.rgb = DARK_GRAY
        rpr = run._r.get_or_add_rPr()
        rf = rpr.get_or_add_rFonts()
        rf.set(qn("w:ascii"), MONO_FONT)
        rf.set(qn("w:hAnsi"), MONO_FONT)
        rf.set(qn("w:cs"), MONO_FONT)
        if n < len(body) - 1:
            run.add_break()


def render_quote(doc, body):
    text = " ".join(l.strip() for l in body if l.strip())
    p = doc.add_paragraph()
    p.paragraph_format.left_indent = Cm(0.5)
    p.paragraph_format.right_indent = Cm(0.3)
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(8)
    para_shading(p, QUOTE_FILL)
    para_border(p, edges=("left",), color="B8860B", size=18)
    add_inline(p, text, size=9.5)


def render_list(doc, items, ordered):
    for n, (level, text) in enumerate(items, start=1):
        checkbox = re.match(r"^\[([ xX])\]\s*(.*)$", text)
        p = doc.add_paragraph()
        p.paragraph_format.left_indent = Cm(0.55 + 0.5 * level)
        p.paragraph_format.first_line_indent = Cm(-0.35)
        p.paragraph_format.space_after = Pt(2)
        if checkbox:
            marker = "☒  " if checkbox.group(1).lower() == "x" else "☐  "
            text = checkbox.group(2)
        elif ordered:
            marker = "%d.  " % n
        else:
            marker = "•  " if level == 0 else "–  "
        mr = p.add_run(marker)
        mr.font.size = Pt(10)
        mr.font.name = BODY_FONT
        mr.font.color.rgb = MEDIUM_BLUE
        mr.bold = ordered or bool(checkbox)
        add_inline(p, text, size=10)


def render_rule(doc):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(6)
    para_border(p, edges=("bottom",), color="D8DEE6", size=6)


def build(meta, outdir):
    src = os.path.join(HERE, meta["src"])
    raw = open(src, encoding="utf-8").read().split("\n")

    doc = Document()
    setup_styles(doc)
    setup_page(doc, meta)

    core = doc.core_properties
    core.title = "%s — %s" % (meta["number"], meta["title"])
    core.subject = meta["subject"]
    core.author = AUTHOR
    core.category = L["programme"]
    core.comments = "%s — %s" % (L["client"], L["classification"])

    blocks = parse_blocks(raw)

    # Drop the markdown title block — heading(s), the front-matter table and the rule
    # that closes it. The cover page carries all of it. Matching on structure rather
    # than on English phrases keeps this correct for the French set too.
    start = 0
    for idx, (kind, _) in enumerate(blocks):
        if kind == "rule":
            start = idx + 1
            break
        if kind not in ("h1", "h2", "h3", "table"):
            start = 0
            break
    trimmed = blocks[start:]

    # Normalise the heading hierarchy. Documents 1, 2 and 4 top out at "##"
    # once the title block is removed; shift them up so section 1, 2, 3 land on
    # Heading 1 and the table of contents nests correctly in every document.
    offset = 0 if any(k == "h1" for k, _ in trimmed) else -1

    # Contents: section and sub-section headings, each with a bookmark to link to.
    toc = []
    for kind, payload in trimmed:
        if kind.startswith("h"):
            level = max(int(kind[1]) + offset, 1)
            if level <= 2:
                clean = re.sub(r"\*\*(.+?)\*\*|`([^`]+)`", lambda m: m.group(1) or m.group(2), payload)
                toc.append((level, clean, "_Toc_s%03d" % len(toc)))
    cover(doc, meta, toc)
    anchors = iter(toc)
    bid = 100

    # Short documents flow section after section; long ones start each main
    # section on a new page.
    section_breaks = meta.get("section_breaks", True)

    def breaks_page(idx):
        """True when block idx is a main heading that will start a new page."""
        if not section_breaks or idx >= len(trimmed):
            return False
        kind = trimmed[idx][0]
        return kind.startswith("h") and max(int(kind[1]) + offset, 1) == 1

    first_content = True
    for idx, (kind, payload) in enumerate(trimmed):
        if kind == "figure":
            render_figure(doc, payload[0], payload[1])
        elif kind == "code":
            render_code(doc, payload)
        elif kind == "table":
            render_table(doc, payload)
        elif kind == "quote":
            render_quote(doc, payload)
        elif kind == "ulist":
            render_list(doc, payload, ordered=False)
        elif kind == "olist":
            render_list(doc, payload, ordered=True)
        elif kind == "rule":
            # A divider right before a new page would be stranded at the foot
            # of the previous page, or pushed onto a page of its own.
            if not (breaks_page(idx + 1) and not first_content):
                render_rule(doc)
        elif kind.startswith("h"):
            level = max(int(kind[1]) + offset, 1)
            # "# PART A — …" separators in the user guide start a new page
            part_break = section_breaks and level == 1 and not first_content
            mark = None
            if level <= 2:
                bid += 1
                mark = (next(anchors)[2], bid)
            heading(doc, payload, level, part_break=part_break, bookmark=mark)
            first_content = False
        else:
            text = payload.strip()
            if text.startswith("*End of Document") or text.startswith("*End of"):
                p = doc.add_paragraph()
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER
                p.paragraph_format.space_before = Pt(14)
                r = p.add_run(re.sub(r"^\*|\*$", "", text))
                r.italic = True
                r.font.size = Pt(9)
                r.font.name = BODY_FONT
                r.font.color.rgb = GRAY
            else:
                p = doc.add_paragraph()
                add_inline(p, text, size=10)
            first_content = False

    out = os.path.join(outdir, meta["out"])
    doc.save(out)
    return out


def main():
    global L
    ap = argparse.ArgumentParser()
    ap.add_argument("--outdir", default=HERE)
    ap.add_argument("--lang", choices=["en", "fr", "both"], default="both")
    args = ap.parse_args()
    os.makedirs(args.outdir, exist_ok=True)

    sets = []
    if args.lang in ("en", "both"):
        sets.append(("en", DOCS_EN))
    if args.lang in ("fr", "both"):
        sets.append(("fr", DOCS_FR))

    for lang, docs in sets:
        L = STRINGS[lang]
        print("--- %s ---" % lang.upper())
        for meta in docs:
            path = build(meta, args.outdir)
            print("%-52s %8.1f KB" % (os.path.basename(path), os.path.getsize(path) / 1024))


if __name__ == "__main__":
    main()
