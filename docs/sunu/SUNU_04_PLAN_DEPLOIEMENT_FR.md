# SUNU Assurances Vie Burkina Faso
## Solution mobile de collecte de primes sur terminaux TPE
### Document 4 — Plan et calendrier de déploiement

| Rubrique | Valeur |
|---|---|
| Document | Plan et calendrier de déploiement |
| Version | 1.0 (projet soumis à revue) |
| Date | 31 août 2026 |
| Destinataire | SUNU Assurances Vie Burkina Faso |
| Émetteur | DBS SA — Digital Business Services |
| Répond à | Courriel d'exigences SUNU §5 — « un déploiement initial avant fin septembre 2026 ; le lancement effectif en production au plus tard en novembre 2026 », et §6 — réunion de direction du 1er octobre 2026 |
| Documents liés | Doc 1 — Architecture technique ; Doc 2 — Sécurité ; Doc 3 — Guide utilisateur |

---

## 1. Objectifs et dates fixes

| # | Jalon | Date | Origine |
|---|---|---|---|
| **M0** | Documentation projet remise à SUNU pour revue | **début septembre 2026** | Le présent ensemble de quatre documents |
| **M1** | Environnement de recette mis à disposition de SUNU | **14 septembre 2026** | Déduit — prérequis de M2 |
| **M2** | Tests de réception achevés et signés | **25 septembre 2026** | Déduit — prérequis de M3 |
| **M3** | **Déploiement initial (pilote) en service sur le terrain** | **30 septembre 2026** | **SUNU — exigence ferme** |
| **M4** | **Présentation de la solution à la Direction Générale de SUNU** | **1er octobre 2026** | **SUNU — date fixe** |
| **M5** | Période d'exploitation du pilote, corrections, durcissement | 1er–31 octobre 2026 | Déduit |
| **M6** | Décision de passage en production (go/no-go) | **28 octobre 2026** | Déduit |
| **M7** | **Lancement effectif en production** | **début novembre 2026** | **SUNU — exigence ferme** |

**M3 et M4 sont à trois et quatre semaines.** Le présent plan est construit à rebours de ces dates et énonce clairement ce qui doit être vrai pour qu'elles tiennent.

---

## 2. Appréciation honnête du calendrier

SUNU a souligné « l'importance du strict respect du calendrier pour le déploiement initial ». Cela mérite une réponse franche plutôt qu'optimiste.

### 2.1 Ce qui rend la date atteignable

- La solution est une **personnalisation d'une plateforme déjà en production**, non une construction nouvelle (Doc 1 §2.1, §4). Les agents, les terminaux, les rôles et habilitations, les clients, les cartes avec leur cycle de vie complet, les transactions avec frais et commissions, la piste d'audit, le SMS, les restitutions avec export Excel, le fonctionnement hors ligne du TPE, l'impression des reçus et la lecture/écriture NFC des cartes existent et fonctionnent aujourd'hui.
- La plateforme comporte déjà un **module d'affiliation volontaire et de cotisations périodiques** structurellement identique au modèle proposition → police → prime de SUNU. Il s'agit d'une adaptation, non d'une invention.
- Le pilote porte délibérément sur **15 terminaux dans un périmètre maîtrisé**, non sur un déploiement national.

### 2.2 Ce qui menace réellement la date

| # | Menace | Nature | Responsable |
|---|---|---|---|
| **1** | **Approvisionnement matériel** : 15 terminaux, 5 000 cartes, la station de personnalisation | Hors logiciel. Les délais dépendent du fournisseur et de la douane et ne se compriment pas en travaillant davantage | SUNU + fournisseur |
| **2** | **Enrôlement Mobile Money** auprès des opérateurs ou d'un agrégateur | Processus commercial et KYC chez un tiers ; généralement plusieurs semaines | SUNU |
| **3** | **API SUNULIFE** : existence, documentation, accès de test | Inconnue à ce jour (Doc 1, OP-1). Impossible à planifier tant que la réponse manque | SUNU / éditeur SUNULIFE |
| **4** | **Décision d'hébergement et mise à disposition des environnements** | Bloque l'environnement de recette, qui bloque tout l'aval | SUNU (Doc 1, OP-5) |
| **5** | **Disponibilité de SUNU pour la recette et la formation** durant les deux dernières semaines de septembre | La réception ne peut pas être déléguée au fournisseur | SUNU |
| **6** | **Décision sur la technologie de carte** (magnétique ou sans contact) | Change ce qui est commandé ; commander le mauvais produit coûte plus cher que le délai | SUNU (Doc 1, OP-3) |

**Quatre des six relèvent de SUNU.** Ce n'est pas une critique — c'est la réalité d'un projet d'intégration, et c'est pourquoi la section 6 liste des dépendances datées avec des responsables nommés.

### 2.3 La décision de cadrage qui protège la date

Plutôt que de tout risquer, le périmètre du pilote est délibérément resserré sur le **cœur du TdR**, les demandes complémentaires du courriel étant échelonnées sur octobre :

| Livré pour le pilote — 30 septembre | Livré durant octobre, en service pour novembre |
|---|---|
| Saisie de souscription avec photos de la proposition et de la CNIB, GPS | Encaissement Mobile Money |
| Frais de police et première prime, deux reçus | Carte virtuelle (QR) à l'échelle |
| Émission de carte physique et carte multi-polices | Synchronisation API SUNULIFE en lecture, phase 1 **(si OP-1 est tranché)** |
| Encaissement de prime par carte, sélection de police, reçu | Améliorations issues des retours du pilote |
| Plafond du collecteur, déclaration et confirmation des versements | Points de durcissement restants (Doc 2 §12) |
| Back-office : profils, habilitations, encaissements, exports | |
| **Export Excel pour SUNULIFE** (TdR Besoin 2) | |
| **Import en masse des clients SUNULIFE existants** (Doc 1 §8.4 phase 0) | |
| Points de sécurité bloquants H-1, H-4, H-6 (Doc 2 §12) | |

**Pourquoi ce découpage fonctionne.** Le pilote livre 100 % des besoins énoncés dans le TdR. Les ajouts du courriel — Mobile Money, QR, API SUNULIFE — sont de véritables exigences, mais aucun d'eux n'est nécessaire pour qu'un collecteur souscrive un client et encaisse une prime sur le terrain le 30 septembre. Vouloir tous les traiter à cette date mettrait le cœur en péril au profit de fonctions dépendant de tiers que nous ne maîtrisons pas.

**Point déterminant : l'import en masse de la phase 0 fait que l'exigence prioritaire de SUNU — que les agents voient les clients déjà présents dans SUNULIFE — est satisfaite dès le pilote même si l'API n'est pas disponible**, au moyen d'une extraction périodique. L'intention de l'exigence est honorée à la date prévue ; le mécanisme est amélioré ensuite.

Si SUNU préfère un autre découpage, c'est la conversation à tenir le 1er octobre, l'arbitrage étant alors explicite plutôt que découvert dans la dernière semaine de septembre.

---

## 3. Calendrier — septembre (jusqu'au pilote)

### Semaine 1 · 1er–6 septembre — Fondations et levée des blocages

| Chantier | Activité | Responsable |
|---|---|---|
| Gouvernance | Lancement ; validation du plan ; désignation des contacts des deux côtés ; ouverture du registre des points ouverts | Les deux |
| Documents | SUNU revoit les Documents 1 à 4 ; questions posées par écrit | SUNU |
| **Blocages** | **Décisions sur OP-3 (carte), OP-4 (MoMo), OP-5 (hébergement)** | **SUNU** |
| **Blocages** | **Questionnaire SUNULIFE transmis à l'éditeur (Doc 1 §8.5)** | **SUNU** |
| Approvisionnement | Commandes des terminaux, des cartes et de la station de personnalisation passées | SUNU |
| Infrastructure | Mise à disposition des environnements de recette et de production ; certificats TLS ; DNS | DBS |
| Développement | Modèle produit et police d'assurance ; proposition adaptée du module d'affiliation | DBS |
| Sécurité | **H-1 (HTTPS de bout en bout, épinglage de certificat)** et **H-6 (journalisation en clair supprimée)** | DBS |

**Jalon G1 — fin de semaine 1 :** OP-3, OP-4 et OP-5 tranchés ; matériel commandé ; environnements disponibles. *Si OP-5 n'est pas tranché cette semaine, M1 glisse et M3 est en risque.*

### Semaine 2 · 7–13 septembre — Construction du cœur

| Chantier | Activité | Responsable |
|---|---|---|
| Développement | Moteur d'encaissement des primes ; modèles de reçus ; plafond et versement du collecteur ; émission de carte multi-polices et export de personnalisation | DBS |
| TPE | Assistant de souscription adapté ; capture des documents et de la CNIB ; GPS ; écran de sélection de police ; indicateur de plafond | DBS |
| Back-office | File de revue des propositions ; vues des encaissements ; **export Excel pour SUNULIFE** | DBS |
| Données | **Import phase 0** : SUNU fournit l'extraction clients et polices ; mapping et chargement de test | SUNU → DBS |
| Sécurité | **H-4 (cryptographie mobile)**, **H-2 (jetons de session)**, **H-3 (limitation de débit)** | DBS |
| Paramétrage | Produits, tarifs, plafonds et profils configurés à partir des réponses de SUNU aux OP-6, OP-7, OP-8, OP-10 | Les deux |
| Documents | Documents mis à jour à partir des commentaires de revue de SUNU | DBS |

**Jalon G2 — fin de semaine 2 :** parcours de bout en bout démontrable en environnement de développement (souscrire → encaisser → reçu → back-office → export). Extraction de phase 0 reçue.

### Semaine 3 · 14–20 septembre — Recette

| Chantier | Activité | Responsable |
|---|---|---|
| **M1** | **Environnement de recette ouvert à SUNU avec des données synthétiques — 14 septembre** | DBS |
| Tests | SUNU exécute les scénarios de réception de la section 4 ; anomalies consignées quotidiennement | SUNU |
| Correction | Cycle quotidien de tri et de correction des anomalies | DBS |
| Matériel | Terminaux reçus, application installée, appareils durcis, terminaux enregistrés et affectés | Les deux |
| Cartes | Cartes vierges reçues ; station de personnalisation installée et testée de bout en bout | Les deux |
| **Formation** | **Collecteurs (1 jour), back-office, superviseurs, agent cartes, administrateur, DSI** — selon l'annexe du Doc 3 | DBS + SUNU |
| Sécurité | Test d'authentification et d'habilitations, tests négatifs, analyse de vulnérabilité | DBS |

**Jalon G3 — 20 septembre :** aucune anomalie bloquante ouverte ; tous les utilisateurs formés ; terminaux disponibles en main.

### Semaine 4 · 21–27 septembre — Réception et préparation

| Chantier | Activité | Responsable |
|---|---|---|
| Tests | Deuxième passe de recette sur les anomalies corrigées ; scénarios de fraude du Doc 2 §3.2 rejoués | SUNU |
| **M2** | **Recette signée — 25 septembre** | **SUNU** |
| Production | Environnement de production finalisé ; sauvegarde en fonctionnement ; **test de restauration exécuté** ; supervision et alertes configurées et routées | DBS |
| Données | Import de phase 0 chargé en production ; volumes et contrôles par sondage validés par SUNU | Les deux |
| Opérations | Périmètre du pilote confirmé : quels collecteurs, quelles zones, quels produits ; compte bancaire et caisse de versement paramétrés | SUNU |
| Sécurité | **Liste de contrôle de mise en service (Doc 2 §18) complétée et signée conjointement** | Les deux |
| Documents | Documents 1 à 4 finalisés avec les copies d'écran de recette ; **versions françaises livrées** | DBS |

**Jalon G4 — 27 septembre : GO / NO-GO du pilote.** Critères en section 5.

### Semaine 5 · 28–30 septembre — Lancement du pilote

| Jour | Activité |
|---|---|
| 28 sept. | Déploiement en production ; tests de fumée ; terminaux synchronisés en production ; briefing final des collecteurs |
| 29 sept. | **Lancement maîtrisé : un petit nombre de collecteurs, transactions réelles, accompagnement rapproché** |
| 30 sept. | **M3 — pilote complet : 15 collecteurs sur le terrain.** Présence de support renforcée ; revue de fin de journée |

### 1er octobre — M4, réunion de direction

| Point | Contenu |
|---|---|
| Démonstration en direct | Une souscription réelle et un encaissement réel exécutés sur un terminal en séance |
| Architecture | Document 1, sections 3, 4, 6 — y compris l'approche SUNULIFE |
| Sécurité | Document 2, sections 4, 8, 12 — y compris le programme de durcissement, présenté ouvertement |
| Fonctions demandées | Carte physique, carte virtuelle QR, Mobile Money — état d'avancement et dates de livraison en octobre |
| SUNULIFE | Section 8 et **le questionnaire du §8.5 — le point décisif à trancher lors de cette réunion** |
| Premiers chiffres | Encaissements du pilote sur les deux premiers jours |
| Calendrier jusqu'à novembre | Le présent document, section 3 |
| Décisions demandées | Accès SUNULIFE, confirmation de la voie Mobile Money, périmètre de production |

---

## 4. Calendrier — octobre et novembre (jusqu'à la production)

### Octobre · semaines 1 à 4 — Exploitation du pilote et achèvement

| Semaine | Objet |
|---|---|
| **1er–4 oct.** | Accompagnement intensif du pilote ; tri quotidien des anomalies ; premier cycle de rapprochement ; recueil des retours du terrain |
| **5–11 oct.** | **Intégration Mobile Money** (sous réserve de l'enrôlement OP-4) ; carte virtuelle QR à l'échelle ; corrections issues des retours du terrain |
| **12–18 oct.** | **SUNULIFE phase 1** en lecture seule *si OP-1 est tranché*, sinon le repli par fichiers ; points de durcissement restants H-5, H-7, H-8, H-9 |
| **19–25 oct.** | Tests de non-régression complets ; test de charge ; **second test de restauration** ; documentation finale et versions françaises vérifiées ; formation de la population d'utilisateurs élargie |
| **26–28 oct.** | Bilan du pilote ; revue d'aptitude à la production ; **M6 — go/no-go le 28 octobre** |

### Novembre — Lancement en production

| Période | Activité |
|---|---|
| **Premiers jours de novembre** | **M7 — lancement effectif en production.** Extension progressive de la population de collecteurs ; support renforcé pendant deux semaines |
| Semaines 2 à 4 | Stabilisation ; supervision ; rapprochement hebdomadaire ; premier rapport mensuel à SUNU |
| Fin novembre | Revue post-lancement ; passage au support standard ; feuille de route de la phase 2 (intégration SUNULIFE en écriture, TdR Besoin 4) |

---

## 5. Critères de réception et jalons go/no-go

### 5.1 Scénarios de réception en recette (M2)

Chaque scénario est exécuté par SUNU et signé.

| # | Scénario | Critère de réussite |
|---|---|---|
| T1 | Souscription complète avec photographies et GPS | Dossier complet et lisible au back-office avec tous les champs exigés par le TdR |
| T2 | Frais de police et première prime | Deux reçus distincts imprimés avec les montants exacts |
| T3 | Souscription entièrement hors ligne, puis reconnexion | Rien de perdu, rien de dupliqué |
| T4 | Émission de carte pour un assuré mono-police | Carte imprimée, encodée, active |
| T5 | Émission de carte pour un assuré **multi-polices** | Toutes les polices présentes sur la carte et affichées sur le terminal |
| T6 | Encaissement de prime par carte | Liste de polices correcte, reçu correct |
| T7 | Encaissement de prime par QR | Même résultat que T6 |
| T8 | Encaissement hors ligne puis synchronisation | Encaissement présent une fois et une seule |
| T9 | Plafond atteint | Le terminal bloque les nouveaux encaissements ; la souscription reste possible |
| T10 | Versement déclaré et confirmé | Solde réduit, terminal libéré |
| T11 | Versement avec écart | Écart consigné et signalé ; solde réduit du montant réel |
| T12 | Export Excel | Le fichier s'ouvre, contient tous les encaissements de la période, champs exploitables pour la saisie SUNULIFE |
| T13 | Import de phase 0 | Un client SUNULIFE existant est retrouvé par un collecteur sur le terrain |
| T14 | Profils et habilitations | Chaque profil ne peut faire que ce que permet le Doc 2 §5.2 — **tests négatifs compris** |
| T15 | Renvoi après expiration réseau | Aucun encaissement en double |
| T16 | Carte bloquée | Carte refusée immédiatement ; le client peut toujours payer par recherche ou par QR |
| T17 | Piste d'audit | Toute action sensible tracée avec acteur, horodatage et valeurs avant/après |
| T18 | Les deux langues | Chaque écran et chaque reçu corrects en français et en anglais |

### 5.2 Jalon G4 — go/no-go du pilote (27 septembre)

**Le GO exige la totalité des points suivants :**

- [ ] Recette signée par SUNU ; aucune anomalie bloquante ou critique ouverte
- [ ] Liste de contrôle sécurité (Doc 2 §18) signée — y compris les points bloquants **H-1, H-4, H-6**
- [ ] 15 terminaux en main, configurés, durcis, affectés à des collecteurs nommés
- [ ] Cartes et station de personnalisation opérationnelles ; au moins 20 cartes produites en test
- [ ] Collecteurs formés et briefings signés
- [ ] Personnel back-office et superviseurs formés
- [ ] Environnement de production opérationnel ; sauvegarde en fonctionnement ; **restauration testée**
- [ ] Données de phase 0 chargées et validées par SUNU
- [ ] Plafonds, produits, tarifs et comptes de versement paramétrés
- [ ] Procédure et contacts de support publiés des deux côtés
- [ ] Procédure de retour arrière documentée et comprise

**Si un point n'est pas satisfait :** un pilote partiel avec moins de collecteurs est préférable à un report intégral — il préserve l'échéance du 30 septembre sur des transactions réelles pendant que l'élément manquant est complété. C'est le repli recommandé, décidé au jalon G4 plutôt qu'improvisé.

### 5.3 Jalon M6 — go/no-go de production (28 octobre)

- [ ] Pilote exploité au moins 3 semaines sans incident critique
- [ ] Toutes les anomalies du pilote clôturées ou acceptées avec contournement
- [ ] Tous les points de durcissement (Doc 2 §12) clôturés
- [ ] Mobile Money en production **ou** formellement reporté par SUNU avec une date
- [ ] SUNULIFE phase 1 en production **ou** repli par fichiers accepté et en fonctionnement
- [ ] Rapprochement conforme pendant 3 semaines consécutives
- [ ] Documentation finale, en français, livrée
- [ ] Population d'utilisateurs élargie formée
- [ ] Second test de restauration réussi
- [ ] Modèle de support convenu pour la production

---

## 6. Dépendances vis-à-vis de SUNU — datées

Chaque ligne est un prérequis du calendrier. Une date manquée décale le plan ; c'est énoncé ici afin que cela ne soit jamais une surprise ultérieure.

| # | Dépendance | Échéance | Bloque | Responsable |
|---|---|---|---|---|
| **D1** | Décision d'hébergement et mise à disposition des environnements (OP-5) | **5 sept.** | M1, donc tout le reste | DSI SUNU |
| **D2** | Décision sur la technologie de carte (OP-3) | **8 sept.** | Commande des cartes ; M3 | Opérations SUNU |
| **D3** | Décision sur la voie Mobile Money et démarrage de l'enrôlement (OP-4) | **8 sept.** | Livraison MoMo en octobre ; M7 | Direction Financière SUNU |
| **D4** | Commande matérielle passée : 15 terminaux, 5 000 cartes, station de personnalisation | **8 sept.** | M3 — le plus long délai du projet | Achats SUNU |
| **D5** | Extraction clients et polices pour l'import de phase 0 (OP-2) | **10 sept.** | T13 ; l'exigence prioritaire SUNULIFE au pilote | DSI SUNU |
| **D6** | Liste des produits du pilote, tarifs et règles de frais de police (OP-7) | **10 sept.** | Paramétrage ; recette | Opérations SUNU |
| **D7** | Contenu du reçu et mentions légales requises (OP-8) | **10 sept.** | Modèles de reçus ; recette | Opérations + Juridique SUNU |
| **D8** | Réponses au questionnaire SUNULIFE (OP-1) | **15 sept.**, ou fermement à M4 | SUNULIFE phase 1 en octobre | DSI SUNU + éditeur |
| **D9** | Montants des plafonds des collecteurs (OP-6) | **15 sept.** | Paramétrage ; recette | Opérations SUNU |
| **D10** | Compte bancaire et caisse désignés pour les versements (OP-10) | **15 sept.** | Paramétrage des versements ; T10 | Direction Financière SUNU |
| **D11** | Testeurs nommés disponibles pour la recette, 14–25 sept. | **14 sept.** | M2 | SUNU |
| **D12** | Collecteurs disponibles pour la formation, semaine du 14 sept. | **14 sept.** | M3 | RH + Opérations SUNU |
| **D13** | Périmètre du pilote défini : collecteurs, zones, produits | **21 sept.** | M3 | Opérations SUNU |
| **D14** | Signature de la recette | **25 sept.** | M3 | SUNU |
| **D15** | Déclaration CIL effectuée (Doc 2 §6.5) | Avant M7 | Mise en production | SUNU (responsable de traitement) |
| **D16** | Contrat de sous-traitance signé | Avant M7 | Mise en production | Les deux |

---

## 7. Registre des risques

Probabilité × Impact, avec la mesure d'atténuation réellement en place — non une formule générique.

| # | Risque | P | I | Atténuation | Responsable |
|---|---|---|---|---|---|
| **R1** | **Matériel non livré à temps pour le 30 septembre** | **Élevée** | **Élevé** | Commander en semaine 1 (D4) ; confirmer par écrit les délais du fournisseur ; **repli : pilote sur un nombre réduit de terminaux**, même 5, pour tenir la date sur des transactions réelles | SUNU |
| **R2** | **API SUNULIFE indisponible ou non documentée** | **Élevée** | Moyen | Architecture conçue dès l'origine avec la phase 0 (import d'extraction) et le phasage 1/2 (Doc 1 §8.4) ; le repli par fichiers est pleinement pris en charge ; **la date n'en dépend pas** | Les deux |
| **R3** | **Enrôlement Mobile Money plus long que prévu** | **Élevée** | Moyen | Démarrer immédiatement (D3) ; agrégateur recommandé comme voie la plus rapide ; le MoMo est hors périmètre du pilote, il menace donc M7 et non M3 | SUNU |
| **R4** | La recette révèle des anomalies majeures | Moyenne | Élevé | Deux semaines de recette avec cycle de correction quotidien ; cœur bâti sur du code éprouvé ; périmètre délibérément resserré | DBS |
| **R5** | Personnel SUNU indisponible pour la recette ou la formation en septembre | Moyenne | **Élevé** | Participants nommés sécurisés en semaine 1 (D11, D12) ; supports de formation prêts à l'avance | SUNU |
| **R6** | Couverture mobile médiocre dans les zones du pilote | Moyenne | Moyen | Le mode hors ligne est une propriété centrale de conception, non une option ; à tester explicitement en zone mal couverte pendant la recette | Les deux |
| **R7** | Résistance ou mauvais usage sur le terrain par les collecteurs | Moyenne | Moyen | Une journée complète de formation avec jeux de rôle ; fiche de poche ; support renforcé la première semaine ; suivi par les superviseurs | SUNU |
| **R8** | Cartes magnétiques choisies, puis incapables de porter la charge utile multi-polices | Moyenne | Élevé | Décision documentée au Doc 1 §7.2 avec recommandation du sans contact ; trancher en semaine 1 (D2) avant de commander | SUNU |
| **R9** | Fraude pendant le pilote | Faible | Élevé | Les plafonds bornent l'exposition ; SMS à l'assuré ; GPS ; registre en ajout seul ; indicateurs de fraude suivis chaque semaine dès le premier jour (Doc 2 §8) | Les deux |
| **R10** | Décision d'hébergement tardive | Moyenne | **Élevé** | D1 datée au 5 septembre ; **repli : hébergement temporaire fourni par DBS pour la recette** afin de ne pas bloquer le calendrier pendant que SUNU finalise l'hébergement de production | Les deux |
| **R11** | Élargissement du périmètre pendant septembre | **Élevée** | **Élevé** | Périmètre gelé au jalon G1 ; tout ajout est évalué et planifié pour octobre ou au-delà — **jamais absorbé silencieusement dans le pilote** | Les deux |
| **R12** | La station de personnalisation arrive avec une interface non documentée | Moyenne | Moyen | Confirmer l'interface auprès du fournisseur à la commande (D4) ; le repli est un export CSV/fichier standard, déjà pris en charge par l'architecture | SUNU |

**Les trois qui décident de l'échéance de septembre : R1, R5, R11.** Deux relèvent de SUNU et un est partagé. Les trois sont maîtrisables — à condition d'être traités en semaine 1.

---

## 8. Responsabilités (RACI)

R = Réalise · A = Approuve (redevable) · C = Consulté · I = Informé

| Activité | Opérations SUNU | DSI SUNU | Finance SUNU | DBS |
|---|---|---|---|---|
| Validation des exigences | **A/R** | C | C | C |
| Architecture technique | C | **A** | I | **R** |
| Conception de la sécurité | I | **A** | I | **R** |
| Développement et paramétrage | C | I | I | **A/R** |
| Approvisionnement matériel | **A/R** | C | C | C |
| Enrôlement Mobile Money | C | C | **A/R** | C |
| Accès SUNULIFE | C | **A/R** | I | C |
| Mise à disposition des environnements | I | **A** | I | **R** |
| Import de données (phase 0) | C | **A/R** | I | **R** |
| Exécution de la recette | **A/R** | R | R | C |
| Animation de la formation | **A** | I | I | **R** |
| Décision go/no-go du pilote | **A** | C | C | C |
| Déploiement en production | C | **A** | I | **R** |
| Confirmation des versements (exploitation) | C | I | **A/R** | — |
| Surveillance de la fraude | **A/R** | C | R | C |
| Déclaration CIL | C | **A/R** | I | C |
| Support en production | C | **A** | I | **R** |

---

## 9. Livrables

| # | Livrable | Format | Date |
|---|---|---|---|
| L1 | Document 1 — Architecture technique | EN puis FR | 31 août (EN) / 25 sept. (FR) |
| L2 | Document 2 — Sécurité | EN puis FR | 31 août (EN) / 25 sept. (FR) |
| L3 | Document 3 — Guide utilisateur (tous profils) | EN puis FR, avec copies d'écran après recette | 31 août (EN) / 25 sept. (final) |
| L4 | Document 4 — Plan de déploiement | EN puis FR | 31 août (EN) / 25 sept. (FR) |
| L5 | Environnement de recette | Système en fonctionnement | 14 sept. |
| L6 | Application TPE personnalisée (APK) | Binaire signé | 14 sept. (recette) / 28 sept. (prod) |
| L7 | Plateforme back-office personnalisée | Système en fonctionnement | 14 sept. (recette) / 28 sept. (prod) |
| L8 | Cahier de recette et résultats | Document | 25 sept. |
| L9 | Liste de contrôle sécurité signée | Document | 27 sept. |
| L10 | Supports de formation et fiches de poche | Document + impression | 14 sept. |
| L11 | Procédures d'exploitation (sauvegarde, incident, support) | Document | 27 sept. |
| L12 | Rapport de bilan du pilote | Document | 26 oct. |
| L13 | Dossier de transfert en production | Document | Nov. |

---

## 10. Modèle de support

| Phase | Couverture | Objectif de réponse |
|---|---|---|
| **Pilote, première semaine (29 sept. – 5 oct.)** | Renforcée, avec présence possible sur le terrain | Critique : immédiat · Élevée : 2 h |
| **Pilote, reste d'octobre** | Heures ouvrées, canal dédié | Critique : 2 h · Élevée : 4 h · Moyenne : 1 jour ouvré |
| **Lancement production, deux premières semaines** | Renforcée | Critique : 1 h · Élevée : 4 h |
| **Production standard** | Heures ouvrées + astreinte pour le critique | Critique : 2 h · Élevée : 1 jour ouvré · Moyenne : 3 jours ouvrés |

| Gravité | Définition |
|---|---|
| **Critique** | Encaissement terrain impossible, ou perte de données |
| **Élevée** | Une fonction majeure indisponible sans contournement |
| **Moyenne** | Fonction dégradée, contournement existant |
| **Faible** | Cosmétique ou demande d'amélioration |

**Escalade :** niveau 1 support interne SUNU (administrateur formé) → niveau 2 technique DBS → niveau 3 direction de projet des deux côtés. Contacts publiés et affichés avant M3.

---

## 11. Retour arrière

| Situation | Action |
|---|---|
| Une livraison introduit une anomalie bloquante | Retour à la version précédente, documenté par livraison ; les encaissements déjà enregistrés ne sont pas affectés — le registre est en ajout seul |
| Le pilote ne peut pas fonctionner du tout | Les collecteurs reviennent aux carnets de reçus pour la zone concernée ; les encaissements saisis sur les terminaux sont récupérés et rapprochés ; le pilote est relancé après correction |
| Un terminal tombe en panne | Réaffecter le collecteur à un terminal de secours ; données non synchronisées récupérées depuis l'appareil d'origine |
| Corruption de données | Restauration depuis sauvegarde (RPO 15 min, RTO 4 h) ; les encaissements détenus sur les terminaux sont resynchronisés, l'idempotence empêchant toute duplication |

Les données de terrain ne sont jamais perdues par un retour arrière : les encaissements demeurent sur le terminal jusqu'à l'acquittement du serveur, et la contrainte de référence unique rend le renvoi sans danger.

---

## 12. Feuille de route post-lancement

| Phase | Contenu | Période indicative |
|---|---|---|
| **Phase 2** | Intégration SUNULIFE en écriture — comptabilisation automatique des encaissements (TdR Besoin 4, S5–S7) | T4 2026 / T1 2027 |
| **Phase 3** | Extension du parc de terminaux au-delà de 15 ; zones supplémentaires | Selon le plan commercial de SUNU |
| **Phase 4** | Application mobile destinée aux assurés : carte virtuelle, historique des paiements, paiement de prime en libre-service | 2027 |
| **Phase 5** | Produits supplémentaires ; déclaration de sinistre sur le terrain ; dispositifs d'intéressement des collecteurs | 2027 |

---

## 13. Synthèse — le calendrier en un coup d'œil

```
SEPTEMBRE 2026
 S1  1–6   ▓▓▓ décisions · achats · environnements · H-1 H-6
           ▲ G1 : OP-3, OP-4, OP-5 tranchés · matériel commandé
 S2  7–13  ▓▓▓ construction du cœur · export Excel · import phase 0 · H-2 H-3 H-4
           ▲ G2 : parcours de bout en bout démontrable
 S3 14–20  ▓▓▓ recette · terminaux reçus · FORMATION
           ▲ M1 (14) : recette ouverte   ▲ G3 (20) : aucune anomalie bloquante
 S4 21–27  ▓▓▓ réception · préparation prod · test de restauration · liste sécurité
           ▲ M2 (25) : recette signée    ▲ G4 (27) : GO/NO-GO DU PILOTE
 S5 28–30  ▓▓▓ déploiement · lancement maîtrisé
           ▲ M3 (30) : ★ PILOTE EN SERVICE — EXIGENCE FERME DE SUNU

OCTOBRE 2026
  1        ▲ M4 : ★ PRÉSENTATION À LA DIRECTION GÉNÉRALE
  1–4      accompagnement du pilote · premier rapprochement
  5–11     Mobile Money · QR à l'échelle
 12–18     SUNULIFE phase 1 (ou repli) · H-5 H-7 H-8 H-9
 19–25     non-régression · charge · restauration · documentation FR finale
 26–28     bilan du pilote      ▲ M6 (28) : GO/NO-GO DE PRODUCTION

NOVEMBRE 2026
  début    ▲ M7 : ★ LANCEMENT EN PRODUCTION — EXIGENCE FERME DE SUNU
  sem. 2–4  stabilisation · supervision · premier rapport mensuel
```

---

*Fin du Document 4.*
