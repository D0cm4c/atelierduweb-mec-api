<?php
/**
 * Module 1 — Organisateur
 *
 * Ajoute le champ « mec_organizer » (name, email, tel, website) aux événements
 * « mec-events » de l'API REST publique, lu depuis le postmeta MEC
 * « mec_organizer_id » (terme de la taxonomie mec_organizer).
 *
 * Lecture seule. Activable/désactivable dans Réglages → ADW MEC API.
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

add_action('rest_api_init', 'adw_mecapi_organizer_register');

/**
 * Enregistre le champ REST si le module est actif.
 */
function adw_mecapi_organizer_register() {
    if (!adw_mecapi_setting('module_organizer')) {
        return;
    }

    register_rest_field('mec-events', 'mec_organizer', array(
        'get_callback' => 'adw_mecapi_get_organizer',
        'schema' => array(
            'description' => 'Organisateur de l\'événement (module 1).',
            'type' => 'object',
        ),
    ));
}

/**
 * Retourne l'organisateur de l'événement, ou null si absent.
 *
 * @param array $post_arr Données du post transmises par l'API REST.
 * @return array|null
 */
function adw_mecapi_get_organizer($post_arr) {
    $post_id = isset($post_arr['id']) ? (int) $post_arr['id'] : 0;
    if (!$post_id) {
        return null;
    }

    $organizer_id = (int) get_post_meta($post_id, 'mec_organizer_id', true);
    if (!$organizer_id) {
        return null;
    }

    $term = get_term($organizer_id);
    if (!$term || is_wp_error($term) || !isset($term->term_id)) {
        return null;
    }

    return array(
        'id' => (int) $term->term_id,
        'name' => $term->name,
        'email' => (string) get_term_meta($term->term_id, 'email', true),
        'tel' => (string) get_term_meta($term->term_id, 'tel', true),
        'website' => (string) get_term_meta($term->term_id, 'website', true),
    );
}
