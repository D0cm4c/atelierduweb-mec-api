<?php
/**
 * Module 3 — Places restantes
 *
 * Ajoute le champ « mec_availability » aux événements « mec-events » de
 * l'API REST publique : capacité et places restantes de l'événement, lues
 * dans les postmeta MEC « mec_tickets » (définition des billets avec
 * « limits ») et « mec_total_bookings_limit ».
 *
 * Permet d'afficher « Complet » dans l'app sans clé MEC.
 * Lecture seule. Activable/désactivable dans Réglages → ADW MEC API.
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

add_action('rest_api_init', 'adw_mecapi_spots_register');

/**
 * Enregistre le champ REST si le module est actif.
 */
function adw_mecapi_spots_register() {
    if (!adw_mecapi_setting('module_spots')) {
        return;
    }

    register_rest_field('mec-events', 'mec_availability', array(
        'get_callback' => 'adw_mecapi_get_availability',
        'schema' => array(
            'description' => 'Disponibilité de l\'événement (module 3).',
            'type' => 'object',
        ),
    ));
}

/**
 * Retourne capacité + places restantes, ou null si l'événement n'utilise
 * pas le module booking MEC.
 *
 * @param array $post_arr Données du post transmises par l'API REST.
 * @return array|null
 */
function adw_mecapi_get_availability($post_arr) {
    $post_id = isset($post_arr['id']) ? (int) $post_arr['id'] : 0;
    if (!$post_id) {
        return null;
    }

    // Limite globale de bookings de l'événement (meta MEC).
    $limit = (int) get_post_meta($post_id, 'mec_total_bookings_limit', true);
    if ($limit <= 0) {
        // Pas de module booking configuré sur cet événement.
        return null;
    }

    // Places déjà réservées : nombre de bookings confirmés/pending pour
    // cet événement (post type « mec-books », meta « mec_event_id »).
    $bookings = get_posts(array(
        'post_type' => 'mec-books',
        'post_status' => array('publish', 'pending', 'new'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => 'mec_event_id',
        'meta_value' => $post_id,
    ));

    $booked = 0;
    foreach ($bookings as $booking_id) {
        // Compter les participants réels : champ « count » du booking
        // (méta « mec_bookings_count »), sinon 1 par booking.
        $count = (int) get_post_meta($booking_id, 'mec_bookings_count', true);
        $booked += ($count > 0 ? $count : 1);
    }

    $left = max(0, $limit - $booked);

    return array(
        'limit' => $limit,
        'booked' => $booked,
        'left' => $left,
        'full' => ($left === 0),
    );
}
