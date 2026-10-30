<?php
/**
 * Module 6 — Étiquettes / fiche compacte des événements.
 *
 * Endpoint PUBLIC (sans secret — données déjà visibles sur le site) :
 *
 *   GET /wp-json/adw-mec/v1/labels
 *
 * Réponse :
 *
 *   { "<event_id>": { "labels": ["Annulé", "Niveau intermédiaire"],
 *                     "price": "40.00", "location": "…", "organizer": "…" } }
 *
 * Sources : taxonomie « mec_label » (étiquettes MEC), postmeta « mec_cost »,
 * termes « mec_location » / « mec_organizer ». Toute donnée manquante → null
 * propre. Lecture seule, aucune écriture en base.
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

add_action('rest_api_init', 'adw_mecapi_labels_register');

/**
 * Enregistre la route publique /labels.
 */
function adw_mecapi_labels_register() {
    register_rest_route('adw-mec/v1', '/labels', array(
        'methods' => 'GET',
        'callback' => 'adw_mecapi_labels_list',
        'permission_callback' => '__return_true',
        'args' => array(
            'event_id' => array(
                'type' => 'integer',
                'required' => false,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));
}

/**
 * Nom du terme lié (organisateur) ou null.
 *
 * @param int $post_id  ID de l'événement.
 * @param string $meta_key Clé postmeta (mec_organizer_id).
 * @return string|null
 */
function adw_mecapi_labels_term_name($post_id, $meta_key) {
    $term_id = (int) get_post_meta($post_id, $meta_key, true);
    if (!$term_id) {
        return null;
    }
    $term = get_term($term_id);
    if (!$term || is_wp_error($term) || !isset($term->term_id)) {
        return null;
    }
    return $term->name;
}

/**
 * Lieu au format de la spec (issue app #89) : « nom, adresse » ou juste
 * le nom si l'adresse est absente. Ex. : « L'Atelier du web, 37 Rue du
 * Fort, 1060 Saint-Gilles ».
 *
 * @param int $post_id ID de l'événement.
 * @return string|null
 */
function adw_mecapi_labels_location($post_id) {
    $term_id = (int) get_post_meta($post_id, 'mec_location_id', true);
    if (!$term_id) {
        return null;
    }
    $term = get_term($term_id);
    if (!$term || is_wp_error($term) || !isset($term->term_id)) {
        return null;
    }
    $address = trim((string) get_term_meta($term->term_id, 'address', true));
    return $address !== '' ? $term->name . ', ' . $address : $term->name;
}

/**
 * Réponse : map event_id → { labels, price, location, organizer }.
 *
 * @param WP_REST_Request $request Requête (?event_id= optionnel).
 * @return WP_REST_Response
 */
function adw_mecapi_labels_list($request) {
    $event_id = $request->get_param('event_id');

    $args = array(
        'post_type' => 'mec-events',
        'post_status' => 'publish',
        'posts_per_page' => 500,
        'fields' => 'ids',
    );
    if ($event_id) {
        $args['p'] = $event_id;
    }

    $ids = get_posts($args);
    $out = array();

    foreach ($ids as $id) {
        $labels = array();
        $terms = get_the_terms($id, 'mec_label');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $labels[] = $term->name;
            }
        }

        $out[(string) $id] = array(
            'labels' => $labels,
            'price' => get_post_meta($id, 'mec_cost', true) ?: null,
            'location' => adw_mecapi_labels_location($id),
            'organizer' => adw_mecapi_labels_term_name($id, 'mec_organizer_id'),
        );
    }

    return rest_ensure_response($out);
}
