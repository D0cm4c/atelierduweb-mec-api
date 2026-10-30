# AGENTS.md — Instructions pour toute session d'assistance (IA/agent) sur ce dépôt

> ⚠️️ **Première action obligatoire de toute session : lire ce fichier, puis `README.md`.**
> ⚠️️ **Deuxième action obligatoire : lire les issues ouvertes** (protocole inter-sessions ci-dessous).

## Le dépôt en une phrase

Plugin WordPress `atelierduweb-mec-api` (lecture seule) pour le site atelierduweb.be : expose les infos M.E. Calendar à l'application de gestion des membres ([dépôt app](https://github.com/D0cm4c/Gestion-Membres-Inscriptions-de-l-Atelier-du-web)) — la spec partagée des interfaces est dans `docs/BACKEND.md` **du dépôt app** (§7-8) et le contrat dans la section « Contrat API » du README.

## Garanties figées (site en production)

- **Lecture seule** : aucune écriture en base (hors option `adw_mecapi_settings` via Réglages → ADW MEC API), aucun fichier modifié.
- Hooks : `rest_api_init`, `wp_footer` (préfill, seulement si paramètres `adw_*`), `template_redirect` (ouverture modale), admin.
- Donnée manquante → `null` propre, jamais d'erreur PHP, jamais de `wp_die`.
- Préfixe `adw_mecapi_*` partout ; erreurs fatales → WordPress désactive le plugin seul.
- L'endpoint **inscriptions** est désactivé par défaut, secret `X-ADW-Secret`, **server-to-server uniquement — jamais de CORS dessus, jamais appelé depuis un navigateur** (décision sécurité, voir CONTEXT.md du dépôt app).
- Conventions : commits en français préfixés (`feat:`, `fix:`, `refactor:`, `docs:`), une seule idée par commit, pousser sur `main` (dépôt plugin dédié, pas de PR obligatoire).

## 📡 Protocole inter-sessions (généraliste)

Ce projet fait partie d'un **écosystème multi-dépôts** où plusieurs sessions IA travaillent sur des dépôts liés et doivent se coordonner **sans messager humain** pour la partie conception.

### Au démarrage de CHAQUE session

1. Lire `gh issue list --state open` (y compris les commentaires récents).
2. Chercher les issues **adressées à ce dépôt** : elles portent le label `pour-<ce-dépôt>` (ex. `pour-plugin`) ou mentionnent ce dépôt/ce plugin dans le titre/corps.
3. Si une issue est adressée à ce dépôt : **la traiter en priorité** (après validation humaine si ambiguë), commenter l'issue avec le plan, livrer, puis **fermer l'issue avec un commentaire** référençant le commit.
4. Si ce dépôt a besoin de quelque chose d'un autre dépôt (spec, correction, endpoint) : **ouvrir une issue sur ce dépôt avec le label `pour-<autre-dépôt>`** et un titre clair (`[plugin] …`). Ne jamais attendre que l'humain transmette.
5. Vérifier aussi les issues que ce dépôt a ouvertes sur d'autres dépôts (`gh issue list --repo <autre> --author <ce-dépôt>`) : une réponse y attend peut-être.

### Règles de rédaction des issues inter-sessions

- **Une demande = une issue**, avec : contexte, spec précise (routes, formats, exemples), critère d'acceptation, et `Closes #<n>` si elle résout une issue liée.
- Titre préfixé par le dépôt émetteur : `[app] Demande …`, `[plugin] Demande …`.
- Toute **décision d'interface** (route, header, format de réponse, paramètres) doit être **répercutée dans la doc du dépôt émetteur ET du récepteur** dans la même livraison — sinon la prochaine session désynchronise (leçon apprise : `adw_name` vs `adw_nom`).
- Les issues ne remplacent pas l'humain pour : arbitrage de sécurité, accès production, décision produit. Elles automatisent la **conception/coordination**, pas la gouvernance.

### Adaptation à un autre projet

Ce protocole est autoporteur : copier cette section dans le `AGENTS.md` de chaque dépôt d'un écosystème, créer les labels `pour-<dépôt>`, et chaque session sait quoi lire. Aucune infrastructure supplémentaire requise (issues GitHub natives).

## ✅ Check-list avant livraison

- [ ] Issues ouvertes lues au démarrage ; celles adressées à ce dépôt traitées ou commentées
- [ ] `README.md` mis à jour si interface modifiée (route, paramètre, format)
- [ ] Doc du dépôt app (via issue si besoin) alignée si une interface partagée a changé
- [ ] Syntaxe vérifiée (tokeniseur équilibré si pas de PHP CLI) ; aucune écriture base
- [ ] Version incrémentée dans l'en-tête du plugin + README

## Livraison

- Committer et pousser sur `main` (dépôt plugin, branche unique).
- Fermer/commenter les issues traitées avec la référence du commit.
- Ne jamais commiter de secrets.
