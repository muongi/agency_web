# SUNU Assurances Vie Burkina Faso
## Solution mobile de collecte de primes sur terminaux TPE
### Document 1 — Architecture technique globale et détaillée

| Rubrique | Valeur |
|---|---|
| Document | Spécification d'architecture technique |
| Version | 1.1 (ajout du §14 sur l'état réel du pilote ; formats d'import au Document 5) |
| Date | 17 septembre 2026 (version 1.0 : 31 août 2026) |
| Destinataire | SUNU Assurances Vie Burkina Faso — Direction Centrale des Opérations |
| Émetteur | DBS SA — Digital Business Services |
| Exigences sources | TdR « Acquisition d'une solution digitale de collecte de primes via TPE », 12 août 2026 ; courriel d'exigences SUNU du 21 août 2026 (réunion du 20 août 2026) |
| Documents liés | Doc 2 — Architecture et procédures de sécurité ; Doc 3 — Guide utilisateur ; Doc 4 — Plan de déploiement ; Doc 5 — Formats d'import et d'échange de données |
| Statut | Pour revue technique SUNU avant la réunion de direction du 1er octobre 2026 |

---

## 1. Objet et périmètre

### 1.1 Objet

Le présent document décrit l'architecture technique globale et détaillée de la solution mobile de collecte de primes destinée à SUNU Assurances Vie Burkina Faso. Il constitue l'architecture de référence sur laquelle la solution est construite, testée, auditée sur le plan de la sécurité et réceptionnée.

Il répond au point 2 du courriel d'exigences de SUNU (« un document présentant l'architecture technique globale et détaillée de la solution ») et au point 1 (« intégration avec SUNULIFE et récupération des données existantes »), traité ici comme une préoccupation architecturale de premier rang et non comme un ajout ultérieur.

La version 1.1 ajoute la section 14, qui décrit ce que le pilote en service depuis septembre 2026 met réellement en œuvre. Les sections 1 à 13 restent l'architecture cible. Les formats de fichiers et de données pour l'import des clients existants sont décrits dans le Document 5.

### 1.2 Périmètre

**Dans le périmètre**

- L'application TPE Android exécutée sur les terminaux de terrain (saisie de souscription, encaissement de primes, impression de reçus, lecture/écriture de carte, lecture de QR, initiation Mobile Money).
- La plateforme back-office web (gestion des utilisateurs et des profils, gestion des assurés et des polices, suivi des encaissements, plafonds et versements des collecteurs, restitutions et export Excel).
- La chaîne d'émission et de personnalisation des cartes (carte physique et carte virtuelle QR).
- La couche d'intégration vers SUNULIFE, les opérateurs Mobile Money et le SMS.
- L'hébergement, le réseau et la topologie de déploiement.

**Hors périmètre du présent document** (traité ailleurs)

- Les contrôles et procédures de sécurité — Document 2.
- Les procédures d'exploitation pour les utilisateurs finaux — Document 3.
- La planification, les jalons et les responsabilités — Document 4.
- Les conditions commerciales, la fourniture matérielle des terminaux, des cartes et de la station de personnalisation — couvertes par l'offre commerciale.

### 1.3 Public visé

La DSI et les Opérations de SUNU, la Direction Générale de SUNU (sections 2 et 3), l'éditeur ou l'équipe de maintenance de SUNULIFE (section 8), et l'équipe de réalisation.

---

## 2. Contexte métier — ce que le système doit faire

L'architecture découle directement des quatre besoins énoncés dans le TdR, complétés par les quatre ajouts fonctionnels demandés dans le courriel du 21 août.

| Réf. | Exigence | Origine | Conséquence architecturale |
|---|---|---|---|
| R1 | Souscrire un prospect à une police de micro-assurance et encaisser la première prime ainsi que les frais de police sur le terrain | TdR Besoin 1 | Module de saisie de souscription sur TPE ; capture de documents/photos ; géolocalisation ; deux reçus distincts |
| R2 | Photographier la proposition signée et la CNIB, transmettre au back-office avec la référence de l'agent, le produit, le numéro de proposition, le nom de l'assuré et les coordonnées GPS | TdR Besoin 1 | Chaîne de traitement des images, file hors ligne, rattachement des métadonnées, file de revue back-office |
| R3 | Émettre une carte portant l'identité de l'assuré et la liste de ses polices ; prendre en charge plusieurs polices par assuré | TdR Besoin 1 | Processus d'émission de carte ; modèle de données multi-polices ; export de personnalisation vers l'imprimante/encodeur |
| R4 | Encaisser les primes récurrentes : lire la carte → lister les polices actives → sélectionner la police → encaisser → imprimer le reçu | TdR Besoin 2 | Flux de lecture de carte sur TPE ; écran de sélection de police ; modèle de reçu ; cache local pour le fonctionnement hors ligne |
| R5 | Plafond d'encaisse par collecteur (montant non versé) ; à l'atteinte du plafond, le collecteur verse sur un compte bancaire SUNU ou à la caisse ; le back-office confirme et libère le terminal | TdR Besoin 2 | Grand livre de l'encaisse du collecteur ; moteur de plafond ; déclaration de versement + confirmation back-office ; libération du terminal |
| R6 | Tous les paiements visibles au back-office et téléchargeables en Excel pour saisie dans SUNULIFE | TdR Besoin 2 | Module de restitution des encaissements et export ; il s'agit du chemin *manuel* de repli vers SUNULIFE |
| R7 | 15 terminaux TPE, back-office avec profils et habilitations, 5 000 cartes, un dispositif de personnalisation des cartes | TdR Besoin 3 | Parc de terminaux, gestion des habilitations, gestion du stock de cartes, intégration de la station de personnalisation |
| R8 | API pour la mise à jour automatique des encaissements dans le logiciel métier | TdR Besoin 4 | Intégration SUNULIFE — voir section 8 |
| R9 | **Récupérer régulièrement les données clients à jour et les informations de polices depuis SUNULIFE via API** | Courriel §1 | L'intégration SUNULIFE est bidirectionnelle et fait partie de l'architecture de base, non de la phase 2 |
| R10 | **Carte physique** | Courriel §4 | Comme R3 |
| R11 | **Carte virtuelle sous forme de QR Code** | Courriel §4 | Justificatif QR signé, imprimable/transmissible, vérifiable hors ligne sur le TPE |
| R12 | **Paiements Mobile Money** | Courriel §4 | Abstraction de passerelle de paiement ; Orange Money / Moov Money, en direct ou via un agrégateur |
| R13 | Documentation d'architecture technique | Courriel §2 | Le présent document |
| R14 | Documentation de sécurité | Courriel §2 | Document 2 |
| R15 | Guide utilisateur par profil | Courriel §3 | Document 3 |
| R16 | Déploiement initial avant fin septembre 2026 ; mise en production d'ici novembre 2026 | Courriel §5 | Plan de déploiement et phasage — Document 4 ; justifie la posture architecturale « réutiliser d'abord » de la section 4 |

### 2.1 Pourquoi l'échéance de septembre est tenable

La solution n'est **pas construite à partir de zéro**. Il s'agit de la personnalisation d'une plateforme d'agency banking existante et déjà en production (application TPE Android + back-office web Laravel) qui met déjà en œuvre : les agents, les terminaux, l'affectation terminal-agent, les unités d'exploitation, les rôles et habilitations, les clients, les comptes, les cartes avec leur cycle d'émission et de statut, les transactions avec frais et commissions, la journalisation d'activité et d'audit, le SMS, les restitutions avec export Excel, et une couche d'intégration configurable vers les systèmes bancaires centraux.

Point déterminant : la plateforme comporte déjà un **module d'assurance sociale volontaire** (affiliation d'assurés volontaires CNSS et encaissement de cotisations périodiques) dont le modèle de données est structurellement identique à celui dont SUNU a besoin — un enregistrement d'affiliation portant l'agent, le terminal, le statut, les données d'identité, une charge utile de formulaire JSON souple et les pièces jointes, plus un enregistrement de cotisation portant le montant, la période de référence, la périodicité et un lien vers l'affiliation et la transaction financière. Le triptyque « proposition d'assurance → police → prime » de SUNU a exactement la même forme que « affiliation → adhésion → cotisation ».

Le pilote de septembre est donc une **adaptation métier de code éprouvé**, et non une construction nouvelle. La section 4 énonce explicitement, composant par composant, ce qui est réutilisé, ce qui est étendu et ce qui est neuf.

---

## 3. Architecture globale (niveau 1)

### 3.1 Vue de contexte

<!-- figure: 01-system-context-fr | Figure 1 — Contexte du système. Les liaisons pleines portent l'encaissement terrain ; les liaisons en pointillés sont non bloquantes : une panne sur l'une d'elles arrête ce canal, jamais l'encaissement des primes. -->

### 3.2 Principes architecturaux

1. **Le terrain d'abord, tolérant au hors-ligne.** Un collecteur sur un marché ou dans un village doit pouvoir travailler quand le réseau est mauvais. Toute opération de terrain écrit d'abord localement, produit immédiatement un reçu imprimé, puis se synchronise. Une perte de connectivité ne fait jamais perdre un encaissement.
2. **Le back-office est le référentiel de l'encaissement ; SUNULIFE demeure le référentiel du contrat.** La solution n'invente jamais une police. Soit elle la reçoit de SUNULIFE (R9), soit elle crée une *proposition* qui devient une police une fois confirmée par SUNULIFE. On évite ainsi deux vérités contradictoires sur le contrat d'un client.
3. **Une colonne vertébrale financière unique.** Les encaissements en espèces, par Mobile Money et identifiés par carte produisent tous le même enregistrement de `transaction`, ne différant que par l'instrument de paiement. Les restitutions, les plafonds, les commissions et la remontée vers SUNULIFE n'ont ainsi qu'un seul chemin de code.
4. **L'intégration derrière des adaptateurs.** SUNULIFE, chaque opérateur Mobile Money et la passerelle SMS se placent derrière une interface interne. Changer d'opérateur, ou voir SUNULIFE modifier son API, relève de la configuration et de l'adaptateur, non d'une refonte. La plateforme applique déjà ce schéma pour les systèmes bancaires centraux (pilote configurable + identifiants par institution + mapping des champs) ; SUNULIFE le réutilise.
5. **Tout est imputable.** Chaque souscription, encaissement, versement et émission de carte porte l'agent, le terminal, l'horodatage et — lorsque l'appareil le permet — les coordonnées GPS. C'est à la fois une exigence du TdR et le socle de la maîtrise de la fraude.
6. **Dégrader, pas arrêter.** Si SUNULIFE est injoignable, l'encaissement continue et l'export Excel du Besoin 2 du TdR reste disponible. Si le Mobile Money est en panne, l'encaissement en espèces continue. Aucune dépendance externe ne peut interrompre la collecte des primes.

### 3.3 Socle technologique

| Couche | Technologie | Justification |
|---|---|---|
| Application TPE | Android (Java), SDK min. 21 ; terminal pilote Telpo F20 (matériel Telpo / Feitian) | Correspond aux terminaux du TdR ; base de code déjà en production ; accès natif au lecteur NFC, à l'imprimante thermique, à l'appareil photo et au GPS |
| Stockage local TPE | SQLite | File hors ligne et caches locaux |
| Application back-office | PHP 8 / Laravel 9, interface Blade | Base de code déjà en production ; personnalisation rapide ; large vivier de maintenance dans la région |
| Base de données | MySQL 8 (InnoDB) | Intégrité transactionnelle, éprouvée à cette échelle |
| Style d'API | REST / JSON sur HTTPS | Simplicité, facilité de diagnostic, adaptée au TPE |
| Traitements asynchrones | Files Laravel (pilote base de données, Redis en option) | Synchronisation SUNULIFE, SMS, exports et traitement d'images hors du chemin de requête |
| Restitutions/export | Génération serveur en XLSX/CSV | Le Besoin 2 du TdR exige explicitement Excel |
| Carte | Carte sans contact/NFC, et personnalisation à piste magnétique telle que spécifiée par le TdR | Section 7 |
| Carte virtuelle | Justificatif QR signé (JWS/HMAC) | Section 7.3 |
| Hébergement | VPS/VM Linux. Pilote : Apache HTTP Server + PHP. Cible production : Nginx + PHP-FPM derrière TLS | Sections 10 et 14 |

---

## 4. Architecture des composants (niveau 2) — réutiliser, étendre, construire

C'est le cœur du plan de réalisation : il énonce honnêtement, composant par composant, ce qui existe déjà.

<!-- figure: 02-component-map-fr | Figure 2 — Cartographie des composants. Le turquoise est réutilisé sans modification, l'ambre est étendu, le rouille est du développement neuf. Le neuf représente 9 composants sur 33. -->

Légende : **[R]** réutilisé tel quel · **[E]** composant existant étendu · **[N]** développement neuf

### 4.1 Plateforme back-office

| # | Composant | Statut | Détail |
|---|---|---|---|
| B1 | Authentification et gestion de session | [R] | Connexion web, durée de session, hachage des mots de passe |
| B2 | Organisation et unités d'exploitation | [R] | Modèle multi-entités ; SUNU Vie Burkina Faso est une unité d'exploitation ; les agences sont modélisées comme des unités |
| B3 | Utilisateurs, rôles, habilitations | [R] | Modèle d'habilitations granulaire avec rôles globaux et par unité. Répond au Besoin 3 du TdR : « créer des profils pour les collecteurs et autres utilisateurs et gérer les habilitations » |
| B4 | Agents collecteurs | [E] | Référentiel des agents avec identité, photo, identifiant, justificatifs, cloisonnement par unité. Étendu avec les paramètres de plafond (R5) |
| B5 | Parc de terminaux et affectation agent-terminal | [R] | Numéro de série, SIM, alias, modèle, historique d'affectation. Couvre la gestion du parc de 15 terminaux |
| B6 | Référentiel des assurés (clients) | [E] | Entité client existante avec identité, documents, groupes. Étendue avec les identifiants SUNULIFE et les attributs propres à l'assurance |
| B7 | Catalogue de produits d'assurance | [N] | Micro-Quiétude, Micro Épargne Retraite et produits futurs : code, libellé, périodicité de prime, règles de frais de police, modèle de reçu |
| B8 | Proposition d'assurance (souscription) | [E] | Adapté du module d'affiliation volontaire existant : numéro de proposition, produit, agent, terminal, GPS, workflow de statut, photographies jointes de la proposition et de la CNIB, charge utile de formulaire JSON souple |
| B9 | Référentiel des polices (contrats) | [N] | Numéro de police, produit, assuré, statut, date d'effet, montant et périodicité de prime, référence SUNULIFE. Alimenté depuis SUNULIFE (R9) ou à la confirmation d'une proposition |
| B10 | Moteur d'encaissement des primes | [E] | Adapté du module de cotisations périodiques existant : montant, période de référence, périodicité, référence unique, lien vers la police, l'agent, le terminal et la transaction financière |
| B11 | Colonne vertébrale transactionnelle | [R] | Montant, frais, commission, quote-part, date, statut, référence unique, métadonnées JSON, imputation complète. Porte déjà la configuration des frais et commissions par transaction avec règles forfaitaires, en pourcentage ou par paliers |
| B12 | Plafond du collecteur et rapprochement des versements | [N] | Plafond par collecteur ; solde non versé courant ; déclaration de versement (dépôt bancaire ou caisse) ; confirmation back-office ; libération du terminal |
| B13 | Gestion et émission des cartes | [E] | Entité carte avec numéro, préfixe, cycle de statut (active/inactive/bloquée/expirée/remplacée/non affectée), expiration, plafonds, horodatages d'émission et d'activation, motif de blocage et chaîne de remplacement ; paramètres de carte par unité (préfixe, longueur de séquence, validité par défaut). Étendue pour le contenu multi-polices et l'export de personnalisation |
| B14 | Émission de carte virtuelle (QR) | [N] | Section 7.3 |
| B15 | Adaptateur de passerelle Mobile Money | [N] | Section 9 |
| B16 | Hub d'intégration SUNULIFE | [N] | Section 8. Construit sur le schéma de pilote de système externe configurable existant (définition du pilote + identifiants par unité + mapping des champs requête/réponse + stratégies d'authentification dont JWT bearer, basic et OAuth password) |
| B17 | Restitution des encaissements et export Excel | [E] | Génération asynchrone de rapports existante avec suivi de statut, téléchargement et reprise. Étendue avec le rapport d'encaissements propre à l'assurance exigé par le Besoin 2 du TdR |
| B18 | Tableaux de bord | [E] | Tableaux de bord par unité existants, étendus avec les indicateurs d'encaissement, l'utilisation des plafonds et la carte d'activité terrain |
| B19 | Piste d'audit / journal d'activité | [R] | Enregistre l'acteur, l'événement, l'objet, les valeurs avant/après, le regroupement par lot |
| B20 | Journalisation des requêtes API | [R] | Un middleware de journalisation dédié existe déjà pour le trafic API des transactions, des cartes et des OTP |
| B21 | Notifications SMS | [R] | Opérateur configurable, expéditeur par unité, gestion des crédits, journal des messages |
| B22 | Interface bilingue (FR/EN) | [R] | Bascule de langue à l'exécution avec traductions françaises déjà en place — indispensable au Burkina Faso |

### 4.2 Application TPE

| # | Composant | Statut | Détail |
|---|---|---|---|
| P1 | Écran de démarrage, connexion, session | [R] | Authentification de l'agent auprès du back-office ; expiration sur inactivité |
| P2 | Tableau de bord d'accueil et navigation | [E] | Reconstruit autour du menu assurance : Souscrire, Encaisser une prime, Verser, Rapports, Paramètres |
| P3 | Capture de documents et de pièce d'identité | [E] | Prise de vue de la proposition signée et de la CNIB (recto/verso), compression, file locale, envoi avec métadonnées |
| P4 | Capture de la géolocalisation | [E] | Coordonnées GPS rattachées à la souscription et à l'encaissement |
| P5 | Parcours de souscription (assistant) | [E] | Adapté de l'assistant d'affiliation multi-étapes existant |
| P6 | Lecture de carte (NFC) | [E] | Activité de lecture de carte existante ; étendue pour renvoyer la liste des polices actives de l'assuré |
| P7 | Écriture / personnalisation de carte | [E] | Activité d'écriture de carte existante ; étendue pour la charge utile multi-polices |
| P8 | Lecture de carte virtuelle QR | [N] | Lecture par appareil photo et vérification de signature hors ligne |
| P9 | Sélection de police et saisie de la prime | [N] | Écran listant les contrats actifs de l'assuré identifié |
| P10 | Choix de l'instrument de paiement | [N] | Espèces / Mobile Money |
| P11 | Encaissement Mobile Money | [N] | Initiation, interrogation du statut, gestion des expirations et des annulations |
| P12 | Impression des reçus | [E] | Intégration imprimante thermique existante ; nouveaux modèles pour les frais de police, la première prime et la prime récurrente |
| P13 | File hors ligne et synchronisation | [E] | Stockage SQLite local ; reprise avec temporisation croissante ; références uniques sans conflit |
| P14 | Indicateur de plafond et déclaration de versement | [N] | Solde non versé en temps réel, seuil d'alerte, état bloqué, soumission du versement |
| P15 | Rapports de l'agent sur l'appareil | [E] | Encaissements du jour, totaux, réimpression d'un reçu |
| P16 | Bilingue FR/EN | [R] | Déjà implémenté |
| P17 | Capture biométrique (empreinte) | [R] | Présent dans la plateforme ; **optionnel** pour SUNU, disponible si SUNU souhaite authentifier l'assuré par biométrie en lieu et place de la carte ou en complément |

### 4.3 Concentration de l'effort

Le développement neuf se concentre en cinq endroits : le modèle produit/police d'assurance (B7, B9), les plafonds et versements (B12, P14), la carte virtuelle QR (B14, P8), le Mobile Money (B15, P10–P11) et le hub SUNULIFE (B16). Tout le reste est l'adaptation de code en exploitation. Le Document 4 en ordonne la séquence.

---

## 5. Architecture des données

### 5.1 Entités principales

```
business_unit
   │
   ├── user ──── role ──── permission
   │
   ├── agent (collecteur) ─┬── agent_terminal ──── terminal
   │                       └── collector_cap / collector_balance      [neuf]
   │
   ├── client (assuré) ────┬── client_document (photo proposition, CNIB)
   │                       ├── proposal              [adapté]
   │                       ├── policy                [neuf]
   │                       └── card ────── card_policy_link           [neuf]
   │                                └── virtual_card_credential       [neuf]
   │
   ├── insurance_product        [neuf]
   │
   ├── premium_collection ─── transaction ─── transaction_type
   │        [adapté]              [réutilisé]
   │
   ├── pay_in ──── pay_in_lines  [neuf]
   │
   ├── momo_payment              [neuf]
   │
   ├── sunulife_sync_log         [neuf]
   │
   ├── report (export asynchrone)  [réutilisé]
   └── activity_log (audit)        [réutilisé]
```

### 5.2 Principales tables nouvelles ou adaptées

**`insurance_product`** — `code`, `name_fr`, `name_en`, `family` (micro-assurance / standard), `default_premium_frequency`, `policy_cost_rule`, `receipt_template`, `sunulife_product_code`, `is_active`.

**`proposal`** — `business_unit_id`, `agent_id`, `terminal_id`, `client_id`, `insurance_product_id`, `proposal_number` (issu du formulaire papier, unique par unité), `status` (saisie → transmise → en revue → acceptée → rejetée), `subscribed_at`, `latitude`, `longitude`, `form_data` (JSON), `proposal_image_path`, `id_document_front_path`, `id_document_back_path`, `sunulife_policy_number` (renseigné à l'acceptation), horodatages. *Reprend directement la table d'affiliation existante, qui porte déjà l'agent, le terminal, le statut, les données d'identité, le JSON `form_data` et les chemins de documents.*

**`policy`** — `business_unit_id`, `client_id`, `insurance_product_id`, `policy_number`, `sunulife_policy_id`, `status` (active / suspendue / en déchéance / résiliée), `effective_date`, `premium_amount`, `premium_frequency`, `next_due_date`, `outstanding_balance`, `source` (`sunulife` | `local_proposal`), `synced_at`. Unicité sur (`business_unit_id`, `policy_number`).

**`premium_collection`** — `business_unit_id`, `policy_id`, `proposal_id` (pour une première prime), `agent_id`, `terminal_id`, `transaction_id`, `amount`, `collection_kind` (`policy_cost` | `first_premium` | `recurring_premium`), `reference_period`, `payment_frequency`, `payment_instrument` (`cash` | `momo` | `bank`), `reference` (unique, générée sur l'appareil), `latitude`, `longitude`, `collected_at`, `receipt_number`, `metadata` (JSON), `sunulife_pushed_at`.

**`collector_cap`** — `agent_id`, `cap_amount`, `warning_threshold_pct`, `currency`, `is_active`.
**`collector_balance`** — `agent_id`, `uncollected_amount`, `last_pay_in_at`, `is_blocked`, `blocked_at`. Tenue de façon transactionnelle à chaque encaissement et à chaque versement confirmé.

**`pay_in`** — `agent_id`, `declared_amount`, `method` (`bank_deposit` | `cash_office`), `bank_reference`, `deposit_slip_path`, `declared_at`, `status` (`declared` | `confirmed` | `rejected`), `confirmed_by`, `confirmed_at`, `confirmed_amount`, `variance`, `notes`. La confirmation libère le terminal.

**`card_policy_link`** — `card_id`, `policy_id`, `slot_index`. Répond à l'exigence explicite du TdR selon laquelle une carte doit conserver plusieurs polices pour un même assuré.

**`virtual_card_credential`** — `client_id`, `card_id` (nullable), `credential_id`, `payload_hash`, `signature`, `key_version`, `issued_at`, `expires_at`, `revoked_at`.

**`momo_payment`** — `premium_collection_id`, `provider`, `provider_reference`, `msisdn`, `amount`, `status`, `initiated_at`, `finalised_at`, `callback_payload` (JSON), `reconciliation_status`.

**`sunulife_sync_log`** — `direction` (`pull` | `push`), `entity`, `entity_id`, `request_payload`, `response_payload`, `http_status`, `status`, `attempts`, `last_error`, `synced_at`.

### 5.3 Idempotence et génération des références

Toute opération issue du terrain porte une **référence unique générée par le client**, construite à partir du numéro de série du terminal, de l'identifiant de l'agent, d'un compteur monotone et d'un horodatage. Le back-office impose l'unicité de cette référence. Un renvoi après expiration réseau ne peut donc pas créer un double encaissement. C'est le contrôle d'intégrité des données le plus important d'un système de collecte tolérant au hors-ligne, et il est appliqué au niveau de la base de données, pas seulement dans le code applicatif.

### 5.4 Conservation des données

| Donnée | Durée | Remarque |
|---|---|---|
| Transactions financières et encaissements | 10 ans | Conservation assurantielle et comptable |
| Images de propositions et de pièces d'identité | 10 ans, archivage en stockage froid après 2 ans | Maîtrise des volumes |
| Piste d'audit | 5 ans | |
| Journaux de requêtes API | 12 mois | |
| Gabarits biométriques (si activés) | Durée de la relation + 1 an | Voir Document 2 |
| Journaux de session et journaux techniques | 90 jours | |

---

## 6. Flux fonctionnels (niveau 3)

### 6.1 Flux A — Souscription et première prime (TdR Besoin 1)

```
COLLECTEUR                   TERMINAL TPE                  BACK-OFFICE            SUNULIFE
    │                             │                             │                     │
    │ remplit la proposition      │                             │                     │
    │ le client signe             │                             │                     │
    │                             │                             │                     │
    ├─ « Nouvelle souscription » ►│                             │                     │
    │                             │ choisit le produit          │                     │
    │                             │ saisit le n° de proposition │                     │
    │                             │ saisit l'identité           │                     │
    │                             │ photographie la proposition │                     │
    │                             │ photographie la CNIB (2 f.) │                     │
    │                             │ capture le GPS              │                     │
    │                             │ ── enregistre en local ───► │                     │
    │                             │                             │                     │
    ├─ encaisse frais de police ─►│ imprime le REÇU n°1 (frais) │                     │
    ├─ encaisse la 1re prime ────►│ imprime le REÇU n°2 (prime) │                     │
    │                             │                             │                     │
    │                             │ ── envoie dès connexion ──► │ crée la proposition │
    │                             │                             │ + 2 encaissements   │
    │                             │                             │ + 2 transactions    │
    │                             │                             │                     │
    │                             │                             │ revue back-office   │
    │                             │                             │ ── pousse la prop. ►│
    │                             │                             │ ◄── n° de police ───┤
    │                             │                             │ crée la police      │
    │                             │                             │ met la carte en file│
```

**Données transmises avec la proposition, conformément au TdR :** les références de l'agent collecteur, le produit souscrit, le numéro de proposition figurant sur le document physique, les nom et prénoms de l'assuré, les coordonnées géographiques du lieu de souscription.

**Données transmises avec chaque paiement, conformément au TdR :** le nom du produit, le numéro de proposition, les références de l'agent collecteur, le montant encaissé, la date, l'heure et, si possible, les coordonnées géographiques.

Les deux reçus sont des documents distincts portant des numéros distincts, le TdR exigeant un reçu pour les frais de police et un reçu séparé pour la première prime.

### 6.2 Flux B — Encaissement d'une prime récurrente par carte (TdR Besoin 2)

```
ASSURÉ          COLLECTEUR       TERMINAL TPE              BACK-OFFICE
   │                │                  │                        │
   ├─ présente carte►│                 │                        │
   │                ├─ pose la carte ─►│ lit l'UID de la carte  │
   │                │                  ├─ recherche par UID ───►│
   │                │                  │◄── assuré + polices ───┤
   │                │                  │    actives             │
   │                │                  │                        │
   │                │◄─ liste polices ─┤  (mise en cache locale │
   │                │                  │   pour le hors-ligne)  │
   │                ├─ choisit police ►│                        │
   │                ├─ saisit montant ►│ contrôle vs prime due  │
   │                ├─ espèces ou      │                        │
   │                │   Mobile Money   │                        │
   │                │                  │ [si MoMo → section 9]  │
   │                │                  │                        │
   │◄─── REÇU ──────┼──────────────────┤ imprime le reçu        │
   │                │                  ├─ poste l'encaissement ►│ encaissement +
   │                │                  │                        │ transaction
   │                │                  │                        │ solde collecteur +
   │                │                  │◄─ état du plafond ─────┤ contrôle plafond
```

Si le terminal est hors ligne, la liste des polices est servie depuis le cache local (rafraîchi à chaque synchronisation réussie), le reçu est imprimé immédiatement et l'encaissement est mis en file. L'assuré n'est jamais éconduit à cause du réseau.

### 6.3 Flux C — Plafond du collecteur et versement (TdR Besoin 2)

```
À chaque encaissement : collector_balance.uncollected_amount += montant

  non versé < seuil d'alerte          → fonctionnement normal
  alerte ≤ non versé < plafond        → bandeau d'avertissement sur tous les écrans
  non versé ≥ plafond                 → le TPE BLOQUE LES NOUVEAUX ENCAISSEMENTS
                                         (la saisie de souscription reste permise)

COLLECTEUR                    TPE               BACK-OFFICE            SUPERVISEUR
    │                          │                     │                      │
    ├─ dépose en banque OU ────┼─────────────────────┼──────────────────────┤
    │  remet les espèces à la caisse                 │                      │
    ├─ déclare le versement ──►│ montant, mode,      │                      │
    │                          │ réf. bancaire, photo│                      │
    │                          │ du bordereau ──────►│ pay_in (déclaré)     │
    │                          │                     ├─ notifie ───────────►│
    │                          │                     │                      │
    │                          │                     │◄── confirme montant ─┤
    │                          │                     │  (ou rejette avec    │
    │                          │                     │   écart consigné)    │
    │                          │                     │ solde -= confirmé    │
    │                          │◄── terminal libéré ─┤ is_blocked = false   │
    ├─ reprend la collecte ───►│                     │                      │
```

La confirmation est une **action contrôlée** : elle est soumise à habilitation, intégralement auditée, et peut être configurée pour exiger une validation à deux personnes (maker-checker) au-delà d'un montant paramétrable. Voir le Document 2, section sur la séparation des tâches.

### 6.4 Flux D — Émission de carte (TdR Besoins 1 et 3)

```
BACK-OFFICE (proposition acceptée)
   │
   ├─ crée la police
   ├─ affecte une carte du stock (statut : non affectée → inactive)
   ├─ rattache la ou les polices à la carte
   ├─ génère le FICHIER DE PERSONNALISATION :
   │     • bloc de marque SUNU Assurances Vie
   │     • nom et prénoms de l'assuré
   │     • numéro de carte
   │     • date d'expiration
   │     • données encodées : UID carte ↔ référence assuré ↔ liste des polices
   │
   ▼
AGENT CARTES à la station de personnalisation
   ├─ imprime la carte personnalisée
   ├─ encode la carte
   ├─ marque la carte comme ÉCRITE au back-office
   ├─ remet ou expédie la carte à l'assuré
   ▼
   statut de la carte → active
```

La carte porte un **identifiant, ni monnaie ni donnée personnelle sensible**. La liste des polices faisant foi réside toujours au back-office ; ce qui est écrit sur la carte permet à un terminal d'identifier rapidement l'assuré et de travailler hors ligne. Justification et analyse de la menace au Document 2, section 7.

### 6.5 Flux E — Synchronisation SUNULIFE

Voir la section 8.

---

## 7. Architecture des cartes (physique et virtuelle)

### 7.1 Carte physique

- **Support :** carte aux couleurs de SUNU, personnalisée avec les informations de SUNU Assurances Vie et les nom et prénoms de l'assuré, conformément au TdR.
- **Numérotation :** le back-office génère le numéro de carte à partir d'un préfixe par unité et d'une séquence de longueur paramétrable ; l'expiration découle de la validité par défaut configurée (mécanisme de paramétrage des cartes existant).
- **Données écrites :** numéro de série/UID de la carte, référence de l'assuré et liste des emplacements de polices. Le support multi-polices est explicite dans le TdR (« si l'assuré a plusieurs polices, nous souhaitons que la carte puisse conserver cette information »).
- **Cycle de vie :** non affectée → inactive → active → (bloquée | expirée | remplacée). Le blocage consigne un motif et un horodatage ; le remplacement consigne la chaîne vers la nouvelle carte. Déjà implémenté.
- **Stock :** les 5 000 cartes sont gérées en stock au back-office ; chaque carte passe de « non affectée » à « affectée » lorsqu'elle est rattachée à un assuré.

### 7.2 Choix de la technologie de carte (point ouvert OP-3)

Le TdR mentionne une « carte magnétique ». Deux options se présentent, et nous recommandons la seconde :

| | Piste magnétique | Sans contact / NFC |
|---|---|---|
| Coût par carte | Plus faible | Légèrement supérieur |
| Résistance aux conditions de terrain | Faible — la piste s'use et se démagnétise | Bonne |
| Stockage multi-polices sur la carte | Très limité (environ 100 caractères exploitables) | Confortable |
| Sécurité | Clonable sans difficulté, aucune authentification | Prend en charge l'authentification mutuelle et des clés par carte |
| Compatibilité TPE sur les terminaux visés | Oui | Oui — les terminaux intègrent déjà un lecteur NFC dans la solution existante |
| Recommandation | | **Préférée** |

**Recommandation :** cartes sans contact. La piste magnétique ne peut pas porter une charge utile multi-polices significative et n'offre aucune protection contre le clonage. Si SUNU préfère des cartes hybrides (imprimées + piste + puce), l'architecture le permet ; le contenu encodé et le fichier de personnalisation sont identiques.

### 7.3 Carte virtuelle — QR Code (courriel §4)

Une carte virtuelle est un **justificatif signé**, non la photographie d'un numéro.

```
Charge utile QR (compacte, base64url) :
{
  "v":   1,                       version du schéma
  "cid": "SUNU-BF-00012345",      identifiant du justificatif
  "ins": "CLI-000987",            référence de l'assuré
  "nm":  "OUEDRAOGO Awa",         nom pour confirmation à l'écran
  "pol": ["P-2026-0043","P-2026-0044"],   références de polices
  "iat": 1756598400,              émis le
  "exp": 1788134400,              expiration
  "kid": "sunu-2026-k1"           version de la clé de signature
}
+ signature détachée (HMAC-SHA256 ou ECDSA P-256)
```

Propriétés :

1. **Vérifiable hors ligne.** Le TPE détient la clé publique (ou la clé HMAC partagée dans son coffre) et valide la signature sans réseau. Un QR contrefait est rejeté sur l'appareil.
2. **Révocable.** Une liste de révocation est poussée vers les terminaux à chaque synchronisation. La révocation est immédiate au back-office et effective sur le terminal à sa prochaine synchronisation.
3. **Limitée dans le temps.** Les justificatifs expirent et sont réémis, ce qui borne la fenêtre d'exploitation d'un QR divulgué.
4. **Non monétaire.** Le QR identifie l'assuré ; il n'autorise rien. Le paiement exige toujours la session authentifiée du collecteur et, si SUNU l'active, le code ou la biométrie de l'assuré.
5. **Diffusion.** Imprimé sur les conditions particulières, envoyé par lien SMS, ou affiché dans une future application destinée aux assurés. Aucun matériel requis — c'est ce qui en fait le canal économique pour un assuré ayant perdu sa carte ou vivant loin d'un point de personnalisation.

La carte physique et la carte virtuelle résolvent vers la **même identité d'assuré** ; le TPE les traite comme deux lecteurs vers une seule recherche.

---

## 8. Architecture d'intégration SUNULIFE (courriel §1 — exigence prioritaire)

SUNU a indiqué qu'une part importante de ses clients existe déjà dans SUNULIFE et a demandé que la solution récupère régulièrement, via API, les données clients à jour et les informations de polices, et que cela soit pris en compte dès la conception de l'architecture. La présente section constitue la réponse architecturale et, tout autant, la **liste de ce dont nous avons besoin de la part de SUNU** pour la réaliser.

### 8.1 Principes d'intégration

1. **SUNULIFE fait foi pour l'identité de l'assuré et les contrats.** La solution reflète, elle ne possède pas.
2. **La solution fait foi pour les événements d'encaissement terrain.** SUNULIFE les reçoit.
3. **Aucun couplage bloquant.** L'indisponibilité de SUNULIFE dégrade la fraîcheur des données, jamais la disponibilité de l'encaissement.
4. **Tout échange est journalisé et rejouable.** `sunulife_sync_log` consigne la requête, la réponse, le statut et les tentatives ; tout élément en échec peut être rejoué sans dupliquer un événement financier (clés d'idempotence, section 5.3).
5. **L'export Excel reste disponible en permanence**, en tant que repli contractuel du Besoin 2 du TdR. L'automatisation supprime le travail manuel ; elle ne supprime pas l'option manuelle.

### 8.2 Surface d'intégration — ce dont nous avons besoin de SUNULIFE

| # | Sens | Capacité | Objet | Fréquence |
|---|---|---|---|---|
| S1 | Lecture | **Recherche / consultation client** par numéro national (CNIB), numéro de police, téléphone ou numéro client | Identification terrain d'un assuré existant ; éviter les doublons | À la demande + delta nocturne |
| S2 | Lecture | **Delta du référentiel client** — clients créés ou modifiés depuis un horodatage | Maintenir le miroir local à jour | Nocturne (paramétrable) |
| S3 | Lecture | **Liste des polices d'un client** — numéro, produit, statut, date d'effet, montant de prime, périodicité, prochaine échéance, solde restant dû | Alimenter la carte et l'écran de sélection de police du TPE | Delta nocturne + à la demande |
| S4 | Lecture | **Catalogue produits** — codes et libellés | Aligner les références produits | Hebdomadaire |
| S5 | Écriture | **Notification d'encaissement de prime** — numéro de police, montant, date de valeur, numéro de reçu, référence du collecteur, instrument de paiement, clé d'idempotence unique | Mise à jour automatique des encaissements (TdR Besoin 4) | Quasi temps réel, mise en file |
| S6 | Écriture | **Nouvelle souscription / proposition** — numéro de proposition, produit, identité de l'assuré, première prime, frais de police, documents | Créer le contrat dans SUNULIFE et retourner le numéro de police | À l'acceptation back-office |
| S7 | Lecture | **Accusé de réception / statut de comptabilisation** d'un encaissement transmis | Rapprochement ; preuve de la bonne prise en compte | Interrogation ou rappel (callback) |

### 8.3 Modèle de synchronisation

<!-- figure: 03-sunulife-integration-fr | Figure 3 — Intégration SUNULIFE et son phasage. La phase 0 porte le pilote de septembre au moyen d'une extraction de fichier et de l'export Excel ; l'API s'insère ensuite derrière le même adaptateur. -->

**Note de réutilisation.** La plateforme met déjà en œuvre exactement ce schéma d'adaptateur pour les systèmes bancaires centraux : une définition de système externe configurable avec URL de base, stratégie d'authentification (JWT bearer, HTTP basic, OAuth password), identifiants détenus séparément par institution, et **mapping des champs de requête et de réponse avec transformations**, par type de transaction. SUNULIFE devient un pilote dans ce cadre. C'est pourquoi S1–S7 peuvent être livrés rapidement *une fois l'API documentée* — la tuyauterie existe.

**Règles de conflit.**
- Un champ client ou police diverge entre le miroir et SUNULIFE → SUNULIFE l'emporte, la valeur précédente est consignée dans la piste d'audit.
- Un assuré créé localement est ensuite rapproché d'un client SUNULIFE (par CNIB ou téléphone) → les enregistrements sont fusionnés, la référence locale est conservée comme alias, les encaissements sont réaffectés.
- Un encaissement transmis à SUNULIFE et rejeté → l'encaissement reste valide et rapproché localement, signalé dans une file d'anomalies back-office pour traitement par l'agent back-office. Une somme déjà encaissée n'est jamais annulée par un échec d'intégration.

### 8.4 Phasage (le chemin pragmatique vers l'échéance de septembre)

| Phase | Contenu | Échéance |
|---|---|---|
| **Phase 0 — dès maintenant** | Export Excel pour saisie manuelle dans SUNULIFE, exactement comme le prévoit le Besoin 2 du TdR. **Import en masse ponctuel** du portefeuille clients et polices existant à partir d'une extraction SUNULIFE (fichier CSV, format décrit au Document 5), afin que les agents de terrain voient immédiatement les clients existants | Pilote, septembre 2026 |
| **Phase 1** | Intégration en lecture seule : S1, S3 à la demande ; S2, S4 en delta planifié | Production, novembre 2026 |
| **Phase 2** | Intégration en écriture : S5, S6, S7 | Après lancement, conformément au Besoin 4 du TdR |

La phase 0 est déterminante : elle signifie que **l'exigence de SUNU — que les agents voient les clients existants — est satisfaite dès le pilote même si l'API n'est pas encore disponible**, au moyen d'une extraction périodique. Cela retire SUNULIFE du chemin critique de l'échéance de septembre tout en honorant l'intention de l'exigence.

### 8.5 Informations requises de SUNU / de l'éditeur de SUNULIFE

Voici le questionnaire à renseigner avant ou lors de la réunion du 1er octobre 2026. Chaque ligne sans réponse constitue un risque de planning.

**Technique**
1. SUNULIFE expose-t-il une API aujourd'hui ? REST/JSON, SOAP/XML, ou uniquement base de données / fichiers ?
2. Existe-t-il une documentation d'API, un fichier WSDL/OpenAPI, ou une collection Postman ?
3. Quelle authentification est utilisée — clé d'API, OAuth 2.0, JWT, TLS mutuel, liste d'IP autorisées ?
4. SUNULIFE est-il hébergé chez SUNU, au niveau du groupe SUNU, ou chez l'éditeur ? Dans quel pays ?
5. Est-il joignable depuis notre environnement d'hébergement, ou un VPN / lien privé est-il requis ?
6. Existe-t-il des limitations de débit ou des fenêtres de maintenance ?
7. Existe-t-il une instance de test/recette, et quand l'accès peut-il être ouvert ?

**Fonctionnel**
8. Qu'est-ce qui identifie de façon unique un client dans SUNULIFE — numéro client, CNIB, ou une combinaison ?
9. Quel est le vocabulaire exact des statuts de police (active, en déchéance, suspendue, résiliée…) ?
10. Quels sont les codes produits de Micro-Quiétude et de Micro Épargne Retraite ?
11. Pour un paiement de prime, quels champs SUNULIFE exige-t-il pour comptabiliser correctement un reçu ?
12. SUNULIFE renvoie-t-il une confirmation de comptabilisation ou une référence de reçu que nous puissions conserver ?
13. Volumes approximatifs : nombre de clients, nombre de polices actives, encaissements quotidiens attendus ?

**Organisationnel**
14. Qui est le contact technique SUNULIFE (éditeur ou équipe interne) ?
15. Une demande de changement ou une autorisation contractuelle est-elle nécessaire auprès de l'éditeur pour ouvrir l'API, et quel en est le délai ?
16. Qui valide le mapping des données côté SUNU ?

**Si la réponse à la question 1 est « aucune API n'existe » :** l'architecture bascule sur un **échange de fichiers documenté** — dépôt sécurisé planifié (SFTP) des extractions en entrée et des fichiers d'encaissement en sortie, dans un format convenu avec SUNU. Ce mode est pleinement pris en charge et ne modifie aucune autre partie de la conception ; seul l'adaptateur change.

---

## 9. Architecture Mobile Money (courriel §4)

### 9.1 Conception indépendante de l'opérateur

La solution ne code en dur aucun opérateur Mobile Money. Une interface interne unique est mise en œuvre par un adaptateur par opérateur :

```
   TPE  ──►  BACK-OFFICE  ──►  PaymentGateway (interface)
                                    │
              ┌─────────────────────┼─────────────────────┐
              ▼                     ▼                     ▼
       OrangeMoneyAdapter    MoovMoneyAdapter     AggregatorAdapter
                                                  (un seul contrat,
                                                   les deux opérateurs)
```

Opérations : `initiateCollection`, `checkStatus`, `handleCallback`, `reconcile`, `refund` (lorsque l'opérateur le permet).

### 9.2 Flux d'encaissement

```
Le collecteur choisit « Mobile Money » → saisit le numéro (MSISDN) de l'assuré
   │
   ├─► le back-office crée momo_payment (statut : initié) + clé d'idempotence
   ├─► l'adaptateur appelle l'API d'encaissement de l'opérateur
   │
   ├─► l'assuré reçoit une invite USSD/push et saisit son code sur SON propre téléphone
   │
   ├─► rappel de l'opérateur ──► back-office (signature vérifiée, IP source contrôlée)
   │       OU interrogation du statut si aucun rappel n'est disponible
   │
   ├─ SUCCÈS  → premium_collection + transaction créés, reçu imprimé,
   │            solde du collecteur NON augmenté (l'argent va à SUNU, pas au collecteur)
   ├─ ÉCHEC   → rien de financier enregistré, le collecteur peut réessayer ou prendre les espèces
   └─ DÉLAI   → paiement laissé « en attente », résolu par le traitement de rapprochement ;
                le reçu n'est imprimé qu'après confirmation
```

**Point de conception qui compte au quotidien :** une prime Mobile Money **n'augmente pas** le solde non versé du collecteur, car celui-ci ne détient jamais ces espèces. Seuls les encaissements en espèces consomment le plafond. Le Mobile Money devient ainsi la soupape naturelle lorsqu'un collecteur approche de son plafond loin d'une banque — un bénéfice opérationnel direct pour SUNU.

### 9.3 Direct ou agrégateur

| Critère | Direct (Orange Money + Moov Money) | Agrégateur |
|---|---|---|
| Intégrations à réaliser | 2 | 1 |
| Contrats commerciaux | 2, chacun avec son propre délai d'enrôlement | 1 |
| Coût par transaction | Tarif opérateur uniquement | Tarif opérateur + marge de l'agrégateur |
| Règlement | Directement sur les portefeuilles/comptes opérateurs de SUNU | Via l'agrégateur, un flux de règlement unique |
| Délai jusqu'à la première transaction | Plus long — l'enrôlement opérateur est la contrainte | Plus court |
| Couverture des deux opérateurs | Oui | Oui |
| Risque de dépendance | Réparti | Concentré sur l'agrégateur |

**Recommandation au vu du calendrier :** démarrer avec un agrégateur pour sécuriser la mise en production de novembre, en conservant l'interface d'adaptateur afin qu'une migration ultérieure vers des intégrations opérateurs directes se réduise à un changement d'adaptateur et de circuit de règlement, et non à une refonte. SUNU décide ; l'architecture prend en charge l'une, l'autre, et les deux simultanément.

**Décision attendue de SUNU (OP-4) :** quelle voie, et quelle entité détient le compte marchand. Le délai d'enrôlement auprès des opérateurs est le principal risque externe pesant sur l'échéance de novembre — voir le registre des risques du Document 4.

### 9.4 Rapprochement

Un traitement quotidien compare, par opérateur : les transactions enregistrées par la plateforme, le rapport de règlement de l'opérateur et les montants crédités sur le compte de SUNU. Les écarts sont remontés dans une file d'anomalies avec indicateur d'ancienneté. Aucun encaissement Mobile Money n'est considéré comme définitif tant qu'il n'apparaît pas dans le fichier de règlement de l'opérateur.

---

## 10. Architecture de déploiement et d'infrastructure

### 10.1 Environnements

| Environnement | Objet | Données | Accès |
|---|---|---|---|
| **Développement** | Construction | Synthétiques | Équipe de réalisation |
| **Recette / UAT** | Tests de réception SUNU, formation | Anonymisées ou synthétiques | Testeurs SUNU, formateurs, équipe de réalisation |
| **Production** | Encaissement réel | Réelles | Restreint, audité |

Un environnement de recette accessible aux testeurs de SUNU est un prérequis du pilote de fin septembre ; c'est sur cet environnement qu'est dispensée la formation du Document 3.

### 10.2 Topologie de production

<!-- figure: 04-deployment-topology-fr | Figure 4 — Topologie de déploiement. Un unique périmètre TLS et de limitation de débit en frontal, la base de données accessible uniquement depuis l'intérieur du périmètre de confiance, et chaque intégration sortante sur liste d'autorisation. -->

### 10.3 Dimensionnement pour le pilote et la première année

| Paramètre | Pilote (sept. 2026) | Année 1 |
|---|---|---|
| Terminaux | 15 | 50–100 |
| Collecteurs | ~15 | ~100 |
| Utilisateurs back-office | 5–10 | 20–30 |
| Encaissements/jour | 300–1 500 | jusqu'à 10 000 |
| Cartes | 5 000 | 20 000+ |
| Images stockées/jour | ~50 souscriptions × 3 images | ~500 × 3 |

Le pilote fonctionne confortablement sur un serveur applicatif unique (4 vCPU / 8 Go) avec un serveur de base de données distinct (4 vCPU / 16 Go) et environ 500 Go de stockage. La topologie ci-dessus s'étend horizontalement par ajout de nœuds applicatifs et de traitement derrière la couche TLS, sans changement d'architecture.

### 10.4 Lieu d'hébergement (point ouvert OP-5)

Décision attendue de SUNU : hébergement au Burkina Faso, ailleurs dans la zone UEMOA, ou en Europe. Ce choix influe sur la latence pour le parc de TPE, les obligations de résidence des données, le chemin réseau vers SUNULIFE et la disposition des opérateurs Mobile Money à autoriser l'environnement. Analyse au Document 2, section sur la résidence des données.

### 10.5 Disponibilité, sauvegarde et continuité

| Objectif | Valeur |
|---|---|
| Disponibilité du service (back-office) | 99,5 % mensuel, hors maintenance annoncée |
| Disponibilité terrain (encaissement possible) | ~100 % — le mode hors ligne rend l'encaissement indépendant du back-office |
| RPO (perte de données maximale) | 15 minutes sur la base de données (expédition des binlogs) ; 0 pour les encaissements terrain, conservés sur l'appareil jusqu'à acquittement |
| RTO (délai maximal de rétablissement) | 4 heures |
| Sauvegardes | Complète quotidienne + binlog continu ; chiffrées ; conservées 30 jours ; archive mensuelle conservée 12 mois ; **test de restauration chaque trimestre** |

---

## 11. Exigences non fonctionnelles

| Domaine | Exigence |
|---|---|
| **Fonctionnement hors ligne** | Saisie complète de souscription et encaissement de prime sans réseau ; synchronisation automatique à la reconnexion ; aucune perte de données ; aucun double enregistrement |
| **Performance** | Transition d'écran TPE < 1 s ; lecture de carte → affichage de la liste des polices < 2 s en ligne, < 500 ms depuis le cache ; pages de liste back-office < 2 s à 100 000 encaissements ; export Excel d'un mois généré de façon asynchrone et téléchargeable |
| **Langues** | Français (principal) et anglais sur le TPE et au back-office |
| **Devise** | XOF, sans décimales à l'affichage ; montants stockés avec 2 décimales |
| **Ergonomie de terrain** | Grandes zones tactiles, fort contraste, lisible en plein soleil, tolérant à une faible littératie (icônes + libellés français) |
| **Autonomie** | Une journée complète d'encaissement sur une charge en usage normal |
| **Impression** | Toute opération financière produit un reçu imprimé ; tout reçu peut être réimprimé dans la journée avec une mention « DUPLICATA » explicite |
| **Auditabilité** | Toute création/modification/suppression sur une entité sensible est consignée avec acteur, horodatage et valeurs avant/après |
| **Maintenabilité** | Configuration plutôt que code : produits, plafonds, frais, modèles de reçus, mappings de champs et habilitations sont paramétrables par un administrateur sans livraison logicielle |

---

## 12. Hypothèses, points ouverts et décisions attendues

### 12.1 Hypothèses

| # | Hypothèse |
|---|---|
| A1 | Les terminaux TPE sont des appareils Android de la famille Telpo/Feitian déjà pris en charge par l'application existante (lecteur NFC, imprimante thermique, appareil photo, GPS, 4G) |
| A2 | SUNU fournit les cartes SIM et le forfait data des 15 terminaux |
| A3 | Les collecteurs sont des salariés ou des mandataires de SUNU, identifiés individuellement, chacun affecté à un terminal déterminé |
| A4 | La proposition d'assurance papier demeure le document juridique pendant le pilote ; la solution la capture et la transmet, elle ne remplace pas la signature |
| A5 | La station de personnalisation est fournie avec une interface documentée ou accepte un fichier/CSV standard en entrée pour l'impression et l'encodage |
| A6 | SUNU fournit une extraction des clients et polices existants pour l'import de phase 0 |
| A7 | Les montants sont exprimés en XOF |

### 12.2 Points ouverts appelant une décision de SUNU

| # | Point ouvert | Impact s'il reste non tranché | Échéance |
|---|---|---|---|
| **OP-1** | Existence, documentation et accès de test à l'API SUNULIFE (section 8.5) | Détermine si la phase 1 est possible pour novembre ou si le repli fichier s'applique | 15 septembre 2026 |
| **OP-2** | Fourniture de l'extraction clients/polices pour l'import de phase 0 | Les agents de terrain ne verraient pas les clients existants au pilote | 10 septembre 2026 |
| **OP-3** | Technologie de carte : piste magnétique comme au TdR, ou sans contact comme recommandé | Délai d'approvisionnement des cartes ; capacité multi-polices ; résistance au clonage | 8 septembre 2026 |
| **OP-4** | Voie Mobile Money (agrégateur ou direct) et entité détenant le compte marchand | L'enrôlement opérateur est le plus long délai externe avant novembre | 8 septembre 2026 |
| **OP-5** | Lieu d'hébergement et fournisseur de l'infrastructure | La mise à disposition des environnements conditionne la recette | 5 septembre 2026 |
| **OP-6** | Montant(s) de plafond des collecteurs, uniforme ou par collecteur | Paramétrage seul, mais nécessaire aux scénarios de recette | 15 septembre 2026 |
| **OP-7** | Liste définitive des produits du pilote et leurs règles de prime / frais de police | Paramétrage du catalogue produits | 10 septembre 2026 |
| **OP-8** | Contenu et mise en page du reçu, y compris les mentions légales exigées au Burkina Faso | Modèle de reçu ; tests d'impression | 10 septembre 2026 |
| **OP-9** | Souhait d'une identification biométrique de l'assuré (capacité existante) | Périmètre et déclaration à la CIL au Document 2 | 1er octobre 2026 |
| **OP-10** | Compte(s) bancaire(s) et caisse(s) désignés pour les versements des collecteurs | Paramétrage des versements | 15 septembre 2026 |

---

## 13. Traçabilité — des exigences à l'architecture

| Exigence | Réponse architecturale |
|---|---|
| TdR Besoin 1 — souscription + première prime | §6.1, composants P3–P5, B8, B10, B11 |
| TdR Besoin 1 — photos proposition + CNIB avec agent, produit, n° de proposition, nom, GPS | §6.1, P3, P4, B8, table `proposal` |
| TdR Besoin 1 — carte aux couleurs SUNU, nom de l'assuré, multi-polices | §6.4, §7.1, B13, table `card_policy_link` |
| TdR Besoin 2 — lecture carte → liste polices → encaisser → reçu | §6.2, P6, P9, P12, B10 |
| TdR Besoin 2 — plafond collecteur, versement, libération du terminal | §6.3, B12, P14 |
| TdR Besoin 2 — tous les paiements au back-office, export Excel | B17, §8.4 phase 0 |
| TdR Besoin 3 — 15 terminaux, profils et habilitations, 5 000 cartes, dispositif de personnalisation | B3, B5, B13, §6.4, §10.3 |
| TdR Besoin 4 — API de mise à jour du logiciel métier | §8, S5–S7 |
| Courriel §1 — récupération et synchronisation SUNULIFE | §8 en entier, S1–S4 |
| Courriel §2 — documentation d'architecture | Le présent document |
| Courriel §2 — documentation de sécurité | Document 2 |
| Courriel §3 — guide utilisateur par profil | Document 3 |
| Courriel §4 — carte physique | §7.1 |
| Courriel §4 — carte virtuelle par QR Code | §7.3 |
| Courriel §4 — Mobile Money | §9 |
| Demande complémentaire du 16 septembre 2026 — architecture réelle, formats de fichiers d'import et exemple | §14 ; Document 5 et ses fichiers CSV d'exemple |
| Courriel §5 — déploiement de septembre, production de novembre | Document 4 ; §4.3 et §8.4 expliquent comment les échéances sont protégées |

---

## 14. Mise en œuvre du pilote — état réel (17 septembre 2026)

Les sections 1 à 13 décrivent l'architecture cible. La présente section décrit ce que l'environnement pilote met réellement en œuvre aujourd'hui, afin que la DSI de SUNU puisse évaluer le système en fonctionnement autant que sa conception. Les fonctionnalités non encore réalisées sont signalées comme telles.

### 14.1 Déploiement du pilote

```
 Terminaux Telpo F20                          Serveur pilote (VPS Linux, hébergé par DBS)
 ┌────────────────────────┐                 ┌──────────────────────────────────────────┐
 │ Application TPE SUNU    │  REST / JSON    │ Apache HTTP Server + PHP 8                │
 │ com.sunu.telpo 1.1.2    │  + multipart    │ Back-office Laravel 9                     │
 │ Android, ARM 32 bits    │ ──────────────▶ │   /api/v1/sunu/*     API terminaux        │
 │ photo · GPS · NFC ·     │                 │   /business/sunu/*   back-office web      │
 │ imprimante thermique    │                 │ Base de données MySQL                     │
 └────────────────────────┘                 │ Fichiers : images propositions et CNIB    │
                                             └───────────────────────┬──────────────────┘
 Utilisateurs back-office SUNU ── navigateur web ────────────────────┘

 Extraction SUNULIFE ──▶ import CSV (clients + polices)     ──▶ back-office
 back-office         ──▶ export CSV (primes encaissées)     ──▶ saisie manuelle dans SUNULIFE
```

| Élément | Valeur pilote |
|---|---|
| Serveur | Un VPS Linux hébergé par DBS ; application, base de données et stockage des images sur le même hôte |
| Couche web et applicative | Apache HTTP Server avec PHP 8, application Laravel 9 |
| Base de données | MySQL, schéma dédié SUNU |
| Stockage des images | Système de fichiers du serveur, un dossier par proposition |
| Application terminal | `com.sunu.telpo` version 1.1.2 (build 4), ARM 32 bits, environ 15 Mo, installée par chargement direct |
| Terminaux | Telpo F20 ; chaque terminal est enregistré dans le back-office par son identifiant Android, et les requêtes d'un terminal non enregistré sont refusées |
| Transport | HTTP sur un port dédié pendant le pilote ; TLS fait partie des actions avant production (§14.6) |

### 14.2 Périmètre livré et périmètre prévu

| Fonctionnalité | Référence cible | État au pilote |
|---|---|---|
| Catalogue produits : Micro Épargne Retraite, Mixte Plus Horizon, Rente Éducation Plus, Micro Quiétude, avec visa DGTCP, catégorie, garanties et valeurs de prime par défaut ; modifiable, activé ou désactivé plutôt que supprimé | B7 | Réalisé |
| Souscription sur le terminal : choix du produit, identité, photo de la proposition signée, CNIB recto et verso, position GPS, sauvegarde hors ligne en cas d'échec d'envoi | P3–P5, B8 | Réalisé ; en test terrain |
| Revue et acceptation des propositions au back-office, création de la police `POL-{numéro de proposition}` | B8, B9 | Réalisé |
| Encaissement des primes : coût de police, première prime, prime récurrente, chacun avec une référence unique | B10 | Réalisé |
| Recherche des polices à partir de l'UID de la carte | P6, B13 | Réalisé |
| Plafond collecteur, déclaration de versement, confirmation ou rejet au back-office | B12, P14 | Réalisé |
| Liste des encaissements et export CSV pour saisie dans SUNULIFE | B17, §8.4 Phase 0 | Réalisé |
| Import des clients et polices existants depuis un fichier CSV | §8.4 Phase 0 | Réalisé ; format décrit dans le Document 5 |
| Synchronisation par API avec SUNULIFE | B16, §8 | Non démarré ; dépend de OP-1 |
| Encaissement Mobile Money | B15, §9 | Non réalisé ; le modèle de données réserve déjà `momo` comme moyen de paiement |
| Carte virtuelle QR | B14, P8 | Non réalisé |

### 14.3 API terminaux du pilote

Chemin de base `/api/v1/sunu`. Chaque appel transmet `business_unit_id` et l'identifiant du terminal ; le back-office retrouve le collecteur à partir de l'affectation du terminal.

| Méthode | Chemin | Rôle |
|---|---|---|
| POST | `/api/v1/agent/login` | Connexion du collecteur |
| GET | `/products` | Produits actifs, mis en cache sur le terminal pour le mode hors ligne |
| POST | `/proposals` | Souscription : identité, produit, GPS et trois images (multipart) |
| GET | `/proposals/{id}` | Détail d'une proposition |
| POST | `/premiums` | Encaissement de prime ; `reference` rend idempotent un envoi répété |
| GET | `/policies/by-card/{uid}` | Polices actives rattachées à une carte |
| GET | `/collector/status` | Plafond, montant non reversé, indicateur de blocage |
| POST | `/pay-ins` | Déclaration de versement du collecteur |

### 14.4 Flux de souscription tel que réalisé

```
 Terminal                                     Back-office
 1  GET  /products ─────────────────────────▶ produits actifs
 2  le collecteur saisit identité, produit,
    photo proposition, CNIB recto, CNIB verso, GPS
 3  POST /proposals (multipart) ────────────▶ contrôle des champs
                                              terminal → collecteur
                                              enregistrement des trois images
                                              création de la proposition, statut « captured »
 4  ◀──────────── JSON : proposition créée, ou HTTP 422 avec la liste des erreurs par champ
    en cas d'échec réseau : sauvegarde hors ligne sur le terminal, renvoi ultérieur

 Utilisateur back-office : Propositions → contrôle images et données → Accepter ──▶ police POL-{numéro}
```

| Champ multipart | Contenu | Enregistré dans |
|---|---|---|
| `proposal_image` | Photo de la proposition signée | `proposal_image_path` |
| `id_document_front` | CNIB, recto | `id_document_front_path` |
| `id_document_back` | CNIB, verso | `id_document_back_path` |

Les images sont compressées sur le terminal à 900 Ko au maximum chacune. Le back-office accepte les formats JPEG, PNG et WebP.

### 14.5 Formats d'échange de données

| Échange | Sens | Format |
|---|---|---|
| API terminaux | Terminal ↔ back-office | JSON, UTF-8 ; images en multipart/form-data |
| Clients et polices existants | Extraction SUNULIFE → back-office | CSV, UTF-8, séparateur virgule ; décrit dans le Document 5 |
| Primes encaissées | Back-office → SUNU | Téléchargement CSV |

### 14.6 Enseignements du pilote et actions avant production

Les tests terrain du 14 au 16 septembre 2026 ont donné lieu à trois versions de l'application terminal (1.1.0 à 1.1.2) :

- **Envoi des images.** Chaque photo circule désormais sous un nom de champ propre, est compressée sur le terminal et est envoyée avec un corps de requête de longueur fixe, car la couche PHP ne lit pas les corps multipart transmis par blocs (chunked). Les limites d'envoi du serveur ont été relevées en conséquence.
- **GPS.** Une position 0,0 est traitée comme absente, et le terminal demande l'autorisation de localisation à l'exécution.
- **Démarrage du terminal.** Les bibliothèques NFC et impression ne se chargent qu'à l'usage, et le paquet d'installation est passé d'environ 70 Mo à environ 15 Mo.
- **Diagnostic.** Les erreurs de validation sont renvoyées champ par champ au terminal et journalisées sur le serveur.

Avant la production, le pilote évolue vers la cible décrite à la section 10 et dans le Document 2 :

1. TLS sur tout le trafic terminaux et web (Document 2 §12 et §18).
2. Authentification API par terminal, en plus de l'identifiant de l'appareil.
3. Topologie de production du §10.2 : Nginx et PHP-FPM, serveur de base de données séparé, sauvegardes chiffrées avec restauration à un instant donné.
4. Décision sur le lieu d'hébergement (OP-5).
5. Décision sur la phase d'intégration SUNULIFE (OP-1).

---

*Fin du Document 1.*
