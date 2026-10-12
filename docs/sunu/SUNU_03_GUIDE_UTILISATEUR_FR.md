# SUNU Assurances Vie Burkina Faso
## Solution mobile de collecte de primes sur terminaux TPE
### Document 3 — Guide utilisateur (tous profils)

| Rubrique | Valeur |
|---|---|
| Document | Guide utilisateur |
| Version | 1.0 (projet soumis à revue) |
| Date | 31 août 2026 |
| Destinataire | SUNU Assurances Vie Burkina Faso |
| Émetteur | DBS SA — Digital Business Services |
| Répond à | Courriel d'exigences SUNU §3 — « un guide utilisateur pour les différents profils qui utiliseront la plateforme » |
| Documents liés | Doc 1 — Architecture technique ; Doc 2 — Sécurité ; Doc 4 — Plan de déploiement |
| Remarque | Les copies d'écran sont ajoutées à l'issue de la recette, une fois les écrans figés. Les emplacements sont marqués 〔ÉCRAN〕. |

---

## Comment utiliser ce guide

Chaque profil dispose d'une partie autonome. **Personne n'a besoin de lire tout le document.** N'imprimez que la partie qui vous concerne.

| Si vous êtes… | Lisez | Points d'intérêt |
|---|---|---|
| Agent collecteur sur le terrain | **Partie A** | Tout, plus la fiche de poche du §A.12 |
| Agent back-office | **Partie B** | Tout |
| Superviseur / contrôleur | **Partie C** | Tout, plus le §B.3 |
| Agent d'émission des cartes | **Partie D** | Tout |
| Administrateur | **Partie E** | Tout |
| DSI | **Partie F** | Tout, plus les Documents 1 et 2 |

Commun à tous : §0 (premiers pas), Partie G (dépannage), Partie H (glossaire).

---

## 0. Premiers pas — pour tous

### 0.1 Votre compte

Votre compte est créé par l'administrateur SUNU. Vous recevez :

- un **identifiant** ;
- un **mot de passe ou code provisoire**, transmis séparément de l'identifiant.

À la première connexion, vous devez le changer. Choisissez quelque chose dont vous vous souviendrez sans avoir à l'écrire.

> **Règles de sécurité valables pour tous les profils.** Ne partagez jamais votre mot de passe ni votre code. Ne laissez jamais quelqu'un d'autre travailler sous votre compte — chaque action est enregistrée à votre nom et vous en répondez. Personne, ni chez SUNU ni chez DBS, ne vous demandera jamais votre code. Si vous pensez que quelqu'un le connaît, changez-le immédiatement et prévenez votre superviseur.

### 0.2 Choisir votre langue

L'ensemble de la solution fonctionne en **français et en anglais**, sur le terminal comme au back-office. Changez de langue à tout moment depuis le sélecteur ; votre choix est mémorisé pour la fois suivante.

### 0.3 Si vous êtes bloqué

Après plusieurs tentatives erronées, votre compte se verrouille temporairement. Patientez, puis réessayez. Si vous avez réellement oublié votre mot de passe, contactez l'administrateur : il le réinitialise et vous en définissez un nouveau à la connexion suivante. Personne ne peut lire votre mot de passe existant, pas même la DSI.

---

# PARTIE A — AGENT COLLECTEUR (terminal TPE)

*Vous êtes la personne sur le terrain. Vous souscrivez de nouveaux clients et encaissez les primes. Cette partie est votre manuel complet.*

## A.1 Votre terminal

Votre terminal vous est affecté personnellement et enregistré à votre nom. Vous ne pouvez pas travailler sur le terminal de quelqu'un d'autre, et cette personne ne peut pas travailler sur le vôtre.

| Ce qu'il possède | Ce à quoi cela vous sert |
|---|---|
| Écran tactile | Tout |
| Appareil photo | Photographier la proposition et la CNIB du client |
| Lecteur de carte | Lire la carte SUNU du client |
| Imprimante | Les reçus |
| GPS | Enregistrer le lieu de la souscription ou de l'encaissement |
| SIM / données mobiles | Envoyer les données au back-office |

**Avant de partir le matin :** terminal chargé, rouleau de papier en place plus un rouleau de secours, une connexion réussie tant que vous avez encore un bon réseau.

## A.2 Se connecter

〔ÉCRAN : connexion〕

1. Allumez le terminal, ouvrez l'application SUNU.
2. Saisissez votre **identifiant**.
3. Saisissez votre **code**.
4. Appuyez sur **Se connecter**.

L'écran d'accueil affiche : votre nom, votre terminal, **votre encaisse non versée actuelle** et la marge qu'il vous reste avant votre plafond.

**Le terminal se verrouille tout seul** après une période d'inactivité. Ressaisissez votre code pour continuer — rien n'est perdu.

**Travailler sans réseau.** Vous pouvez vous connecter et travailler hors ligne pendant une période bornée après votre dernière connexion en ligne. Au-delà, le terminal vous demande de vous reconnecter une fois. Prévoyez d'être en zone couverte au moins une fois par jour.

## A.3 L'écran d'accueil et la signification des couleurs

〔ÉCRAN : accueil〕

```
  ┌────────────────────────────────────┐
  │  OUEDRAOGO Ali · TPE-BF-007        │
  │                                    │
  │  Non versé :   185 000 FCFA        │  ← ce que vous détenez
  │  Plafond :     250 000 FCFA        │  ← votre limite
  │  ████████████████░░░░  74%         │
  │                                    │
  │  ┌──────────┐   ┌──────────┐      │
  │  │ NOUVELLE │   │ ENCAISSER│      │
  │  │ SOUSCRIP.│   │ UNE PRIME│      │
  │  └──────────┘   └──────────┘      │
  │  ┌──────────┐   ┌──────────┐      │
  │  │ VERSER   │   │ MA       │      │
  │  │          │   │ JOURNÉE  │      │
  │  └──────────┘   └──────────┘      │
  │                                    │
  │  ⟳ 3 éléments en attente d'envoi   │
  └────────────────────────────────────┘
```

| Couleur de la barre | Signification | Ce que vous devez faire |
|---|---|---|
| **Vert** | Bien en dessous de votre plafond | Continuez |
| **Orange** | Vous approchez de votre plafond | Prévoyez votre versement aujourd'hui |
| **Rouge** | Plafond atteint — **encaissement bloqué** | Versez maintenant (§A.8). Vous pouvez toujours prendre de nouvelles souscriptions |

Le **compteur ⟳** indique les éléments pas encore envoyés au back-office. Il se vide de lui-même dès que vous avez du réseau. Il est normal de le voir sur le terrain.

## A.4 Nouvelle souscription (avec première prime)

*À utiliser lorsqu'un prospect signe une nouvelle police.*

**Avant de toucher au terminal :** remplissez la proposition papier, faites-la signer par le client et ayez sa CNIB à portée de main. Le document papier reste le contrat juridique — le terminal le capture et le transmet.

〔ÉCRAN : assistant de souscription〕

**Étape 1 — Produit.** Sélectionnez le produit (par exemple Micro-Quiétude, Micro Épargne Retraite).

**Étape 2 — Numéro de proposition.** Saisissez le numéro imprimé sur la proposition papier. Saisissez-le avec soin — c'est par lui que le back-office et SUNULIFE rattacheront cette souscription.

**Étape 3 — Identité du client.** Nom, prénom(s), date de naissance, numéro de téléphone, numéro CNIB. Si le client dit être déjà client SUNU, utilisez d'abord **Rechercher un client existant** : s'il est trouvé, ses données se remplissent automatiquement et aucun doublon n'est créé.

**Étape 4 — Photographies.** Trois photographies :
1. la **proposition signée** ;
2. la **CNIB, recto** ;
3. la **CNIB, verso**.

Tenez le terminal bien stable, cadrez le document plein écran, évitez les ombres et les reflets. Après chaque prise, le terminal vous montre le résultat : appuyez sur **Reprendre** si un texte est illisible. *Une photo floue signifie que le back-office ne peut pas traiter le dossier et que quelqu'un vous rappellera à ce sujet.*

**Étape 5 — Localisation.** Le terminal capture le GPS automatiquement. Attendez l'indicateur vert. Si le GPS n'accroche pas — à l'intérieur d'un bâtiment par exemple — sortez un instant ; si cela échoue encore, continuez : la souscription n'est pas bloquée.

**Étape 6 — Frais de police.** Saisissez le montant reçu au titre des frais de police → **Encaisser** → **le terminal imprime le reçu n° 1**. Remettez-le au client.

**Étape 7 — Première prime.** Saisissez le montant de la première prime → **Encaisser** → **le terminal imprime le reçu n° 2**. Remettez-le au client.

> **Ce sont deux reçus distincts et les deux doivent être remis.** Un pour les frais de police, un pour la première prime. C'est une exigence formelle de SUNU.

**Étape 8 — Confirmation.** Un récapitulatif s'affiche. Si vous avez du réseau, le dossier part immédiatement ; sinon il rejoint la file et partira tout seul plus tard. **Dans les deux cas, le client est bien enregistré et détient des reçus valables.**

**Ce qui se passe ensuite.** Le back-office examine le dossier, le valide et commande la carte du client. Le client est informé de la façon dont il la recevra.

## A.5 Encaisser une prime auprès d'un client existant

*C'est votre opération la plus fréquente.*

〔ÉCRAN : encaisser une prime〕

**Étape 1 — Identifier le client.** Trois moyens :

| Moyen | Comment | Quand |
|---|---|---|
| **Carte SUNU** | Appuyez sur **Encaisser une prime**, posez la carte sur le lecteur | Cas normal |
| **QR Code (carte virtuelle)** | Appuyez sur **Scanner le QR**, cadrez le code sur le téléphone du client ou sur ses conditions particulières | Le client n'a pas de carte physique |
| **Recherche manuelle** | Appuyez sur **Rechercher**, saisissez le numéro de police, le nom ou le téléphone | Carte perdue ou illisible |

**Étape 2 — Vérifier le client.** L'écran affiche le nom de l'assuré et la **liste de ses polices actives**. Vérifiez le nom à voix haute avec le client.

**Étape 3 — Choisir la police.** Touchez la police concernée par le paiement. Si le client a plusieurs polices, demandez-lui laquelle — ne devinez pas. L'écran affiche la prime attendue et la prochaine échéance.

**Étape 4 — Saisir le montant.** Tapez le montant reçu. La prime attendue est proposée ; vous pouvez la modifier si le client paie un montant différent.

**Étape 5 — Choisir le mode de paiement.**

| Mode | Ce qui se passe |
|---|---|
| **Espèces** | Vous prenez l'argent. **Cela s'impute sur votre plafond.** |
| **Mobile Money** | Saisissez le numéro du client. **Le client reçoit une invite sur son propre téléphone et y saisit son propre code.** Attendez la confirmation sur votre écran. **Cela ne s'impute pas sur votre plafond** — l'argent va directement à SUNU |

> **Ne demandez jamais à un client son code Mobile Money, et ne le saisissez jamais vous-même.** Le client le saisit sur son propre appareil. Quiconque demande ce code commet une fraude.

**Étape 6 — Reçu.** Le terminal imprime le reçu. **Remettez-le au client.** Pour le Mobile Money, le reçu ne s'imprime **qu'après confirmation du paiement** — s'il n'est pas confirmé, pas de reçu, et aucun argent n'a bougé.

## A.6 Réimprimer un reçu

**Ma journée → trouvez l'encaissement → Réimprimer.** La copie porte la mention **DUPLICATA**. Chaque réimpression est enregistrée. Utilisez-la lorsque l'imprimante se bloque ou que le papier se déchire — pas pour produire un second « original ».

## A.7 Suivre votre journée

**Ma journée** affiche : le nombre d'encaissements, le total encaissé, la répartition espèces / Mobile Money, la liste des opérations du jour et les éléments restant à synchroniser.

Consultez-la avant de terminer votre journée. **Les espèces dans votre sacoche doivent être égales au total espèces affiché.** Si ce n'est pas le cas, ne rentrez pas chez vous sans prévenir votre superviseur.

## A.8 Verser (libérer votre plafond)

*À faire dès que la barre passe à l'orange, et toujours avant qu'elle ne passe au rouge.*

**Étape 1.** Déposez physiquement l'argent : soit à la banque sur le compte SUNU désigné, soit à une caisse SUNU.

**Étape 2.** Sur le terminal : **Verser** →
- montant déposé ;
- mode (dépôt bancaire / caisse) ;
- référence bancaire ou numéro de bordereau ;
- **photographie du bordereau de dépôt** (vivement recommandée — elle accélère la confirmation).

**Étape 3.** Validez. Le statut devient **Déclaré, en attente de confirmation**.

**Étape 4.** Le superviseur le vérifie au regard du relevé bancaire ou du comptage en caisse et le confirme. Votre solde diminue, la barre repasse au vert et l'encaissement est débloqué. C'est généralement le jour même ; cela dépend du moment où le relevé bancaire est disponible.

> **Déclarer un versement ne libère pas à lui seul votre plafond.** Seule la confirmation du superviseur le fait. Versez donc *avant* d'être bloqué, pas après.

## A.9 Travailler sans réseau

Tout ce qui suit fonctionne hors ligne :

| Fonctionne hors ligne | Nécessite le réseau |
|---|---|
| Se connecter (dans la période hors ligne suivant votre dernière connexion en ligne) | La toute première connexion sur un terminal neuf |
| Saisie complète d'une souscription, photos, GPS | Rechercher un client absent de votre cache local |
| Encaisser depuis une carte ou un QR (client en cache) | Les paiements Mobile Money |
| Imprimer les reçus | Confirmer un versement |
| Consulter votre journée | |

Le compteur ⟳ indique ce qui est en attente. Dès que vous retrouvez du réseau, tout part automatiquement. **Ne réinstallez pas l'application et n'effacez pas les données de l'application tant que des éléments sont en attente** — vous détruiriez des encaissements qui ne sont pas encore parvenus au back-office. Si le compteur refuse de se vider pendant plus d'une journée, appelez le support.

## A.10 Ce que vous ne devez jamais faire

- Ne jamais encaisser d'argent sans imprimer de reçu.
- Ne jamais garder la carte d'un client.
- Ne jamais demander le code Mobile Money d'un client.
- Ne jamais prêter votre terminal ni votre code.
- Ne jamais dépasser le délai autorisé pour verser.
- Ne jamais promettre quoi que ce soit à un client au sujet de son contrat — c'est le rôle du back-office, pas le vôtre.

## A.11 En cas de problème

| Situation | Que faire |
|---|---|
| Terminal perdu ou volé | **Appelez immédiatement votre superviseur.** Le compte est désactivé à distance. Il n'y a aucune espèce sur l'appareil |
| Terminal cassé | Signalez-le ; un terminal de secours vous est affecté. Les encaissements non encore envoyés sont récupérés si l'appareil est réparé |
| Carte illisible | Utilisez la recherche manuelle ; signalez la carte pour qu'un remplacement soit émis |
| Le client conteste un paiement | Réimprimez le reçu, notez la référence, renvoyez au back-office. Ne remboursez pas de votre poche |
| Argent encaissé mais le client est parti avant l'impression du reçu | Notez la référence, appelez le superviseur le jour même. **Ne supprimez rien** |
| Montant erroné saisi | N'essayez pas de le corriger vous-même. Signalez-le : le back-office établit une écriture de correction |

## A.12 Fiche de poche — à imprimer et conserver

```
 ┌────────────────────────────────────────────────┐
 │  SUNU — AGENT COLLECTEUR · AIDE-MÉMOIRE        │
 ├────────────────────────────────────────────────┤
 │  NOUVEAU CLIENT                                 │
 │   Proposition papier signée → Nouvelle          │
 │   souscription → produit → n° proposition →     │
 │   identité → 3 photos → GPS → frais de police   │
 │   (reçu 1) → première prime (reçu 2)            │
 │                                                 │
 │  CLIENT EXISTANT                                │
 │   Encaisser une prime → carte / QR / recherche →│
 │   vérifier le NOM → choisir la POLICE →         │
 │   montant → espèces ou MoMo → reçu              │
 │                                                 │
 │  LA BARRE                                       │
 │   vert = continuez · orange = versez aujourd'hui│
 │   rouge = bloqué, versez maintenant             │
 │                                                 │
 │  TOUJOURS                                       │
 │   ✓ un reçu pour chaque paiement                │
 │   ✓ vérifier le nom avant d'encaisser           │
 │   ✓ verser avant d'être bloqué                  │
 │   ✗ ne jamais demander le code MoMo du client   │
 │   ✗ ne jamais prêter terminal ni code           │
 │                                                 │
 │  Support : ___________  Superviseur : ________  │
 └────────────────────────────────────────────────┘
```

---

# PARTIE B — AGENT BACK-OFFICE

*Vous traitez ce qui remonte du terrain : dossiers de souscription, encaissements, exports, dossiers clients.*

## B.1 Se connecter et votre tableau de bord

Ouvrez l'adresse du back-office dans votre navigateur, saisissez vos identifiants. 〔ÉCRAN : tableau de bord〕

Votre tableau de bord affiche : les encaissements et totaux du jour, les **propositions en attente de revue** (votre file principale), les collecteurs actifs et l'état de leur plafond, les anomalies à traiter et les derniers exports.

## B.2 Examiner les dossiers de souscription — votre tâche centrale

**Menu : Souscriptions → En attente de revue.** Chaque ligne est un dossier envoyé du terrain.

Pour chaque dossier :

1. Ouvrez-le. À gauche, les données saisies ; à droite, les photographies.
2. **Vérifiez les photographies.** La proposition est-elle lisible ? Est-elle signée ? La CNIB est-elle lisible sur les deux faces ?
3. **Vérifiez la cohérence.** Le nom sur la proposition correspond-il au nom saisi ? Le numéro de proposition correspond-il au document papier ? Le produit correspond-il ?
4. **Vérifiez les paiements.** Frais de police et première prime présents, et cohérents avec le tarif du produit ?
5. **Vérifiez l'absence de doublon.** Le système signale un client existant possible par CNIB ou par téléphone. S'il s'agit de la même personne, utilisez **Fusionner** plutôt que de créer un second dossier.

Puis décidez :

| Décision | Effet |
|---|---|
| **Accepter** | Police créée, émission de carte mise en file (Partie D), dossier clos |
| **Demander une correction** | Retourné au collecteur avec votre commentaire (par exemple « photo de la CNIB illisible ») |
| **Rejeter** | Dossier clos avec motif obligatoire. **Les encaissements déjà effectués ne sont pas annulés** — l'argent reçu reste enregistré. Tout remboursement relève d'une décision de gestion distincte |

> **N'acceptez jamais un dossier dont vous ne pouvez pas lire les photographies.** La photographie est la preuve de la souscription. Demandez une correction — cela coûte deux minutes au collecteur et évite un litige plus tard.

## B.3 Consulter les encaissements

**Menu : Encaissements.** Filtrez par date, collecteur, produit, police, mode de paiement, montant, statut.

Chaque encaissement affiche : date et heure, collecteur, terminal, assuré, police, montant, mode, numéro de reçu, référence unique, position GPS lorsqu'elle a été capturée, et l'état de transmission vers SUNULIFE.

Vues utiles : *Aujourd'hui*, *Ce collecteur*, *Mobile Money en attente*, *Non encore transmis à SUNULIFE*.

## B.4 Export Excel — saisie dans SUNULIFE

*C'est l'opération que le TdR exige explicitement.*

1. **Menu : Rapports → Encaissements.**
2. Fixez la période et les filtres éventuels (collecteur, produit, mode de paiement).
3. Choisissez le format **Excel (XLSX)**.
4. **Générer**. Le rapport est produit en arrière-plan — vous pouvez continuer à travailler.
5. Lorsque le statut affiche **Prêt**, cliquez sur **Télécharger**.

Le fichier contient une ligne par encaissement avec les champs requis par SUNULIFE : date, produit, numéro de police, numéro de proposition, assuré, montant, mode de paiement, numéro de reçu, référence du collecteur, terminal, référence unique.

**Bonne pratique :** exportez **une fois par jour à heure fixe**, sur une période qui ne chevauche pas l'export précédent, et saisissez dans SUNULIFE le jour même. Conservez le fichier. Lorsque l'intégration automatique SUNULIFE sera activée (Document 1 §8.4), cet export restera disponible comme moyen de contrôle et de repli — il ne disparaît pas.

## B.5 Gérer les clients et les polices

**Menu : Clients.** Recherchez par nom, téléphone, CNIB, numéro de police ou de carte.

Un dossier client affiche l'identité, les documents, les polices, les cartes (physiques et virtuelles) et l'historique complet des encaissements.

| Action | Remarque |
|---|---|
| Corriger une donnée d'identité | Audité ; corrigez une faute de frappe, n'écrasez pas une personne différente |
| Ajouter un document | Pour une CNIB renouvelée, par exemple |
| Fusionner deux dossiers | Uniquement lorsqu'il s'agit à coup sûr de la même personne ; l'opération est tracée et réversible seulement par la DSI |
| Demander une carte | Envoie la demande à l'agent cartes (Partie D) |
| Émettre une carte virtuelle (QR) | Immédiat ; envoyée par SMS ou imprimée |

**Protection des données.** Vous voyez des données personnelles parce que votre fonction l'exige. N'exportez pas au-delà de ce dont vous avez besoin, ne les transmettez pas hors de SUNU et n'en discutez pas hors de votre rôle. Chaque export est journalisé à votre nom.

## B.6 Files d'anomalies

**Menu : Anomalies.**

| File | Ce que cela signifie | Ce que vous faites |
|---|---|---|
| **Mobile Money en attente** | Paiement ni confirmé ni échoué | Attendez le rapprochement ; si toujours en attente après 24 h, escaladez à la DSI |
| **Rejeté par SUNULIFE** | La transmission a été refusée | Lisez l'erreur, corrigez la donnée s'il s'agit d'un problème de données, resoumettez ; escaladez si c'est technique |
| **Doublon possible** | Deux dossiers clients semblent désigner la même personne | Comparez et fusionnez, ou écartez |
| **Encaissement non rattaché** | Encaissement sans lien de police valable | Identifiez la bonne police et rétablissez le lien |

Traitez-les **quotidiennement**. Elles ne se résolvent pas d'elles-mêmes et elles s'accumulent.

---

# PARTIE C — SUPERVISEUR / CONTRÔLEUR

*Vous êtes le contrôle financier. Votre validation libère les collecteurs et atteste que l'argent de SUNU est bien arrivé.*

## C.1 Votre tableau de bord

Il affiche : les **versements en attente de confirmation** (votre priorité), tous les collecteurs avec le taux d'utilisation de leur plafond, les collecteurs bloqués, les alertes d'écart, l'ancienneté des soldes non versés, les totaux du jour et de la semaine.

## C.2 Confirmer un versement — votre responsabilité critique

**Menu : Versements → En attente de confirmation.**

Pour chaque déclaration, vous voyez : le collecteur, le montant déclaré, le mode, la référence bancaire, la photo du bordereau, la date et l'heure de déclaration, et le solde du collecteur avant et après.

**Procédure :**

1. **Vérifiez à la source.** Dépôt bancaire → retrouvez le crédit sur le relevé. Caisse → obtenez le procès-verbal de comptage auprès du caissier.
2. **Comparez** le montant réellement reçu au montant déclaré.
3. Puis :

| Cas | Action |
|---|---|
| Les montants correspondent | **Confirmez.** Le solde diminue, le terminal est libéré |
| Reçu *moins* que déclaré | **Confirmez le montant réel.** L'écart est consigné au nom du collecteur et signalé. Informez votre hiérarchie le jour même |
| Reçu *plus* que déclaré | Confirmez le montant réel ; notez le motif |
| Rien reçu | **Rejetez** avec un motif. Le collecteur reste bloqué. Escaladez immédiatement |

> **Ne confirmez que sur preuve.** Confirmer sans avoir vu le crédit bancaire ou le procès-verbal de comptage revient à déclarer que SUNU détient un argent qu'elle ne détient peut-être pas. Au-delà d'un montant paramétré, un second valideur est requis — cela vous protège autant que cela protège SUNU.

## C.3 Surveiller les plafonds

**Menu : Collecteurs → Plafonds.**

| Signal | Lecture | Action |
|---|---|---|
| Collecteur en permanence proche de son plafond | Plafond trop bas, ou versements trop rares | Revoyez le plafond, ou le rythme des versements |
| Solde dont l'ancienneté dépasse la limite de la politique | Espèces détenues trop longtemps | Appelez le collecteur aujourd'hui |
| Collecteur bloqué depuis plus d'une journée | Travail à l'arrêt | Relancez le versement ou la confirmation |
| Encaissements sans versement correspondant sur une longue période | **Signal d'alerte** | Enquêtez sans délai |

## C.4 Indicateurs de fraude à surveiller chaque semaine

| Indicateur | Lecture possible |
|---|---|
| Rupture dans la numérotation des reçus d'un terminal | Reçus supprimés ou détruits |
| Encaissements regroupés sur un même point GPS loin de tout client | Encaissements fictifs |
| Encaissements enregistrés à des heures invraisemblables | Pratique irrégulière |
| Écarts répétés sur les versements d'un même collecteur | Détournement |
| Réimpressions fréquentes sur les mêmes encaissements | Reçus remis en double |
| Nombreuses corrections demandées par un même collecteur | Mauvaise pratique ou manipulation |

Le système les remonte automatiquement. **Traitez une alerte comme une question à poser, non comme une accusation.** La plupart ont une explication innocente ; celles qui n'en ont pas doivent être escaladées par écrit le jour même.

## C.5 Rapprochement

**Hebdomadaire :** total encaissé − total des versements confirmés = somme des soldes non versés. Si cela ne s'équilibre pas, escaladez à la DSI en précisant la période concernée.

**Quotidien pour le Mobile Money :** total plateforme face au rapport de règlement de l'opérateur face au montant crédité sur le compte bancaire. Tout écart part en file d'anomalies.

---

# PARTIE D — AGENT D'ÉMISSION DES CARTES

*Vous produisez et délivrez les cartes SUNU, physiques et virtuelles.*

## D.1 Stock de cartes

**Menu : Cartes → Stock.** Les cartes vierges sont enregistrées et chaque numéro est traçable du stock jusqu'à l'assuré.

| Tâche | Fréquence |
|---|---|
| Enregistrer une nouvelle livraison de cartes vierges | À la réception |
| Comptage physique face à l'enregistrement système | Mensuel |
| Signaler tout écart | Immédiatement — c'est un incident de sécurité |

## D.2 Émettre une carte physique

**Menu : Cartes → Demandes.** Chaque demande provient d'une souscription acceptée.

1. Ouvrez la demande. Vérifiez le nom de l'assuré et la **liste des polices** — la carte doit porter toutes les polices actives de cet assuré, comme l'exige le TdR.
2. **Affectez une carte** depuis le stock. Le système attribue le numéro et l'expiration.
3. **Générez le fichier de personnalisation** — il contient le bloc de marque SUNU, le nom et les prénoms de l'assuré, le numéro de carte, l'expiration et les données à encoder.
4. Sur la station de personnalisation : **imprimez** et **encodez** la carte.
5. Contrôlez la carte imprimée : nom correctement orthographié, numéro lisible, marque conforme.
6. De retour dans le système : **Marquer comme écrite**. La carte devient active.
7. Remettez ou expédiez la carte, et enregistrez la remise.

> **Vérifiez l'orthographe du nom avant d'imprimer.** Une carte mal imprimée est une carte perdue et un client qui se sent mal traité.

## D.3 Émettre une carte virtuelle (QR)

**Menu : Clients → [client] → Émettre une carte virtuelle.**

Choisissez le mode de remise : **SMS** au numéro du client, **impression** sur les conditions particulières, ou **les deux**. Le QR est généré immédiatement et fonctionne sur n'importe quel terminal, y compris hors ligne.

À utiliser lorsque : le client attend sa carte physique, l'a perdue, ou vit loin d'un point de personnalisation. C'est aussi le moyen le plus rapide de rendre un nouveau client opérationnel le jour même de sa souscription.

## D.4 Bloquer, remplacer, renouveler

| Situation | Action | Effet |
|---|---|---|
| Carte perdue ou volée | **Bloquer** avec le motif | Immédiatement inutilisable ; le blocage est instantané côté serveur |
| Carte endommagée | **Remplacer** | Nouvelle carte liée à l'ancienne ; l'ancienne est définitivement morte |
| Carte arrivant à expiration | **Renouveler** | Nouvelle carte avec une nouvelle date d'expiration |
| Le client ajoute une police | **Mettre à jour la carte** | Réencoder, ou émettre une carte virtuelle actualisée |

Bloquer une carte ne bloque **jamais** les polices du client ni ses paiements — le client peut toujours payer par recherche manuelle ou par QR.

## D.5 Protection des données à la station

La station de personnalisation manipule des noms de clients et des données de cartes. Maintenez le local à accès contrôlé, ne laissez pas de fichiers de personnalisation sur le poste après usage, et ne sortez jamais de données de cartes des locaux de SUNU.

---

# PARTIE E — ADMINISTRATEUR (SUNU)

*Vous configurez la solution et gérez qui peut faire quoi.*

## E.1 Utilisateurs et profils

**Menu : Utilisateurs.**

Créer un utilisateur : identité et courriel → **attribuez exactement un profil** (Agent collecteur, Agent back-office, Superviseur, Agent cartes, Administrateur) → mot de passe provisoire remis séparément de l'identifiant → l'utilisateur le change à la première connexion.

> **Attribuez le profil minimal permettant à la personne de faire son travail.** Un agent back-office n'a pas besoin de droits de superviseur « au cas où ». Si quelqu'un a besoin d'une action supplémentaire occasionnelle, cela relève d'une demande au superviseur, pas d'une habilitation permanente.

**Départs — le jour même :** désactivez le compte (ne le supprimez jamais — la suppression romprait la piste d'audit), clôturez l'affectation du terminal, assurez-vous que toutes les espèces sont versées et confirmées, récupérez les cartes éventuelles.

**Chaque trimestre :** passez en revue la liste complète des comptes et habilitations avec chaque responsable de service, désactivez ce qui est inutilisé, et consignez que vous l'avez fait.

## E.2 Collecteurs

**Menu : Agents.** Créez le collecteur, affectez un terminal, fixez le plafond.

Fixer un plafond est un jugement de gestion :

| Trop bas | Trop élevé |
|---|---|
| Collecteur bloqué en permanence | Montant important exposé si le collecteur est malhonnête ou victime d'un vol |
| Déplacements inutiles pour verser | Contrôle affaibli |

Commencez prudemment — un plafond couvrant environ un à deux jours d'encaissement normal sur la tournée de ce collecteur — puis ajustez à partir des données réelles au bout d'un mois.

## E.3 Terminaux

**Menu : Terminaux.** Enregistrez chaque appareil avec son numéro de série, sa SIM, son alias et son modèle ; affectez-le à un collecteur ; gardez des terminaux de secours en stock. En cas de perte ou de vol : marquez-le, désactivez le compte du collecteur, remettez un terminal de secours.

## E.4 Produits

**Menu : Produits.** Pour chaque produit d'assurance : code, libellé français et anglais, périodicité de prime, règle de frais de police, modèle de reçu, et le code produit SUNULIFE.

Modifier le tarif d'un produit n'affecte que les encaissements futurs. **Ne supprimez jamais un produit qui porte des encaissements** — désactivez-le à la place.

## E.5 Frais, commissions et limites

La plateforme prend en charge des règles forfaitaires, en pourcentage et par paliers pour les frais de transaction et les commissions des collecteurs, définissables par produit et par type de transaction. Configurez-les avec la Direction Financière ; chaque modification est auditée et s'applique pour l'avenir.

## E.6 Notifications

**Menu : SMS.** Configurez le nom de l'expéditeur, surveillez le solde de crédits et choisissez les événements qui notifient l'assuré. Recommandé au minimum : **une confirmation SMS à l'assuré pour chaque prime encaissée** — c'est le contrôle le plus efficace de toute la solution contre un collecteur qui garderait les espèces.

## E.7 Ce que vous ne devez pas faire

- Ne vous attribuez pas les droits de confirmation des versements. Configuration et confirmation financière doivent rester en des mains différentes.
- Ne créez pas de comptes partagés ou génériques (« agent1 », « backoffice »). Chaque compte appartient à une personne nommément identifiée.
- Ne réutilisez pas le compte d'un employé parti pour son remplaçant. Créez-en un nouveau.

---

# PARTIE F — PROFIL DSI / TECHNIQUE

*Vous maintenez la plateforme en fonctionnement et les intégrations en bonne santé.*

## F.1 Périmètre

Configuration des environnements, intégrations et leurs identifiants, supervision technique, sauvegardes, livraisons et support de niveau 2. **Vous ne confirmez pas les versements** — le pouvoir technique ne doit pas égaler le pouvoir financier (Document 2 §5.2).

## F.2 Intégrations

**Menu : Configuration → Intégrations.**

| Intégration | Ce que vous gérez |
|---|---|
| **SUNULIFE** | Point d'entrée, authentification, mapping des champs, planification de la synchronisation, journal de synchronisation et rejeu |
| **Mobile Money** | Opérateur, identifiants, URL de rappel, rapprochement |
| **SMS** | Opérateur, expéditeur, crédits |

Les identifiants sont saisis une fois et ne sont plus réaffichés. Effectuez leur rotation au moins une fois par an et immédiatement en cas de suspicion, en suivant la procédure documentée (Document 2 §5.4).

## F.3 Contrôles quotidiens

- Sauvegarde nocturne terminée.
- Traitements asynchrones actifs ; aucune file qui s'accumule.
- Synchronisation SUNULIFE : dernière exécution réussie, nombre d'erreurs.
- Mobile Money : paiements en attente et état du rapprochement.
- Taux d'erreur et temps de réponse.
- Alertes de sécurité.

## F.4 Mensuel et trimestriel

**Mensuel :** analyse de vulnérabilité des dépendances ; application des correctifs dans la fenêtre convenue ; revue des accès des comptes privilégiés ; contrôle de la croissance du stockage.
**Trimestriel :** **test complet de restauration depuis sauvegarde dans un environnement vierge** — une sauvegarde jamais restaurée n'est pas une sauvegarde ; revue de la configuration des alertes ; revue de la piste d'audit à la recherche d'anomalies.

## F.5 Livraisons

Chaque livraison : testée en recette, approuvée par SUNU, déployée dans la fenêtre convenue, documentée dans un journal des changements, avec un retour arrière documenté. Ne déployez jamais directement en production. N'utilisez jamais de données réelles de production en recette.

## F.6 Support de niveau 2

| Symptôme | Premiers contrôles |
|---|---|
| Un terminal ne se synchronise pas | Agent actif ? Terminal affecté ? Clé d'API valide ? Limite de débit atteinte ? Journal de synchronisation |
| Paiements Mobile Money bloqués en attente | État de l'opérateur, accessibilité du rappel, vérification de signature, traitement de rapprochement |
| Synchronisation SUNULIFE en échec | Point d'entrée joignable, identifiants valides, erreurs de validation de charge utile dans le journal |
| Un export ne se termine jamais | Traitement asynchrone actif, espace disque, erreurs du traitement de rapport |
| Un utilisateur ne peut pas se connecter | Compte actif, non verrouillé, profil correct, dispositif MFA |

---

# PARTIE G — DÉPANNAGE (tous profils)

| Problème | Cause probable | Solution |
|---|---|---|
| Le terminal n'imprime pas | Papier, bourrage, capot ouvert, batterie | Rechargez le papier, débloquez, refermez le capot, rechargez. Puis **Réimprimer** |
| Carte non lue | Carte abîmée, passage trop rapide, mauvais sens | Maintenez-la à plat et immobile sur le lecteur. Si cela échoue encore, utilisez la recherche manuelle et signalez la carte |
| QR non scanné | Écran trop sombre, objectif sale, impression abîmée | Augmentez la luminosité du téléphone, nettoyez l'objectif, ou utilisez la recherche manuelle |
| Le GPS n'accroche pas | À l'intérieur, ciel peu dégagé | Sortez un instant ; si cela échoue encore, continuez — vous n'êtes pas bloqué |
| « Plafond atteint » | Vous détenez trop d'espèces | Versez (§A.8) |
| « Terminal non affecté » | Mauvais terminal, ou affectation non faite | Contactez l'administrateur |
| Le compteur de synchronisation ne se vide pas | Pas de réseau, ou problème serveur | Déplacez-vous en zone couverte. Si cela persiste plus d'une journée, appelez le support |
| Le Mobile Money reste en attente | Opérateur lent ou injoignable | Patientez ; n'encaissez pas deux fois. Cela se résout par le rapprochement |
| Export bloqué sur « Génération » | Congestion des traitements | Patientez quelques minutes ; si cela persiste, appelez la DSI |
| Compte verrouillé | Trop de tentatives erronées | Patientez, puis réessayez. Sinon, demandez une réinitialisation à l'administrateur |

**Quand vous appelez le support, ayez sous la main :** votre nom et votre profil, le numéro de série du terminal le cas échéant, l'heure exacte, la référence de l'opération ou le numéro de reçu, et le message d'erreur exact.

---

# PARTIE H — GLOSSAIRE

| Terme | Signification |
|---|---|
| **Assuré** | La personne couverte par la police |
| **CNIB** | Carte Nationale d'Identité Burkinabè |
| **DUPLICATA** | La mention imprimée sur un reçu réimprimé |
| **Encaissement** | Un paiement de prime ou de frais de police reçu d'un assuré |
| **Frais de police** | Les frais dus à la souscription, distincts de la prime |
| **Période de référence** | La période couverte par une prime (par exemple septembre 2026) |
| **Plafond** | Le montant maximal d'espèces encaissées qu'un collecteur peut détenir avant d'être tenu de verser |
| **Prime** | Le montant que l'assuré paie pour sa couverture |
| **Proposition** | Le formulaire de souscription signé, et l'enregistrement qui en découle |
| **Recette (UAT)** | La phase de tests de réception par SUNU avant la mise en service |
| **Référence (unique)** | L'identifiant généré par le terminal pour chaque opération ; il rend le double comptage impossible |
| **Reçu** | Le justificatif imprimé remis au client pour chaque paiement |
| **Synchronisation** | L'envoi au back-office des données détenues sur le terminal |
| **SUNULIFE** | Le logiciel métier de SUNU, référentiel des clients et des contrats |
| **TPE** | Le terminal de paiement électronique utilisé sur le terrain |
| **Versement** | Le fait de remettre les espèces encaissées à la banque ou à la caisse SUNU |
| **Carte virtuelle** | Le QR Code qui identifie un assuré à la place d'une carte physique |

---

## Annexe — Plan de formation

| Profil | Durée | Contenu | Participants (pilote) |
|---|---|---|---|
| Agent collecteur | 1 jour | Parties 0 et A, prise en main sur terminaux réels, jeux de rôle complets sur les deux parcours, règles de fraude et de sécurité | 15 |
| Agent back-office | ½ journée | Parties 0 et B, revue de dossiers réels, exports | 3–5 |
| Superviseur | ½ journée | Parties 0, C et B.3, confirmation de versements sur cas réels | 2–3 |
| Agent cartes | ½ journée | Parties 0 et D, prise en main de la station de personnalisation | 1–2 |
| Administrateur | ½ journée | Parties 0 et E | 1–2 |
| DSI | 1 jour | Partie F plus Documents 1 et 2 | 1–2 |

La formation est dispensée sur l'environnement de recette avec des données synthétiques, avant le pilote. Chaque participant conserve la partie du guide qui le concerne ; les collecteurs conservent en outre la fiche de poche du §A.12.

---

*Fin du Document 3. Les copies d'écran sont insérées après le gel des écrans en recette.*
