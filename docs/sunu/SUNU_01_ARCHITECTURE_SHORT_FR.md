# SUNU Assurances Vie Burkina Faso
## Solution mobile de collecte de primes sur terminaux TPE
### Document 1 — Architecture de la solution

| Rubrique | Valeur |
|---|---|
| Document | Architecture de la solution |
| Version | 2.0 |
| Date | 17 septembre 2026 |
| Destinataire | SUNU Assurances Vie Burkina Faso, Direction Centrale des Opérations et DSI |
| Émetteur | DBS SA — Digital Business Services |
| Documents liés | Doc 2 Sécurité ; Doc 3 Guide utilisateur ; Doc 4 Plan de déploiement ; Doc 5 Formats d'import et d'échange de données |

---

## 1. Contexte métier

SUNU propose de la micro-assurance à une clientèle qui se rend rarement en agence. Ses agents vont à sa rencontre sur les marchés, dans les villages et sur les lieux de travail, font souscrire sur papier et encaissent les primes en espèces. Aujourd'hui, ces encaissements arrivent dans SUNULIFE, le logiciel métier, en retard et par saisie manuelle.

La solution équipe chaque agent d'un terminal de paiement relié à un back-office web. L'agent enregistre les nouveaux clients et encaisse les primes sur le terminal, remet un reçu imprimé sur place, et le back-office voit l'opération dès son envoi.

| Besoin | Concrètement |
|---|---|
| Souscription sur le terrain | L'agent choisit le produit, saisit les informations du client, photographie la proposition signée et les deux faces de la CNIB, et enregistre la position GPS. La première prime et le coût de police sont encaissés, avec un reçu pour chacun |
| Encaissement des primes par carte | L'agent lit la carte du client, voit ses polices actives, en choisit une, encaisse la prime et imprime le reçu |
| Plafond collecteur et versement | Chaque agent ne peut détenir qu'un montant limité d'espèces non reversées. Une fois le plafond atteint, le terminal bloque les encaissements jusqu'à ce que l'agent verse l'argent sur un compte bancaire ou à une caisse SUNU et que le back-office le confirme |
| Back-office | Tous les paiements visibles au même endroit et exportables pour SUNULIFE ; profils et droits des utilisateurs ; 15 terminaux ; 5 000 cartes et un poste de personnalisation |
| Lien avec SUNULIFE | Chargement des clients et polices existants depuis SUNULIFE, et remontée des encaissements vers SUNULIFE |
| Autres moyens de paiement et cartes | Mobile Money (Orange Money, Moov Money) et carte virtuelle sous forme de QR code |

---

## 2. Vue d'ensemble de l'architecture (niveau 1)

<!-- figure: 01-system-context-fr | Figure 1 — Contexte du système. Les traits pleins sont indispensables à la collecte terrain. Les traits pointillés sont des canaux optionnels : si l'un d'eux est indisponible, seul ce canal s'arrête et l'encaissement en espèces continue. -->

Le système repose sur deux blocs, les terminaux et le back-office, et tout le reste se raccorde au back-office.

**L'assuré** n'a affaire qu'à l'agent. Il signe la proposition, paie et présente sa carte.

**L'agent collecteur** est affecté à un terminal. Le terminal sait qui l'utilise : chaque souscription et chaque paiement portent donc le nom de l'agent, le terminal et l'heure.

**Le terminal TPE** est l'outil de travail. Il enregistre les souscriptions, encaisse les primes, lit les cartes, imprime les reçus et prend les photos. En cas de coupure réseau, l'agent peut conserver l'opération sur le terminal et l'envoyer plus tard.

**Le back-office** fait foi pour tout ce qui est encaissé : clients, polices, paiements, reçus, plafonds et versements des agents. Le personnel SUNU y accède par navigateur web, avec des droits définis par profil.

**Les systèmes externes** sont raccordés au back-office, jamais directement au terminal. SUNULIFE reste la référence pour le contrat d'assurance ; le back-office est la référence pour l'encaissement. Mobile Money, la passerelle SMS et le poste de personnalisation des cartes sont raccordés chacun de leur côté : une panne de l'un d'eux n'empêche pas un agent d'encaisser en espèces.

---

## 3. Architecture des composants (niveau 2)

Les tableaux ci-dessous présentent les composants et indiquent s'ils sont déjà en service ou encore prévus.

### 3.1 Terminal TPE

| Composant | Rôle | Pilote |
|---|---|---|
| Connexion et session | L'agent se connecte avec son propre compte ; la session se ferme après inactivité | En service |
| Liste des produits | Télécharge les produits actifs et en garde une copie pour le mode hors ligne | En service |
| Souscription | Formulaire pas à pas : produit, identité, bénéficiaire, puis trois photos et GPS | En service |
| Prise de photos | Proposition, CNIB recto, CNIB verso ; chaque photo est compressée sur le terminal | En service |
| Encaissement des primes | Coût de police, première prime ou prime récurrente, chacun avec une référence unique | En service |
| Lecture de carte | Lit la carte NFC et demande au back-office les polices actives du client | En service |
| Impression des reçus | Imprime un reçu sur l'imprimante thermique intégrée pour chaque paiement | En service |
| Plafond collecteur | Affiche le montant non reversé de l'agent et bloque l'encaissement au plafond | En service |
| Versement | L'agent déclare un dépôt bancaire ou un versement en caisse | En service |
| Sauvegarde hors ligne | Conserve une opération sur le terminal quand elle ne peut pas être envoyée | En service |
| Carte virtuelle QR | Lit et vérifie la carte QR d'un client | Prévu |
| Mobile Money | Déclenche un paiement sur le téléphone du client et attend la confirmation | Prévu |

### 3.2 Back-office

| Composant | Rôle | Pilote |
|---|---|---|
| Utilisateurs, profils, droits | Connexion web ; cinq profils back-office (agent back-office, superviseur, chargé des cartes, administrateur, informatique) plus le profil agent terrain | En service |
| Agents et terminaux | Registre des agents, registre des terminaux, affectation agent-terminal | En service |
| Catalogue produits | Produits SUNU avec visa DGTCP, catégorie, garanties et primes par défaut ; un produit se désactive, il n'est jamais supprimé | En service |
| Propositions | Liste des souscriptions reçues du terrain avec photos et GPS ; contrôle et acceptation | En service |
| Polices | Polices créées à l'acceptation d'une proposition ou importées depuis SUNULIFE | En service |
| Encaissements | Chaque paiement avec agent, terminal, référence et reçu ; export CSV pour SUNULIFE | En service |
| Plafonds et versements | Plafond par agent, montant non reversé en cours, confirmation ou rejet des versements | En service |
| Import des clients | Charge les clients et polices existants depuis un fichier CSV (Document 5) | En service |
| Cartes | Registre et statut des cartes (active, bloquée, remplacée…), rattachement d'une carte à plusieurs polices | En service |
| Journal d'audit | Enregistre qui a modifié utilisateurs, agents, terminaux, clients et cartes, et quand | En service |
| Connecteur SUNULIFE | Échange automatique des clients, polices et encaissements avec SUNULIFE | Prévu, en attente d'un accès à l'API SUNULIFE |
| Connecteur Mobile Money | Orange Money et Moov Money, en direct ou via un agrégateur | Prévu |
| Émission de cartes QR | Justificatif QR signé pour un client | Prévu |

---

## 4. Socle technique

| Couche | Technologie |
|---|---|
| Matériel terminal | TPE Android Telpo F20 : écran tactile, lecteur NFC, imprimante thermique, appareil photo, GPS, 4G |
| Application terminal | Android, en Java ; niveau d'API Android minimum 21 |
| Stockage local du terminal | SQLite |
| Application back-office | PHP 8 avec le framework Laravel 9 ; pages web générées côté serveur |
| Base de données | MySQL |
| Serveur web | Apache HTTP Server pour le pilote ; Nginx avec PHP-FPM prévu en production |
| Stockage des fichiers | Système de fichiers du serveur pour les photos des propositions et des CNIB |
| API | REST en JSON ; photos envoyées en multipart/form-data |
| Imports et exports | CSV, UTF-8 |
| Langues | Français et anglais, sur le terminal comme au back-office |
| Hébergement | Serveur virtuel Linux |

---

## 5. Application terminal

L'application du terminal est l'outil de travail complet de l'agent. Après connexion, l'écran d'accueil donne accès à quatre fonctions : faire souscrire un client, encaisser une prime, déclarer un versement et consulter l'activité du jour.

**Souscription.** L'agent choisit un produit dans la liste téléchargée à la connexion. Le formulaire enchaîne ensuite l'identité du client, les autres informations et le bénéficiaire. Viennent ensuite trois photos prises avec l'appareil du terminal : la proposition signée, puis le recto et le verso de la CNIB. Chaque photo est ramenée à 900 Ko au plus avant l'envoi, ce qui limite la durée de transfert sur une connexion mobile faible. Le terminal ajoute sa position GPS et envoie le tout en une seule requête. Si le back-office refuse la demande, l'agent voit le motif champ par champ. Sans réseau, l'agent peut enregistrer la souscription et l'envoyer plus tard.

**Encaissement d'une prime.** L'agent lit la carte du client. Le terminal demande au back-office les polices rattachées à cette carte et les affiche. L'agent choisit la police, confirme le montant, et le terminal envoie le paiement avec une référence qu'il génère lui-même. Si le même paiement est envoyé deux fois, par exemple après un délai dépassé, le back-office reconnaît la référence et ne l'enregistre pas une seconde fois. Chaque paiement donne lieu à un reçu imprimé.

**Respect du plafond.** Chaque encaissement s'ajoute au montant non reversé de l'agent. Le terminal indique où en est l'agent par rapport à son plafond. Une fois celui-ci atteint, le back-office refuse tout nouvel encaissement jusqu'à ce que l'agent déclare un versement et qu'un superviseur le confirme.

**Identification du terminal.** Chaque terminal est enregistré au back-office sous son identifiant Android. Le terminal transmet cet identifiant à chaque requête, et le back-office s'en sert pour retrouver l'agent. Un terminal non enregistré est refusé.

**Installation.** L'application pèse environ 15 Mo et s'installe directement sur le terminal. La version actuelle du pilote est la 1.1.2.

---

## 6. Application back-office

Le back-office est une application web utilisée par le personnel SUNU et l'équipe support de DBS. Il porte aussi l'API utilisée par les terminaux : les deux partagent donc la même base de données et les mêmes règles.

**Écrans principaux.** Dans le menu Collecte primes : Propositions, Polices, Encaissements, Versements, Produits et Import Phase 0. Autour, les écrans d'administration habituels pour les utilisateurs, les profils, les agents, les terminaux et les cartes.

**De la proposition à la police.** Une souscription envoyée depuis un terminal arrive sous forme de proposition, avec ses trois photos et sa position GPS. Un utilisateur du back-office compare les photos aux données saisies et l'accepte. L'acceptation crée la police, numérotée `POL-` suivi du numéro de proposition.

**Paiements.** Chaque paiement est enregistré une seule fois, avec son montant, son type (coût de police, première prime, prime récurrente), son moyen de paiement, l'agent, le terminal, la référence, le numéro de reçu et l'heure. L'écran des encaissements permet de les exporter en CSV pour les saisir dans SUNULIFE, en attendant la connexion directe.

**Plafonds et versements.** Chaque agent a un plafond et un solde d'espèces non encore reversées. Un versement déclaré sur le terminal apparaît au back-office, où un superviseur confirme le montant reçu ou rejette la déclaration. La confirmation réduit le solde et débloque le terminal.

**Clients existants.** Les clients et polices actuels de SUNU sont chargés à partir d'une extraction CSV de SUNULIFE. Le fichier est décrit dans le Document 5.

**Données principales.**

```
entité (SUNU Vie Burkina Faso)
 ├── utilisateurs ── profils ── droits
 ├── agents ── terminaux
 │      └── plafond et solde non reversé ── versements
 ├── produits
 ├── clients
 │      ├── propositions (avec photos et GPS)
 │      ├── polices
 │      └── cartes ── rattachements carte-police
 ├── encaissements de primes ── transactions financières
 └── journal d'audit
```

**Traçabilité.** Les modifications des utilisateurs, profils, agents, terminaux, clients et cartes sont inscrites au journal d'audit avec l'utilisateur, l'heure et les valeurs avant et après. Propositions, polices et paiements portent eux-mêmes leur agent, leur terminal et leurs horodatages.

---

## 7. API

Les terminaux communiquent avec le back-office par une API REST. Requêtes et réponses sont en JSON, encodé en UTF-8. Les souscriptions avec photos sont envoyées en `multipart/form-data`.

### 7.1 Points d'accès

| Méthode | Chemin | Usage |
|---|---|---|
| POST | `/api/v1/agent/login` | Connexion de l'agent |
| GET | `/api/v1/sunu/products` | Produits actifs |
| POST | `/api/v1/sunu/proposals` | Nouvelle souscription avec photos |
| GET | `/api/v1/sunu/proposals/{id}` | Détail d'une proposition |
| POST | `/api/v1/sunu/premiums` | Enregistrement d'un paiement de prime |
| GET | `/api/v1/sunu/policies/by-card/{uid}` | Polices actives d'une carte |
| GET | `/api/v1/sunu/collector/status` | Plafond et montant non reversé de l'agent |
| POST | `/api/v1/sunu/pay-ins` | Déclaration d'un versement |

Chaque appel contient `business_unit_id`. Les appels faits pour le compte d'un agent contiennent aussi `device_serial`, l'identifiant du terminal.

### 7.2 Nouvelle souscription

`POST /api/v1/sunu/proposals`

| Champ | Obligatoire | Remarques |
|---|---|---|
| `business_unit_id` | Oui | Entité SUNU |
| `device_serial` | Oui | Identifiant du terminal (`agent_id` accepté à la place) |
| `insurance_product_id` | Oui | Issu de la liste des produits |
| `last_name`, `first_names` | Oui | Nom et prénoms du client |
| `phone` | Non | |
| `proposal_number` | Non | Numéro imprimé sur la proposition papier ; généré s'il est absent |
| `latitude`, `longitude` | Non | Degrés décimaux |
| `client_reference` | Non | Identifiant unique fixé par le terminal ; un renvoi avec la même valeur renvoie le premier résultat |
| `form_data` | Non | Autres réponses du formulaire, en JSON |
| `proposal_image` | Non | Photo de la proposition signée ; JPEG, PNG ou WebP |
| `id_document_front`, `id_document_back` | Non | Photos de la CNIB ; JPEG, PNG ou WebP |

Un appel réussi renvoie HTTP 201 et la proposition créée.

### 7.3 Paiement de prime

`POST /api/v1/sunu/premiums`

| Champ | Obligatoire | Remarques |
|---|---|---|
| `business_unit_id` | Oui | |
| `device_serial` | Oui | |
| `policy_id` ou `proposal_id` | L'un des deux | Proposition pour une première prime, police sinon |
| `amount` | Oui | En FCFA |
| `collection_kind` | Oui | `policy_cost`, `first_premium` ou `recurring_premium` |
| `reference` | Recommandé | Identifiant unique fixé par le terminal ; évite les doubles enregistrements |
| `payment_instrument` | Non | `cash`, `momo` ou `bank` ; `cash` pendant le pilote |
| `reference_period`, `payment_frequency` | Non | Par exemple `2026-09`, `monthly` |
| `receipt_number`, `latitude`, `longitude` | Non | |

La réponse contient le paiement et l'état à jour du plafond de l'agent, ce qui permet au terminal de le prévenir immédiatement.

Exemple de réponse à `GET /api/v1/sunu/collector/status` (valeurs données à titre d'illustration) :

```
{
  "success": true,
  "data": {
    "agent_id": 12,
    "cap_amount": 500000,
    "warning_threshold_pct": 80,
    "currency": "XOF",
    "uncollected_amount": 415000,
    "utilisation_pct": 83,
    "is_blocked": false,
    "blocked_at": null,
    "last_pay_in_at": "2026-09-15T16:40:12+00:00"
  }
}
```

### 7.4 Erreurs

| Code HTTP | Signification |
|---|---|
| 201 | Créé |
| 404 | Entité, proposition ou carte introuvable |
| 422 | Données invalides. Le corps contient un `message` et un objet `errors` qui liste chaque champ et son problème ; également renvoyé pour un terminal non enregistré |
| 423 | Agent bloqué : un versement est nécessaire avant de reprendre les encaissements |
| 500 | Erreur serveur ; l'opération n'a pas été enregistrée et peut être renvoyée |

### 7.5 Conventions

| Élément | Format |
|---|---|
| Montants | Nombre en FCFA |
| Dates | `AAAA-MM-JJ` ; dates-heures en ISO 8601 |
| GPS | Degrés décimaux |
| Photos | JPEG, PNG ou WebP ; le terminal envoie 900 Ko au plus par photo |

Pendant le pilote, le terminal est identifié par son identifiant d'appareil enregistré. Le transport chiffré (HTTPS) et un jeton d'accès par terminal seront en place avant la production ; le Document 2 traite ce point.

---

*Fin du Document 1.*
