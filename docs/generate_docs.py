#!/usr/bin/env python3
"""
Generate professional DOCX documentation for Agency Banking Solution.
Creates 4 documents: Marketing (EN/FR) and Technical (EN/FR).
"""

import os
from docx import Document
from docx.shared import Inches, Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.section import WD_ORIENT
from docx.oxml.ns import qn, nsdecls
from docx.oxml import parse_xml
from datetime import datetime

OUTPUT_DIR = os.path.dirname(os.path.abspath(__file__))

# Brand colors
DARK_BLUE = RGBColor(0x1B, 0x3A, 0x5C)
MEDIUM_BLUE = RGBColor(0x2C, 0x5F, 0x8A)
LIGHT_BLUE = RGBColor(0xD6, 0xE8, 0xF7)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
BLACK = RGBColor(0x00, 0x00, 0x00)
DARK_GRAY = RGBColor(0x33, 0x33, 0x33)
GRAY = RGBColor(0x66, 0x66, 0x66)


def set_cell_shading(cell, color_hex):
    """Set background color of a table cell."""
    shading = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{color_hex}"/>')
    cell._tc.get_or_add_tcPr().append(shading)


def set_cell_border(cell, **kwargs):
    """Set cell borders."""
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    tcBorders = parse_xml(f'<w:tcBorders {nsdecls("w")}></w:tcBorders>')
    for edge, val in kwargs.items():
        element = parse_xml(
            f'<w:{edge} {nsdecls("w")} w:val="{val.get("val", "single")}" '
            f'w:sz="{val.get("sz", "4")}" w:space="0" '
            f'w:color="{val.get("color", "000000")}"/>'
        )
        tcBorders.append(element)
    tcPr.append(tcBorders)


def add_styled_paragraph(doc, text, font_name="Calibri", font_size=11,
                         bold=False, italic=False, color=None,
                         alignment=None, space_before=0, space_after=6,
                         line_spacing=1.15):
    """Add a paragraph with custom styling."""
    p = doc.add_paragraph()
    run = p.add_run(text)
    run.font.name = font_name
    run.font.size = Pt(font_size)
    run.font.bold = bold
    run.font.italic = italic
    if color:
        run.font.color.rgb = color
    if alignment is not None:
        p.alignment = alignment
    p.paragraph_format.space_before = Pt(space_before)
    p.paragraph_format.space_after = Pt(space_after)
    p.paragraph_format.line_spacing = line_spacing
    return p


def add_heading_styled(doc, text, level=1):
    """Add a heading with custom styling."""
    h = doc.add_heading(text, level=level)
    for run in h.runs:
        run.font.color.rgb = DARK_BLUE
        run.font.name = "Calibri"
    return h


def add_bullet(doc, text, bold_prefix="", font_size=11):
    """Add a bullet point."""
    p = doc.add_paragraph(style="List Bullet")
    if bold_prefix:
        run_b = p.add_run(bold_prefix)
        run_b.font.bold = True
        run_b.font.name = "Calibri"
        run_b.font.size = Pt(font_size)
        run_b.font.color.rgb = DARK_GRAY
    run = p.add_run(text)
    run.font.name = "Calibri"
    run.font.size = Pt(font_size)
    run.font.color.rgb = DARK_GRAY
    return p


def add_feature_table(doc, headers, rows):
    """Add a professionally styled feature table."""
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = True

    # Header row
    for i, header in enumerate(headers):
        cell = table.rows[0].cells[i]
        cell.text = ""
        p = cell.paragraphs[0]
        run = p.add_run(header)
        run.font.bold = True
        run.font.color.rgb = WHITE
        run.font.name = "Calibri"
        run.font.size = Pt(10)
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        set_cell_shading(cell, "1B3A5C")

    # Data rows
    for r_idx, row in enumerate(rows):
        for c_idx, cell_text in enumerate(row):
            cell = table.rows[r_idx + 1].cells[c_idx]
            cell.text = ""
            p = cell.paragraphs[0]
            run = p.add_run(str(cell_text))
            run.font.name = "Calibri"
            run.font.size = Pt(10)
            run.font.color.rgb = DARK_GRAY
            if r_idx % 2 == 0:
                set_cell_shading(cell, "F2F7FC")
            else:
                set_cell_shading(cell, "FFFFFF")

    doc.add_paragraph()  # spacing
    return table


def add_cover_page(doc, title, subtitle, doc_type, date_str, confidentiality_text):
    """Add a professional cover page."""
    # Add spacing before title
    for _ in range(6):
        add_styled_paragraph(doc, "", space_after=0)

    add_styled_paragraph(doc, title, font_size=28, bold=True,
                         color=DARK_BLUE, alignment=WD_ALIGN_PARAGRAPH.CENTER,
                         space_after=12)

    add_styled_paragraph(doc, subtitle, font_size=16, italic=True,
                         color=MEDIUM_BLUE, alignment=WD_ALIGN_PARAGRAPH.CENTER,
                         space_after=6)

    # Separator line
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run("_" * 60)
    run.font.color.rgb = MEDIUM_BLUE
    run.font.size = Pt(12)

    add_styled_paragraph(doc, "", space_after=12)

    add_styled_paragraph(doc, doc_type, font_size=18, bold=True,
                         color=DARK_BLUE, alignment=WD_ALIGN_PARAGRAPH.CENTER,
                         space_after=24)

    add_styled_paragraph(doc, date_str, font_size=12,
                         color=GRAY, alignment=WD_ALIGN_PARAGRAPH.CENTER,
                         space_after=6)

    add_styled_paragraph(doc, "[Company Name]", font_size=14, bold=True,
                         color=DARK_BLUE, alignment=WD_ALIGN_PARAGRAPH.CENTER,
                         space_after=48)

    # Confidentiality
    add_styled_paragraph(doc, "", space_after=24)
    add_styled_paragraph(doc, "Confidentiality Notice", font_size=12, bold=True,
                         color=DARK_BLUE, alignment=WD_ALIGN_PARAGRAPH.CENTER,
                         space_before=12, space_after=6)
    add_styled_paragraph(doc, confidentiality_text, font_size=9,
                         italic=True, color=GRAY,
                         alignment=WD_ALIGN_PARAGRAPH.JUSTIFY,
                         space_after=0)

    doc.add_page_break()


def add_toc_placeholder(doc, title="Table of Contents"):
    """Add a Table of Contents placeholder."""
    add_heading_styled(doc, title, level=1)
    add_styled_paragraph(
        doc,
        "[Table of Contents - Update this field in Microsoft Word: "
        "References > Update Table]",
        font_size=10, italic=True, color=GRAY
    )
    doc.add_page_break()


# =============================================================================
# MARKETING DOCUMENT - ENGLISH
# =============================================================================
def generate_marketing_en():
    doc = Document()

    # Page margins
    for section in doc.sections:
        section.top_margin = Cm(2.5)
        section.bottom_margin = Cm(2.5)
        section.left_margin = Cm(2.5)
        section.right_margin = Cm(2.5)

    # Cover page
    add_cover_page(
        doc,
        "[Product Name]",
        "Enterprise Agency Banking Solution",
        "Commercial Proposal & Solution Overview",
        datetime.now().strftime("%B %Y"),
        "The information provided in this document is for informational purposes only. "
        "This document contains confidential information of [Company Name] and is to be used "
        "only for the purpose for which it has been supplied. The contents of this document may "
        "not, neither in whole nor in part, be communicated to any third party without the prior "
        "written approval of [Company Name]. All copyrights in this document belong to [Company Name]. "
        "The validity period of this proposal is 90 days as of the submission date."
    )

    # Table of Contents
    add_toc_placeholder(doc)

    # --- EXECUTIVE SUMMARY ---
    add_heading_styled(doc, "1. Executive Summary", level=1)
    add_styled_paragraph(
        doc,
        "As financial institutions across Africa and emerging markets strive to expand their reach "
        "to underbanked and unbanked populations, the need for robust, scalable, and affordable "
        "agency banking solutions has never been greater. Traditional brick-and-mortar branch "
        "expansion is costly and impractical for rural and remote areas where millions of potential "
        "customers reside.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "[Company Name] presents [Product Name], a comprehensive Enterprise Agency Banking Platform "
        "designed specifically for microfinance institutions (MFIs), banks, and financial service "
        "providers seeking to extend their services through third-party agent networks. Our platform "
        "combines a powerful web-based administration portal with a feature-rich Android POS application, "
        "enabling institutions to manage agent operations, process transactions, open accounts, and "
        "deliver financial services to customers wherever they are.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "[Product Name] is built on proven technology and has been designed with the unique challenges "
        "of emerging markets in mind, including low-bandwidth environments, multi-language support "
        "(English, French, and Portuguese), offline capability, and integration with multiple core "
        "banking systems. The platform supports multi-tenant operations, allowing a single deployment "
        "to serve multiple business units or microfinance institutions from a centralized super "
        "administration panel.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- KEY BENEFITS ---
    add_heading_styled(doc, "2. Key Benefits", level=1)

    benefits = [
        ("Financial Inclusion: ", "Extend banking services to underbanked and unbanked populations in rural and remote areas through a network of third-party agents, eliminating the need for costly branch expansion."),
        ("Increased Outreach: ", "Deploy agents equipped with POS devices to serve customers in their communities, villages, and marketplaces, bringing financial services directly to the people."),
        ("Reduced Operational Costs: ", "Minimize infrastructure investments by leveraging existing retail networks as banking agents, significantly reducing the cost per transaction compared to traditional branch operations."),
        ("Customer Convenience: ", "Enable customers to perform cash deposits, withdrawals, balance enquiries, and account opening at convenient agent locations, outside traditional banking hours."),
        ("Agent Empowerment: ", "Provide agents with comprehensive tools including NFC smart card support, biometric authentication, thermal receipt printing, and real-time transaction processing."),
        ("Digital Transformation: ", "Modernize operations with digital client registration, electronic KYC data capture, biometric enrollment, and paperless workflows."),
        ("Scalability: ", "Multi-tenant architecture supports multiple business units from a single platform, enabling rapid expansion across regions and countries."),
        ("Security & Compliance: ", "Multi-factor authentication (PIN, OTP, biometrics), encrypted communications, comprehensive audit trails, and role-based access control ensure regulatory compliance."),
    ]
    for prefix, text in benefits:
        add_bullet(doc, text, bold_prefix=prefix)

    doc.add_paragraph()

    # --- SOLUTION OVERVIEW ---
    add_heading_styled(doc, "3. Solution Overview", level=1)
    add_styled_paragraph(
        doc,
        "[Product Name] is a complete turnkey agency banking platform consisting of two integrated components:",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "3.1 Web Administration Portal", level=2)
    add_styled_paragraph(
        doc,
        "A powerful web-based management console that provides financial institutions with full "
        "control over their agency banking operations. The portal enables super administrators to "
        "manage multiple business units, configure transaction rules, monitor agent performance, "
        "generate reports, and maintain complete oversight of all operations.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "3.2 Mobile POS Application", level=2)
    add_styled_paragraph(
        doc,
        "An Android-based application optimized for Telpo POS devices, equipped with integrated NFC "
        "card readers, fingerprint scanners, and thermal printers. The application empowers field "
        "agents to register clients, open accounts, process financial transactions, capture biometric "
        "data, and issue smart cards - all from a single handheld device.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- WEB ADMIN MODULES ---
    add_heading_styled(doc, "4. Web Administration Portal - Module Highlights", level=1)

    # Multi-Business Unit
    add_heading_styled(doc, "4.1 Multi-Business Unit Management", level=2)
    add_styled_paragraph(
        doc,
        "[Product Name] supports a multi-tenant architecture where a single super administrator can "
        "manage multiple microfinance institutions or business units from a centralized dashboard. "
        "Each business unit operates with complete data isolation, its own configuration settings, "
        "user roles, transaction types, commission structures, and SMS settings. This architecture "
        "enables holding companies and microfinance networks to deploy a single platform across "
        "multiple subsidiaries or branches, reducing IT costs while maintaining operational independence.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Agent Management
    add_heading_styled(doc, "4.2 Agent Management", level=2)
    add_styled_paragraph(
        doc,
        "The Agent Management module provides comprehensive tools for recruiting, registering, and "
        "managing third-party banking agents. Administrators can capture complete agent profiles "
        "including personal details, identification documents, and photographs. Agents are assigned "
        "POS terminals and linked to float and commission accounts for transaction processing. "
        "The system supports automatic agent registration with core banking systems, PIN generation "
        "and distribution via SMS, and real-time monitoring of agent activity. Powerful search, "
        "filtering, and export capabilities enable efficient network management.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Client Management
    add_heading_styled(doc, "4.3 Client & Customer Management", level=2)
    add_styled_paragraph(
        doc,
        "The Client Management module supports comprehensive customer registration with full KYC "
        "(Know Your Customer) data capture including personal information, demographics, emergency "
        "contacts, next of kin details, housing information, income data, and geo-location. Clients "
        "can be organized into groups for efficient management of solidarity lending or village "
        "savings programs. The module supports biometric enrollment with up to four fingerprint "
        "templates per client, enabling secure biometric authentication for transactions. Photo "
        "capture and document management capabilities ensure complete digital client profiles.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Account Management
    add_heading_styled(doc, "4.4 Account Management", level=2)
    add_styled_paragraph(
        doc,
        "The Account Management module handles the creation and lifecycle management of multiple "
        "account types including client savings accounts, agent float accounts, and agent commission "
        "accounts. Account numbers can be auto-generated or manually assigned, with built-in "
        "uniqueness validation per business unit. The system supports multi-currency operations "
        "and tracks account status (active, inactive, suspended, closed), balances, and opening/"
        "closing dates. Accounts are seamlessly linked to both clients and agents through a "
        "flexible polymorphic relationship model.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Transaction Processing
    add_heading_styled(doc, "4.5 Transaction Processing", level=2)
    add_styled_paragraph(
        doc,
        "The Transaction Management module is the heart of the platform, supporting a full range "
        "of financial transactions including cash deposits, cash withdrawals, biometric withdrawals, "
        "balance enquiries, and mini statements. Each transaction is processed with configurable "
        "verification methods including OTP and PIN authentication. The system automatically "
        "calculates transaction costs and agent commissions using a sophisticated tiered pricing "
        "engine. Transaction types are fully configurable per business unit, and comprehensive "
        "filtering, reporting, and export capabilities provide full visibility into transaction activity.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Card Management
    add_heading_styled(doc, "4.6 Card Management", level=2)
    add_styled_paragraph(
        doc,
        "The Card Management module provides end-to-end smart card lifecycle management. Cards "
        "are linked to client accounts and support NFC-based contactless operations with both "
        "Mifare Classic (M1) and Mifare Ultralight (M0) card types. The system manages the complete "
        "card lifecycle including issuance, activation, blocking, replacement, and UID mapping. "
        "Bulk UID import capabilities streamline card provisioning operations, and configurable "
        "card settings per business unit (prefix, expiration, daily limits) ensure flexibility. "
        "Cards store customer identity and account information for quick, secure transactions at agent locations.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Terminal Management
    add_heading_styled(doc, "4.7 Terminal & POS Device Management", level=2)
    add_styled_paragraph(
        doc,
        "The Terminal Management module tracks all POS devices deployed across the agent network. "
        "Each terminal is registered with its serial number, SIM card details, and model information. "
        "Terminals are assigned to agents with full assignment history tracking, including assignment "
        "and unassignment dates. The module provides visibility into terminal status, transaction "
        "activity per device, and enables efficient device lifecycle management across the network.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Fees & Commissions
    add_heading_styled(doc, "4.8 Fees & Commission Engine", level=2)
    add_styled_paragraph(
        doc,
        "The Fees and Commission Engine provides a highly flexible rules-based system for calculating "
        "transaction costs and agent commissions. The engine supports multiple calculation methods "
        "including fixed amounts, percentages, tiered pricing, tiered percentages, and tiered fixed "
        "amounts. Transaction costs are calculated from the transaction amount, while commissions "
        "are calculated from the transaction cost, with the business share automatically determined "
        "as the difference. Each rule can be activated or deactivated per transaction type and per "
        "business unit, enabling precise control over pricing and incentive structures.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # SMS
    add_heading_styled(doc, "4.9 SMS Alerts & Notifications", level=2)
    add_styled_paragraph(
        doc,
        "The SMS Management module enables financial institutions to communicate with agents and "
        "customers via SMS. The system integrates with Orange SMS API for message delivery and "
        "supports single and bulk SMS sending, SMS credit management per business unit, and "
        "transactional alerts. SMS is used for OTP delivery, agent PIN notifications, transaction "
        "confirmations, and promotional campaigns. The super administrator manages SMS configuration "
        "and credit allocation across business units, ensuring cost control and accountability.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Core Banking Integration
    add_heading_styled(doc, "4.10 Core Banking Integration", level=2)
    add_styled_paragraph(
        doc,
        "[Product Name] is designed to be CBS-agnostic, supporting integration with multiple core "
        "banking systems. The platform currently integrates with the KATO core banking system for "
        "automatic agent registration and real-time transaction processing. The integration framework "
        "is extensible, allowing connection to additional CBS platforms as required. Each business "
        "unit can be configured to use a different core banking system, providing maximum flexibility "
        "for institutions operating diverse technology environments.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "Beyond KATO, the platform includes a Configurable (JSON mapper) driver that lets administrators "
        "onboard any REST-based core banking system entirely from the Super Admin portal - no custom "
        "development required. A new CBS is defined once (base URL, authentication, timeout), then "
        "internal fields are visually mapped to the target system's API for each transaction type. "
        "This dramatically shortens integration timelines and lets a single deployment serve "
        "institutions running different core banking platforms side by side.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Reporting
    add_heading_styled(doc, "4.11 Reporting & Analytics", level=2)
    add_styled_paragraph(
        doc,
        "The Reporting module provides comprehensive analytics and export capabilities across all "
        "platform modules. Reports can be generated for agents, clients, transactions, commissions, "
        "and costs with flexible date range filtering. The system supports asynchronous report "
        "generation for large datasets and delivers reports in CSV, Excel, and PDF formats. "
        "The audit trail module tracks all system activities with before/after change logging, "
        "providing full accountability and regulatory compliance support.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # RBAC
    add_heading_styled(doc, "4.12 Role-Based Access Control", level=2)
    add_styled_paragraph(
        doc,
        "The platform implements a comprehensive role-based access control (RBAC) system that "
        "enables granular permission management. Custom roles can be created with specific permissions "
        "assigned at the operation level (create, view, edit, delete) for each module. Users are "
        "assigned to business units with specific roles, ensuring that staff members only access "
        "the functions and data relevant to their responsibilities. The super administrator has "
        "full control over all business units and system-wide configurations.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- MOBILE APPLICATION ---
    add_heading_styled(doc, "5. Mobile POS Application", level=1)
    add_styled_paragraph(
        doc,
        "The [Product Name] Mobile POS Application is an Android-based agent application optimized "
        "for Telpo POS terminals with integrated NFC card readers, biometric fingerprint scanners, "
        "and thermal receipt printers. The application provides agents with all the tools needed to "
        "deliver financial services in the field.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    # Mobile features table
    add_feature_table(doc, ["Feature", "Description"], [
        ["Client Registration", "Multi-step client registration with personal information capture, photo, account type selection, and biometric fingerprint enrollment (4 fingers). Printed receipt on completion."],
        ["Cash Deposit", "NFC card-based deposits with automatic account identification. Secured with PIN and OTP verification. Automatic receipt printing."],
        ["Cash Withdrawal", "NFC card-based withdrawals with PIN and OTP verification. Supports standard and biometric withdrawal modes."],
        ["Biometric Withdrawal", "Fingerprint-authenticated withdrawals that eliminate the need for PIN or OTP, providing a seamless customer experience for authenticated users."],
        ["Balance Enquiry", "Instant account balance retrieval via NFC card tap. Displays current balance and currency information."],
        ["Mini Statement", "Retrieve last transactions for a customer account via NFC card tap. Displays transaction history with dates, amounts, and types."],
        ["NFC Smart Card Operations", "Read and write customer data to Mifare Classic (M1) and Mifare Ultralight (M0) NFC cards. Stores customer name, account number, and card number on the card."],
        ["Fingerprint Enrollment", "Capture up to 4 fingerprint templates per customer using integrated Feitian biometric scanner. Templates stored securely for future authentication."],
        ["Thermal Receipt Printing", "Automatic receipt printing for all transactions and account openings using the integrated thermal printer. Includes transaction details, amounts, and reference numbers."],
        ["Service Payments", "Support for utility and service payments including OneAWater and Yolse payment integrations."],
        ["Offline Capability", "Local SQLite database caching enables continued operation in low-connectivity environments with automatic synchronization when connection is restored."],
        ["Multi-Language", "Support for French and English with runtime language switching."],
        ["Session Security", "Automatic session timeout after 10 minutes of inactivity. PIN encryption for secure authentication."],
    ])

    # --- SECURITY ---
    add_heading_styled(doc, "6. Security & Compliance", level=1)
    add_styled_paragraph(
        doc,
        "[Product Name] implements multiple layers of security to protect financial transactions and "
        "customer data:",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    security_items = [
        ("Multi-Factor Authentication: ", "Transactions are secured with PIN, OTP, and biometric verification options, providing flexible authentication based on transaction type and risk level."),
        ("Biometric Security: ", "Fingerprint-based authentication provides the highest level of identity verification, preventing unauthorized transactions even if a card is lost or stolen."),
        ("Encrypted Communications: ", "All API communications between the POS application and backend server use encrypted channels. PINs are encrypted before transmission."),
        ("Audit Trail: ", "Every system action is logged with full before/after change tracking, user identification, and timestamps, providing complete accountability."),
        ("Role-Based Access: ", "Granular permission controls ensure users only access functions appropriate to their role, minimizing the risk of unauthorized operations."),
        ("Data Isolation: ", "Multi-tenant data isolation ensures that each business unit's data is completely separated, preventing cross-tenant data access."),
        ("Session Management: ", "Automatic session timeouts and secure token-based authentication protect against unauthorized access."),
    ]
    for prefix, text in security_items:
        add_bullet(doc, text, bold_prefix=prefix)

    doc.add_paragraph()

    # --- MULTI-LANGUAGE ---
    add_heading_styled(doc, "7. Multi-Language Support", level=1)
    add_styled_paragraph(
        doc,
        "[Product Name] is designed for deployment across multiple African markets with full "
        "localization support. The web administration portal supports English, French, and Portuguese, "
        "while the mobile POS application supports French and English. All user interface elements, "
        "transaction messages, and reports are fully translated, ensuring that agents and administrators "
        "can work in their preferred language. Language can be switched at any time without restarting "
        "the application.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- WHY CHOOSE ---
    add_heading_styled(doc, "8. Why Choose [Product Name]?", level=1)

    differentiators = [
        ("Proven Technology: ", "Built on enterprise-grade Laravel framework with a robust REST API architecture, ensuring reliability, scalability, and maintainability."),
        ("Complete Solution: ", "A single platform covers all agency banking needs - from agent recruitment to transaction processing, from card management to regulatory reporting."),
        ("Hardware Integration: ", "Deep integration with Telpo POS devices, NFC smart cards, biometric scanners, and thermal printers provides a complete hardware-software solution."),
        ("Multi-Tenant Architecture: ", "Support multiple business units, MFIs, or subsidiaries from a single platform, reducing deployment and operational costs."),
        ("CBS Agnostic: ", "Flexible core banking integration framework supports connection to any CBS, protecting your existing technology investments."),
        ("Offline Capable: ", "The mobile application continues to function in low-connectivity environments, critical for rural and remote agent locations."),
        ("Rapid Deployment: ", "Pre-built modules and configurable settings enable rapid deployment and customization to match specific business requirements."),
        ("Comprehensive Security: ", "Multi-factor authentication, biometric verification, audit trails, and role-based access control meet the strictest regulatory requirements."),
    ]
    for prefix, text in differentiators:
        add_bullet(doc, text, bold_prefix=prefix)

    doc.add_paragraph()

    # --- REFERENCES ---
    add_heading_styled(doc, "9. References & Use Cases", level=1)
    add_styled_paragraph(
        doc,
        "[Company Name] has successfully deployed agency banking solutions for microfinance "
        "institutions across multiple African markets. Our solutions have enabled these institutions "
        "to significantly expand their outreach, reduce operational costs, and improve customer "
        "satisfaction through convenient agent-based banking services.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "[Insert client references and case studies here]",
        font_size=11, italic=True, color=GRAY, space_after=8
    )

    # --- CONTACT ---
    add_heading_styled(doc, "10. Contact Information", level=1)
    add_feature_table(doc, ["", ""], [
        ["Company", "[Company Name]"],
        ["Address", "[Company Address]"],
        ["Phone", "[Phone Number]"],
        ["Email", "[Email Address]"],
        ["Website", "[Website URL]"],
    ])

    filepath = os.path.join(OUTPUT_DIR, "Agency_Banking_Solution_Marketing_EN.docx")
    doc.save(filepath)
    print(f"Generated: {filepath}")
    return filepath


# =============================================================================
# MARKETING DOCUMENT - FRENCH
# =============================================================================
def generate_marketing_fr():
    doc = Document()

    for section in doc.sections:
        section.top_margin = Cm(2.5)
        section.bottom_margin = Cm(2.5)
        section.left_margin = Cm(2.5)
        section.right_margin = Cm(2.5)

    # Cover page
    add_cover_page(
        doc,
        "[Nom du Produit]",
        "Solution d'Agency Banking pour Entreprises",
        "Proposition Commerciale & Apercu de la Solution",
        datetime.now().strftime("%B %Y"),
        "Les informations fournies dans ce document sont a titre informatif uniquement. "
        "Ce document contient des informations confidentielles de [Nom de l'Entreprise] et ne doit "
        "etre utilise que dans le but pour lequel il a ete fourni. Le contenu de ce document ne peut "
        "etre communique, en tout ou en partie, a un tiers sans l'approbation ecrite prealable de "
        "[Nom de l'Entreprise]. Tous les droits d'auteur de ce document appartiennent a "
        "[Nom de l'Entreprise]. La validite de cette proposition est de 90 jours a compter de la date de soumission."
    )

    add_toc_placeholder(doc, "Table des Matieres")

    # --- RESUME EXECUTIF ---
    add_heading_styled(doc, "1. Resume Executif", level=1)
    add_styled_paragraph(
        doc,
        "Alors que les institutions financieres a travers l'Afrique et les marches emergents "
        "s'efforcent d'etendre leur portee aux populations sous-bancarisees et non bancarisees, "
        "le besoin de solutions d'agency banking robustes, evolutives et abordables n'a jamais "
        "ete aussi grand. L'expansion traditionnelle des agences physiques est couteuse et peu "
        "pratique pour les zones rurales et eloignees ou resident des millions de clients potentiels.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "[Nom de l'Entreprise] presente [Nom du Produit], une plateforme complete d'Agency Banking "
        "pour entreprises concue specifiquement pour les institutions de microfinance (IMF), les "
        "banques et les prestataires de services financiers cherchant a etendre leurs services "
        "a travers des reseaux d'agents tiers. Notre plateforme combine un puissant portail "
        "d'administration web avec une application Android POS riche en fonctionnalites, permettant "
        "aux institutions de gerer les operations des agents, de traiter les transactions, d'ouvrir "
        "des comptes et de fournir des services financiers aux clients ou qu'ils se trouvent.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "[Nom du Produit] est construit sur une technologie eprouvee et a ete concu en tenant compte "
        "des defis uniques des marches emergents, notamment les environnements a faible bande passante, "
        "le support multilingue (anglais, francais et portugais), la capacite hors ligne et "
        "l'integration avec plusieurs systemes bancaires centraux. La plateforme prend en charge "
        "les operations multi-locataires, permettant a un seul deploiement de servir plusieurs "
        "unites commerciales ou institutions de microfinance a partir d'un panneau de super "
        "administration centralise.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- AVANTAGES CLES ---
    add_heading_styled(doc, "2. Avantages Cles", level=1)
    benefits_fr = [
        ("Inclusion Financiere : ", "Etendre les services bancaires aux populations sous-bancarisees et non bancarisees dans les zones rurales et eloignees grace a un reseau d'agents tiers, eliminant le besoin d'expansion couteuse des agences."),
        ("Portee Accrue : ", "Deployer des agents equipes de terminaux POS pour servir les clients dans leurs communautes, villages et marches, rapprochant les services financiers des populations."),
        ("Reduction des Couts Operationnels : ", "Minimiser les investissements en infrastructure en exploitant les reseaux de detail existants comme agents bancaires, reduisant significativement le cout par transaction par rapport aux operations traditionnelles en agence."),
        ("Commodite pour les Clients : ", "Permettre aux clients d'effectuer des depots, des retraits, des consultations de solde et l'ouverture de comptes dans des points d'agents pratiques, en dehors des heures bancaires traditionnelles."),
        ("Autonomisation des Agents : ", "Fournir aux agents des outils complets incluant le support des cartes a puce NFC, l'authentification biometrique, l'impression de recus thermiques et le traitement des transactions en temps reel."),
        ("Transformation Numerique : ", "Moderniser les operations avec l'enregistrement numerique des clients, la capture electronique des donnees KYC, l'inscription biometrique et les flux de travail sans papier."),
        ("Evolutivite : ", "L'architecture multi-locataire prend en charge plusieurs unites commerciales a partir d'une seule plateforme, permettant une expansion rapide a travers les regions et les pays."),
        ("Securite et Conformite : ", "L'authentification multi-facteurs (PIN, OTP, biometrie), les communications cryptees, les pistes d'audit completes et le controle d'acces base sur les roles assurent la conformite reglementaire."),
    ]
    for prefix, text in benefits_fr:
        add_bullet(doc, text, bold_prefix=prefix)

    doc.add_paragraph()

    # --- APERCU DE LA SOLUTION ---
    add_heading_styled(doc, "3. Apercu de la Solution", level=1)
    add_styled_paragraph(
        doc,
        "[Nom du Produit] est une plateforme d'agency banking cle en main complete composee de deux composants integres :",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "3.1 Portail d'Administration Web", level=2)
    add_styled_paragraph(
        doc,
        "Une puissante console de gestion basee sur le web qui offre aux institutions financieres "
        "un controle total sur leurs operations d'agency banking. Le portail permet aux super "
        "administrateurs de gerer plusieurs unites commerciales, de configurer les regles de "
        "transaction, de surveiller les performances des agents, de generer des rapports et de "
        "maintenir une supervision complete de toutes les operations.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "3.2 Application Mobile POS", level=2)
    add_styled_paragraph(
        doc,
        "Une application basee sur Android optimisee pour les terminaux POS Telpo, equipee de "
        "lecteurs de cartes NFC integres, de scanners d'empreintes digitales et d'imprimantes "
        "thermiques. L'application permet aux agents de terrain d'enregistrer des clients, d'ouvrir "
        "des comptes, de traiter des transactions financieres, de capturer des donnees biometriques "
        "et d'emettre des cartes a puce - le tout a partir d'un seul appareil portatif.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- MODULES DU PORTAIL WEB ---
    add_heading_styled(doc, "4. Portail d'Administration Web - Points Forts des Modules", level=1)

    add_heading_styled(doc, "4.1 Gestion Multi-Unites Commerciales", level=2)
    add_styled_paragraph(
        doc,
        "[Nom du Produit] prend en charge une architecture multi-locataire ou un seul super "
        "administrateur peut gerer plusieurs institutions de microfinance ou unites commerciales "
        "a partir d'un tableau de bord centralise. Chaque unite commerciale fonctionne avec une "
        "isolation complete des donnees, ses propres parametres de configuration, roles utilisateur, "
        "types de transactions, structures de commissions et parametres SMS. Cette architecture "
        "permet aux societes holding et aux reseaux de microfinance de deployer une seule plateforme "
        "a travers plusieurs filiales ou succursales, reduisant les couts informatiques tout en "
        "maintenant l'independance operationnelle.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.2 Gestion des Agents", level=2)
    add_styled_paragraph(
        doc,
        "Le module de gestion des agents fournit des outils complets pour le recrutement, "
        "l'enregistrement et la gestion des agents bancaires tiers. Les administrateurs peuvent "
        "capturer des profils d'agents complets incluant les details personnels, les documents "
        "d'identification et les photographies. Les agents sont affectes a des terminaux POS et "
        "lies a des comptes de flottant et de commission pour le traitement des transactions. "
        "Le systeme prend en charge l'enregistrement automatique des agents aupres des systemes "
        "bancaires centraux, la generation et la distribution de PIN par SMS, et la surveillance "
        "en temps reel de l'activite des agents.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.3 Gestion des Clients", level=2)
    add_styled_paragraph(
        doc,
        "Le module de gestion des clients prend en charge l'enregistrement complet des clients "
        "avec la capture complete des donnees KYC (Know Your Customer) incluant les informations "
        "personnelles, les donnees demographiques, les contacts d'urgence, les details du plus "
        "proche parent, les informations sur le logement, les donnees de revenus et la "
        "geolocalisation. Les clients peuvent etre organises en groupes pour une gestion efficace "
        "des programmes de prets solidaires ou d'epargne villageoise. Le module prend en charge "
        "l'inscription biometrique avec jusqu'a quatre empreintes digitales par client.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.4 Gestion des Comptes", level=2)
    add_styled_paragraph(
        doc,
        "Le module de gestion des comptes gere la creation et le cycle de vie de plusieurs types "
        "de comptes, y compris les comptes d'epargne clients, les comptes de flottant des agents "
        "et les comptes de commission des agents. Les numeros de compte peuvent etre generes "
        "automatiquement ou attribues manuellement, avec une validation d'unicite integree par "
        "unite commerciale. Le systeme prend en charge les operations multi-devises et suit le "
        "statut du compte, les soldes et les dates d'ouverture/fermeture.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.5 Traitement des Transactions", level=2)
    add_styled_paragraph(
        doc,
        "Le module de gestion des transactions est le coeur de la plateforme, prenant en charge "
        "une gamme complete de transactions financieres incluant les depots en especes, les retraits "
        "en especes, les retraits biometriques, les consultations de solde et les mini-releves. "
        "Chaque transaction est traitee avec des methodes de verification configurables incluant "
        "l'authentification OTP et PIN. Le systeme calcule automatiquement les couts de transaction "
        "et les commissions des agents en utilisant un moteur de tarification echelonnee sophistique.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.6 Gestion des Cartes", level=2)
    add_styled_paragraph(
        doc,
        "Le module de gestion des cartes fournit une gestion complete du cycle de vie des cartes "
        "a puce. Les cartes sont liees aux comptes clients et prennent en charge les operations "
        "sans contact NFC avec les types de cartes Mifare Classic (M1) et Mifare Ultralight (M0). "
        "Le systeme gere le cycle de vie complet de la carte incluant l'emission, l'activation, "
        "le blocage, le remplacement et le mappage UID. Les capacites d'importation en masse "
        "rationalisent les operations d'approvisionnement des cartes.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.7 Gestion des Terminaux et Appareils POS", level=2)
    add_styled_paragraph(
        doc,
        "Le module de gestion des terminaux suit tous les appareils POS deployes a travers le "
        "reseau d'agents. Chaque terminal est enregistre avec son numero de serie, les details "
        "de la carte SIM et les informations du modele. Les terminaux sont affectes aux agents "
        "avec un suivi complet de l'historique d'affectation. Le module offre une visibilite "
        "sur le statut du terminal et l'activite transactionnelle par appareil.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.8 Moteur de Frais et Commissions", level=2)
    add_styled_paragraph(
        doc,
        "Le moteur de frais et commissions fournit un systeme flexible base sur des regles pour "
        "le calcul des couts de transaction et des commissions des agents. Le moteur prend en "
        "charge plusieurs methodes de calcul incluant les montants fixes, les pourcentages, la "
        "tarification echelonnee, les pourcentages echelonnes et les montants fixes echelonnes. "
        "Chaque regle peut etre activee ou desactivee par type de transaction et par unite "
        "commerciale, permettant un controle precis des structures de tarification et d'incitation.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.9 Alertes SMS et Notifications", level=2)
    add_styled_paragraph(
        doc,
        "Le module de gestion SMS permet aux institutions financieres de communiquer avec les "
        "agents et les clients par SMS. Le systeme s'integre avec l'API SMS Orange pour la "
        "livraison des messages et prend en charge l'envoi de SMS individuels et en masse, la "
        "gestion des credits SMS par unite commerciale et les alertes transactionnelles. Le SMS "
        "est utilise pour la livraison d'OTP, les notifications de PIN des agents, les confirmations "
        "de transactions et les campagnes promotionnelles.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.10 Integration Bancaire Centrale", level=2)
    add_styled_paragraph(
        doc,
        "[Nom du Produit] est concu pour etre agnostique au CBS, prenant en charge l'integration "
        "avec plusieurs systemes bancaires centraux. La plateforme s'integre actuellement avec le "
        "systeme bancaire central KATO pour l'enregistrement automatique des agents et le traitement "
        "des transactions en temps reel. Le cadre d'integration est extensible, permettant la "
        "connexion a des plateformes CBS supplementaires selon les besoins.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "Au-dela de KATO, la plateforme inclut un pilote Configurable (mappeur JSON) qui permet aux "
        "administrateurs d'integrer n'importe quel systeme bancaire central REST entierement depuis le "
        "portail Super Admin, sans developpement sur mesure. Un nouveau CBS est defini une seule fois "
        "(URL de base, authentification, delai d'attente), puis les champs internes sont mappes "
        "visuellement vers l'API du systeme cible pour chaque type de transaction. Cela reduit "
        "considerablement les delais d'integration et permet a un seul deploiement de servir des "
        "institutions utilisant differentes plateformes bancaires centrales en parallele.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.11 Rapports et Analytique", level=2)
    add_styled_paragraph(
        doc,
        "Le module de rapports fournit des analyses completes et des capacites d'exportation "
        "a travers tous les modules de la plateforme. Les rapports peuvent etre generes pour les "
        "agents, les clients, les transactions, les commissions et les couts avec un filtrage "
        "flexible par plage de dates. Le systeme prend en charge la generation asynchrone de "
        "rapports et livre les rapports aux formats CSV, Excel et PDF.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "4.12 Controle d'Acces Base sur les Roles", level=2)
    add_styled_paragraph(
        doc,
        "La plateforme implemente un systeme complet de controle d'acces base sur les roles (RBAC) "
        "qui permet une gestion granulaire des permissions. Des roles personnalises peuvent etre "
        "crees avec des permissions specifiques attribuees au niveau de l'operation pour chaque "
        "module. Le super administrateur a un controle total sur toutes les unites commerciales "
        "et les configurations a l'echelle du systeme.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- APPLICATION MOBILE ---
    add_heading_styled(doc, "5. Application Mobile POS", level=1)
    add_styled_paragraph(
        doc,
        "L'application Mobile POS [Nom du Produit] est une application Android pour agents "
        "optimisee pour les terminaux POS Telpo avec lecteurs de cartes NFC integres, scanners "
        "d'empreintes digitales biometriques et imprimantes thermiques de recus.",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_feature_table(doc, ["Fonctionnalite", "Description"], [
        ["Enregistrement Client", "Enregistrement client en plusieurs etapes avec capture d'informations personnelles, photo, selection du type de compte et inscription biometrique des empreintes digitales (4 doigts). Recu imprime a la fin."],
        ["Depot en Especes", "Depots bases sur la carte NFC avec identification automatique du compte. Securise par verification PIN et OTP. Impression automatique du recu."],
        ["Retrait en Especes", "Retraits bases sur la carte NFC avec verification PIN et OTP. Prend en charge les modes de retrait standard et biometrique."],
        ["Retrait Biometrique", "Retraits authentifies par empreinte digitale qui eliminent le besoin de PIN ou OTP, offrant une experience client fluide."],
        ["Consultation de Solde", "Recuperation instantanee du solde du compte via le tap de la carte NFC."],
        ["Mini-Releve", "Recuperation des dernieres transactions du compte client via le tap de la carte NFC."],
        ["Operations Cartes NFC", "Lecture et ecriture de donnees clients sur les cartes NFC Mifare Classic (M1) et Mifare Ultralight (M0)."],
        ["Inscription Biometrique", "Capture de jusqu'a 4 empreintes digitales par client a l'aide du scanner biometrique Feitian integre."],
        ["Impression de Recus", "Impression automatique de recus pour toutes les transactions et ouvertures de comptes."],
        ["Paiements de Services", "Support pour les paiements de services et utilitaires incluant OneAWater et Yolse."],
        ["Capacite Hors Ligne", "La mise en cache locale SQLite permet un fonctionnement continu en environnements a faible connectivite."],
        ["Multi-Langue", "Support du francais et de l'anglais avec changement de langue en temps reel."],
    ])

    # --- SECURITE ---
    add_heading_styled(doc, "6. Securite et Conformite", level=1)
    add_styled_paragraph(
        doc,
        "[Nom du Produit] implemente plusieurs couches de securite pour proteger les transactions "
        "financieres et les donnees clients :",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    security_fr = [
        ("Authentification Multi-Facteurs : ", "Les transactions sont securisees avec des options de verification PIN, OTP et biometrique."),
        ("Securite Biometrique : ", "L'authentification par empreinte digitale fournit le plus haut niveau de verification d'identite."),
        ("Communications Cryptees : ", "Toutes les communications API utilisent des canaux cryptes. Les PIN sont cryptes avant transmission."),
        ("Piste d'Audit : ", "Chaque action du systeme est enregistree avec un suivi complet des modifications avant/apres."),
        ("Acces Base sur les Roles : ", "Les controles de permissions granulaires garantissent que les utilisateurs n'accedent qu'aux fonctions appropriees a leur role."),
        ("Isolation des Donnees : ", "L'isolation des donnees multi-locataire garantit que les donnees de chaque unite commerciale sont completement separees."),
        ("Gestion des Sessions : ", "Les delais d'expiration automatiques des sessions et l'authentification securisee par jeton protegent contre les acces non autorises."),
    ]
    for prefix, text in security_fr:
        add_bullet(doc, text, bold_prefix=prefix)

    doc.add_paragraph()

    # --- MULTI-LANGUE ---
    add_heading_styled(doc, "7. Support Multilingue", level=1)
    add_styled_paragraph(
        doc,
        "[Nom du Produit] est concu pour le deploiement sur plusieurs marches africains avec un "
        "support complet de localisation. Le portail d'administration web prend en charge l'anglais, "
        "le francais et le portugais, tandis que l'application POS mobile prend en charge le francais "
        "et l'anglais. Tous les elements de l'interface utilisateur, les messages de transaction et "
        "les rapports sont entierement traduits.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- POURQUOI CHOISIR ---
    add_heading_styled(doc, "8. Pourquoi Choisir [Nom du Produit] ?", level=1)
    diff_fr = [
        ("Technologie Eprouvee : ", "Construit sur le framework Laravel de niveau entreprise avec une architecture API REST robuste."),
        ("Solution Complete : ", "Une seule plateforme couvre tous les besoins d'agency banking."),
        ("Integration Materielle : ", "Integration approfondie avec les appareils POS Telpo, les cartes NFC, les scanners biometriques et les imprimantes thermiques."),
        ("Architecture Multi-Locataire : ", "Support de plusieurs unites commerciales a partir d'une seule plateforme."),
        ("Agnostique CBS : ", "Le cadre d'integration flexible prend en charge la connexion a n'importe quel systeme bancaire central."),
        ("Capacite Hors Ligne : ", "L'application mobile continue de fonctionner dans les environnements a faible connectivite."),
        ("Deploiement Rapide : ", "Les modules preconstruits et les parametres configurables permettent un deploiement rapide."),
        ("Securite Complete : ", "L'authentification multi-facteurs, la verification biometrique et les pistes d'audit repondent aux exigences reglementaires les plus strictes."),
    ]
    for prefix, text in diff_fr:
        add_bullet(doc, text, bold_prefix=prefix)

    doc.add_paragraph()

    # --- REFERENCES ---
    add_heading_styled(doc, "9. References et Cas d'Utilisation", level=1)
    add_styled_paragraph(
        doc,
        "[Nom de l'Entreprise] a deploye avec succes des solutions d'agency banking pour des "
        "institutions de microfinance sur plusieurs marches africains.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "[Inserer les references clients et les etudes de cas ici]",
        font_size=11, italic=True, color=GRAY, space_after=8
    )

    # --- CONTACT ---
    add_heading_styled(doc, "10. Informations de Contact", level=1)
    add_feature_table(doc, ["", ""], [
        ["Entreprise", "[Nom de l'Entreprise]"],
        ["Adresse", "[Adresse de l'Entreprise]"],
        ["Telephone", "[Numero de Telephone]"],
        ["Email", "[Adresse Email]"],
        ["Site Web", "[URL du Site Web]"],
    ])

    filepath = os.path.join(OUTPUT_DIR, "Agency_Banking_Solution_Marketing_FR.docx")
    doc.save(filepath)
    print(f"Generated: {filepath}")
    return filepath


# =============================================================================
# TECHNICAL DOCUMENT - ENGLISH
# =============================================================================
def generate_technical_en():
    doc = Document()

    for section in doc.sections:
        section.top_margin = Cm(2.5)
        section.bottom_margin = Cm(2.5)
        section.left_margin = Cm(2.5)
        section.right_margin = Cm(2.5)

    add_cover_page(
        doc,
        "[Product Name]",
        "Enterprise Agency Banking Solution",
        "Technical Proposal & Solution Architecture",
        datetime.now().strftime("%B %Y"),
        "The information provided in this document is for informational purposes only. "
        "This document contains confidential information of [Company Name] and is to be used "
        "only for the purpose for which it has been supplied. The contents of this document may "
        "not, neither in whole nor in part, be communicated to any third party without the prior "
        "written approval of [Company Name]. All copyrights in this document belong to [Company Name]. "
        "The validity period of this proposal is 90 days as of the submission date."
    )

    add_toc_placeholder(doc)

    # --- 1. EXECUTIVE SUMMARY ---
    add_heading_styled(doc, "1. Executive Summary", level=1)
    add_styled_paragraph(
        doc,
        "[Product Name] is a comprehensive, multi-tenant enterprise agency banking platform "
        "designed to enable financial institutions to extend banking services through third-party "
        "agent networks. The solution comprises a web-based administration portal built on the "
        "Laravel PHP framework and an Android POS application optimized for Telpo terminals with "
        "integrated NFC, biometric, and thermal printing hardware.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "This document provides a detailed technical overview of the platform architecture, "
        "modules, API specifications, security features, and integration capabilities. It is "
        "intended for IT managers, solution architects, and technical evaluators assessing the "
        "platform for deployment.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- 2. SOLUTION ARCHITECTURE ---
    add_heading_styled(doc, "2. Solution Architecture", level=1)

    add_heading_styled(doc, "2.1 Platform Overview", level=2)
    add_feature_table(doc, ["Component", "Technology", "Details"], [
        ["Web Backend", "Laravel / PHP", "RESTful API architecture, MVC pattern, Eloquent ORM"],
        ["Database", "MySQL / PostgreSQL", "Relational database with multi-tenant data isolation"],
        ["Web Authentication", "Laravel Session", "Session-based authentication for web portal users"],
        ["API Authentication", "Laravel Sanctum", "Token-based authentication for mobile/POS API calls"],
        ["API Key Auth", "Custom Middleware", "Alternative API key-based authentication for system integrations"],
        ["Mobile Application", "Android (Java)", "Min SDK 19 (Android 4.4), Target SDK 26 (Android 8.0)"],
        ["POS Hardware", "Telpo Terminal", "Integrated NFC reader, biometric scanner, thermal printer"],
        ["NFC SDK", "Feitian FTSDK", "Mifare Classic (M1) and Ultralight (M0) card support"],
        ["Biometric SDK", "Feitian FingerPrint", "4-finger enrollment, template matching, ISO format"],
        ["SMS Integration", "Orange SMS API", "JWT-authenticated SMS delivery for OTP and alerts"],
        ["CBS Integration", "KATO System", "RESTful integration for agent registration and transactions"],
        ["Export Formats", "CSV / Excel / PDF", "Configurable report generation with async processing"],
        ["Localization", "i18n (en, fr, pt)", "Multi-language support with runtime switching"],
    ])

    add_heading_styled(doc, "2.2 Multi-Tenancy Model", level=2)
    add_styled_paragraph(
        doc,
        "The platform implements a shared-database, shared-schema multi-tenancy model where all "
        "entities are scoped using a `business_unit_id` foreign key. Data isolation is enforced "
        "at the application layer through middleware and query scoping. Each business unit "
        "maintains independent configurations for:",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    isolation_items = [
        "Transaction types and fee structures",
        "Commission configurations and tier definitions",
        "SMS settings and credit balances",
        "Card settings (prefix, expiration, limits)",
        "User roles and permissions",
        "Core banking system assignment",
        "Agent and client data",
    ]
    for item in isolation_items:
        add_bullet(doc, item)

    doc.add_paragraph()

    add_heading_styled(doc, "2.3 Authentication Architecture", level=2)
    add_styled_paragraph(
        doc,
        "The platform implements multiple authentication mechanisms:",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_feature_table(doc, ["Method", "Use Case", "Details"], [
        ["Session Auth", "Web Portal", "Laravel session-based authentication with CSRF protection. Remember-me support. Password reset capability."],
        ["Sanctum Tokens", "Mobile API", "Token-based stateless authentication for POS application. Tokens issued on agent login."],
        ["API Key", "System Integration", "Custom middleware-based API key validation for server-to-server integrations."],
        ["PIN", "Transaction Auth", "Agent PIN verification for transaction authorization. Encrypted before transmission."],
        ["OTP", "Transaction Auth", "5-digit one-time passwords with 10-minute expiration. Delivered via SMS. Required for financial transactions."],
        ["Biometric", "Transaction Auth", "Fingerprint template matching for biometric withdrawals. Up to 4 templates per client."],
    ])

    add_heading_styled(doc, "2.4 Role-Based Access Control (RBAC)", level=2)
    add_styled_paragraph(
        doc,
        "The RBAC system is implemented with three core entities: Users, Roles, and Permissions. "
        "Users are assigned to business units with specific roles. Roles contain sets of permissions "
        "that control access at the operation level (create, view, edit, delete) for each module. "
        "A global super-admin flag grants unrestricted access across all business units.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- 3. WEB PLATFORM MODULES ---
    add_heading_styled(doc, "3. Web Platform Modules", level=1)

    # Business Unit Management
    add_heading_styled(doc, "3.1 Organization & Business Unit Management", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Business Unit Creation", "Create and manage business units with name, description, email, phone, address, logo, tax number, mailing address, and relation manager details."],
        ["2", "Business Unit Configuration", "Configure SMS sender number, core banking system assignment, KATO company ID, preferred language, and active/inactive status per unit."],
        ["3", "Transaction Type Assignment", "Assign available transaction types to each business unit from the global transaction type catalog."],
        ["4", "User Assignment", "Add users to business units with role assignments. Users can belong to multiple business units with different roles."],
        ["5", "Super Admin Panel", "Global super administration with ability to switch between business units and manage all system-wide configurations."],
    ])

    # User & Role Management
    add_heading_styled(doc, "3.2 User & Role Management", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "User Registration", "Create users with personal details, email, password. Assign to business units with specific roles."],
        ["2", "Role Management", "Create custom roles with granular permission sets. Roles are bound to users at the business unit level."],
        ["3", "Permission Management", "Define permissions by group (agent, client, transaction, card, etc.) with operation-level granularity (create, view, edit, delete)."],
        ["4", "User Preferences", "Preferred language (en/fr/pt), theme (light/dark), current business unit tracking."],
        ["5", "Super Admin Flag", "Global super admin designation with unrestricted access to all business units and system configurations."],
    ])

    # Agent Management
    add_heading_styled(doc, "3.3 Agent Management", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Agent Registration", "Full profile capture: first/second name, email, phone, ID/passport, photo, address, PO box, username/password."],
        ["2", "Terminal Assignment", "Assign/unassign POS terminals to agents with full history tracking (assigned_at, unassigned_at)."],
        ["3", "Account Linking", "Link float and commission accounts to agents for transaction processing and commission settlement."],
        ["4", "PIN Management", "Generate initial PINs via SMS. Reset PIN via OTP-authenticated flow (web and API). Track first-time PIN status."],
        ["5", "CBS Registration", "Automatic agent registration with KATO core banking system. Track KATO agent IDs and registration errors."],
        ["6", "Search & Export", "Search by name, email, username, phone. Export to CSV, Excel, PDF."],
    ])

    # Client Management
    add_heading_styled(doc, "3.4 Client Management", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Client Registration", "Comprehensive registration: salutation, names, email, ID/passport, phone, gender, marital status, DOB, place of birth, nationality, occupation, address."],
        ["2", "Extended Demographics", "Housing type, monthly income, number of dependents, location, geo-coordinates, distance from branch, observation notes."],
        ["3", "Emergency & Next of Kin", "Emergency contact (name, address). Next of kin (full name, phone, address, gender)."],
        ["4", "Biometric Enrollment", "Store up to 4 fingerprint templates per client. Biometrics status flag (enabled/disabled)."],
        ["5", "Client Groups", "Create and manage client groups. Bulk assign clients to groups. Filter clients by group membership."],
        ["6", "Photo & Documents", "Photo upload and storage. Document capture support."],
        ["7", "Search & Export", "Search by name, email, phone, ID/passport. Export to CSV, Excel, PDF."],
    ])

    # Account Management
    add_heading_styled(doc, "3.5 Account Management", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Account Types", "Client accounts (general), agent float accounts, agent commission accounts. Polymorphic relationship to Client or Agent models."],
        ["2", "Account Creation", "Auto-generate or manually assign account numbers. Uniqueness validation per business unit. Multi-currency support (default: CFA)."],
        ["3", "Account Properties", "Account number, member code, manual number, opening/closing dates, status (active/inactive/suspended/closed), balance (decimal), signatures required, manager name."],
        ["4", "Account Lifecycle", "Status management, balance tracking, account linking to clients and agents. Deletion protection for required agent accounts."],
        ["5", "Search & Export", "Search by account number, description, owner name. Export to CSV, Excel, PDF."],
    ])

    # Transaction Management
    add_heading_styled(doc, "3.6 Transaction Management", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Transaction Types", "Configurable transaction types per business unit. Global types managed by super admin. Each type has code and name."],
        ["2", "Transaction Processing", "Full transaction lifecycle: creation, verification (OTP/PIN), completion. Statuses: pending, completed, failed, cancelled."],
        ["3", "Cost Calculation", "Transaction amount -> cost calculation using configurable rules: fixed, percentage, tiered, tiered percentage, tiered fixed."],
        ["4", "Commission Calculation", "Transaction cost -> commission calculation. Business share = cost - commission. Same calculation types as costs."],
        ["5", "Transaction Methods", "Standard agent transactions, biometric withdrawals (fingerprint verification), cash deposits. Card number and account tracking."],
        ["6", "Filtering & Export", "Filter by date range, agent, terminal, transaction type, status. Export to CSV, Excel, PDF."],
    ])

    # Card Management
    add_heading_styled(doc, "3.7 Card Management", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Card Creation", "Auto-generate card numbers with business unit prefix. Link to client accounts. Card serial tracking."],
        ["2", "Card Lifecycle", "Status management: issued, activated, blocked, written, replaced. Timestamp tracking for each status change. Block/replace with reason tracking."],
        ["3", "Card Properties", "Card number (unique), PIN hash, expiration date, daily transaction/withdrawal limits."],
        ["4", "UID Management", "Bulk import card UIDs. Map UIDs to card numbers. Track written status. Sample template download."],
        ["5", "Card Settings", "Configure per business unit: card prefix, auto-increment length, default expiration years, daily limits."],
        ["6", "NFC Operations", "Read/write data to Mifare Classic (M1) and Ultralight (M0) cards via POS application."],
    ])

    # Terminal Management
    add_heading_styled(doc, "3.8 Terminal / POS Management", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Terminal Registration", "Register terminals with serial number (unique), SIM card number, device alias, model/make."],
        ["2", "Agent Assignment", "Assign terminals to agents with history tracking. Track assignment/unassignment dates."],
        ["3", "Terminal Monitoring", "View all transactions per terminal. Filter by date range and status. Active/inactive status tracking."],
        ["4", "Search & Export", "Search by serial number, SIM card, alias, model. Export to CSV, Excel, PDF."],
    ])

    # Fees & Commission Engine
    add_heading_styled(doc, "3.9 Fees & Commission Engine", level=2)
    add_styled_paragraph(
        doc,
        "The fees and commission engine calculates transaction costs and agent commissions "
        "using a two-stage calculation pipeline:",
        font_size=11, color=DARK_GRAY, space_after=6
    )
    add_styled_paragraph(
        doc,
        "Stage 1: Transaction Amount -> Transaction Cost (using cost configuration rules)\n"
        "Stage 2: Transaction Cost -> Agent Commission (using commission configuration rules)\n"
        "Business Share = Transaction Cost - Agent Commission",
        font_size=10, color=MEDIUM_BLUE, space_after=8
    )
    add_feature_table(doc, ["Calculation Type", "Cost Configuration", "Commission Configuration"], [
        ["Fixed Amount", "Fixed fee per transaction regardless of amount", "Fixed commission per transaction"],
        ["Percentage", "Percentage of transaction amount", "Percentage of transaction cost"],
        ["Tiered", "Variable amount based on transaction amount ranges", "Variable amount based on cost ranges"],
        ["Tiered Percentage", "Variable percentage based on amount ranges", "Variable percentage based on cost ranges"],
        ["Tiered Fixed", "Fixed amount per tier based on amount ranges", "Fixed amount per tier based on cost ranges"],
    ])

    # SMS Management
    add_heading_styled(doc, "3.10 SMS Management", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "SMS Configuration", "Super admin: configure Orange SMS API credentials (client ID, secret, JWT token), endpoint URL, sender name. Test SMS functionality. Activate/deactivate service."],
        ["2", "SMS Credits", "Super admin: load SMS credits to business units. View balance and transaction history. Track debit/credit per unit."],
        ["3", "SMS Messaging", "Send single and bulk SMS to phone numbers. SMS history tracking with timestamps. Track success/failure."],
        ["4", "Automated SMS", "Agent PIN notifications, OTP delivery, transactional alerts. Multi-language message support."],
        ["5", "Balance Validation", "Validate SMS credit balance before sending. Per-business-unit SMS sender configuration."],
    ])

    # OTP Module
    add_heading_styled(doc, "3.11 OTP Module", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "OTP Generation", "Auto-generate 5-digit OTP codes with 10-minute expiration."],
        ["2", "OTP Delivery", "Send OTP via SMS to registered phone number (client or agent)."],
        ["3", "OTP Verification", "Validate OTP code and expiration. Mark as verified/used. Prevent reuse."],
        ["4", "OTP Purposes", "Client transaction verification, agent PIN reset verification."],
        ["5", "OTP Lookup", "Lookup by card number or account number."],
    ])

    # Biometrics Module
    add_heading_styled(doc, "3.12 Biometrics Module", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Fingerprint Storage", "Store up to 4 fingerprint templates per client. Encrypted storage (templates not logged)."],
        ["2", "Biometrics Status", "Per-client biometrics enabled/disabled flag."],
        ["3", "Biometric Verification", "Fetch fingerprints for transaction verification. Support biometric withdrawals without PIN/OTP."],
        ["4", "API Integration", "POST /api/v1/biometrics/fetch - Retrieve client fingerprints by account number."],
    ])

    # Core Banking Integration
    add_heading_styled(doc, "3.13 Core Banking Integration", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "CBS Configuration", "Super admin: create/manage CBS configurations with name, code, description, base URL, active/inactive status."],
        ["2", "KATO Integration", "Automatic agent registration with KATO system. Track KATO agent IDs and registration errors."],
        ["3", "Configurable Driver", "JSON mapper driver for any REST CBS. Per-transaction-type field mappings configured in Super Admin; no code changes needed."],
        ["4", "Business Unit CBS", "Each business unit can be assigned a different CBS, with its own credentials, extra headers and field mappings."],
        ["5", "Extensibility", "CBS-agnostic framework design. New CBS adapters can be added without modifying existing modules."],
    ])

    # Audit Trail
    add_heading_styled(doc, "3.14 Audit Trail & Activity Logging", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Activity Logging", "Track all create, update, delete operations per model. Record who made changes and when."],
        ["2", "Change Tracking", "Store before/after values for all field changes. Batch UUID tracking for related operations."],
        ["3", "Audit Views", "Filter by user, model type, event type, date range. Search audit descriptions."],
        ["4", "Export", "Export audit trail to CSV, Excel, PDF. Permission-based audit trail viewing."],
    ])

    # Reporting Module
    add_heading_styled(doc, "3.15 Reporting Module", level=2)
    add_feature_table(doc, ["#", "Component", "Description"], [
        ["1", "Report Types", "Agent reports, client reports, transaction reports, commission reports, cost reports."],
        ["2", "Generation", "Asynchronous job-based generation. Date range filtering. Business unit scoping."],
        ["3", "Status Tracking", "Statuses: pending, processing, completed, failed. Error messages on failure."],
        ["4", "Management", "View, download, retry, delete reports. Permission-based access control."],
        ["5", "Export Formats", "CSV, Excel (XLSX with formatted sheets), PDF (formatted with filter display)."],
    ])

    # --- 4. MOBILE/POS APPLICATION ---
    add_heading_styled(doc, "4. Mobile POS Application - Technical Details", level=1)

    add_heading_styled(doc, "4.1 Application Architecture", level=2)
    add_feature_table(doc, ["Component", "Details"], [
        ["Platform", "Android (Java)"],
        ["Min SDK", "19 (Android 4.4 KitKat)"],
        ["Target SDK", "26 (Android 8.0 Oreo)"],
        ["Package", "com.rhenium.bukina / com.its.telpo"],
        ["Architecture", "Activity-based with Fragment support"],
        ["Local Storage", "SQLite database (ditialservice.db)"],
        ["API Communication", "Retrofit/HttpURLConnection with JSON"],
        ["Hardware SDKs", "Feitian FTSDK (NFC), FingerPrintLib (biometric), Telpo API (printer)"],
        ["NFC Support", "Mifare Classic M1 (authenticated) and Mifare Ultralight M0"],
        ["Biometric", "Feitian FingerPrintLib v1.3.1 - 4-finger enrollment, template matching"],
        ["Printing", "Telpo/Feitian thermal printer API with status monitoring"],
        ["Localization", "French (default) and English with runtime switching"],
    ])

    add_heading_styled(doc, "4.2 Hardware Integration", level=2)

    add_heading_styled(doc, "4.2.1 NFC Smart Card Operations", level=3)
    add_styled_paragraph(
        doc,
        "The application supports two NFC card types for customer identification and transaction "
        "processing:",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_feature_table(doc, ["Feature", "Mifare Classic M1", "Mifare Ultralight M0"], [
        ["Authentication", "Key A required (default: FF FF FF FF FF FF)", "No authentication needed"],
        ["Block Size", "16 bytes per block", "4 bytes per page (4 pages = 16 bytes)"],
        ["Data Storage", "Blocks 24-28", "Pages 4-15 (4 pages per field)"],
        ["Customer Name", "Block 24 (16 bytes)", "Pages 4-7"],
        ["Account Number", "Block 25 (16 bytes)", "Pages 8-11"],
        ["Card Number", "Block 26 (16 bytes)", "Pages 12-15"],
        ["Surname", "Block 28 (16 bytes)", "Pages 16-19"],
        ["Detection", "ISO 14443 Type A", "ISO 14443 Type A"],
        ["Timeout", "10-second polling", "10-second polling"],
    ])

    add_heading_styled(doc, "4.2.2 Biometric Scanner", level=3)
    add_feature_table(doc, ["Feature", "Specification"], [
        ["SDK", "Feitian FingerPrintLib v1.3.1"],
        ["Enrollment", "4 fingers: thumb, index, middle, ring"],
        ["Image Quality", "Minimum 50% threshold"],
        ["Template Format", "Base64-encoded fingerprint templates"],
        ["Storage", "Transmitted to backend via API for secure storage"],
        ["Verification", "Template comparison on backend for biometric withdrawals"],
    ])

    add_heading_styled(doc, "4.2.3 Thermal Printer", level=3)
    add_feature_table(doc, ["Feature", "Specification"], [
        ["API", "Telpo/Feitian Printer API"],
        ["Alignment", "Left, center, right text alignment"],
        ["Status Monitoring", "No paper, low battery, paper jam, overheat detection"],
        ["Receipt Types", "Account opening, transaction confirmation, balance enquiry"],
        ["Processing", "Asynchronous handler-based printing"],
    ])

    add_heading_styled(doc, "4.3 Transaction Workflows", level=2)

    add_heading_styled(doc, "4.3.1 Cash Deposit Workflow", level=3)
    add_styled_paragraph(
        doc,
        "1. Agent taps customer's NFC card on POS device\n"
        "2. System reads card UID, account number, and customer name from card blocks\n"
        "3. Agent enters deposit amount and narration\n"
        "4. System displays transaction summary for confirmation\n"
        "5. Agent enters PIN for authorization\n"
        "6. OTP is sent to customer's registered phone number\n"
        "7. Agent enters OTP received by customer\n"
        "8. Transaction is submitted to backend API (POST /api/v1/transactions/cash-deposit)\n"
        "9. On success: receipt is printed, transaction recorded locally in SQLite\n"
        "10. On failure: error message displayed, transaction can be retried",
        font_size=10, color=DARK_GRAY, space_after=8, line_spacing=1.4
    )

    add_heading_styled(doc, "4.3.2 Biometric Withdrawal Workflow", level=3)
    add_styled_paragraph(
        doc,
        "1. Agent selects 'Biometric Withdrawal' from withdrawal options\n"
        "2. Agent enters account number and withdrawal amount\n"
        "3. Customer places finger on biometric scanner\n"
        "4. Fingerprint template is captured and encoded\n"
        "5. Template is sent to backend with withdrawal request (POST /api/v1/transactions/biometric-withdraw)\n"
        "6. Backend matches template against stored fingerprints for the account\n"
        "7. On match: transaction is processed, no PIN or OTP required\n"
        "8. On success: receipt is printed, transaction recorded locally",
        font_size=10, color=DARK_GRAY, space_after=8, line_spacing=1.4
    )

    add_heading_styled(doc, "4.3.3 Client Registration & Account Opening", level=3)
    add_styled_paragraph(
        doc,
        "1. Agent navigates to Financial Services > Register Client\n"
        "2. Step 1: Enter personal information (name, DOB, marital status, address, phone, email) + capture photo\n"
        "3. Step 2: Select account type and currency\n"
        "4. Step 3: Capture 4 fingerprint templates using biometric scanner\n"
        "5. All data submitted to backend API (POST /api/v1/client/account/open) as multipart form\n"
        "   including business_unit_id, agent_id (from login session), client fields, account fields, and photo\n"
        "6. Backend creates client record, opens account, stores biometric templates\n"
        "7. Account opening receipt is printed with account details",
        font_size=10, color=DARK_GRAY, space_after=8, line_spacing=1.4
    )

    add_heading_styled(doc, "4.4 Local Database Schema", level=2)
    add_feature_table(doc, ["Table", "Key Fields", "Purpose"], [
        ["users", "username, password, idnumber, fullname, mfi, mfi_name, email, phone_number, photo", "Logged-in agent data and session"],
        ["accounts", "account_id, account_number, account_type, balance, currency, status, agent_id", "Agent's associated accounts cached locally"],
        ["client", "idnumber, params (JSON), sent_status, yearofbirth", "Locally registered clients pending sync"],
        ["transactions", "account_number, cardnumber, amount, transaction_type, refno, response_code", "Transaction history and offline cache"],
        ["parameters", "name, value", "Application configuration parameters"],
        ["t_reciepts", "reciept_type, tdate, amount, stritems, reciept_info", "Printed receipt records"],
    ])

    # --- 5. API REFERENCE ---
    add_heading_styled(doc, "5. API Reference Summary", level=1)
    add_styled_paragraph(
        doc,
        "Base Path: /api/v1\n"
        "Authentication: Laravel Sanctum tokens + X-API-Key header\n"
        "Format: JSON request/response\n"
        "Timeouts: 15-second connection, 15-second read",
        font_size=10, color=MEDIUM_BLUE, space_after=8
    )

    add_feature_table(doc, ["Method", "Endpoint", "Description"], [
        ["POST", "/api/v1/agent/login", "Agent authentication. Returns agent profile, business unit, and linked accounts."],
        ["POST", "/api/v1/agent/pin/reset", "Reset agent PIN via OTP verification."],
        ["POST", "/api/v1/client/account/open", "Open new client account. Multipart form with agent_id (required for KATO), client data, account info, and photo."],
        ["POST", "/api/v1/transactions", "Create general transaction with type, amount, and verification."],
        ["POST", "/api/v1/transactions/cash-deposit", "Process cash deposit. Parameters: businessUnitId, agentId, pin, otp, amount, accountNumber."],
        ["POST", "/api/v1/transactions/biometric-withdraw", "Process biometric withdrawal. Parameters: businessUnitId, accountNumber, biometricTemplate, amount."],
        ["POST", "/api/v1/otp/send", "Generate and send OTP via SMS to registered phone."],
        ["POST", "/api/v1/otp/forward", "Forward provided OTP code via SMS for client verification."],
        ["POST", "/api/v1/otp/forward-agent-pin-reset", "Send agent PIN reset OTP."],
        ["GET", "/api/v1/cards/uid/{uid}", "Lookup card by NFC UID. Returns card ID, account number, status."],
        ["POST", "/api/v1/cards/create", "Create card by UID and account number assignment."],
        ["POST", "/api/v1/cards/{id}/mark-written", "Mark card as written after NFC data write operation."],
        ["POST", "/api/v1/biometrics/fetch", "Retrieve stored client fingerprint templates by account number."],
    ])

    # --- 6. SECURITY FEATURES ---
    add_heading_styled(doc, "6. Security Features", level=1)

    add_feature_table(doc, ["Security Layer", "Implementation", "Details"], [
        ["Web Authentication", "Laravel Session + CSRF", "Session-based auth with cross-site request forgery protection for web portal."],
        ["API Authentication", "Sanctum + API Key", "Token-based auth for mobile API. Optional API key middleware for system integrations."],
        ["Password Security", "Laravel Hash (Bcrypt)", "All passwords and PINs hashed before storage. PINs encrypted before API transmission."],
        ["Transaction Auth", "PIN + OTP + Biometric", "Multi-factor: agent PIN, customer OTP (5-digit, 10-min expiry), fingerprint verification."],
        ["Data Isolation", "Business Unit Scoping", "All queries scoped by business_unit_id. Middleware validates user business unit access."],
        ["Access Control", "RBAC", "Granular role-based permissions at operation level per module."],
        ["Audit Trail", "Activity Logging", "All CRUD operations logged with before/after values, user ID, timestamps, batch tracking."],
        ["Session Security", "Timeout + Token Expiry", "Web: configurable session timeout. Mobile: 10-minute inactivity timeout."],
        ["Middleware Stack", "Multi-layer", "Authentication, business access validation, super admin checks, locale setting, CSRF."],
        ["Sensitive Data", "Masking + Encryption", "PINs masked in logs. Biometric templates not logged. Encrypted storage for sensitive fields."],
    ])

    # --- 7. INTEGRATION SERVICES ---
    add_heading_styled(doc, "7. Integration Services", level=1)

    add_heading_styled(doc, "7.1 Core Banking System Integration", level=2)
    add_styled_paragraph(
        doc,
        "The platform is designed to be CBS-agnostic with an extensible integration framework. Two "
        "driver types are supported: the legacy KATO driver, and a Configurable (JSON mapper) driver "
        "that connects to any REST-based core banking system without code changes.\n"
        "- Automatic agent registration on creation/update (KATO)\n"
        "- KATO agent ID tracking and error logging\n"
        "- Per-business-unit CBS configuration (company ID, API credentials, extra headers)\n"
        "- New CBS adapters can be developed without modifying existing platform modules",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "7.1.1 Configurable CBS (JSON Mapper)", level=3)
    add_styled_paragraph(
        doc,
        "Non-KATO systems use the Configurable driver. Integration is performed entirely from the "
        "Super Admin portal in two places: (1) Core Banking Systems, where the driver, base URL and "
        "authentication type are defined; and (2) the Business Unit's CBS Mappings, where internal "
        "fields are mapped to the target CBS JSON keys per transaction type. No redeployment is "
        "required to onboard a new core banking system.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_feature_table(doc, ["Step", "Where", "What you configure"], [
        ["1", "Core Banking Systems → Create", "CBS name/code, base URL, Driver = Configurable (JSON mapper), authentication type (JWT Bearer / Basic Auth / OAuth Password), request timeout."],
        ["2", "Business Unit → Transaction Types", "Assign the transaction types (e.g. Cash Deposit 210000, Cash Withdrawal 0110000). Mappings only apply to assigned types."],
        ["3", "Business Unit → Edit", "Select the configurable CBS and enter Core Banking Credentials: username/password, static bearer token, and extra headers JSON (e.g. X-Company-ID)."],
        ["4", "Business Unit → Manage CBS Mappings", "Per transaction type: HTTP method, endpoint path, request field mappings, response field mappings, success path/value, error message path."],
    ])
    add_styled_paragraph(
        doc,
        "Available source fields (from CbsContextBuilder): transaction.amount, transaction.reference, "
        "transaction.type_code; account.account_number, account.account_code, account.balance; "
        "client.member_no, client.phone, client.first_name, client.last_name, client.id_passport; "
        "agent.id, agent.username; request.pin, request.otp; business_unit.id, business_unit.name. "
        "Transforms: none, uppercase, lowercase, float, integer, string, date. Constants use a Literal "
        "source with a default value.",
        font_size=10, color=GRAY, space_after=6
    )
    add_styled_paragraph(
        doc,
        "Runtime: when a terminal posts a transaction_type_code, ApiTransactionController resolves the "
        "type for the business unit; if a CBS mapping exists, CbsGateway builds the JSON payload from "
        "the mappings and POSTs it to the external CBS. If the CBS call fails the transaction is "
        "rejected (CBS-first, identical to the KATO rule); if no mapping exists the transaction posts "
        "to the internal ledger only. Mapped response values (e.g. cbs_transaction_id) are stored in "
        "the transaction metadata.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    add_heading_styled(doc, "7.2 SMS Gateway Integration", level=2)
    add_styled_paragraph(
        doc,
        "Integration with Orange SMS API for message delivery:\n"
        "- JWT-authenticated API access\n"
        "- Configurable sender name and endpoint per business unit\n"
        "- SMS credit management and balance tracking\n"
        "- Support for transactional (OTP, alerts) and bulk messaging",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- 8. DATABASE SCHEMA ---
    add_heading_styled(doc, "8. Database Schema Overview", level=1)
    add_feature_table(doc, ["Category", "Tables", "Description"], [
        ["Identity", "users, roles, permissions, business_user", "User management, RBAC, business unit assignments"],
        ["Business", "business_units, core_banking_systems", "Multi-tenant business unit configurations"],
        ["Agents", "agents, terminals, agent_terminal", "Agent profiles, POS devices, assignment history"],
        ["Clients", "clients, client_groups, client_client_group", "Client profiles, group management"],
        ["Accounts", "accounts, card_sequences, card_settings", "Account management, card numbering configuration"],
        ["Cards", "cards", "Card lifecycle management with status tracking"],
        ["Transactions", "transactions, transaction_types", "Financial transaction records and type definitions"],
        ["Fees", "commission_configurations, commission_tiers, transaction_cost_configurations, transaction_cost_tiers", "Tiered fee and commission rule engine"],
        ["SMS", "sms_configurations, sms_messages, account_sms", "SMS gateway config, message history, credit management"],
        ["Security", "otps, api_keys, personal_access_tokens", "OTP records, API keys, Sanctum tokens"],
        ["Audit", "activity_logs", "Complete audit trail with change tracking"],
        ["Reports", "reports", "Async report generation metadata and status"],
        ["System", "failed_jobs, jobs, password_resets", "Laravel queue system, password reset tokens"],
    ])

    # --- 9. HARDWARE & SOFTWARE REQUIREMENTS ---
    add_heading_styled(doc, "9. Hardware & Software Requirements", level=1)

    add_heading_styled(doc, "9.1 Server Requirements", level=2)
    add_feature_table(doc, ["Component", "Minimum Requirement"], [
        ["Operating System", "Linux (Ubuntu 20.04+ / CentOS 7+)"],
        ["Web Server", "Apache 2.4+ or Nginx 1.18+"],
        ["PHP", "8.1 or higher with extensions: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML"],
        ["Database", "MySQL 8.0+ or PostgreSQL 13+"],
        ["RAM", "4 GB minimum, 8 GB recommended"],
        ["Storage", "50 GB minimum (expandable based on transaction volume)"],
        ["SSL", "TLS 1.2+ certificate for HTTPS"],
    ])

    add_heading_styled(doc, "9.2 POS Device Requirements", level=2)
    add_feature_table(doc, ["Component", "Specification"], [
        ["Device", "Telpo POS terminal (TPS550 or compatible)"],
        ["Operating System", "Android 4.4+ (KitKat)"],
        ["NFC", "Integrated NFC reader (ISO 14443 Type A/B) for Mifare cards"],
        ["Biometric", "Integrated fingerprint scanner (Feitian compatible)"],
        ["Printer", "Integrated thermal receipt printer"],
        ["Connectivity", "GPRS / 3G / 4G / WiFi"],
        ["Memory", "Minimum 1 GB RAM"],
        ["Storage", "Minimum 8 GB internal storage"],
    ])

    # --- 10. LOCALIZATION ---
    add_heading_styled(doc, "10. Multi-Language & Localization Support", level=1)
    add_feature_table(doc, ["Platform", "Languages", "Implementation"], [
        ["Web Portal", "English, French, Portuguese", "Laravel localization (lang/en, lang/fr, lang/pt). User preference stored in profile. Runtime switching."],
        ["Mobile App", "French, English", "Android string resources (strings.xml). SharedPreferences-based preference. Activity recreation on switch."],
        ["SMS Messages", "Multi-language", "Message templates support multiple languages based on recipient preference."],
        ["Reports", "Multi-language", "Report headers and labels localized based on user language preference."],
    ])

    filepath = os.path.join(OUTPUT_DIR, "Agency_Banking_Solution_Technical_EN.docx")
    doc.save(filepath)
    print(f"Generated: {filepath}")
    return filepath


# =============================================================================
# TECHNICAL DOCUMENT - FRENCH
# =============================================================================
def generate_technical_fr():
    doc = Document()

    for section in doc.sections:
        section.top_margin = Cm(2.5)
        section.bottom_margin = Cm(2.5)
        section.left_margin = Cm(2.5)
        section.right_margin = Cm(2.5)

    add_cover_page(
        doc,
        "[Nom du Produit]",
        "Solution d'Agency Banking pour Entreprises",
        "Proposition Technique & Architecture de la Solution",
        datetime.now().strftime("%B %Y"),
        "Les informations fournies dans ce document sont a titre informatif uniquement. "
        "Ce document contient des informations confidentielles de [Nom de l'Entreprise] et ne doit "
        "etre utilise que dans le but pour lequel il a ete fourni. Le contenu de ce document ne peut "
        "etre communique, en tout ou en partie, a un tiers sans l'approbation ecrite prealable de "
        "[Nom de l'Entreprise]. Tous les droits d'auteur de ce document appartiennent a "
        "[Nom de l'Entreprise]. La validite de cette proposition est de 90 jours a compter de la date de soumission."
    )

    add_toc_placeholder(doc, "Table des Matieres")

    # --- 1. RESUME EXECUTIF ---
    add_heading_styled(doc, "1. Resume Executif", level=1)
    add_styled_paragraph(
        doc,
        "[Nom du Produit] est une plateforme d'agency banking multi-locataire complete concue "
        "pour permettre aux institutions financieres d'etendre les services bancaires a travers "
        "des reseaux d'agents tiers. La solution comprend un portail d'administration web construit "
        "sur le framework PHP Laravel et une application Android POS optimisee pour les terminaux "
        "Telpo avec materiel integre NFC, biometrique et impression thermique.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_styled_paragraph(
        doc,
        "Ce document fournit un apercu technique detaille de l'architecture de la plateforme, "
        "des modules, des specifications API, des fonctionnalites de securite et des capacites "
        "d'integration. Il est destine aux responsables informatiques, architectes de solutions "
        "et evaluateurs techniques.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- 2. ARCHITECTURE DE LA SOLUTION ---
    add_heading_styled(doc, "2. Architecture de la Solution", level=1)

    add_heading_styled(doc, "2.1 Apercu de la Plateforme", level=2)
    add_feature_table(doc, ["Composant", "Technologie", "Details"], [
        ["Backend Web", "Laravel / PHP", "Architecture API RESTful, modele MVC, ORM Eloquent"],
        ["Base de Donnees", "MySQL / PostgreSQL", "Base de donnees relationnelle avec isolation des donnees multi-locataire"],
        ["Auth Web", "Session Laravel", "Authentification basee sur les sessions pour les utilisateurs du portail web"],
        ["Auth API", "Laravel Sanctum", "Authentification basee sur les jetons pour les appels API mobile/POS"],
        ["Auth Cle API", "Middleware Personnalise", "Authentification par cle API pour les integrations systeme"],
        ["Application Mobile", "Android (Java)", "SDK Min 19 (Android 4.4), SDK Cible 26 (Android 8.0)"],
        ["Materiel POS", "Terminal Telpo", "Lecteur NFC integre, scanner biometrique, imprimante thermique"],
        ["SDK NFC", "Feitian FTSDK", "Support des cartes Mifare Classic (M1) et Ultralight (M0)"],
        ["SDK Biometrique", "Feitian FingerPrint", "Inscription 4 doigts, correspondance de modeles, format ISO"],
        ["Integration SMS", "API SMS Orange", "Livraison SMS authentifiee JWT pour OTP et alertes"],
        ["Integration CBS", "Systeme KATO", "Integration RESTful pour l'enregistrement des agents et les transactions"],
        ["Formats d'Export", "CSV / Excel / PDF", "Generation de rapports configurable avec traitement asynchrone"],
        ["Localisation", "i18n (en, fr, pt)", "Support multilingue avec changement en temps d'execution"],
    ])

    add_heading_styled(doc, "2.2 Modele Multi-Locataire", level=2)
    add_styled_paragraph(
        doc,
        "La plateforme implemente un modele multi-locataire a base de donnees partagee et schema "
        "partage ou toutes les entites sont scopees en utilisant une cle etrangere `business_unit_id`. "
        "L'isolation des donnees est appliquee au niveau applicatif via le middleware et le scoping "
        "des requetes. Chaque unite commerciale maintient des configurations independantes pour :",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    items_fr = [
        "Types de transactions et structures tarifaires",
        "Configurations de commissions et definitions de paliers",
        "Parametres SMS et soldes de credits",
        "Parametres de cartes (prefixe, expiration, limites)",
        "Roles et permissions utilisateurs",
        "Attribution du systeme bancaire central",
        "Donnees des agents et des clients",
    ]
    for item in items_fr:
        add_bullet(doc, item)

    doc.add_paragraph()

    add_heading_styled(doc, "2.3 Architecture d'Authentification", level=2)
    add_feature_table(doc, ["Methode", "Cas d'Utilisation", "Details"], [
        ["Auth Session", "Portail Web", "Authentification basee sur les sessions Laravel avec protection CSRF. Support 'Se souvenir de moi'. Reinitialisation de mot de passe."],
        ["Jetons Sanctum", "API Mobile", "Authentification sans etat basee sur les jetons pour l'application POS. Jetons emis lors de la connexion de l'agent."],
        ["Cle API", "Integration Systeme", "Validation de cle API par middleware personnalise pour les integrations serveur a serveur."],
        ["PIN", "Auth Transaction", "Verification du PIN de l'agent pour l'autorisation des transactions. Crypte avant transmission."],
        ["OTP", "Auth Transaction", "Mots de passe a usage unique de 5 chiffres avec expiration de 10 minutes. Livres par SMS."],
        ["Biometrique", "Auth Transaction", "Correspondance de modeles d'empreintes digitales pour les retraits biometriques. Jusqu'a 4 modeles par client."],
    ])

    add_heading_styled(doc, "2.4 Controle d'Acces Base sur les Roles (RBAC)", level=2)
    add_styled_paragraph(
        doc,
        "Le systeme RBAC est implemente avec trois entites principales : Utilisateurs, Roles et "
        "Permissions. Les utilisateurs sont affectes aux unites commerciales avec des roles specifiques. "
        "Les roles contiennent des ensembles de permissions qui controlent l'acces au niveau de "
        "l'operation (creer, voir, modifier, supprimer) pour chaque module. Un indicateur de super "
        "administrateur global accorde un acces illimite a toutes les unites commerciales.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- 3. MODULES DE LA PLATEFORME WEB ---
    add_heading_styled(doc, "3. Modules de la Plateforme Web", level=1)

    add_heading_styled(doc, "3.1 Gestion de l'Organisation et des Unites Commerciales", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Creation d'Unite Commerciale", "Creer et gerer les unites commerciales avec nom, description, email, telephone, adresse, logo, numero fiscal, adresse postale et details du responsable."],
        ["2", "Configuration", "Configurer le numero d'envoi SMS, l'attribution CBS, l'identifiant KATO, la langue preferee et le statut actif/inactif par unite."],
        ["3", "Attribution des Types de Transaction", "Attribuer les types de transactions disponibles a chaque unite commerciale depuis le catalogue global."],
        ["4", "Attribution des Utilisateurs", "Ajouter des utilisateurs aux unites commerciales avec attribution de roles."],
        ["5", "Panneau Super Admin", "Super administration globale avec capacite de basculer entre les unites commerciales."],
    ])

    add_heading_styled(doc, "3.2 Gestion des Utilisateurs et des Roles", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Enregistrement Utilisateur", "Creer des utilisateurs avec details personnels, email, mot de passe. Assigner aux unites commerciales avec des roles specifiques."],
        ["2", "Gestion des Roles", "Creer des roles personnalises avec des ensembles de permissions granulaires. Roles lies aux utilisateurs au niveau de l'unite commerciale."],
        ["3", "Gestion des Permissions", "Definir les permissions par groupe (agent, client, transaction, carte, etc.) avec granularite au niveau de l'operation."],
        ["4", "Preferences Utilisateur", "Langue preferee (en/fr/pt), theme (clair/sombre), suivi de l'unite commerciale actuelle."],
        ["5", "Indicateur Super Admin", "Designation de super administrateur global avec acces illimite a toutes les unites commerciales."],
    ])

    add_heading_styled(doc, "3.3 Gestion des Agents", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Enregistrement Agent", "Capture complete du profil : prenom/nom, email, telephone, carte d'identite/passeport, photo, adresse, boite postale, nom d'utilisateur/mot de passe."],
        ["2", "Attribution de Terminal", "Attribuer/desattribuer les terminaux POS aux agents avec suivi complet de l'historique."],
        ["3", "Liaison de Comptes", "Lier les comptes de flottant et de commission aux agents pour le traitement des transactions."],
        ["4", "Gestion du PIN", "Generer les PIN initiaux par SMS. Reinitialiser le PIN via OTP. Suivi du statut de premiere utilisation du PIN."],
        ["5", "Enregistrement CBS", "Enregistrement automatique des agents aupres du systeme bancaire central KATO."],
        ["6", "Recherche et Export", "Recherche par nom, email, nom d'utilisateur, telephone. Export CSV, Excel, PDF."],
    ])

    add_heading_styled(doc, "3.4 Gestion des Clients", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Enregistrement Client", "Enregistrement complet : civilite, noms, email, identite, telephone, genre, statut matrimonial, date et lieu de naissance, nationalite, profession, adresse."],
        ["2", "Donnees Demographiques", "Type de logement, revenu mensuel, nombre de personnes a charge, localisation, coordonnees GPS, distance de l'agence."],
        ["3", "Urgence et Proche Parent", "Contact d'urgence (nom, adresse). Plus proche parent (nom complet, telephone, adresse, genre)."],
        ["4", "Inscription Biometrique", "Stocker jusqu'a 4 modeles d'empreintes digitales par client. Indicateur de statut biometrique."],
        ["5", "Groupes de Clients", "Creer et gerer les groupes de clients. Attribution en masse. Filtrage par groupe."],
        ["6", "Photos et Documents", "Telechargement et stockage de photos. Support de capture de documents."],
        ["7", "Recherche et Export", "Recherche par nom, email, telephone, identite. Export CSV, Excel, PDF."],
    ])

    add_heading_styled(doc, "3.5 Gestion des Comptes", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Types de Comptes", "Comptes clients (general), comptes de flottant agents, comptes de commission agents. Relation polymorphique avec les modeles Client ou Agent."],
        ["2", "Creation de Compte", "Generation automatique ou attribution manuelle des numeros de compte. Validation d'unicite par unite commerciale. Support multi-devises (defaut : CFA)."],
        ["3", "Proprietes du Compte", "Numero de compte, code membre, numero manuel, dates d'ouverture/fermeture, statut, solde (decimal), signatures requises, nom du gestionnaire."],
        ["4", "Cycle de Vie", "Gestion des statuts, suivi des soldes, liaison aux clients et agents. Protection contre la suppression des comptes agents requis."],
        ["5", "Recherche et Export", "Recherche par numero de compte, description, nom du proprietaire. Export CSV, Excel, PDF."],
    ])

    add_heading_styled(doc, "3.6 Gestion des Transactions", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Types de Transaction", "Types de transactions configurables par unite commerciale. Types globaux geres par le super admin."],
        ["2", "Traitement", "Cycle de vie complet : creation, verification (OTP/PIN), completion. Statuts : en attente, complete, echoue, annule."],
        ["3", "Calcul des Couts", "Montant de transaction -> calcul du cout en utilisant des regles configurables : fixe, pourcentage, echelonne."],
        ["4", "Calcul des Commissions", "Cout de transaction -> calcul de commission. Part de l'entreprise = cout - commission."],
        ["5", "Methodes de Transaction", "Transactions agent standard, retraits biometriques, depots en especes."],
        ["6", "Filtrage et Export", "Filtrer par plage de dates, agent, terminal, type, statut. Export CSV, Excel, PDF."],
    ])

    add_heading_styled(doc, "3.7 Gestion des Cartes", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Creation de Carte", "Generation automatique des numeros de carte avec prefixe de l'unite commerciale. Liaison aux comptes clients."],
        ["2", "Cycle de Vie", "Gestion des statuts : emise, activee, bloquee, ecrite, remplacee. Suivi des horodatages pour chaque changement de statut."],
        ["3", "Proprietes", "Numero de carte (unique), hash PIN, date d'expiration, limites quotidiennes de transaction/retrait."],
        ["4", "Gestion UID", "Import en masse des UID de cartes. Mappage UID aux numeros de carte. Suivi du statut d'ecriture."],
        ["5", "Parametres de Carte", "Configuration par unite commerciale : prefixe, longueur auto-increment, annees d'expiration par defaut, limites."],
        ["6", "Operations NFC", "Lecture/ecriture de donnees sur les cartes Mifare Classic (M1) et Ultralight (M0) via l'application POS."],
    ])

    add_heading_styled(doc, "3.8 Gestion des Terminaux / POS", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Enregistrement Terminal", "Enregistrer les terminaux avec numero de serie (unique), numero de carte SIM, alias, modele/marque."],
        ["2", "Attribution aux Agents", "Attribuer les terminaux aux agents avec suivi de l'historique d'attribution."],
        ["3", "Surveillance", "Voir toutes les transactions par terminal. Filtrer par plage de dates et statut."],
        ["4", "Recherche et Export", "Recherche par numero de serie, carte SIM, alias, modele. Export CSV, Excel, PDF."],
    ])

    add_heading_styled(doc, "3.9 Moteur de Frais et Commissions", level=2)
    add_styled_paragraph(
        doc,
        "Le moteur de frais et commissions calcule les couts de transaction et les commissions "
        "des agents en utilisant un pipeline de calcul en deux etapes :",
        font_size=11, color=DARK_GRAY, space_after=6
    )
    add_styled_paragraph(
        doc,
        "Etape 1 : Montant de Transaction -> Cout de Transaction (regles de configuration des couts)\n"
        "Etape 2 : Cout de Transaction -> Commission Agent (regles de configuration des commissions)\n"
        "Part de l'Entreprise = Cout de Transaction - Commission Agent",
        font_size=10, color=MEDIUM_BLUE, space_after=8
    )
    add_feature_table(doc, ["Type de Calcul", "Configuration des Couts", "Configuration des Commissions"], [
        ["Montant Fixe", "Frais fixes par transaction quel que soit le montant", "Commission fixe par transaction"],
        ["Pourcentage", "Pourcentage du montant de la transaction", "Pourcentage du cout de transaction"],
        ["Echelonne", "Montant variable base sur les tranches de montant", "Montant variable base sur les tranches de cout"],
        ["Pourcentage Echelonne", "Pourcentage variable base sur les tranches de montant", "Pourcentage variable base sur les tranches de cout"],
        ["Fixe Echelonne", "Montant fixe par palier base sur les tranches de montant", "Montant fixe par palier base sur les tranches de cout"],
    ])

    add_heading_styled(doc, "3.10 Gestion SMS", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Configuration SMS", "Super admin : configurer les identifiants API SMS Orange (ID client, secret, jeton JWT), URL du point de terminaison, nom de l'expediteur."],
        ["2", "Credits SMS", "Super admin : charger les credits SMS aux unites commerciales. Voir le solde et l'historique des transactions."],
        ["3", "Messagerie SMS", "Envoyer des SMS individuels et en masse. Suivi de l'historique SMS avec horodatages."],
        ["4", "SMS Automatises", "Notifications de PIN des agents, livraison d'OTP, alertes transactionnelles."],
        ["5", "Validation du Solde", "Valider le solde des credits SMS avant envoi. Configuration de l'expediteur par unite commerciale."],
    ])

    add_heading_styled(doc, "3.11 Module OTP", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Generation OTP", "Generation automatique de codes OTP a 5 chiffres avec expiration de 10 minutes."],
        ["2", "Livraison OTP", "Envoi de l'OTP par SMS au numero de telephone enregistre (client ou agent)."],
        ["3", "Verification OTP", "Valider le code OTP et l'expiration. Marquer comme verifie/utilise. Empecher la reutilisation."],
        ["4", "Finalites OTP", "Verification des transactions clients, verification de reinitialisation du PIN agent."],
    ])

    add_heading_styled(doc, "3.12 Module Biometrique", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Stockage des Empreintes", "Stocker jusqu'a 4 modeles d'empreintes digitales par client. Stockage crypte."],
        ["2", "Statut Biometrique", "Indicateur biometrique active/desactive par client."],
        ["3", "Verification Biometrique", "Recuperer les empreintes pour la verification des transactions. Support des retraits biometriques sans PIN/OTP."],
        ["4", "Integration API", "POST /api/v1/biometrics/fetch - Recuperer les empreintes digitales du client par numero de compte."],
    ])

    add_heading_styled(doc, "3.13 Integration Bancaire Centrale", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Configuration CBS", "Super admin : creer/gerer les configurations CBS avec nom, code, description, URL de base, statut actif/inactif."],
        ["2", "Integration KATO", "Enregistrement automatique des agents aupres du systeme KATO. Suivi des identifiants et erreurs KATO."],
        ["3", "Pilote Configurable", "Pilote mappeur JSON pour tout CBS REST. Mappages de champs par type de transaction configures dans Super Admin ; sans modification du code."],
        ["4", "CBS par Unite", "Chaque unite commerciale peut etre assignee a un CBS different, avec ses propres identifiants, en-tetes et mappages de champs."],
        ["5", "Extensibilite", "Conception agnostique au CBS. De nouveaux adaptateurs CBS peuvent etre ajoutes sans modifier les modules existants."],
    ])

    add_heading_styled(doc, "3.14 Piste d'Audit et Journalisation", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Journalisation des Activites", "Suivre toutes les operations de creation, modification, suppression par modele."],
        ["2", "Suivi des Modifications", "Stocker les valeurs avant/apres pour tous les changements de champs. Suivi UUID par lot."],
        ["3", "Vues d'Audit", "Filtrer par utilisateur, type de modele, type d'evenement, plage de dates."],
        ["4", "Export", "Exporter la piste d'audit en CSV, Excel, PDF. Acces base sur les permissions."],
    ])

    add_heading_styled(doc, "3.15 Module de Rapports", level=2)
    add_feature_table(doc, ["#", "Composant", "Description"], [
        ["1", "Types de Rapports", "Rapports agents, rapports clients, rapports de transactions, rapports de commissions, rapports de couts."],
        ["2", "Generation", "Generation asynchrone basee sur les jobs. Filtrage par plage de dates. Scoping par unite commerciale."],
        ["3", "Suivi du Statut", "Statuts : en attente, en traitement, termine, echoue. Messages d'erreur en cas d'echec."],
        ["4", "Gestion", "Voir, telecharger, reessayer, supprimer les rapports. Controle d'acces base sur les permissions."],
        ["5", "Formats d'Export", "CSV, Excel (XLSX avec feuilles formatees), PDF (formate avec affichage des filtres)."],
    ])

    # --- 4. APPLICATION MOBILE POS ---
    add_heading_styled(doc, "4. Application Mobile POS - Details Techniques", level=1)

    add_heading_styled(doc, "4.1 Architecture de l'Application", level=2)
    add_feature_table(doc, ["Composant", "Details"], [
        ["Plateforme", "Android (Java)"],
        ["SDK Min", "19 (Android 4.4 KitKat)"],
        ["SDK Cible", "26 (Android 8.0 Oreo)"],
        ["Package", "com.rhenium.bukina / com.its.telpo"],
        ["Architecture", "Basee sur les Activities avec support de Fragments"],
        ["Stockage Local", "Base de donnees SQLite (ditialservice.db)"],
        ["Communication API", "HttpURLConnection avec JSON"],
        ["SDKs Materiels", "Feitian FTSDK (NFC), FingerPrintLib (biometrique), API Telpo (imprimante)"],
        ["Support NFC", "Mifare Classic M1 (authentifie) et Mifare Ultralight M0"],
        ["Biometrique", "Feitian FingerPrintLib v1.3.1 - inscription 4 doigts, correspondance de modeles"],
        ["Impression", "API d'imprimante thermique Telpo/Feitian avec surveillance de statut"],
        ["Localisation", "Francais (defaut) et anglais avec changement en temps d'execution"],
    ])

    add_heading_styled(doc, "4.2 Integration Materielle", level=2)

    add_heading_styled(doc, "4.2.1 Operations de Cartes NFC", level=3)
    add_feature_table(doc, ["Caracteristique", "Mifare Classic M1", "Mifare Ultralight M0"], [
        ["Authentification", "Cle A requise (defaut : FF FF FF FF FF FF)", "Aucune authentification requise"],
        ["Taille de Bloc", "16 octets par bloc", "4 octets par page (4 pages = 16 octets)"],
        ["Stockage de Donnees", "Blocs 24-28", "Pages 4-15 (4 pages par champ)"],
        ["Nom du Client", "Bloc 24 (16 octets)", "Pages 4-7"],
        ["Numero de Compte", "Bloc 25 (16 octets)", "Pages 8-11"],
        ["Numero de Carte", "Bloc 26 (16 octets)", "Pages 12-15"],
        ["Nom de Famille", "Bloc 28 (16 octets)", "Pages 16-19"],
        ["Detection", "ISO 14443 Type A", "ISO 14443 Type A"],
        ["Delai d'Attente", "Interrogation de 10 secondes", "Interrogation de 10 secondes"],
    ])

    add_heading_styled(doc, "4.2.2 Scanner Biometrique", level=3)
    add_feature_table(doc, ["Caracteristique", "Specification"], [
        ["SDK", "Feitian FingerPrintLib v1.3.1"],
        ["Inscription", "4 doigts : pouce, index, majeur, annulaire"],
        ["Qualite d'Image", "Seuil minimum de 50%"],
        ["Format de Modele", "Modeles d'empreintes digitales encodes en Base64"],
        ["Stockage", "Transmis au backend via API pour stockage securise"],
        ["Verification", "Comparaison de modeles sur le backend pour les retraits biometriques"],
    ])

    add_heading_styled(doc, "4.2.3 Imprimante Thermique", level=3)
    add_feature_table(doc, ["Caracteristique", "Specification"], [
        ["API", "API Imprimante Telpo/Feitian"],
        ["Alignement", "Alignement de texte a gauche, centre, droite"],
        ["Surveillance de Statut", "Detection de papier manquant, batterie faible, bourrage papier, surchauffe"],
        ["Types de Recus", "Ouverture de compte, confirmation de transaction, consultation de solde"],
        ["Traitement", "Impression asynchrone basee sur Handler"],
    ])

    add_heading_styled(doc, "4.3 Flux de Transactions", level=2)

    add_heading_styled(doc, "4.3.1 Flux de Depot en Especes", level=3)
    add_styled_paragraph(
        doc,
        "1. L'agent presente la carte NFC du client sur le terminal POS\n"
        "2. Le systeme lit l'UID de la carte, le numero de compte et le nom du client\n"
        "3. L'agent saisit le montant du depot et la narration\n"
        "4. Le systeme affiche le resume de la transaction pour confirmation\n"
        "5. L'agent saisit son PIN pour l'autorisation\n"
        "6. Un OTP est envoye au numero de telephone enregistre du client\n"
        "7. L'agent saisit l'OTP recu par le client\n"
        "8. La transaction est soumise a l'API backend (POST /api/v1/transactions/cash-deposit)\n"
        "9. En cas de succes : le recu est imprime, la transaction est enregistree localement\n"
        "10. En cas d'echec : un message d'erreur est affiche, la transaction peut etre retentee",
        font_size=10, color=DARK_GRAY, space_after=8, line_spacing=1.4
    )

    add_heading_styled(doc, "4.3.2 Flux de Retrait Biometrique", level=3)
    add_styled_paragraph(
        doc,
        "1. L'agent selectionne 'Retrait Biometrique' dans les options de retrait\n"
        "2. L'agent saisit le numero de compte et le montant du retrait\n"
        "3. Le client place son doigt sur le scanner biometrique\n"
        "4. Le modele d'empreinte digitale est capture et encode\n"
        "5. Le modele est envoye au backend avec la demande de retrait (POST /api/v1/transactions/biometric-withdraw)\n"
        "6. Le backend compare le modele avec les empreintes stockees pour le compte\n"
        "7. En cas de correspondance : la transaction est traitee, aucun PIN ou OTP requis\n"
        "8. En cas de succes : le recu est imprime, la transaction est enregistree localement",
        font_size=10, color=DARK_GRAY, space_after=8, line_spacing=1.4
    )

    add_heading_styled(doc, "4.3.3 Enregistrement Client et Ouverture de Compte", level=3)
    add_styled_paragraph(
        doc,
        "1. L'agent navigue vers Services Financiers > Enregistrer Client\n"
        "2. Etape 1 : Saisir les informations personnelles (nom, date de naissance, statut matrimonial, adresse, telephone, email) + capturer la photo\n"
        "3. Etape 2 : Selectionner le type de compte et la devise\n"
        "4. Etape 3 : Capturer 4 modeles d'empreintes digitales avec le scanner biometrique\n"
        "5. Toutes les donnees sont soumises a l'API backend (POST /api/v1/client/account/open) en formulaire multipart\n"
        "   incluant business_unit_id, agent_id (session de connexion), champs client, compte et photo\n"
        "6. Le backend cree l'enregistrement client, ouvre le compte, stocke les modeles biometriques\n"
        "7. Le recu d'ouverture de compte est imprime avec les details du compte",
        font_size=10, color=DARK_GRAY, space_after=8, line_spacing=1.4
    )

    add_heading_styled(doc, "4.4 Schema de la Base de Donnees Locale", level=2)
    add_feature_table(doc, ["Table", "Champs Cles", "Objectif"], [
        ["users", "username, password, idnumber, fullname, mfi, mfi_name, email, phone_number, photo", "Donnees de l'agent connecte et session"],
        ["accounts", "account_id, account_number, account_type, balance, currency, status, agent_id", "Comptes associes a l'agent caches localement"],
        ["client", "idnumber, params (JSON), sent_status, yearofbirth", "Clients enregistres localement en attente de synchronisation"],
        ["transactions", "account_number, cardnumber, amount, transaction_type, refno, response_code", "Historique des transactions et cache hors ligne"],
        ["parameters", "name, value", "Parametres de configuration de l'application"],
        ["t_reciepts", "reciept_type, tdate, amount, stritems, reciept_info", "Enregistrements des recus imprimes"],
    ])

    # --- 5. REFERENCE API ---
    add_heading_styled(doc, "5. Resume de Reference API", level=1)
    add_styled_paragraph(
        doc,
        "Chemin de Base : /api/v1\n"
        "Authentification : Jetons Laravel Sanctum + En-tete X-API-Key\n"
        "Format : Requete/Reponse JSON\n"
        "Delais d'Attente : 15 secondes connexion, 15 secondes lecture",
        font_size=10, color=MEDIUM_BLUE, space_after=8
    )

    add_feature_table(doc, ["Methode", "Point de Terminaison", "Description"], [
        ["POST", "/api/v1/agent/login", "Authentification de l'agent. Retourne le profil, l'unite commerciale et les comptes lies."],
        ["POST", "/api/v1/agent/pin/reset", "Reinitialiser le PIN de l'agent via verification OTP."],
        ["POST", "/api/v1/client/account/open", "Ouvrir un nouveau compte client. Formulaire multipart avec agent_id (requis pour KATO), donnees client, info compte et photo."],
        ["POST", "/api/v1/transactions", "Creer une transaction generale avec type, montant et verification."],
        ["POST", "/api/v1/transactions/cash-deposit", "Traiter un depot en especes. Parametres : businessUnitId, agentId, pin, otp, amount, accountNumber."],
        ["POST", "/api/v1/transactions/biometric-withdraw", "Traiter un retrait biometrique. Parametres : businessUnitId, accountNumber, biometricTemplate, amount."],
        ["POST", "/api/v1/otp/send", "Generer et envoyer un OTP par SMS au telephone enregistre."],
        ["POST", "/api/v1/otp/forward", "Transmettre le code OTP fourni par SMS pour la verification client."],
        ["POST", "/api/v1/otp/forward-agent-pin-reset", "Envoyer l'OTP de reinitialisation du PIN agent."],
        ["GET", "/api/v1/cards/uid/{uid}", "Rechercher une carte par UID NFC. Retourne l'ID de carte, le numero de compte, le statut."],
        ["POST", "/api/v1/cards/create", "Creer une carte par attribution UID et numero de compte."],
        ["POST", "/api/v1/cards/{id}/mark-written", "Marquer la carte comme ecrite apres l'operation d'ecriture NFC."],
        ["POST", "/api/v1/biometrics/fetch", "Recuperer les modeles d'empreintes digitales stockes du client par numero de compte."],
    ])

    # --- 6. FONCTIONNALITES DE SECURITE ---
    add_heading_styled(doc, "6. Fonctionnalites de Securite", level=1)
    add_feature_table(doc, ["Couche de Securite", "Implementation", "Details"], [
        ["Auth Web", "Session Laravel + CSRF", "Authentification basee sur les sessions avec protection contre la falsification de requetes intersites."],
        ["Auth API", "Sanctum + Cle API", "Auth basee sur les jetons pour l'API mobile. Middleware de cle API optionnel pour les integrations."],
        ["Securite Mots de Passe", "Laravel Hash (Bcrypt)", "Tous les mots de passe et PIN sont haches avant stockage. PIN cryptes avant transmission API."],
        ["Auth Transaction", "PIN + OTP + Biometrique", "Multi-facteurs : PIN agent, OTP client (5 chiffres, expiration 10 min), verification d'empreinte."],
        ["Isolation des Donnees", "Scoping par Unite", "Toutes les requetes scopees par business_unit_id. Middleware de validation d'acces."],
        ["Controle d'Acces", "RBAC", "Permissions granulaires basees sur les roles au niveau de l'operation par module."],
        ["Piste d'Audit", "Journalisation des Activites", "Toutes les operations CRUD enregistrees avec valeurs avant/apres, ID utilisateur, horodatages."],
        ["Securite de Session", "Delai d'Expiration + Expiration de Jeton", "Web : delai configurable. Mobile : delai d'inactivite de 10 minutes."],
    ])

    # --- 7. SERVICES D'INTEGRATION ---
    add_heading_styled(doc, "7. Services d'Integration", level=1)

    add_heading_styled(doc, "7.1 Integration du Systeme Bancaire Central", level=2)
    add_styled_paragraph(
        doc,
        "La plateforme est concue pour etre agnostique au CBS avec un cadre d'integration extensible. "
        "Deux types de pilotes sont pris en charge : le pilote KATO existant, et un pilote Configurable "
        "(mappeur JSON) qui se connecte a n'importe quel systeme bancaire central REST sans modification "
        "du code.\n"
        "- Enregistrement automatique des agents a la creation/mise a jour (KATO)\n"
        "- Suivi des identifiants agents KATO et journalisation des erreurs\n"
        "- Configuration CBS par unite commerciale (identifiant entreprise, identifiants API, en-tetes supplementaires)\n"
        "- De nouveaux adaptateurs CBS peuvent etre developpes sans modifier les modules existants",
        font_size=11, color=DARK_GRAY, space_after=8
    )

    add_heading_styled(doc, "7.1.1 CBS Configurable (Mappeur JSON)", level=3)
    add_styled_paragraph(
        doc,
        "Les systemes non-KATO utilisent le pilote Configurable. L'integration s'effectue entierement "
        "depuis le portail Super Admin, a deux endroits : (1) Systemes Bancaires Centraux, ou l'on "
        "definit le pilote, l'URL de base et le type d'authentification ; et (2) les Mappages CBS de "
        "l'unite commerciale, ou les champs internes sont mappes vers les cles JSON du CBS cible par "
        "type de transaction. Aucun redeploiement n'est necessaire pour integrer un nouveau systeme.",
        font_size=11, color=DARK_GRAY, space_after=8
    )
    add_feature_table(doc, ["Etape", "Ou", "Ce que vous configurez"], [
        ["1", "Systemes Bancaires Centraux → Creer", "Nom/code du CBS, URL de base, Pilote = Configurable (mappeur JSON), type d'authentification (JWT Bearer / Basic Auth / OAuth Password), delai d'attente."],
        ["2", "Unite Commerciale → Types de Transaction", "Assigner les types de transaction (ex. Depot 210000, Retrait 0110000). Les mappages ne s'appliquent qu'aux types assignes."],
        ["3", "Unite Commerciale → Modifier", "Selectionner le CBS configurable et saisir les Identifiants Bancaires : nom d'utilisateur/mot de passe, jeton bearer statique, en-tetes JSON supplementaires (ex. X-Company-ID)."],
        ["4", "Unite Commerciale → Gerer les Mappages CBS", "Par type de transaction : methode HTTP, chemin du point de terminaison, mappages des champs de requete et de reponse, chemin/valeur de succes, chemin du message d'erreur."],
    ])
    add_styled_paragraph(
        doc,
        "Champs sources disponibles (via CbsContextBuilder) : transaction.amount, transaction.reference, "
        "transaction.type_code ; account.account_number, account.account_code, account.balance ; "
        "client.member_no, client.phone, client.first_name, client.last_name, client.id_passport ; "
        "agent.id, agent.username ; request.pin, request.otp ; business_unit.id, business_unit.name. "
        "Transformations : none, uppercase, lowercase, float, integer, string, date. Les constantes "
        "utilisent une source Litterale avec une valeur par defaut.",
        font_size=10, color=GRAY, space_after=6
    )
    add_styled_paragraph(
        doc,
        "Execution : lorsqu'un terminal envoie un transaction_type_code, ApiTransactionController "
        "resout le type pour l'unite commerciale ; si un mappage CBS existe, CbsGateway construit la "
        "charge utile JSON a partir des mappages et la POST au CBS externe. Si l'appel CBS echoue, la "
        "transaction est rejetee (CBS-first, regle identique a KATO) ; sans mappage, la transaction est "
        "enregistree uniquement dans le grand livre interne. Les valeurs de reponse mappees (ex. "
        "cbs_transaction_id) sont stockees dans les metadonnees de la transaction.",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    add_heading_styled(doc, "7.2 Integration de la Passerelle SMS", level=2)
    add_styled_paragraph(
        doc,
        "Integration avec l'API SMS Orange pour la livraison des messages :\n"
        "- Acces API authentifie JWT\n"
        "- Nom d'expediteur et point de terminaison configurables par unite commerciale\n"
        "- Gestion des credits SMS et suivi des soldes\n"
        "- Support pour la messagerie transactionnelle (OTP, alertes) et en masse",
        font_size=11, color=DARK_GRAY, space_after=12
    )

    # --- 8. SCHEMA DE BASE DE DONNEES ---
    add_heading_styled(doc, "8. Apercu du Schema de Base de Donnees", level=1)
    add_feature_table(doc, ["Categorie", "Tables", "Description"], [
        ["Identite", "users, roles, permissions, business_user", "Gestion des utilisateurs, RBAC, attributions d'unites commerciales"],
        ["Entreprise", "business_units, core_banking_systems", "Configurations des unites commerciales multi-locataires"],
        ["Agents", "agents, terminals, agent_terminal", "Profils des agents, appareils POS, historique d'attribution"],
        ["Clients", "clients, client_groups, client_client_group", "Profils des clients, gestion des groupes"],
        ["Comptes", "accounts, card_sequences, card_settings", "Gestion des comptes, configuration de la numerotation des cartes"],
        ["Cartes", "cards", "Gestion du cycle de vie des cartes avec suivi des statuts"],
        ["Transactions", "transactions, transaction_types", "Enregistrements des transactions financieres et definitions des types"],
        ["Frais", "commission_configurations, commission_tiers, transaction_cost_configurations, transaction_cost_tiers", "Moteur de regles de frais et commissions echelonnes"],
        ["SMS", "sms_configurations, sms_messages, account_sms", "Configuration de la passerelle SMS, historique des messages, gestion des credits"],
        ["Securite", "otps, api_keys, personal_access_tokens", "Enregistrements OTP, cles API, jetons Sanctum"],
        ["Audit", "activity_logs", "Piste d'audit complete avec suivi des modifications"],
        ["Rapports", "reports", "Metadonnees et statuts de generation asynchrone de rapports"],
    ])

    # --- 9. EXIGENCES MATERIELLES ET LOGICIELLES ---
    add_heading_styled(doc, "9. Exigences Materielles et Logicielles", level=1)

    add_heading_styled(doc, "9.1 Exigences Serveur", level=2)
    add_feature_table(doc, ["Composant", "Exigence Minimale"], [
        ["Systeme d'Exploitation", "Linux (Ubuntu 20.04+ / CentOS 7+)"],
        ["Serveur Web", "Apache 2.4+ ou Nginx 1.18+"],
        ["PHP", "8.1 ou superieur avec extensions : BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML"],
        ["Base de Donnees", "MySQL 8.0+ ou PostgreSQL 13+"],
        ["RAM", "4 Go minimum, 8 Go recommande"],
        ["Stockage", "50 Go minimum (extensible selon le volume de transactions)"],
        ["SSL", "Certificat TLS 1.2+ pour HTTPS"],
    ])

    add_heading_styled(doc, "9.2 Exigences de l'Appareil POS", level=2)
    add_feature_table(doc, ["Composant", "Specification"], [
        ["Appareil", "Terminal POS Telpo (TPS550 ou compatible)"],
        ["Systeme d'Exploitation", "Android 4.4+ (KitKat)"],
        ["NFC", "Lecteur NFC integre (ISO 14443 Type A/B) pour cartes Mifare"],
        ["Biometrique", "Scanner d'empreintes digitales integre (compatible Feitian)"],
        ["Imprimante", "Imprimante thermique de recus integree"],
        ["Connectivite", "GPRS / 3G / 4G / WiFi"],
        ["Memoire", "Minimum 1 Go de RAM"],
        ["Stockage", "Minimum 8 Go de stockage interne"],
    ])

    # --- 10. LOCALISATION ---
    add_heading_styled(doc, "10. Support Multilingue et Localisation", level=1)
    add_feature_table(doc, ["Plateforme", "Langues", "Implementation"], [
        ["Portail Web", "Anglais, Francais, Portugais", "Localisation Laravel (lang/en, lang/fr, lang/pt). Preference utilisateur stockee dans le profil."],
        ["Application Mobile", "Francais, Anglais", "Ressources de chaines Android (strings.xml). Preference basee sur SharedPreferences."],
        ["Messages SMS", "Multilingue", "Les modeles de messages supportent plusieurs langues selon la preference du destinataire."],
        ["Rapports", "Multilingue", "En-tetes et libelles de rapports localises selon la preference linguistique de l'utilisateur."],
    ])

    filepath = os.path.join(OUTPUT_DIR, "Agency_Banking_Solution_Technical_FR.docx")
    doc.save(filepath)
    print(f"Generated: {filepath}")
    return filepath


# =============================================================================
# MAIN
# =============================================================================
if __name__ == "__main__":
    print("Generating Agency Banking Solution Documentation...")
    print("=" * 60)

    generate_marketing_en()
    generate_marketing_fr()
    generate_technical_en()
    generate_technical_fr()

    print("=" * 60)
    print("All documents generated successfully!")
    print(f"Output directory: {OUTPUT_DIR}")
