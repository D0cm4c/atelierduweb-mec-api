<?php
/**
 * Plugin Name: Atelier du Web — MEC API
 * Description: Expose organisateur, lieu, tarif, places restantes et inscriptions des événements M.E. Calendar via l'API REST — pour l'application de gestion des membres de l'Atelier du Web.
 * Version: 0.3.1
 * Author: Atelier du Web
 * License: GPL-2.0-or-later
 * Text Domain: adw-mecapi
 *
 * Garanties (site en production) :
 *   - Lecture seule : aucune écriture en base, aucun fichier modifié.
 *   - Hooks : « rest_api_init » uniquement (plus l'admin pour les réglages).
 *   - Toute donnée manquante → « null » propre, jamais d'erreur PHP.
 *   - L'endpoint inscriptions (module 5) est DÉSACTIVÉ par défaut et
 *     protégé par un secret : jamais appelé depuis un navigateur.
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

define('ADW_MECAPI_VERSION', '0.3.1');

require_once plugin_dir_path(__FILE__) . 'includes/module-organizer.php';
require_once plugin_dir_path(__FILE__) . 'includes/module-location.php';
require_once plugin_dir_path(__FILE__) . 'includes/module-price.php';
require_once plugin_dir_path(__FILE__) . 'includes/module-spots.php';
require_once plugin_dir_path(__FILE__) . 'includes/module-bookings.php';
require_once plugin_dir_path(__FILE__) . 'includes/module-prefill.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';

/**
 * Activation : réglages par défaut (aucune table créée, aucune donnée modifiée).
 */
function adw_mecapi_activate() {
    $defaults = array(
        'module_organizer' => 1,
        'module_location' => 1,
        'module_price' => 1,
        'module_spots' => 1,
        'module_prefill' => 1,
        'module_bookings' => 0, // Désactivé par défaut — nécessite un secret.
        'bookings_secret' => '',
    );
    if (get_option('adw_mecapi_settings') === false) {
        add_option('adw_mecapi_settings', $defaults);
    }
}
register_activation_hook(__FILE__, 'adw_mecapi_activate');

/**
 * Réglages du plugin (helper partagé par tous les modules).
 *
 * @param string $key Clé de réglage.
 * @return mixed Valeur du réglage (false si module inconnu).
 */
function adw_mecapi_setting($key) {
    $settings = get_option('adw_mecapi_settings', array());
    return isset($settings[$key]) ? $settings[$key] : false;
}
