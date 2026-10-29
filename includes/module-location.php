<?php
/**
 * Module 2 — Lieu
 *
 * Ajoute le champ « mec_location » (name, address, website) aux événements
 * « mec-events » de l'API REST publique, lu depuis le postmeta MEC
 * « mec_location_id » (terme de la taxonomie mec_location).
 *
 * Lecture seule. Activable/désactivable dans Réglages → ADW MEC API.
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

add_action('rest_api_init', 'adw_mecapi_location_register');

/**
 * Enregistre le champ REST si le module est actif.
 */
function adw_mecapi_location_register() {
    if (!adw_mecapi_setting('module_location')) {
        return;
    }

    register_rest_field('mec-events', 'mec_location', array(
        'get_callback' => 'adw_mecapi_get_location',
        'schema' => array(
            'description' => 'Lieu de l\'événement (module 2).',
            'type' => 'object',
        ),
    ));
}

/**
 * Retourne le lieu de l'événement, ou null si absent.
 *
 * @param array $post_arr Données du post transmises par l'API REST.
 * @return array|null
 */
function adw_mecapi_get_location($post_arr) {
    $post_id = isset($post_arr['id']) ? (int) $post_arr['id'] : 0;
    if (!$post_id) {
        return null;
    }

    $location_id = (int) get_post_meta($post_id, 'mec_location_id', true);
    if (!$location_id) {
        return null;
    }

    $term = get_term($location_id);
    if (!$term || is_wp_error($term) || !isset($term->term_id)) {
        return null;
    }

    return array(
        'id' => (int) $term->term_id,
        'name' => $term->name,
        'address' => (string) get_term_meta($term->term_id, 'address', true),
        'website' => (string) get_term_meta($term->term_id, 'website', true),
    );
}
