#!/usr/bin/env python3
"""Check each .docx against its markdown source: nothing dropped, no markdown left behind."""
import re, sys, zipfile
from docx import Document
from docx.shared import Pt
import os
LOGO_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'branding', 'dbs_logo.png')
LOGO_BYTES = open(LOGO_PATH, 'rb').read() if os.path.exists(LOGO_PATH) else None

PAIRS = [
    ('SUNU_01_Solution_Architecture_EN.docx', 'SUNU_01_ARCHITECTURE_SHORT_EN.md'),
    ('SUNU_01_Architecture_Solution_FR.docx', 'SUNU_01_ARCHITECTURE_SHORT_FR.md'),
    ('SUNU_01_Technical_Architecture_EN.docx', 'SUNU_01_TECHNICAL_ARCHITECTURE.md'),
    ('SUNU_02_Security_EN.docx', 'SUNU_02_SECURITY.md'),
    ('SUNU_03_User_Guide_EN.docx', 'SUNU_03_USER_GUIDE.md'),
    ('SUNU_04_Deployment_Plan_EN.docx', 'SUNU_04_DEPLOYMENT_PLAN.md'),
    ('SUNU_01_Architecture_Technique_FR.docx', 'SUNU_01_ARCHITECTURE_TECHNIQUE_FR.md'),
    ('SUNU_02_Securite_FR.docx', 'SUNU_02_SECURITE_FR.md'),
    ('SUNU_03_Guide_Utilisateur_FR.docx', 'SUNU_03_GUIDE_UTILISATEUR_FR.md'),
    ('SUNU_04_Plan_Deploiement_FR.docx', 'SUNU_04_PLAN_DEPLOIEMENT_FR.md'),
    ('SUNU_05_Data_Import_Formats_EN.docx', 'SUNU_05_DATA_IMPORT_FORMATS.md'),
    ('SUNU_05_Formats_Import_Donnees_FR.docx', 'SUNU_05_FORMATS_IMPORT_DONNEES_FR.md'),
]


def count_md(md):
    """Count source constructs, minus the front-matter table the cover page absorbs."""
    fences = md.count('```') // 2
    tables, intbl = 0, False
    for line in md.split('\n'):
        if line.startswith('|') and not intbl:
            tables += 1
            intbl = True
        elif not line.startswith('|'):
            intbl = False
    figures = md.count('<!-- figure:')
    # a figure marked "replace" supersedes the fenced ASCII that follows it
    replaced = len(re.findall(r'<!-- figure:[^>]*\|\s*replace\s*-->', md))
    return {'code': fences - replaced,
            'tables': tables - 1 + 1,   # drop front-matter, add the cover table
            'boxes': len(re.findall(r'^- \[[ xX]\]', md, re.M)),
            'figures': figures}


def count_docx(path):
    d = Document(path)
    txt = "\n".join(p.text for p in d.paragraphs)
    tbl = "\n".join(c.text for t in d.tables for r in t.rows for c in r.cells)
    both = txt + "\n" + tbl
    return {'code': len([p for p in d.paragraphs
                         if p.runs and p.text.strip() and all(r.font.name == 'Consolas' and r.font.size == Pt(7.5) for r in p.runs)]),
            'tables': len(d.tables),
            'boxes': sum(p.text.count('☐') + p.text.count('☒') for p in d.paragraphs),
            'figures': len([r for r in d.part.rels.values() if 'image' in r.reltype
                        and r.target_part.blob != LOGO_BYTES]),
            'leftover': {'**': both.count('**'), '`': both.count('`'),
                         'pipe': len([l for l in txt.split('\n') if l.strip().startswith('|')]),
                         'atx': len(re.findall(r'^#{1,6}\s', txt, re.M)),
                         'marker': both.count('<!-- figure')},
            'h1': len([p for p in d.paragraphs if p.style.name == 'Heading 1'])}


ok_all = True
for docx, md in PAIRS:
    want = count_md(open(md, encoding='utf-8').read())
    got = count_docx(docx)
    zipfile.ZipFile(docx).testzip()
    ok = (all(got[k] == want[k] for k in want) and not any(got['leftover'].values()))
    ok_all &= ok
    print('%s %-44s code %d/%d  tables %d/%d  boxes %d/%d  figures %d/%d  H1=%d'
          % ('OK ' if ok else 'BAD', docx, got['code'], want['code'], got['tables'], want['tables'],
             got['boxes'], want['boxes'], got['figures'], want['figures'], got['h1']))
    if not ok:
        print('      leftover markdown: %s' % got['leftover'])

print('\n%s' % ('All 8 documents verified against source.' if ok_all else 'PROBLEMS FOUND'))
sys.exit(0 if ok_all else 1)
