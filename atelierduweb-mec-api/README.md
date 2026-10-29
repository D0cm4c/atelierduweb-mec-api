# Atelier du Web — MEC API (plugin WordPress)

Plugin qui expose les informations de **M.E. Calendar** via l'API REST pour l'application de gestion des membres de l'Atelier du Web ([dépôt app](https://github.com/D0cm4c/Gestion-Membres-Inscriptions-de-l-Atelier-du-web)).

⚠️ Conçu pour un **site en production** : lecture seule, aucune écriture en base, hooks REST uniquement, rollback = désactiver le plugin.

## Installation

1. Télécharger le zip (bouton *Code → Download ZIP* ou release) ou zipper ce dossier.
2. WP Admin → **Extensions → Ajouter → Téléverser une extension** → choisir le zip → **Installer** → **Activer**.
3. **Réglages → ADW MEC API** : les modules publics (organisateur, lieu, tarif, places) sont actifs par défaut ; l'endpoint inscriptions est **désactivé** par défaut.

## Modules

| Module | Champ / endpoint | Par défaut | Description |
|---|---|---|---|
| 1 — Organisateur | `mec_organizer` (wp/v2/mec-events) | ✅ actif | Nom, email, tél, site de l'organisateur (postmeta `mec_organizer_id`). |
| 2 — Lieu | `mec_location` (wp/v2/mec-events) | ✅ actif | Nom, adresse, site du lieu (postmeta `mec_location_id`). |
| Tarif | `mec_price` (wp/v2/mec-events) | ✅ actif | Coût brut (`mec_cost`), montant numérique, devise MEC. |
| 3 — Places restantes | `mec_availability` (wp/v2/mec-events) | ✅ actif | Limite (`mec_total_bookings_limit`), réservées, restantes, « Complet ». |
| 5 — Inscriptions | `GET /wp-json/adw/v1/bookings` | ❌ inactif | Bookings MEC avec **n° de membre** (détecté par le libellé du champ formulaire contenant « membre »). **Server-to-server uniquement**, secret header `X-ADW-Secret`. |

*(Module 4 — préfill modale de réservation : à venir dans une version suivante.)*

### Endpoint inscriptions

```
curl -H "X-ADW-Secret: VOTRE_SECRET" \
  "https://atelierduweb.be/wp-json/adw/v1/bookings?per_page=100"
```

Paramètres : `event_id=<id>`, `since=<AAAA-MM-JJ ou ISO>` (créés/modifiés depuis), `per_page` (défaut 100, max 500).

Réponse : `{ version, count, items: [ { id, event_id, event_title, date, created, modified, name, email, count, status, member_no, fields } ] }`.

⚠️ **Le secret ne doit jamais apparaître côté navigateur** — usage réservé au futur backend de l'application (décision sécurité, voir CONTEXT.md du dépôt app).

## Garanties production

- **Lecture seule** : aucune requête `INSERT/UPDATE/DELETE`, aucune table créée (seule l'option `adw_mecapi_settings` est écrite, via les réglages).
- Accroche **`rest_api_init`** uniquement : si un module échoue, seul l'appel API concerné renvoie une erreur ; le front et l'admin restent intacts.
- Toute donnée manquante → `null` propre (jamais d'exception PHP, jamais de `wp_die`).
- Erreur de syntaxe fatale → WordPress désactive le plugin automatiquement (récupération native) — le site reste joignable.
- Fonctions préfixées `adw_mecapi_*` — aucune collision possible.
- Secret comparé en **temps constant** (`hash_equals`).

## Test d'acceptation (après activation)

```
https://atelierduweb.be/wp-json/wp/v2/mec-events?per_page=1&_fields=id,title,mec_organizer,mec_location,mec_price,mec_availability
```

Les 4 objets doivent apparaître dans la réponse JSON.

## Version

0.1.0 — modules 1, 2, tarif, 3, 5. Prochain : module 4 (préfill modale).
