# SUNU Assurances Vie Burkina Faso
## Solution mobile de collecte de primes sur terminaux TPE
### Document 5 — Formats d'import et d'échange de données

| Rubrique | Valeur |
|---|---|
| Document | Spécification des formats d'import et d'échange de données |
| Version | 1.0 |
| Date | 17 septembre 2026 |
| Destinataire | SUNU Assurances Vie Burkina Faso — Direction Centrale des Opérations et DSI |
| Émetteur | DBS SA — Digital Business Services |
| Fichiers joints | `SUNU_client_import_sample_EN.csv` · `SUNU_import_clients_exemple_FR.csv` |
| Documents liés | Doc 1 — Architecture de la solution (§6, §7) ; Doc 2 — Architecture et procédures de sécurité |

---

## 1. Objet et périmètre

Ce document décrit les fichiers et formats de données utilisés par la solution, et la forme que doit avoir un fichier pour être importé sans erreur. Il couvre :

- le **fichier d'import des clients et des polices**, qui charge dans le back-office les clients existants de SUNU et leurs polices à partir d'une extraction SUNULIFE, afin que les collecteurs les retrouvent sur les terminaux dès le premier jour ;
- les autres formats de données échangés par la solution : l'API des terminaux, les images de souscription et l'export des encaissements de primes.

Il décrit la version actuellement en service sur le pilote.

---

## 2. Formats pris en charge — vue d'ensemble

| Échange | Sens | Format | Encodage | Canal |
|---|---|---|---|---|
| Clients et polices existants | SUNU → back-office | CSV (`.csv`), séparateur virgule | UTF-8 sans BOM | Téléversement web par un utilisateur back-office |
| Encaissements de primes | Back-office → SUNU | CSV (`.csv`), séparateur virgule | UTF-8 | Téléchargement web |
| Images de souscription | Terminal → back-office | JPEG, PNG ou WebP | Binaire, multipart/form-data | API terminaux |
| Données de l'API terminaux | Terminal ↔ back-office | JSON | UTF-8 | API REST |

> **Les classeurs Excel (`.xlsx`) ne sont pas lus par l'import.** Enregistrez d'abord le classeur au format CSV, comme indiqué à la section 3.8.

---

## 3. Fichier d'import des clients et des polices

### 3.1 Fonctionnement de l'import

Un utilisateur back-office ouvre **Collecte primes → Import Phase 0** (`/business/sunu/import`), sélectionne le fichier et clique sur **Importer**.

Chaque ligne de données décrit **une police** et le client qui la détient. Pour chaque ligne, le back-office :

1. Recherche le produit par son code. Si le code est inconnu, la ligne est rejetée avec le message `Unknown product: <code>`.
2. Retrouve le client ayant les mêmes nom, prénom et numéro de téléphone, ou le crée.
3. Crée la police, ou la met à jour si une police portant le même numéro existe déjà.
4. Passe la police au statut **active**, avec **SUNULIFE** comme origine, et enregistre la date et l'heure de l'import.

À la fin du traitement, l'écran affiche le nombre de lignes importées et les 20 premiers messages d'erreur. Les lignes sans erreur sont importées même si d'autres lignes échouent.

```
 Extraction SUNULIFE ──▶ fichier CSV ──▶ Back-office : Import
                                           │
                        pour chaque ligne ─┤
                                           ├─ code_produit → produit d'assurance (doit exister)
                                           ├─ nom + prenom + telephone → client (retrouvé ou créé)
                                           └─ numero_police → police (créée ou mise à jour), statut active
                                           │
                                           ▼
                         « N lignes importées » + 20 premières erreurs
```

### 3.2 Exigences de format du fichier

| Propriété | Exigence |
|---|---|
| Type de fichier | CSV en texte brut, extension `.csv` |
| Encodage des caractères | UTF-8 **sans** indicateur d'ordre des octets (BOM). Les noms accentués tels que `Rasmané` ou `Noëlle` sont pris en charge |
| Séparateur de champs | Virgule `,` — le point-virgule n'est **pas** accepté |
| Délimiteur de texte | Guillemet double `"`, nécessaire uniquement si une valeur contient une virgule ou un guillemet |
| Séparateur décimal | Point `.` — par exemple `5000` ou `5000.00` |
| Séparateur de milliers | Aucun — `5000`, et non `5 000` ni `5,000` |
| Format de date | ISO 8601 `AAAA-MM-JJ` — par exemple `2025-03-01` |
| Fin de ligne | CRLF (Windows) ou LF (Linux) |
| Première ligne | En-têtes de colonnes (section 3.3) |
| Ordre des colonnes | Libre ; les colonnes sont reconnues par leur en-tête |
| Casse des en-têtes | Libre ; `Numero Police`, `NUMERO_POLICE` et `numero_police` sont équivalents |
| Accents dans les en-têtes | **Interdits** — écrire `prenom`, et non `prénom` |
| Lignes par fichier | Jusqu'à 5 000 recommandées ; découper les extractions plus volumineuses en plusieurs fichiers |

### 3.3 Colonnes

| Colonne (en-tête anglais) | En-tête français accepté | Obligatoire | Format | Exemple | Règle |
|---|---|---|---|---|---|
| `policy_number` | `numero_police` | **Oui** | Texte, 255 car. max. | `MER-2025-000101` | Numéro de police unique. Réimporter le même numéro met à jour cette police |
| `product_code` | `code_produit` | **Oui** | Texte, code produit | `MICRO_EPARGNE_RETRAITE` | Doit figurer parmi les codes de la section 3.4 |
| `last_name` | `nom` | **Oui** | Texte, 255 car. max. | `OUEDRAOGO` | Nom du client |
| `first_name` | `prenom` | **Oui** | Texte, 255 car. max. | `Aminata` | Prénom(s) du client |
| `phone` | `telephone` | **Oui** | Texte, 8 chiffres | `70112233` | Format national sans espace ni indicatif. Sert à reconnaître le client |
| `premium_amount` | — | Facultatif | Nombre, FCFA | `5000` | Prime par période. Si la colonne est absente, la valeur par défaut du produit s'applique |
| `premium_frequency` | — | Facultatif | Code (section 3.5) | `monthly` | Si la colonne est absente, la valeur par défaut du produit s'applique |
| `effective_date` | — | Facultatif | `AAAA-MM-JJ` | `2025-03-01` | Date d'effet de la police. Si la colonne est absente, la date de l'import est utilisée |
| `sunulife_policy_id` | — | Facultatif | Texte, 255 car. max. | `SL-778101` | Identifiant interne SUNULIFE, conservé pour la future synchronisation par API |

Les quatre dernières colonnes n'ont qu'un en-tête anglais ; en-têtes français et anglais peuvent être mélangés dans un même fichier.

> **Cellules vides dans les colonnes facultatives.** Lorsqu'une colonne facultative est présente, renseignez-la sur toutes les lignes. Pour appliquer la valeur par défaut du produit à toutes les lignes, retirez entièrement la colonne du fichier. Une cellule `premium_amount` ou `effective_date` vide entraîne le rejet de la ligne.

### 3.4 Codes produits

| product_code / code_produit | Produit (français) | Produit (anglais) | Catégorie | Prime par défaut du pilote |
|---|---|---|---|---|
| `MICRO_EPARGNE_RETRAITE` | Micro Épargne Retraite | Micro Retirement Savings | Retraite | 5 000 FCFA mensuelle |
| `MIXTE_PLUS_HORIZON` | Mixte Plus Horizon | Mixte Plus Horizon | Projet | Non définie — renseigner `premium_amount` |
| `RENTE_EDUCATION_PLUS` | Rente Éducation Plus | Education Annuity Plus | Education | Non définie — renseigner `premium_amount` |
| `MICRO_QUIETUDE` | Micro Quiétude | Micro Quietude | Funérailles | 2 000 FCFA mensuelle |

Les codes s'écrivent en majuscules, exactement comme indiqué. Les primes par défaut sont celles paramétrées pour le pilote et peuvent être modifiées au back-office. Les produits ajoutés ultérieurement dans **Collecte primes → Produits** peuvent être importés avec le code saisi à cet endroit.

### 3.5 Codes de périodicité de prime

| premium_frequency | Signification |
|---|---|
| `daily` | Journalière |
| `weekly` | Hebdomadaire |
| `monthly` | Mensuelle |
| `quarterly` | Trimestrielle |
| `annual` | Annuelle |

### 3.6 Réimport et doublons

| Situation | Résultat |
|---|---|
| Même `numero_police` importé de nouveau | Police existante mise à jour ; aucun doublon créé |
| Même client (nom, prénom, téléphone) sur plusieurs lignes | Un seul client détenant plusieurs polices |
| Même client avec un autre téléphone ou une autre orthographe | Un second client est créé ; gardez orthographes et numéros cohérents dans toute l'extraction |
| Ligne rejetée | La police de cette ligne n'est pas enregistrée ; son client peut déjà avoir été créé et sera réutilisé au prochain import. Corrigez la ligne et réimportez le fichier ; les lignes déjà importées sont mises à jour, pas dupliquées |

Réimporter un fichier corrigé est donc sans risque.

### 3.7 Erreurs courantes et solutions

| Constat | Cause | Solution |
|---|---|---|
| 0 ligne importée, aucune erreur | Fichier enregistré avec des points-virgules (`;`), le réglage par défaut d'Excel en français | Enregistrer avec la virgule comme séparateur (section 3.8) |
| Toutes les lignes rejetées, la première colonne semble absente | Fichier enregistré en « CSV UTF-8 » depuis Excel, qui ajoute un BOM | Enregistrer sans BOM (section 3.8) |
| Prénoms ou téléphones tous vides ou affichés « Client » | En-tête accentué tel que `prénom` ou `téléphone` | Utiliser `prenom`, `telephone`, ou les en-têtes anglais |
| `Unknown product: …` | Code produit mal orthographié ou produit non créé | Utiliser un code de la section 3.4 |
| Ligne rejetée avec un message de base de données sur une date | Date saisie `01/03/2025` ou cellule vide | Utiliser `2025-03-01`, ou retirer la colonne |
| Ligne rejetée avec un message de base de données sur une valeur décimale | Montant saisi `5 000` ou `5000,00`, ou cellule vide | Utiliser `5000` ou `5000.00`, ou retirer la colonne |
| Téléphone affiché `7,01E+07` ou tronqué | Le tableur a converti le téléphone en nombre | Mettre la colonne téléphone au format Texte avant l'enregistrement |
| L'import s'arrête sur une erreur serveur | Une ligne compte plus de cellules que la ligne d'en-têtes, souvent à cause d'une virgule dans une valeur sans guillemets. Les lignes précédentes sont déjà importées | Encadrer de guillemets doubles les valeurs contenant une virgule, puis réimporter le fichier complet |

### 3.8 Préparation du fichier

**Recommandé — LibreOffice Calc**

1. Ouvrir l'extraction.
2. **Fichier → Enregistrer sous**, type **Texte CSV (.csv)**, cocher **Éditer les paramètres du filtre**.
3. Jeu de caractères **Unicode (UTF-8)**, séparateur de champs **`,`**, séparateur de chaînes **`"`**.

LibreOffice écrit en UTF-8 sans BOM.

**Google Sheets** — **Fichier → Télécharger → Valeurs séparées par des virgules (.csv)**. Le fichier est en UTF-8 sans BOM, séparé par des virgules.

**Microsoft Excel** — Excel en français enregistre les CSV avec des points-virgules, et son option « CSV UTF-8 » ajoute un BOM ; aucune des deux options ne produit donc directement un fichier valide. Le classeur peut être préparé dans Excel, puis ouvert dans LibreOffice Calc ou chargé dans Google Sheets pour enregistrer le CSV.

**Directement depuis SUNULIFE ou une base de données** — exporter en UTF-8, séparateur virgule, avec les en-têtes de la section 3.3.

### 3.9 Données non portées par le fichier d'import

Le fichier porte les données nécessaires à l'encaissement : nom du client, téléphone et caractéristiques de la police. Pièces d'identité, date de naissance, adresse et photographies ne sont pas importées dans cette version ; elles sont saisies sur le terminal lors de la souscription. D'autres champs de SUNULIFE pourront être ajoutés au fichier par la suite si nécessaire.

### 3.10 Protection des données

Le fichier d'import contient des données personnelles. Transmettez-le par un canal convenu avec la DSI de SUNU (pas par courriel ordinaire), supprimez les copies de travail après l'import et réservez l'accès à l'écran Import Phase 0 aux profils back-office autorisés. Voir le Document 2.

---

## 4. Fichiers d'exemple

Deux fichiers d'exemple accompagnent ce document. Ils contiennent des clients et des numéros de police **fictifs** et utilisent les quatre produits du pilote.

| Fichier | En-têtes | Lignes |
|---|---|---|
| `SUNU_import_clients_exemple_FR.csv` | En-têtes français pour les cinq colonnes obligatoires, en-têtes anglais pour les colonnes facultatives | 6 |
| `SUNU_client_import_sample_EN.csv` | En-têtes anglais | 6 |

Contenu de l'exemple français :

```
numero_police,code_produit,nom,prenom,telephone,premium_amount,premium_frequency,effective_date,sunulife_policy_id
MER-2025-000101,MICRO_EPARGNE_RETRAITE,OUEDRAOGO,Aminata,70112233,5000,monthly,2025-03-01,SL-778101
MER-2025-000102,MICRO_EPARGNE_RETRAITE,SAWADOGO,Issouf,76223344,10000,monthly,2025-06-15,SL-778102
MQT-2024-000517,MICRO_QUIETUDE,KABORE,Marie-Noëlle,65334455,2000,monthly,2024-11-01,SL-640517
MQT-2026-000033,MICRO_QUIETUDE,ZONGO,"Adama Jean-Baptiste",71445566,6000,quarterly,2026-01-01,SL-640533
MPH-2025-000009,MIXTE_PLUS_HORIZON,TRAORE,Fatimata,78556677,25000,monthly,2025-09-01,SL-812009
REP-2023-000245,RENTE_EDUCATION_PLUS,COMPAORE,Rasmané,74667788,120000,annual,2023-10-01,SL-590245
```

L'exemple anglais contient les mêmes lignes sous cette ligne d'en-têtes :

```
policy_number,product_code,last_name,first_name,phone,premium_amount,premium_frequency,effective_date,sunulife_policy_id
```

Le plus petit fichier valide n'utilise que les cinq colonnes obligatoires ; montant, périodicité et date d'effet proviennent alors des valeurs par défaut du produit et de la date d'import :

```
numero_police,code_produit,nom,prenom,telephone
MQT-2024-000517,MICRO_QUIETUDE,KABORE,Marie-Noëlle,65334455
```

Les formats de numéros de police des exemples sont indicatifs. Utilisez les numéros de police tels qu'ils existent dans SUNULIFE.

---

## 5. Autres formats de données

### 5.1 Export des encaissements de primes

**Collecte primes → Encaissements → Export Excel/CSV (SUNULIFE)** télécharge un fichier CSV nommé `sunu_premiums_AAAAMMJJ_HHMMSS.csv`, séparé par des virgules, une ligne par encaissement, du plus récent au plus ancien.

| Colonne | Contenu |
|---|---|
| `Reference` | Référence unique de l'encaissement, générée par le terminal |
| `Kind` | `policy_cost` (coût de police), `first_premium` (première prime) ou `recurring_premium` (prime récurrente) |
| `Amount` | Montant en FCFA, point comme séparateur décimal |
| `Policy` | Numéro de police, ou `-` |
| `Proposal` | Numéro de proposition pour une première prime, ou `-` |
| `Product` | Code produit |
| `Client` | Nom et prénom |
| `Agent` | Nom du collecteur |
| `Instrument` | `cash` (espèces), `momo` (Mobile Money) ou `bank` (banque) |
| `Collected At` | `AAAA-MM-JJ HH:MM:SS` |
| `Receipt` | Numéro de reçu, ou `-` |

Les en-têtes de ce fichier sont en anglais quelle que soit la langue de l'interface.

### 5.2 Images de souscription

Chaque souscription envoyée depuis un terminal comporte trois images : la proposition signée, la CNIB recto et la CNIB verso. Le terminal compresse chaque image à 900 Ko au maximum avant envoi. Le back-office accepte les formats JPEG, PNG et WebP et range les images dans un dossier par proposition.

### 5.3 API des terminaux

Le terminal et le back-office échangent du JSON via une API REST ; les souscriptions avec images utilisent `multipart/form-data`. Conventions communes :

| Donnée | Format |
|---|---|
| Texte | UTF-8 |
| Montants | Nombre en FCFA, stocké avec 2 décimales |
| Dates et heures | `AAAA-MM-JJ` et `AAAA-MM-JJ HH:MM:SS` |
| GPS | Degrés décimaux, 7 décimales (`12.3714277`, `-1.5196603`) |
| Erreurs | HTTP 422 avec `message` et un objet `errors` listant chaque champ |

La liste des points d'accès et le détail des champs figurent au Document 1 §7.

---

## 6. Liste de contrôle avant l'envoi d'un fichier d'import

- [ ] Fichier enregistré en `.csv`, UTF-8 sans BOM
- [ ] Séparateur virgule
- [ ] Ligne d'en-têtes présente, sans accents
- [ ] `numero_police`, `code_produit`, `nom`, `prenom`, `telephone` renseignés sur chaque ligne
- [ ] Codes produits repris de la section 3.4
- [ ] Téléphones sur 8 chiffres, colonne au format Texte
- [ ] Dates au format `AAAA-MM-JJ` ; montants sans espaces
- [ ] Colonnes facultatives soit renseignées sur toutes les lignes, soit retirées
- [ ] Valeurs contenant une virgule encadrées de guillemets doubles
- [ ] 5 000 lignes au plus par fichier
- [ ] Transmission par le canal convenu avec la DSI de SUNU

---

*Fin du Document 5.*
