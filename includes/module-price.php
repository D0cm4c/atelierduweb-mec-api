<?php
/**
 * Module — Tarif
 *
 * Ajoute le champ « mec_price » aux événements « mec-events » de l'API REST
 * publique : tarif de l'événement lu depuis le postmeta MEC « mec_cost »
 * (chaîne laissée telle quelle, ex. « 30 », « 30.5 », « Gratuit ») + un
 * montant numérique normalisé « amount » et la devise configurée MEC.
 *
 * Lecture seule. Activable/désactivable dans Réglages → ADW MEC API.
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

add_action('rest_api_init', 'adw_mecapi_price_register');

/**
 * Enregistre le champ REST si le module est actif.
 */
function adw_mecapi_price_register() {
    if (!adw_mecapi_setting('module_price')) {
        return;
    }

    register_rest_field('mec-events', 'mec_price', array(
        'get_callback' => 'adw_mecapi_get_price',
        'schema' => array(
            'description' => 'Tarif de l\'événement (module tarif).',
            'type' => 'object',
        ),
    ));
}

/**
 * Retourne le tarif de l'événement, ou null si absent.
 *
 * @param array $post_arr Données du post transmises par l'API REST.
 * @return array|null
 */
function adw_mecapi_get_price($post_arr) {
    $post_id = isset($post_arr['id']) ? (int) $post_arr['id'] : 0;
    if (!$post_id) {
        return null;
    }

    $cost = get_post_meta($post_id, 'mec_cost', true);
    if ($cost === '' || $cost === null) {
        return null;
    }

    // Devise configurée dans MEC (ex. EUR), si disponible.
    $options = get_option('mec_options', array());
    $currency = isset($options['currency']) ? (string) $options['currency'] : '';

    // Montant numérique normalisé (null si le coût n'est pas numérique,
    // ex. « Gratuit » ou « Prix sur demande »).
    $amount = is_numeric($cost) ? (float) $cost : null;

    return array(
        'cost' => (string) $cost,
        'amount' => $amount,
        'currency' => $currency,
    );
}
