#!/usr/bin/env python3
"""
Produce the French diagrams from the English SVGs.

Text nodes are swapped by exact match, so the layout, grid and arrow geometry are
untouched. French strings are kept at or below the English width to protect the
boxes they sit in.
"""
import glob, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))

FR = {
 # --- 1. system context
 'Insured': 'Assuré',
 'prospect / policyholder': 'prospect / souscripteur',
 'Collection agent': 'Agent collecteur',
 'assigned to one terminal': 'affecté à un seul TPE',
 'POS TERMINAL': 'TERMINAL TPE',
 '15 units': '15 unités',
 '• subscription capture': '• saisie de souscription',
 '• premium collection': '• encaissement de prime',
 '• card read / QR scan': '• lecture carte / QR',
 '• receipt printing': '• impression du reçu',
 '• camera · GPS': '• appareil photo · GPS',
 '• offline queue': '• file hors ligne',
 'BACK-OFFICE PLATFORM': 'PLATEFORME BACK-OFFICE',
 '• insured &amp; policy repository': '• référentiel assurés et polices',
 '• collections, receipts, exports': '• encaissements, reçus, exports',
 '• collector caps &amp; pay-ins': '• plafonds et versements',
 '• profiles &amp; permissions': '• profils et habilitations',
 '• card issuance': '• émission des cartes',
 '• integration hub': '• hub d’intégration',
 'system of record for COLLECTION': 'référentiel de l’ENCAISSEMENT',
 'SUNU back office': 'Back-office SUNU',
 'users (5 profiles)': 'utilisateurs (5 profils)',
 'core business': 'logiciel métier',
 'customer &amp;': 'synchronisation',
 'policy sync': 'clients / polices',
 'Orange · Moov': 'Orange · Moov',
 'collect': 'encaisser',
 'premium': 'la prime',
 'SMS gateway': 'Passerelle',
 'receipt': 'confirmation',
 'confirmation': 'au client',
 'Card station': 'Station cartes',
 'personalisation': 'personnalisation',
 'card data': 'données',
 'pays premium': 'paie la prime',
 'operates': 'utilise',
 'queued offline': 'file hors ligne',
 'browser · HTTPS': 'navigateur · HTTPS',
 'required for field collection': 'indispensable à l’encaissement terrain',
 'non-blocking — if unavailable that channel stops, cash collection continues':
   'non bloquant — si indisponible, ce canal s’arrête, l’encaissement espèces continue',
 'System context: the POS terminal and back office carry field collection; SUNULIFE, Mobile Money, '
 'SMS and the card station are non-blocking dependencies.':
   'Contexte du système : le TPE et le back-office portent l’encaissement terrain ; SUNULIFE, '
   'Mobile Money, SMS et la station cartes sont des dépendances non bloquantes.',

 # --- 2. component map
 'POS APPLICATION  (Android)': 'APPLICATION TPE  (Android)',
 '12 components': '12 composants',
 'Login &amp; session': 'Connexion et session',
 'Home &amp; navigation': 'Accueil et navigation',
 'Proposal &amp; CNIB capture': 'Saisie proposition et CNIB',
 'Geolocation': 'Géolocalisation',
 'Card read / write (NFC)': 'Lecture / écriture carte (NFC)',
 'Receipt printing': 'Impression des reçus',
 'Offline queue &amp; sync': 'File hors ligne et synchro',
 'Agent day reports': 'Journée de l’agent',
 'QR scan &amp; verify': 'Lecture et contrôle QR',
 'Policy selection': 'Sélection de la police',
 'Mobile Money capture': 'Encaissement Mobile Money',
 'Cap &amp; pay-in screen': 'Écran plafond et versement',
 'BACK-OFFICE PLATFORM  (web + API)': 'PLATEFORME BACK-OFFICE  (web + API)',
 '21 components': '21 composants',
 'Authentication': 'Authentification',
 'Business units': 'Unités d’exploitation',
 'Roles &amp; permissions': 'Rôles et habilitations',
 'Terminal registry': 'Parc de terminaux',
 'Transaction spine': 'Socle transactionnel',
 'Audit trail': 'Piste d’audit',
 'API request logging': 'Journalisation des API',
 'SMS notifications': 'Notifications SMS',
 'Bilingual FR / EN': 'Bilingue FR / EN',
 'Collection agents': 'Agents collecteurs',
 'Insured repository': 'Référentiel des assurés',
 'Proposal (subscription)': 'Proposition (souscription)',
 'Premium collection': 'Encaissement des primes',
 'Card management': 'Gestion des cartes',
 'Reporting &amp; Excel export': 'Restitutions et export Excel',
 'Dashboards': 'Tableaux de bord',
 'Product &amp; policy model': 'Modèle produit et police',
 'Cap &amp; pay-in reconciliation': 'Plafonds et rapprochement',
 'Virtual card (QR)': 'Carte virtuelle (QR)',
 'Mobile Money gateway': 'Passerelle Mobile Money',
 'SUNULIFE integration hub': 'Hub d’intégration SUNULIFE',
 'Why the September date is credible': 'Pourquoi l’échéance de septembre tient',
 'Nothing in the teal or amber columns is being invented.':
   'Rien dans les colonnes turquoise et ambre n’est inventé.',
 'The platform already runs a voluntary-insurance':
   'La plateforme exploite déjà un module d’affiliation',
 'enrolment and periodic-contribution module whose data':
   'volontaire et de cotisations périodiques dont le modèle',
 'model matches proposal → policy → premium.':
   'correspond à proposition → police → prime.',
 'The pilot is a domain adaptation of proven code,':
   'Le pilote est une adaptation métier de code éprouvé,',
 'not new construction.': 'et non une construction nouvelle.',
 'reused as-is — 10': 'réutilisé tel quel — 10',
 'extended — 14': 'étendu — 14',
 'new build — 9': 'développement neuf — 9',
 'New build is 9 of 33 components, and it is concentrated in five places: the insurance '
 'product/policy model, collector':
   'Le neuf représente 9 composants sur 33 et se concentre en cinq points : le modèle '
   'produit/police, les plafonds et',
 'caps and pay-ins, the QR virtual card, Mobile Money and the SUNULIFE hub. Everything else is '
 'adaptation of code already in production.':
   'versements, la carte virtuelle QR, le Mobile Money et le hub SUNULIFE. Le reste est '
   'l’adaptation de code déjà en production.',
 'Component map: most of the platform is reused or extended; new build is concentrated in the '
 'insurance model, caps and pay-ins, QR, Mobile Money and the SUNULIFE hub.':
   'Cartographie des composants : l’essentiel de la plateforme est réutilisé ou étendu ; le '
   'développement neuf se concentre sur le modèle d’assurance, les plafonds et versements, '
   'le QR, le Mobile Money et le hub SUNULIFE.',

 # --- 3. SUNULIFE
 'master of customers and contracts': 'référentiel des clients et des contrats',
 'PHASE 0 — pilot, September': 'PHASE 0 — pilote, septembre',
 'CSV / XLSX extract': 'Extraction CSV / XLSX',
 'one-off + periodic': 'initiale puis périodique',
 'manual extract': 'extraction manuelle',
 'Bulk import': 'Import en masse',
 'existing customers visible': 'clients existants visibles',
 'Excel export': 'Export Excel',
 'back office keys into SUNULIFE': 'saisie back-office dans SUNULIFE',
 'no API required': 'aucune API requise',
 'PHASE 1 (read) · PHASE 2 (write)': 'PHASE 1 (lecture) · PHASE 2 (écriture)',
 'Scheduled sync workers': 'Synchronisation planifiée',
 'customer + policy delta, nightly': 'delta clients + polices, chaque nuit',
 'Outbound event queue': 'File d’événements sortants',
 'collection posted · proposal': 'encaissement · proposition',
 'PULL': 'LECTURE',
 'PUSH': 'ÉCRITURE',
 'SUNULIFE ADAPTER': 'ADAPTATEUR SUNULIFE',
 'auth strategy · field mapping · idempotency keys · circuit breaker · full request/response log':
   'authentification · mapping des champs · clés d’idempotence · coupe-circuit · journal complet',
 'Local mirror': 'Miroir local',
 'client + policy, mirrored — never authored here':
   'clients + polices, en miroir — jamais créés ici',
 'Retry on failure: 1m · 5m · 15m · 1h · 6h · 24h, then a manual review queue.':
   'Reprise sur échec : 1 min · 5 min · 15 min · 1 h · 6 h · 24 h, puis file de revue manuelle.',
 'A rejected push never reverses money already collected — it raises an exception.':
   'Un rejet n’annule jamais un encaissement déjà réalisé — il génère une anomalie.',
 'Collection continues': 'L’encaissement continue',
 'SUNULIFE downtime is not a stoppage': 'une panne SUNULIFE n’arrête rien',
 'The pilot date does not depend on the SUNULIFE API.':
   'L’échéance du pilote ne dépend pas de l’API SUNULIFE.',
 "Phase 0 meets SUNU's requirement that field agents see customers who already exist, using a "
 "periodic extract. If the API turns out":
   'La phase 0 répond à l’exigence de SUNU — que les agents voient les clients existants — au '
   'moyen d’une extraction périodique. Si l’API',
 'not to exist, the same design runs on a scheduled SFTP file exchange — only the adapter changes, '
 'nothing else in the architecture.':
   'n’existe pas, la même conception fonctionne par échange de fichiers SFTP planifié : seul '
   'l’adaptateur change, rien d’autre dans l’architecture.',
 'SUNULIFE integration: the pilot runs on a file extract and Excel export, while the API phases in '
 'afterwards without changing the rest of the design.':
   'Intégration SUNULIFE : le pilote fonctionne sur une extraction de fichier et l’export Excel, '
   'l’API arrivant ensuite sans modifier le reste de la conception.',

 # --- 4. deployment
 'HOSTED ENVIRONMENT — trust boundary': 'ENVIRONNEMENT HÉBERGÉ — périmètre de confiance',
 'POS fleet': 'Parc de TPE',
 '15 terminals · 4G': '15 terminaux · 4G',
 'SUNU office': 'Bureaux SUNU',
 'back-office users': 'utilisateurs back-office',
 'MoMo callbacks': 'Retours MoMo',
 'signed · IP allow-listed': 'signés · IP autorisées',
 'TLS TERMINATION': 'TERMINAISON TLS',
 'HTTPS only': 'HTTPS uniquement',
 'TLS 1.2+ · HSTS': 'TLS 1.2+ · HSTS',
 'certificate pinning': 'épinglage de certificat',
 'WAF': 'WAF',
 'rate limiting': 'limitation de débit',
 'per key · agent · IP': 'par clé · agent · IP',
 'Web / API': 'Web / API',
 'Nginx + PHP-FPM': 'Nginx + PHP-FPM',
 'Queue workers': 'Traitements asynchrones',
 'sync · SMS · exports': 'synchro · SMS · exports',
 'Scheduler': 'Ordonnanceur',
 'delta sync · recon · backup': 'delta · rapprochement · sauvegarde',
 'MySQL 8': 'MySQL 8',
 'no public route': 'aucune route publique',
 'Object store': 'Stockage fichiers',
 'proposal &amp; ID images': 'images proposition et CNIB',
 'reads / writes': 'lectures / écritures',
 'Backup': 'Sauvegarde',
 'daily + binlog · PITR': 'quotidienne + binlog · PITR',
 'RPO 15 min': 'RPO 15 min',
 'encrypted at rest': 'chiffré au repos',
 'Outbound, allow-listed: SUNULIFE · Mobile Money providers · SMS gateway':
   'Sortant, sur liste d’autorisation : SUNULIFE · opérateurs Mobile Money · passerelle SMS',
 'queued, retried, logged': 'mis en file, repris, journalisé',
 'Inbound': 'Entrant',
 'allow-listed:': 'autorisé :',
 'POS fleet,': 'parc de TPE,',
 'office network,': 'réseau bureaux,',
 'Pilot sizing: one application server (4 vCPU / 8 GB) and one database server (4 vCPU / 16 GB). '
 'The tier scales':
   'Dimensionnement pilote : un serveur applicatif (4 vCPU / 8 Go) et un serveur de base de '
   'données (4 vCPU / 16 Go). L’ensemble',
 'by adding application and worker nodes behind the same boundary — no architectural change.':
   's’étend en ajoutant des nœuds applicatifs derrière le même périmètre — sans changement '
   'd’architecture.',
 'Deployment topology: the hosted environment behind one TLS and rate-limiting boundary, with the '
 'database reachable only from inside it.':
   'Topologie de déploiement : l’environnement hébergé derrière un unique périmètre TLS et de '
   'limitation de débit, la base n’étant accessible que de l’intérieur.',
}

# Strings that are identical in both languages and need no entry.
SAME = {'SUNULIFE', 'Mobile Money', 'HTTPS · JSON', 'personalisation'}


def translate(svg):
    missing = []

    def sub_text(m):
        body = m.group(2)
        key = body.strip()
        if key in FR:
            return m.group(1) + FR[key] + m.group(3)
        if key and key not in SAME and not re.fullmatch(r'[\W\d·•—–\-+/().,]*', key):
            missing.append(key)
        return m.group(0)

    svg = re.sub(r'(<text[^>]*>)(.*?)(</text>)', sub_text, svg, flags=re.S)

    def sub_aria(m):
        key = m.group(1).strip()
        if key in FR:
            return 'aria-label="%s"' % FR[key]
        missing.append(key)
        return m.group(0)

    svg = re.sub(r'aria-label="(.*?)"', sub_aria, svg, flags=re.S)
    return svg, missing


if __name__ == '__main__':
    total = []
    for f in sorted(glob.glob(os.path.join(HERE, '[0-9]*.svg'))):
        if f.endswith('-fr.svg'):
            continue
        out, missing = translate(open(f, encoding='utf-8').read())
        dest = f[:-4] + '-fr.svg'
        open(dest, 'w', encoding='utf-8').write(out)
        total += missing
        print('%-34s -> %s%s' % (os.path.basename(f), os.path.basename(dest),
                                 '   MISSING: %d' % len(missing) if missing else ''))
        for m in missing:
            print('      untranslated: %r' % m)
    sys.exit(1 if total else 0)
