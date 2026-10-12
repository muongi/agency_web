# SUNU Assurances Vie Burkina Faso
## Solution mobile de collecte de primes sur terminaux TPE
### Document 2 — Architecture, procédures et dispositifs de sécurité

| Rubrique | Valeur |
|---|---|
| Document | Architecture et procédures de sécurité |
| Version | 1.0 (projet soumis à revue) |
| Date | 31 août 2026 |
| Destinataire | SUNU Assurances Vie Burkina Faso — Direction Centrale des Opérations |
| Émetteur | DBS SA — Digital Business Services |
| Répond à | Courriel d'exigences SUNU §2 — « une documentation décrivant les procédures et mécanismes mis en œuvre pour assurer la sécurité de la solution et des données » |
| Documents liés | Doc 1 — Architecture technique ; Doc 3 — Guide utilisateur ; Doc 4 — Plan de déploiement |
| Classification | Confidentiel — usage interne SUNU et équipe de réalisation |

---

## 1. Objet, périmètre et démarche

### 1.1 Objet

Le présent document décrit les mécanismes et procédures de sécurité protégeant la solution mobile de collecte de primes et les données qu'elle traite : qui peut faire quoi, comment l'argent et les données d'identité sont protégés, comment la solution se comporte en cas d'attaque ou de défaillance, et ce que SUNU doit faire au plan opérationnel pour la maintenir sûre.

### 1.2 Périmètre

| Dans le périmètre | Hors périmètre |
|---|---|
| Application TPE et parc de terminaux | Réseau bureautique interne et postes de travail de SUNU |
| Application web back-office et API REST | Sécurité interne propre à SUNULIFE (responsabilité SUNU/éditeur) |
| Base de données, stockage de fichiers, sauvegardes | Sécurité interne des opérateurs Mobile Money |
| Intégrations : SUNULIFE, Mobile Money, SMS | Sécurité physique des locaux de SUNU |
| Chaîne d'émission des cartes (physique et virtuelle) | Validité juridique de la proposition papier |
| Procédures de sécurité opérationnelles pour le personnel SUNU | |

### 1.3 Démarche et déclaration de transparence

La solution est la personnalisation d'une plateforme déjà en production pour des services financiers. C'est un avantage : les contrôles décrits ici sont pour l'essentiel des contrôles *en fonctionnement*, non des promesses.

Cela signifie aussi que la plateforme porte des choix d'implémentation préexistants qui **ne conviennent pas au déploiement de production d'un assureur** et qui sont corrigés dans le cadre du présent projet. La section 12 les énumère explicitement, avec le correctif et l'échéance, plutôt que de les taire. La DSI d'un assureur les trouvera ; les déclarer d'emblée est la seule position défendable, et c'est ainsi que ce projet traite la sécurité de bout en bout.

### 1.4 Référentiels de référence

La conception s'aligne, sans revendiquer de certification, sur :

- **ISO/IEC 27001:2022** — management de la sécurité de l'information, utilisé comme taxonomie de contrôles en section 15.
- **ISO/IEC 27002** — guide de mise en œuvre.
- **PCI-DSS v4.0** — utilisé comme *référence de conception* pour le traitement des cartes et des paiements. À noter : la solution ne stocke **aucun justificatif de paiement par carte ni aucune donnée de carte bancaire** ; les cartes émises sont des cartes d'identification d'assuré. Le périmètre complet de PCI-DSS ne s'applique donc pas, mais ses contrôles sur la gestion des clés, la journalisation et la segmentation réseau sont adoptés comme bonnes pratiques.
- **OWASP ASVS 4.0 niveau 2** et **OWASP Top 10 (2021)** — vérification de la sécurité applicative.
- **OWASP MASVS** — sécurité des applications mobiles.
- **Loi n°001-2021/AN portant protection des personnes à l'égard du traitement des données à caractère personnel (Burkina Faso)** et le contrôle de la **Commission de l'Informatique et des Libertés (CIL)** — données personnelles.
- **Code CIMA** et exigences **BCEAO** applicables aux opérations d'assurance et à l'acceptation de monnaie électronique dans la zone UEMOA.

---

## 2. Actifs et objectifs de sécurité

### 2.1 Actifs à protéger

| # | Actif | Confidentialité | Intégrité | Disponibilité |
|---|---|---|---|---|
| A1 | Primes encaissées en espèces détenues par les collecteurs | — | **Critique** | **Critique** |
| A2 | Enregistrements d'encaissement et de transaction | Élevée | **Critique** | Élevée |
| A3 | Données personnelles des assurés (identité, CNIB, téléphone, adresse) | **Critique** | Élevée | Moyenne |
| A4 | Images de pièces d'identité (proposition, CNIB) | **Critique** | Élevée | Moyenne |
| A5 | Données de polices reflétées depuis SUNULIFE | Élevée | **Critique** | Moyenne |
| A6 | Stock de cartes et données de personnalisation | Élevée | Élevée | Moyenne |
| A7 | Clés de signature des cartes virtuelles | **Critique** | **Critique** | Élevée |
| A8 | Justificatifs des collecteurs et des utilisateurs back-office | **Critique** | **Critique** | Élevée |
| A9 | Identifiants d'intégration SUNULIFE et Mobile Money | **Critique** | **Critique** | Élevée |
| A10 | Gabarits biométriques (si l'option est activée) | **Critique** | **Critique** | Faible |
| A11 | Les terminaux TPE eux-mêmes | Moyenne | Élevée | Élevée |
| A12 | Piste d'audit | Élevée | **Critique** | Moyenne |

### 2.2 Objectifs de sécurité

1. **Aucune prime encaissée ne peut être perdue, altérée ou comptée deux fois.**
2. **Aucune prime ne peut être détournée sans laisser une trace identifiant qui, quand et où.**
3. **Les données personnelles des assurés ne sont divulguées à personne hors du périmètre SUNU autorisé.**
4. **Un terminal volé ou une carte volée ne rapporte ni argent ni donnée personnelle exploitable.**
5. **Toute action sensible est imputable à une personne nommément identifiée.**
6. **Une défaillance de sécurité dégrade le service ; elle ne détruit pas l'enregistrement des encaissements.**

---

## 3. Modèle de menaces

### 3.1 Acteurs de menace

| Acteur | Motivation | Capacité | Priorité |
|---|---|---|---|
| **T1 — Collecteur malhonnête** | Détourner des primes encaissées, émettre de faux reçus, sous-déclarer | Justificatifs légitimes, possession physique du terminal, autonomie sur le terrain | **La plus élevée** |
| **T2 — Voleur** | Revendre un terminal ou une carte volés, en faire un usage frauduleux | Accès physique uniquement | Élevée |
| **T3 — Assuré agissant frauduleusement** | Obtenir un reçu sans payer, revendiquer un paiement non effectué, réutiliser un QR | Interaction avec le collecteur | Élevée |
| **T4 — Attaquant externe** | Voler la base de données personnelles, extorquer, perturber | Accès Internet, outillage courant | Élevée |
| **T5 — Utilisateur back-office interne malhonnête** | Confirmer un faux versement, altérer un encaissement, exfiltrer des données | Accès applicatif privilégié | Élevée |
| **T6 — Interception sur le réseau mobile** | Capter le trafic entre le terminal et la plateforme | Position réseau, Wi-Fi public | Moyenne |
| **T7 — Tiers compromis** (agrégateur, opérateur SMS, hébergeur) | Accéder aux données en transit ou au repos | Accès d'intégration légitime | Moyenne |

### 3.2 Principaux scénarios de menace et contre-mesures

| # | Scénario | Contre-mesures |
|---|---|---|
| **TH-1** | Le collecteur encaisse une prime et la garde, sans imprimer de reçu | Le reçu est produit par le système, non par le collecteur ; numérotation séquentielle des reçus par terminal avec détection des ruptures ; confirmation SMS à l'assuré (§8.4) ; sensibilisation de l'assuré au fait qu'un paiement sans reçu imprimé n'est pas valable ; encaissements rapprochés du plafond et des versements du collecteur |
| **TH-2** | Le collecteur imprime un reçu puis annule ou supprime l'enregistrement | Les encaissements sont en **ajout seul** ; aucune suppression sur l'appareil ni au back-office ; une correction est une écriture d'extourne nouvelle, soumise à habilitation et auditée ; la file locale du terminal n'est pas accessible à l'utilisateur |
| **TH-3** | Le collecteur accumule des espèces et disparaît | Le plafond par collecteur (Doc 1 §6.3) borne l'exposition maximale ; le terminal bloque les nouveaux encaissements au plafond ; suivi quotidien des soldes non versés ; alerte d'ancienneté sur les collecteurs au-dessus du seuil depuis plus de N jours |
| **TH-4** | Le collecteur déclare un versement supérieur au dépôt réel | Le versement est une *déclaration* ; seule la confirmation back-office face au relevé bancaire ou au comptage en caisse réduit le solde ; écart consigné ; confirmation à deux personnes au-delà d'un montant paramétrable |
| **TH-5** | Terminal volé | Appareil verrouillé par les justificatifs de l'agent et l'expiration sur inactivité ; aucune espèce sur l'appareil ; données locales chiffrées et cloisonnées à cet agent ; désactivation à distance du compte agent et du terminal depuis le back-office ; encaissements non synchronisés récupérables au retour |
| **TH-6** | Carte volée ou clonée | La carte porte un identifiant, **jamais de monnaie ni de solde** ; une carte identifie, elle n'autorise pas un paiement — c'est la session authentifiée du collecteur qui le fait ; blocage instantané au back-office ; recommandation du sans contact avec clés par carte plutôt que de la piste magnétique clonable (Doc 1 §7.2) |
| **TH-7** | QR de carte virtuelle contrefait ou rejoué | Signature cryptographique vérifiée hors ligne sur le terminal ; expiration du justificatif ; liste de révocation synchronisée vers les terminaux ; le QR identifie mais n'autorise pas ; présenter un QR produit malgré tout un reçu au nom du véritable assuré, si bien que le rejeu n'apporte aucun gain financier |
| **TH-8** | Interception du trafic TPE ↔ plateforme | TLS 1.2+ obligatoire ; épinglage de certificat dans l'application TPE ; aucune donnée sensible dans les URL ; refus du HTTP en clair |
| **TH-9** | Vol de la base de données personnelles | Chiffrement au repos ; base non exposée sur Internet ; comptes de base au moindre privilège ; sauvegardes chiffrées ; journalisation des accès ; alerte sur les volumes d'export anormaux |
| **TH-10** | Compromission d'une clé d'API | Clés par unité d'exploitation, hachées au repos, révocables instantanément, limitées en débit, usage journalisé avec horodatage de dernière utilisation ; procédure de rotation (§5.4) |
| **TH-11** | Un utilisateur back-office exporte tout le portefeuille clients | L'export est soumis à habilitation ; chaque export est journalisé avec l'acteur, les filtres et le nombre de lignes ; alerte volumétrique ; exports marqués au nom du demandeur |
| **TH-12** | Injection / XSS / CSRF sur le back-office | Requêtes paramétrées au niveau du framework, échappement en sortie, jetons CSRF sur toutes les requêtes web modifiant l'état, validation stricte côté serveur sur chaque point d'entrée |
| **TH-13** | Force brute sur les justificatifs de collecteur ou d'utilisateur | Limitation de débit et verrouillage progressif, protection contre le bourrage d'identifiants, absence d'énumération des comptes dans les messages d'erreur, alerte en cas d'échecs répétés |
| **TH-14** | Injection de données SUNULIFE malveillantes ou erronées corrompant les polices | Validation stricte du schéma à l'ingestion ; une synchronisation ne supprime jamais un encaissement local ; les anomalies sont mises en quarantaine plutôt qu'appliquées |

---

## 4. Défense en profondeur — vue par couches

```
 ┌───────────────────────────────────────────────────────────────────┐
 │ N7 GOUVERNANCE  politique de sécurité · rôles · formation ·       │
 │                 déclaration CIL · revue fournisseurs · plan       │
 ├───────────────────────────────────────────────────────────────────┤
 │ N6 SURVEILLANCE piste d'audit · journaux API · alertes ·          │
 │                 indicateurs de fraude · revue trimestrielle       │
 ├───────────────────────────────────────────────────────────────────┤
 │ N5 DONNÉES      chiffrement au repos · classification ·           │
 │                 minimisation · conservation · sauvegardes         │
 ├───────────────────────────────────────────────────────────────────┤
 │ N4 APPLICATION  habilitations · validation des entrées ·          │
 │                 idempotence · registre en ajout seul ·            │
 │                 maker-checker · plafonds                          │
 ├───────────────────────────────────────────────────────────────────┤
 │ N3 IDENTITÉ     authentification · MFA administrateurs ·          │
 │                 authentification agent · clés d'API ·             │
 │                 liaison au terminal · contrôle de session         │
 ├───────────────────────────────────────────────────────────────────┤
 │ N2 RÉSEAU       TLS 1.2+ · épinglage · WAF · limitation de débit ·│
 │                 listes d'autorisation · pas de base publique      │
 ├───────────────────────────────────────────────────────────────────┤
 │ N1 PHYSIQUE     garde des terminaux · contrôle du stock cartes ·  │
 │                 station de personnalisation · centre de données   │
 └───────────────────────────────────────────────────────────────────┘
```

Aucun contrôle n'est pris isolément. Le plafond (N4) borne la perte qu'un collecteur compromis (N1/N3) peut causer ; la piste d'audit (N6) la rend détectable ; la confirmation de versement (N4) la rend récupérable.

---

## 5. Gestion des identités et des accès

### 5.1 Utilisateurs back-office

| Contrôle | Mise en œuvre |
|---|---|
| Authentification | Identifiant/courriel + mot de passe sur HTTPS |
| Stockage des mots de passe | bcrypt avec sel par mot de passe ; jamais réversible, jamais journalisé |
| Politique de mot de passe | 12 caractères minimum, trois classes de caractères, blocage sur liste de mots de passe courants, pas de réutilisation des 5 derniers, expiration paramétrable (90 jours par défaut pour les profils privilégiés) |
| Première connexion | Changement de mot de passe imposé |
| Tentatives échouées | Temporisation progressive puis verrouillage temporaire ; alerte à l'administrateur |
| **Authentification multifacteur** | **Obligatoire pour les profils Administrateur et DSI** ; application TOTP ou code SMS. Voir §12 point H-5 |
| Session | Expiration sur inactivité (15 min par défaut pour les profils privilégiés, 30 min sinon) ; durée de vie absolue ; session régénérée à la connexion ; cookies secure, HttpOnly, SameSite |
| Désactivation | Immédiate au départ ; les comptes sont désactivés, jamais supprimés, afin de préserver la piste d'audit |
| Revue | Revue trimestrielle des comptes et habilitations par SUNU (§14.2) |

### 5.2 Contrôle d'accès fondé sur les rôles

La plateforme met en œuvre des habilitations granulaires regroupées en rôles, attribuables globalement ou par unité d'exploitation. Profils définis pour SUNU :

| Profil | Rôle | Habilitations notables | Explicitement refusé |
|---|---|---|---|
| **Agent collecteur (TPE)** | Encaissement terrain | Souscrire, encaisser une prime, déclarer un versement, imprimer/réimprimer ses propres reçus, consulter ses encaissements | Tout accès back-office ; consultation des données d'autres agents ; confirmation d'un versement ; modification d'un encaissement |
| **Agent back-office** | Traitement quotidien | Revoir les propositions, consulter tous les encaissements, générer et télécharger les exports, gérer les dossiers des assurés | Confirmation des versements ; gestion des utilisateurs ; modification des plafonds ou des frais |
| **Superviseur / Contrôleur** | Contrôle financier | Confirmer les versements, consulter plafonds et soldes, traiter les files d'anomalies, effectuer les rapprochements | Gestion des utilisateurs ; modification de la configuration système |
| **Agent d'émission des cartes** | Chaîne carte | Affecter des cartes, générer les fichiers de personnalisation, marquer les cartes comme écrites, bloquer/remplacer | Opérations financières ; gestion des utilisateurs |
| **Administrateur (SUNU)** | Configuration | Gérer utilisateurs, rôles, agents, terminaux, produits, plafonds, frais | Accès direct à la base ; suppression d'enregistrements d'audit |
| **DSI / Super administrateur** | Technique | Configuration des environnements, intégrations, identifiants, supervision technique | *Configuré sans habilitation de confirmation des versements* — le pouvoir technique ne doit pas égaler le pouvoir financier |

**Principe appliqué de bout en bout : séparation entre celui qui encaisse, celui qui confirme et celui qui configure.** Aucun profil ne détient les trois.

### 5.3 Authentification du collecteur sur le TPE

| Contrôle | Mise en œuvre |
|---|---|
| Justificatif | Identifiant individuel + mot de passe/code, vérifié côté serveur contre l'empreinte bcrypt |
| Première utilisation | Changement de code imposé à la première connexion |
| Jeton de session | Jeton signé de courte durée émis à la connexion, renouvelé à l'activité, invalide après l'expiration sur inactivité. Voir §12 point H-2 |
| Authentification hors ligne | Déverrouillage hors ligne autorisé pendant une période bornée au moyen d'un vérificateur stocké localement ; au-delà, le terminal exige une réauthentification en ligne avant tout nouvel encaissement |
| Liaison au terminal | L'agent est affecté à un terminal déterminé au back-office ; une connexion depuis un terminal non affecté est refusée et donne lieu à alerte. L'identité du terminal est son numéro de série enregistré |
| Révocation | La désactivation de l'agent au back-office refuse l'authentification suivante et rejette les synchronisations de cet agent |
| Inactivité | Verrouillage automatique ; ressaisie du code exigée |

### 5.4 Justificatifs machine à machine (clés d'API)

| Contrôle | Mise en œuvre |
|---|---|
| Portée | Une clé par unité d'exploitation, transmise dans un en-tête dédié |
| Stockage | Hachée au repos ; la valeur en clair est affichée une seule fois à la création et n'est jamais récupérable ensuite |
| Cycle de vie | Indicateur d'activation permettant une révocation instantanée ; horodatage de dernière utilisation ; usage journalisé par requête |
| Rotation | Procédure documentée : créer la nouvelle clé → configurer les terminaux → vérifier le trafic sur la nouvelle clé → révoquer l'ancienne. Rotation au moins annuelle et immédiate en cas de suspicion de compromission |
| Limitation de débit | Par clé et par IP. Voir §12 point H-3 |

### 5.5 Identifiants d'intégration tiers

Les identifiants SUNULIFE, Mobile Money et SMS sont détenus par unité d'exploitation dans un coffre dédié, séparé des définitions d'intégration, chiffrés au repos avec la clé applicative, jamais écrits dans les journaux et jamais réaffichés après saisie. Seul le profil DSI peut les modifier, et chaque modification est auditée.

---

## 6. Protection des données

### 6.1 Classification

| Classe | Exemples | Traitement |
|---|---|---|
| **C4 — Critique** | Mots de passe, codes, clés de signature, identifiants d'intégration, gabarits biométriques | Chiffrés ou hachés ; jamais journalisés ; jamais exportés ; aucun affichage |
| **C3 — Confidentiel** | Numéros CNIB, images d'identité, données personnelles des assurés, données de polices | Chiffrés au repos et en transit ; accès soumis à habilitation ; accès journalisé ; masqués dans les listes |
| **C2 — Interne** | Encaissements, transactions, plafonds, rapports | Accès par profil ; audité ; exportable par les seuls profils autorisés |
| **C1 — Public** | Noms de produits, documentation générale | Aucune restriction |

### 6.2 Chiffrement en transit

- **HTTPS/TLS 1.2 minimum, TLS 1.3 de préférence**, sur chaque canal : TPE ↔ plateforme, navigateur ↔ plateforme, plateforme ↔ SUNULIFE, plateforme ↔ Mobile Money.
- Suites cryptographiques modernes uniquement ; SSLv3, TLS 1.0 et 1.1 désactivés ; HSTS activé.
- **Épinglage de certificat dans l'application TPE**, ce qui met en échec l'interception par un proxy malveillant sur un réseau mobile hostile.
- HTTP en clair refusé, et non redirigé, sur les points d'entrée d'API. Voir §12 point H-1.

### 6.3 Chiffrement au repos

| Donnée | Protection |
|---|---|
| Mots de passe et codes | Empreinte bcrypt, jamais chiffrée (irréversible par conception) |
| Identifiants d'intégration | Chiffrement symétrique avec la clé applicative, détenue hors du dépôt de code |
| Images d'identité (proposition, CNIB) | Volume de stockage chiffré ; noms de fichiers non devinables ; servies uniquement par un lien authentifié, contrôlé en habilitation et limité dans le temps — jamais par une URL publique |
| Base de données | Chiffrement au repos au niveau du volume ; chiffrement au niveau colonne pour les champs les plus sensibles |
| Sauvegardes | Chiffrées avant de quitter le serveur ; clé détenue séparément des sauvegardes |
| Base locale du TPE | Stockage privé à l'application, chiffré ; effacé à la déconnexion de l'agent affecté ; aucun chemin d'export exposé à l'utilisateur de l'appareil |

### 6.4 Minimisation des données

- Le TPE ne détient que ce dont le collecteur affecté a besoin : ses propres clients et encaissements, et le cache de polices nécessaire au travail hors ligne. **Aucun terminal ne détient jamais l'intégralité du portefeuille clients.**
- Les images d'identité sont téléversées puis supprimées de l'appareil dès acquittement par le serveur.
- Les exports ne contiennent que les colonnes requises par la finalité du rapport.
- Les listes masquent les identifiants sensibles (la CNIB n'est affichée que partiellement) ; l'affichage complet est une action distincte et journalisée.

### 6.5 Données personnelles et droit burkinabè

| Obligation | Réponse |
|---|---|
| Déclaration du traitement à la CIL | **SUNU est le responsable de traitement** et effectue la déclaration ; DBS agit comme sous-traitant et fournit la description technique requise |
| Base légale | Exécution du contrat d'assurance et mesures précontractuelles |
| Information de la personne concernée | Mention sur le formulaire de proposition et mention résumée sur le reçu |
| Droits d'accès, de rectification et d'effacement | Procédure back-office documentée au Document 3 ; demandes suivies et traitées dans le délai légal |
| Limites de conservation | Appliquées conformément au Document 1 §5.4 ; traitements d'archivage et de purge automatisés |
| Obligations du sous-traitant | Contrat de sous-traitance entre SUNU et DBS couvrant la confidentialité, les sous-traitants ultérieurs, la notification des violations, la restitution/destruction en fin de contrat |
| Transfert transfrontalier | Dépend de la décision d'hébergement (Doc 1, OP-5). Si l'hébergement est hors du Burkina Faso, le transfert doit être couvert par la déclaration CIL et par des clauses contractuelles |

### 6.6 Données biométriques (capacité optionnelle)

La plateforme prend en charge la capture et la vérification d'empreintes. **Pour SUNU, il s'agit d'une option, non d'un choix par défaut.** Si SUNU l'active :

- Seul un **gabarit mathématique** est stocké, jamais une image d'empreinte ; le gabarit n'est pas réversible en image.
- Le gabarit est une donnée C4 : chiffré, jamais exporté, jamais affiché, jamais transmis à des tiers, y compris SUNULIFE.
- Le consentement explicite, éclairé et écrit de l'assuré est requis, avec une alternative non biométrique toujours disponible.
- Une déclaration CIL spécifique de SUNU est nécessaire, la donnée biométrique constituant une catégorie sensible.
- Recommandation : **ne pas activer la biométrie pour le pilote.** Elle ajoute une complexité juridique et de recueil du consentement sans apport par rapport à carte + QR à ce stade. À réexaminer une fois la solution en production.

---

## 7. Sécurité des cartes physiques et virtuelles

### 7.1 Propriété de sécurité fondamentale

**Une carte SUNU est un jeton d'identité, non un instrument de paiement.** Elle ne porte aucun solde, ne stocke aucune somme, ne détient aucun justificatif bancaire et ne peut par elle-même autoriser aucun débit. Présenter une carte amène le terminal à *rechercher* un assuré et à afficher ses polices. L'argent ne se déplace que lorsqu'un collecteur authentifié enregistre un encaissement. Ce seul choix de conception élimine toute la classe de risque « carte volée = argent volé ».

### 7.2 Contrôles de la carte physique

| Contrôle | Mise en œuvre |
|---|---|
| Contenu écrit | Identifiant de carte, référence de l'assuré, emplacements de polices. **Ni CNIB, ni adresse, ni montants, ni code** |
| Contenu imprimé | Marque SUNU, nom et prénoms de l'assuré, numéro de carte, expiration — conformément au TdR |
| Résistance au clonage | Cartes sans contact avec clés par carte vivement recommandées plutôt que la piste magnétique, clonable sans difficulté (Doc 1 §7.2, point ouvert OP-3) |
| Cycle de vie | Modèle de statut complet avec motif de blocage, horodatages et chaîne de remplacement ; une carte remplacée est définitivement inutilisable |
| Blocage | Immédiat au back-office ; propagé aux terminaux à la synchronisation suivante et appliqué côté serveur sans délai |
| Contrôle du stock | Les cartes vierges sont inventoriées ; chaque numéro est traçable du stock jusqu'à l'assuré ; comptage périodique rapproché des enregistrements d'émission |
| Station de personnalisation | Physiquement sécurisée, à accès contrôlé, dans un local sous contrôle SUNU ; les fichiers de personnalisation sont générés par lot, transférés par un canal interne et supprimés après usage |

### 7.3 Contrôles de la carte virtuelle (QR)

| Contrôle | Mise en œuvre |
|---|---|
| Intégrité | Charge utile signée cryptographiquement (HMAC-SHA256 ou ECDSA P-256) ; toute altération invalide la signature |
| Vérification hors ligne | Le terminal vérifie la signature sans accès réseau ; un QR contrefait est rejeté sur l'appareil |
| Gestion des clés | Clé de signature versionnée (`kid` dans la charge utile) ; clé privée/HMAC détenue côté serveur dans le coffre ; le terminal ne détient que ce qui lui est nécessaire pour vérifier ; rotation annuelle, avec chevauchement de validité pour que les cartes en circulation continuent de fonctionner |
| Expiration | Chaque justificatif porte une date d'émission et une date d'expiration ; les QR expirés sont refusés |
| Révocation | Liste de révocation synchronisée vers les terminaux à chaque synchronisation ; le rejet côté serveur est immédiat |
| Anti-rejeu | Un QR identifie mais n'autorise jamais. Même un QR parfaitement copié ne produit qu'un encaissement enregistré **au nom du véritable assuré et sur sa véritable police** — il n'y a aucun gain frauduleux |
| Diffusion | Imprimé sur les conditions particulières ou envoyé par lien SMS ; jamais transmis avec un quelconque secret |
| Vie privée | La charge utile contient une référence et un nom d'affichage — ni CNIB, ni adresse, ni montant |

---

## 8. Contrôles financiers et anti-fraude

Cette section traite la menace de plus haute priorité : le collecteur malhonnête (T1).

### 8.1 Registre d'encaissement en ajout seul

Les encaissements et les transactions ne sont **jamais physiquement supprimés ni écrasés**. Une correction est une écriture nouvelle et inverse, exigeant une habilitation explicite, un motif obligatoire, et générant un enregistrement d'audit. Chaque écriture porte une référence unique et un lien vers son terminal, son agent, son horodatage et, lorsque disponible, sa position GPS.

### 8.2 Le plafond et le versement comme plancher de perte

Le plafond du collecteur (Doc 1 §6.3) est le contrôle financier principal : il **borne le montant maximal qu'un collecteur peut détenir à un instant donné**. Le terminal l'applique — au plafond, les nouveaux encaissements sont refusés jusqu'à confirmation d'un versement. Cela convertit une exposition à la fraude non bornée en une limite connue, paramétrable et propre à chaque collecteur.

### 8.3 Séparation des tâches sur le versement

```
   encaisse  ─────►  COLLECTEUR       (peut déclarer, ne peut pas confirmer)
   confirme  ─────►  SUPERVISEUR      (peut confirmer, ne peut pas encaisser)
   configure ─────►  ADMINISTRATEUR   (fixe le plafond, ne fait ni l'un ni l'autre)
   au-delà du seuil ► SECOND VALIDEUR (maker-checker, montant paramétrable)
```

La confirmation exige une preuve : référence bancaire ou image du bordereau pour un dépôt en banque, procès-verbal de comptage pour une remise en caisse. Tout écart entre le montant déclaré et le montant confirmé est consigné, imputé et signalé — il n'est jamais absorbé silencieusement.

### 8.4 Contrôles de détection

| Contrôle | Objet |
|---|---|
| Numérotation séquentielle des reçus par terminal, avec détection automatique des ruptures | Détecte les reçus supprimés ou détruits |
| Confirmation SMS de l'encaissement au téléphone de l'assuré | L'assuré devient un témoin indépendant de chaque encaissement — le contrôle isolé le plus efficace contre TH-1 |
| Position GPS sur chaque encaissement | Détecte les encaissements enregistrés loin de tout lieu client plausible, ou regroupés au domicile du collecteur |
| Analyse temporelle | Signale les séquences improbables (nombreux encaissements en quelques secondes, activité à des heures invraisemblables) |
| Ancienneté des soldes non versés | Signale les collecteurs détenant des espèces plus longtemps que la politique ne l'autorise |
| Rapprochement : encaissements − versements confirmés = solde non versé | Contrôle croisé permanent |
| Rapprochement des règlements Mobile Money | Aucun encaissement MoMo n'est définitif tant qu'il n'apparaît pas dans le fichier de règlement de l'opérateur |
| Rejet des références en doublon au niveau de la base | Rend le double enregistrement structurellement impossible, y compris en cas de renvoi |
| Alerte volumétrique sur les exports et les consultations sensibles | Détecte l'exfiltration de données par un utilisateur interne |

### 8.5 Intégrité du reçu

Le reçu est produit par l'application, non saisi par le collecteur. Il porte le numéro de reçu, le produit, le numéro de police ou de proposition, le montant, la date et l'heure, la référence du collecteur et l'identité du terminal. Une réimpression est explicitement marquée **DUPLICATA** et est elle-même journalisée. La communication aux assurés doit énoncer clairement : *un paiement sans reçu SUNU imprimé n'est pas un paiement.*

---

## 9. Sécurité des terminaux et de l'application mobile

| Contrôle | Mise en œuvre |
|---|---|
| Registre du parc | Chaque terminal enregistré avec numéro de série, SIM, alias et modèle ; affectation à un agent nommé avec historique daté |
| Liaison agent-terminal | Connexion refusée depuis un terminal non affecté à cet agent ; tentative signalée |
| Distribution de l'application | APK signé distribué par un canal contrôlé ; installation depuis des sources inconnues désactivée sur le parc ; aucune distribution en magasin public |
| Durcissement de l'appareil | Recommandé : mode kiosque/COSU restreignant le terminal à l'application SUNU ; options développeur et débogage USB désactivés ; verrouillage d'écran imposé |
| Stockage local | Privé à l'application, chiffré, cloisonné par agent ; effacé à la déconnexion de l'agent |
| Anti-altération | Détection de root/altération au démarrage ; un appareil rooté est refusé avec remontée d'alerte au back-office |
| Secrets sur l'appareil | Aucun secret statique partagé dans le paquet applicatif. Clés par appareil provisionnées à l'enrôlement et détenues dans le keystore Android lorsque le matériel le permet. Voir §12 point H-4 |
| Réaction à distance en cas de perte | Compte agent désactivé → authentification et synchronisation suivantes refusées ; le terminal est marqué perdu au registre ; les encaissements non synchronisés sont récupérés si l'appareil est restitué |
| Captures d'écran | Désactivées sur les écrans affichant des données personnelles |
| Journaux | Aucun code, mot de passe, jeton, CNIB complète ni donnée personnelle écrit dans les journaux de l'appareil |

---

## 10. Sécurité des intégrations

### 10.1 SUNULIFE

| Contrôle | Mise en œuvre |
|---|---|
| Transport | TLS 1.2+ obligatoire. Si SUNULIFE est hébergé dans les locaux de SUNU, un VPN site à site ou un lien privé est préférable à l'Internet public |
| Authentification | Selon ce qu'exige SUNULIFE : OAuth 2.0, JWT bearer, HTTP basic ou TLS mutuel — le cadre d'adaptateur prend déjà en charge ces stratégies |
| Moindre privilège | Le compte d'intégration ne se voit accorder que les opérations du Doc 1 §8.2 — aucun droit d'administration dans SUNULIFE |
| Restriction réseau | SUNU inscrit les adresses IP de sortie de la plateforme sur liste d'autorisation |
| Validation des entrées | Chaque champ reçu de SUNULIFE est validé au regard du schéma avant de toucher la base ; les charges utiles malformées ou anormales sont mises en quarantaine, non appliquées |
| Non destructif par construction | Une synchronisation depuis SUNULIFE ne peut jamais supprimer ni altérer un enregistrement d'encaissement local |
| Traçabilité | Journalisation complète requête/réponse avec masquage des champs sensibles ; chaque échange rejouable et idempotent |
| Isolation de disponibilité | Coupe-circuit ; une indisponibilité SUNULIFE ne bloque jamais l'encaissement terrain |

### 10.2 Mobile Money

| Contrôle | Mise en œuvre |
|---|---|
| Identifiants | Détenus dans le coffre chiffré ; identifiants distincts par environnement ; jamais dans le dépôt de code |
| Authentification des rappels | Les rappels de l'opérateur sont vérifiés par signature et par liste d'IP sources autorisées ; un rappel non vérifié est rejeté et signalé |
| Idempotence | Chaque initiation porte une clé d'idempotence ; un rappel répété ne peut pas créer un second encaissement |
| Contrôle du montant | Le montant confirmé par l'opérateur est comparé au montant demandé ; toute divergence bloque l'encaissement et lève une anomalie |
| Aucun traitement du code | L'assuré saisit son code Mobile Money **sur son propre téléphone, sur le canal de l'opérateur**. Le code ne transite jamais par le TPE, l'application ou la plateforme |
| Rapprochement des règlements | Rapprochement quotidien à trois voies (plateforme / rapport opérateur / crédit bancaire) avec file d'anomalies horodatée |
| Gestion des expirations | Un paiement non confirmé reste en attente et est résolu par le rapprochement ; **le reçu n'est imprimé qu'après confirmation** |

### 10.3 SMS

Identifiants de l'opérateur dans le coffre ; contenu des messages limité au strict nécessaire (montant, référence de police, date) avec **ni CNIB, ni détail complet de police, ni lien porteur d'un secret** ; statut de remise journalisé.

---

## 11. Journalisation, audit et supervision

### 11.1 Ce qui est consigné

| Journal | Contenu | Conservation |
|---|---|---|
| **Piste d'audit** | Toute création/modification/suppression sur une entité sensible : acteur, action, objet, valeurs avant/après, horodatage, IP, regroupé par lot d'opération | 5 ans |
| **Journal d'authentification** | Succès et échecs, back-office et TPE, avec IP, terminal et agent utilisateur | 12 mois |
| **Journal API** | Un middleware dédié consigne déjà le trafic API des transactions, des cartes et des OTP : point d'entrée, charge utile (champs sensibles masqués), réponse, statut, latence | 12 mois |
| **Journal financier** | Chaque encaissement, versement, confirmation, correction et modification de plafond | 10 ans |
| **Journal d'intégration** | Chaque échange SUNULIFE et Mobile Money avec statut et nombre de tentatives | 24 mois |
| **Journal technique** | Erreurs, exceptions, performance | 90 jours |

### 11.2 Protection des journaux

Les journaux sont en ajout seul pour les utilisateurs applicatifs ; aucun profil applicatif ne peut supprimer un enregistrement d'audit. Les valeurs sensibles (mots de passe, codes, jetons, CNIB complète, gabarits biométriques) sont masquées au moment de l'écriture — une configuration de journalisation susceptible de les émettre en clair ne doit pas exister en production. Voir §12 point H-6.

### 11.3 Alertes

| Événement | Gravité | Destinataire |
|---|---|---|
| Échecs d'authentification répétés sur un compte | Moyenne | Administrateur |
| Connexion depuis un terminal non affecté | Élevée | Superviseur + Administrateur |
| Appareil rooté ou altéré détecté | Élevée | DSI |
| Rupture détectée dans la numérotation des reçus | Élevée | Superviseur |
| Collecteur au-dessus du plafond depuis plus de N jours | Moyenne | Superviseur |
| Écart de versement au-delà de la tolérance | Élevée | Superviseur + Finance |
| Volume d'export anormal | Élevée | Administrateur + DSI |
| Taux d'échec d'intégration au-dessus du seuil | Moyenne | DSI |
| Rappel Mobile Money non vérifié | Élevée | DSI |
| Échec de sauvegarde | Élevée | DSI |

### 11.4 Cadence de revue

Quotidienne : file d'alertes et files d'anomalies. Hebdomadaire : rapprochement des plafonds et des versements. Mensuelle : revue des accès des comptes privilégiés, santé des intégrations. Trimestrielle : revue complète des utilisateurs et habilitations, test de restauration des sauvegardes, revue des correctifs de sécurité.

---

## 12. Programme de durcissement — points préexistants à corriger

Ces points existent dans la plateforme actuelle et sont **corrigés dans le cadre du présent projet**, avant la mise en production. Ils sont listés ouvertement afin que SUNU puisse vérifier chacun d'eux à la réception. Chacun porte une échéance alignée sur le Document 4.

| # | Point | État actuel | Correction | Échéance |
|---|---|---|---|---|
| **H-1** | **Sécurité du transport** | L'URL de base d'API par défaut de l'application TPE est en `http://` en clair, et les points d'entrée d'API acceptent HTTP | Imposer HTTPS de bout en bout ; refuser le clair sur l'API ; ajouter l'épinglage de certificat dans l'application TPE ; HSTS sur le back-office | **Avant la recette** — jalon obligatoire |
| **H-2** | **Jeton de session du collecteur** | La connexion TPE renvoie le profil de l'agent mais n'émet aucun jeton de session borné ; les appels suivants reposent sur une clé d'API partagée | Émettre un jeton signé de courte durée par agent, renouvelé à l'activité, révocable côté serveur et invalidé sur inactivité | Avant le pilote |
| **H-3** | **Limitation de débit des API** | Les routes d'API ne sont pas limitées en débit | Limitation par clé d'API, par agent et par IP, avec verrouillage progressif et alerte | Avant le pilote |
| **H-4** | **Cryptographie côté mobile** | Un utilitaire de traitement des codes dans l'application TPE utilise une clé symétrique codée en dur dans l'application, en mode ECB. Une clé présente dans l'APK est extractible, et l'ECB laisse transparaître la structure | Supprimer la clé codée en dur ; clés par appareil provisionnées à l'enrôlement et stockées dans le keystore Android ; AES-GCM (chiffrement authentifié) en remplacement de l'ECB ; vérification du code côté serveur contre l'empreinte bcrypt plutôt que par chiffrement réversible sur l'appareil | Avant le pilote |
| **H-5** | **MFA pour les profils privilégiés** | L'authentification back-office est à facteur unique | Second facteur TOTP ou SMS, obligatoire pour les profils Administrateur et DSI | Avant la production |
| **H-6** | **Commutateur de journalisation en clair** | Un indicateur de configuration peut faire journaliser les codes à usage unique en clair | Indicateur définitivement désactivé en production et retiré de la configuration de production ; vérifié par la liste de contrôle de mise en service | Avant la recette |
| **H-7** | **Efficacité de la recherche de clé d'API** | La vérification parcourt toutes les clés actives en effectuant une comparaison d'empreinte sur chacune — correct, mais linéaire en nombre de clés et coûteux en CPU | Recherche indexée sur un préfixe de clé, puis une seule comparaison à temps constant | Avant la production |
| **H-8** | **En-têtes de sécurité** | Non entièrement configurés | Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy | Avant la production |
| **H-9** | **Gestion des secrets** | Secrets détenus dans la configuration d'environnement sur le serveur | Permissions de fichier restreintes, exclusion du dépôt, procédure de rotation documentée ; coffre à secrets géré si le choix d'hébergement le permet | Avant la production |
| **H-10** | **Gestion des dépendances et des correctifs** | Au cas par cas | Analyse mensuelle de vulnérabilité des dépendances ; fenêtre de correctifs documentée ; correctifs critiques sous 7 jours | En continu dès la mise en service |

**Position de réception :** H-1, H-4 et H-6 sont traités comme **bloquants** — le pilote ne démarre pas sans eux. Les autres sont planifiés avant la mise en production de novembre et vérifiés par la liste de contrôle de la section 16.

---

## 13. Continuité d'activité et résilience

| Scénario | Impact | Réponse | Objectif |
|---|---|---|---|
| Réseau mobile indisponible sur le terrain | Aucun sur l'encaissement | Mode hors ligne ; file locale ; synchronisation à la reconnexion | Fonctionnement continu |
| Plateforme back-office indisponible | L'encaissement continue, les restitutions s'arrêtent | Redondance, supervision, procédure de redémarrage documentée | RTO 4 h |
| Corruption ou perte de la base | Perte de données potentielle | Sauvegarde complète quotidienne chiffrée + binlog continu ; test de restauration trimestriel | RPO 15 min |
| Terminal perdu, volé ou cassé | Un collecteur s'arrête | Terminaux de rechange en stock ; agent réaffecté à un terminal de secours ; données non synchronisées récupérées si l'appareil revient | Le jour même |
| SUNULIFE indisponible | La fraîcheur des données de polices se dégrade | Miroir local + remontée en file avec reprise ; repli Excel du Besoin 2 du TdR | Aucun impact sur l'encaissement |
| Opérateur Mobile Money indisponible | Le canal MoMo s'arrête | L'encaissement espèces continue ; les paiements en attente sont résolus par rapprochement | Aucun impact sur l'encaissement |
| Perte du centre de données | Panne totale | Sauvegardes chiffrées hors site ; procédure de reconstruction documentée ; délai de rétablissement contractuel | RTO 24 h |
| Rançongiciel | Chiffrement de l'environnement | Sauvegardes hors site immuables ; moindre privilège ; procédure de restauration testée trimestriellement | RTO 24 h |

---

## 14. Procédures de sécurité pour SUNU

La technologie ne fait que la moitié du contrôle. Voici les responsabilités opérationnelles de SUNU.

### 14.1 Entrée et sortie d'un collecteur

**Entrée :** vérification d'identité et enquête de moralité selon la politique RH de SUNU → compte créé par l'Administrateur avec le seul profil Agent collecteur → terminal affecté et consigné → code remis par un canal distinct et changé à la première utilisation → plafond fixé → briefing de sécurité signé (garde du terminal, non-partage du code, discipline des reçus, signalement immédiat en cas de perte).

**Sortie, exécutée le jour même :** terminal restitué et son affectation close → toutes les espèces versées et confirmées → compte désactivé (jamais supprimé) → solde résiduel rapproché à zéro → cartes en possession du collecteur restituées et rapprochées du stock.

### 14.2 Revue des accès

Chaque trimestre, l'Administrateur passe en revue chaque compte et ses habilitations avec le responsable de service concerné ; les comptes inutilisés sont désactivés ; la revue est documentée et conservée.

### 14.3 Stock de cartes

Les cartes vierges sont conservées dans un local fermé sous la responsabilité d'un dépositaire nommé. Les mouvements d'entrée et de sortie sont consignés. Un comptage mensuel rapproche le stock physique des enregistrements d'émission du back-office ; tout écart fait l'objet d'une enquête en tant qu'incident de sécurité.

### 14.4 Gestion des justificatifs

Les codes et mots de passe sont remis par un canal distinct de celui de l'identifiant, changés à la première utilisation, jamais partagés, jamais écrits, jamais transmis par courriel ou messagerie. Aucun collaborateur de SUNU ou de DBS ne demandera jamais à un collecteur ou à un assuré son code.

### 14.5 Sensibilisation

Briefing de sécurité à l'entrée, rappel annuel, et court rappel trimestriel sur les risques qui comptent réellement ici : hameçonnage sur les comptes back-office, discipline des reçus sur le terrain, garde du terminal, et signalement immédiat de tout fait inhabituel.

---

## 15. Cartographie des contrôles (ISO/IEC 27001:2022 Annexe A)

| Domaine de contrôle | Référence | Traité en |
|---|---|---|
| Politiques de sécurité de l'information | A.5.1 | §14, politique de sécurité annexée à la mise en service |
| Séparation des tâches | A.5.3 | §5.2, §8.3 |
| Relations avec les autorités | A.5.5 | §17 (CIL, CIMA) |
| Renseignement sur les menaces | A.5.7 | §12 H-10 |
| Sécurité dans les relations fournisseurs | A.5.19–5.22 | §10, contrat de sous-traitance §6.5 |
| Gestion des incidents | A.5.24–5.28 | §16 |
| Continuité d'activité | A.5.29–5.30 | §13 |
| Exigences légales et contractuelles | A.5.31–5.34 | §1.4, §6.5 |
| Sélection et conditions d'emploi | A.6.1–6.2 | §14.1 |
| Sensibilisation et formation | A.6.3 | §14.5 |
| Processus disciplinaire | A.6.4 | Politique RH de SUNU |
| Sécurité physique | A.7.1–7.4 | §7.2, §14.3, hébergement |
| Équipements et mise au rebut sécurisée | A.7.9–7.14 | §9, §14.1 |
| Terminaux utilisateurs | A.8.1 | §9 |
| Droits d'accès privilégiés | A.8.2 | §5.1, §5.2 |
| Restriction d'accès à l'information | A.8.3 | §5.2, §6.4 |
| Authentification | A.8.5 | §5.1, §5.3 |
| Gestion de la capacité | A.8.6 | Doc 1 §10.3 |
| Protection contre les codes malveillants | A.8.7 | §9, durcissement des hôtes |
| Gestion des vulnérabilités | A.8.8 | §12 H-10, §16.3 |
| Gestion de la configuration | A.8.9 | §12 H-9 |
| Masquage des données | A.8.11 | §6.4, §11.2 |
| Prévention des fuites de données | A.8.12 | §8.4, §11.3 |
| Sauvegarde | A.8.13 | §13 |
| Journalisation et supervision | A.8.15–8.16 | §11 |
| Sécurité et cloisonnement réseau | A.8.20–8.22 | Doc 1 §10.2, §10 |
| Développement sécurisé | A.8.25–8.29 | §16.3 |
| Gestion des changements | A.8.32 | §16.3 |
| Données de test | A.8.33 | §16.3 — données anonymisées en recette |

---

## 16. Assurance

### 16.1 Réponse aux incidents

| Étape | Action | Délai |
|---|---|---|
| 1. Détecter | Alerte, signalement utilisateur ou supervision | En continu |
| 2. Qualifier | Gravité évaluée selon l'échelle ci-dessous | ≤ 1 h |
| 3. Contenir | Désactiver le compte, révoquer la clé, isoler le terminal, bloquer la carte | ≤ 4 h (critique) |
| 4. Notifier | Contact incident SUNU ; CIL en cas de violation de données personnelles ; personnes assurées si la loi l'exige | Selon le délai légal |
| 5. Éradiquer et rétablir | Corriger, restaurer depuis sauvegarde si nécessaire, vérifier l'intégrité | Selon la gravité |
| 6. Revoir | Rapport post-incident écrit, cause racine, actions correctives avec responsables et dates | ≤ 10 jours ouvrés |

| Gravité | Définition | Exemples |
|---|---|---|
| **Critique** | Violation de données personnelles, fraude systémique, panne totale | Exfiltration de la base, compromission d'un compte administrateur |
| **Élevée** | Fraude localisée, perte d'un terminal avec données, compromission d'intégration | Collecteur malhonnête, terminal volé |
| **Moyenne** | Tentative sans impact, dysfonctionnement isolé | Tentative de force brute bloquée |
| **Faible** | Anomalie mineure | Erreur de configuration isolée |

### 16.2 Tests avant mise en service

| Test | Périmètre | Quand |
|---|---|---|
| Test d'authentification et d'habilitations | Chaque profil face à chaque action restreinte, y compris tests négatifs | Avant la recette |
| Analyse de vulnérabilité applicative | Back-office et API — OWASP Top 10 | Avant la recette et avant la production |
| Revue de l'application TPE | Alignée MASVS : stockage, cryptographie, transport, anti-altération | Avant le pilote |
| Tests de scénarios de fraude | Chaque scénario du §3.2 rejoué en recette | Pendant la recette |
| Test de restauration | Restauration complète depuis sauvegarde dans un environnement vierge | Avant la production |
| Test de charge | Volume d'encaissement de pointe | Avant la production |
| Test d'intrusion indépendant | Recommandé, commandé par SUNU | Recommandé avant la production |

### 16.3 Développement sécurisé et gestion des changements

Code source versionné ; revue par les pairs avant fusion ; aucun secret dans le dépôt ; environnements séparés sans données réelles en recette ; livraisons versionnées, documentées dans un journal des changements et approuvées par SUNU avant production ; retour arrière documenté pour chaque livraison ; analyse mensuelle de vulnérabilité des dépendances ; correctifs critiques sous 7 jours.

---

## 17. Matrice de responsabilités

| Domaine | SUNU | DBS |
|---|---|---|
| Qualité de responsable de traitement, déclaration CIL | **Responsable** | Fournit la description technique |
| Sécurité applicative et d'infrastructure | Valide | **Responsable** |
| Sécurité de l'hébergement (si hébergé par SUNU) | **Responsable** | Conseille |
| Création et revue des comptes utilisateurs | **Responsable** | Fournit l'outillage et la procédure |
| Sélection et intégration des collecteurs | **Responsable** | Fournit le contenu du briefing de sécurité |
| Garde physique des terminaux et du stock de cartes | **Responsable** | Fournit le registre et les procédures |
| Définition des plafonds et confirmation des versements | **Responsable** | Fournit le mécanisme |
| Supervision et traitement des alertes | Traite les alertes métier | Traite les alertes techniques |
| Réponse aux incidents | Décide, notifie les autorités | Investigue, contient, remédie |
| Sauvegardes et tests de restauration | Valide les résultats | **Responsable** (exécute) |
| Application des correctifs de sécurité | Approuve les fenêtres | **Responsable** |
| Sécurité côté SUNULIFE | **Responsable** | Sécurise le point d'entrée d'intégration de notre côté |

---

## 18. Liste de contrôle de réception sécurité (jalon de mise en service)

À signer conjointement avant la mise en production.

**Bloquant — aucune mise en production sans ces points**

- [ ] HTTPS/TLS 1.2+ imposé sur chaque canal ; HTTP en clair refusé (H-1)
- [ ] Épinglage de certificat actif dans l'application TPE (H-1)
- [ ] Clé cryptographique mobile codée en dur supprimée ; clés par appareil et chiffrement authentifié en place (H-4)
- [ ] Journalisation en clair des codes à usage unique désactivée et retirée de la configuration de production (H-6)
- [ ] Jetons de session collecteur bornés et révocables en place (H-2)
- [ ] Limitation de débit des API active (H-3)
- [ ] MFA imposée pour les profils Administrateur et DSI (H-5)
- [ ] Chaque profil testé face à chaque action restreinte, tests négatifs compris
- [ ] Sauvegarde chiffrée en fonctionnement et restauration complète testée avec succès
- [ ] Piste d'audit vérifiée complète sur les opérations d'encaissement, de versement et de carte
- [ ] Rejet des références en doublon vérifié au niveau de la base
- [ ] Aucun secret présent dans le dépôt de code ni dans l'APK
- [ ] Déclaration CIL effectuée par SUNU
- [ ] Contrat de sous-traitance signé

**Requis avant la production**

- [ ] En-têtes de sécurité configurés (H-8)
- [ ] Recherche de clé d'API durcie (H-7)
- [ ] Procédure de gestion des secrets appliquée et rotation documentée (H-9)
- [ ] Analyse de vulnérabilité effectuée sans constat critique ou élevé non résolu
- [ ] Alertes configurées et routées vers des destinataires nommés
- [ ] Terminaux durcis : mode kiosque, sources inconnues désactivées, verrouillage d'écran
- [ ] Briefings de sécurité des collecteurs signés
- [ ] Contacts de réponse aux incidents publiés des deux côtés
- [ ] Procédure de revue des accès planifiée et responsable désigné

---

*Fin du Document 2.*
