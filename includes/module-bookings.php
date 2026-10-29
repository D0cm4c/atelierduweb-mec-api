<?php
/**
 * Module 5 — Inscriptions (bookings)
 *
 * Route REST protégée : GET /wp-json/adw/v1/bookings
 *
 * Retourne la liste des inscriptions M.E. Calendar (post type « mec-books ») :
 * id, event_id, event_title, date, name, email, count, status, et les champs
 * personnalisés du formulaire (dont le « n° de membre » s'il a été ajouté au
 * formulaire MEC — détecté automatiquement par son libellé).
 *
 * ⚠️ SÉCURITÉ :
 *   - Module DÉSACTIVÉ par défaut. Activation + secret dans Réglages →
 *     ADW MEC API.
 *   - Le secret se transmet via le header « X-ADW-Secret » (pas dans
 *     l'URL, pas dans la sortie).
 *   - Usage server-to-server uniquement : JAMAIS appelé depuis un
 *     navigateur (le secret ne doit jamais apparaître côté client).
 *   - Lecture seule.
 *
 * Paramètres : ?event_id=<id> (filtrer un événement), ?since=<ISO date>
 * (bookings créés/modifiés depuis), ?per_page= (défaut 100, max 500).
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

add_action('rest_api_init', 'adw_mecapi_bookings_register');

/**
 * Enregistre la route REST si le module est actif et le secret configuré.
 */
function adw_mecapi_bookings_register() {
    if (!adw_mecapi_setting('module_bookings')) {
        return;
    }

    register_rest_route('adw/v1', '/bookings', array(
        'methods' => 'GET',
        'callback' => 'adw_mecapi_bookings_list',
        'permission_callback' => 'adw_mecapi_bookings_permission',
        'args' => array(
            'event_id' => array(
                'type' => 'integer',
                'required' => false,
            ),
            'since' => array(
                'type' => 'string',
                'required' => false,
            ),
            'per_page' => array(
                'type' => 'integer',
                'default' => 100,
                'sanitize_callback' => 'absint',
            ),
        ),
    ));
}

/**
 * Permission : secret obligatoire (header X-ADW-Secret), comparé en temps
 * constant. 401 si absent ou incorrect, 403 si le module est actif mais
 * qu'aucun secret n'est configuré (mauvaise configuration).
 *
 * @param WP_REST_Request $request Requête.
 * @return bool|WP_Error
 */
function adw_mecapi_bookings_permission($request) {
    $secret = (string) adw_mecapi_setting('bookings_secret');
    if ($secret === '') {
        return new WP_Error(
            'adw_mecapi_not_configured',
            'Module inscriptions actif mais aucun secret configuré.',
            array('status' => 403)
        );
    }

    $provided = $request->get_header('X-ADW-Secret');
    if ($provided === '' || $provided === null) {
        return new WP_Error(
            'adw_mecapi_missing_secret',
            'Secret manquant (header X-ADW-Secret).',
            array('status' => 401)
        );
    }

    if (!hash_equals($secret, $provided)) {
        return new WP_Error(
            'adw_mecapi_invalid_secret',
            'Secret invalide.',
            array('status' => 401)
        );
    }

    return true;
}

/**
 * Liste des inscriptions.
 *
 * @param WP_REST_Request $request Requête.
 * @return WP_REST_Response|WP_Error
 */
function adw_mecapi_bookings_list($request) {
    $event_id = $request->get_param('event_id');
    $since = $request->get_param('since');
    $per_page = min(500, max(1, (int) $request->get_param('per_page')));

    $args = array(
        'post_type' => 'mec-books',
        'post_status' => 'any',
        'posts_per_page' => $per_page,
        'orderby' => 'ID',
        'order' => 'ASC',
    );

    if ($event_id) {
        $args['meta_key'] = 'mec_event_id';
        $args['meta_value'] = (int) $event_id;
    }

    // Filtre « since » : bookings créés ou modifiés après cette date.
    if ($since) {
        $since_utc = adw_mecapi_parse_since($since);
        if ($since_utc !== null) {
            $args['date_query'] = array(
                array('after' => $since_utc, 'inclusive' => true),
                array('column' => 'post_modified', 'after' => $since_utc, 'inclusive' => true),
                'relation' => 'OR',
            );
        } else {
            return new WP_Error(
                'adw_mecapi_bad_since',
                'Paramètre « since » invalide (format attendu : AAAA-MM-JJ ou ISO 8601).',
                array('status' => 400)
            );
        }
    }

    $bookings = get_posts($args);
    $items = array();

    foreach ($bookings as $booking) {
        $items[] = adw_mecapi_booking_item($booking);
    }

    return rest_ensure_response(array(
        'version' => ADW_MECAPI_VERSION,
        'count' => count($items),
        'items' => $items,
    ));
}

/**
 * Convertit une date « since » (AAAA-MM-JJ ou ISO 8601) en timestamp UTC
 * exploitable par WP_Date_Query. Retourne null si le format est invalide.
 *
 * @param string $since Date fournie.
 * @return int|null
 */
function adw_mecapi_parse_since($since) {
    $ts = strtotime((string) $since);
    return ($ts === false) ? null : $ts;
}

/**
 * Transforme un post « mec-books » en item d'inscription normalisé.
 * Ne lève jamais d'erreur : chaque donnée manquante devient null/vide.
 *
 * @param WP_Post $booking Post booking.
 * @return array
 */
function adw_mecapi_booking_item($booking) {
    $booking_id = (int) $booking->ID;
    $event_id = (int) get_post_meta($booking_id, 'mec_event_id', true);

    // Participants : méta « mec_attendees » (tableau sérialisé MEC).
    $attendees = get_post_meta($booking_id, 'mec_attendees', true);
    $attendees = is_string($attendees) ? maybe_unserialize($attendees) : $attendees;
    if (!is_array($attendees)) {
        $attendees = array();
    }

    // Premier participant = inscrit principal (nom + email de la résa).
    $first = isset($attendees[0]) && is_array($attendees[0]) ? $attendees[0] : array();
    $name = isset($first['name']) ? (string) $first['name'] : '';
    $email = isset($first['email']) ? (string) $first['email'] : '';

    // Nombre de places réservées.
    $count = (int) get_post_meta($booking_id, 'mec_bookings_count', true);
    if ($count <= 0) {
        $count = max(1, count($attendees));
    }

    // Champs personnalisés du formulaire (« reg »), avec libellés.
    $reg = array();
    if (isset($first['reg']) && is_array($first['reg'])) {
        $reg = adw_mecapi_label_reg_fields($event_id, $first['reg']);
    }

    // Détection du « n° de membre » : libellé contenant « membre »
    // (ex. « N° de membre », « Numéro de membre », « Member no »).
    $member_no = null;
    foreach ($reg as $label => $value) {
        if ($member_no === null && stripos($label, 'membre') !== false) {
            $member_no = ($value !== '') ? $value : null;
        }
    }

    return array(
        'id' => $booking_id,
        'event_id' => $event_id,
        'event_title' => $event_id ? get_the_title($event_id) : '',
        'date' => get_post_meta($booking_id, 'mec_booking_time', true),
        'created' => $booking->post_date_gmt,
        'modified' => $booking->post_modified_gmt,
        'name' => $name,
        'email' => $email,
        'count' => $count,
        // Statut du booking : publié = confirmé, pending = en attente.
        'status' => $booking->post_status,
        'member_no' => $member_no,
        'fields' => $reg,
    );
}

/**
 * Remplace les identifiants numériques des champs « reg » par leur libellé
 * lisible (configuré dans le formulaire MEC). Les libellés des champs
 * principaux (nom, email) sont exclus.
 *
 * @param int   $event_id ID de l'événement (0 si inconnu).
 * @param array $reg      Valeurs « reg » du participant.
 * @return array Libellé => valeur.
 */
function adw_mecapi_label_reg_fields($event_id, $reg) {
    $fields = array();
    $options = get_option('mec_options', array());
    $reg_fields = isset($options['reg_fields']) && is_array($options['reg_fields']) ? $options['reg_fields'] : array();

    // Formulaire spécifique à l'événement si l'héritage global est désactivé.
    if ($event_id) {
        $inheritance = get_post_meta($event_id, 'mec_reg_fields_global_inheritance', true);
        if ($inheritance !== '' && !(int) $inheritance) {
            $event_fields = get_post_meta($event_id, 'mec_reg_fields', true);
            if (is_array($event_fields)) {
                $reg_fields = $event_fields;
            }
        }
    }

    foreach ($reg as $field_id => $value) {
        $label = null;
        if (isset($reg_fields[$field_id]['label'])) {
            $label = (string) $reg_fields[$field_id]['label'];
        }

        // Valeurs tableau (cases à cocher) → chaîne séparée par « | ».
        if (is_array($value)) {
            $value = implode(' | ', $value);
        }

        $key = $label !== null ? $label : ('champ_' . $field_id);
        $fields[$key] = (string) $value;
    }

    return $fields;
}
