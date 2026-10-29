<?php
/**
 * Page de réglages admin : Réglages → ADW MEC API
 *
 * - Activer/désactiver chaque module (organisateur, lieu, tarif, places,
 *   inscriptions).
 * - Secret du module inscriptions (généré/regénéré en un clic).
 * - Aucune constante à éditer dans le code.
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

add_action('admin_menu', 'adw_mecapi_settings_menu');
add_action('admin_init', 'adw_mecapi_settings_save');

/**
 * Enregistre la page de réglages (capability manage_options).
 */
function adw_mecapi_settings_menu() {
    add_options_page(
        'ADW MEC API',
        'ADW MEC API',
        'manage_options',
        'adw-mecapi-settings',
        'adw_mecapi_settings_page'
    );
}

/**
 * Enregistre les réglages (+ nonces).
 */
function adw_mecapi_settings_save() {
    if (!isset($_POST['adw_mecapi_nonce'])) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!wp_verify_nonce(sanitize_key($_POST['adw_mecapi_nonce']), 'adw_mecapi_settings')) {
        return;
    }

    $settings = array(
        'module_organizer' => isset($_POST['module_organizer']) ? 1 : 0,
        'module_location' => isset($_POST['module_location']) ? 1 : 0,
        'module_price' => isset($_POST['module_price']) ? 1 : 0,
        'module_spots' => isset($_POST['module_spots']) ? 1 : 0,
        'module_prefill' => isset($_POST['module_prefill']) ? 1 : 0,
        'module_bookings' => isset($_POST['module_bookings']) ? 1 : 0,
        'bookings_secret' => isset($_POST['bookings_secret']) ? sanitize_text_field(wp_unslash($_POST['bookings_secret'])) : '',
    );

    update_option('adw_mecapi_settings', $settings);

    add_settings_error('adw_mecapi', 'adw_mecapi_saved', 'Réglages enregistrés.', 'updated');
}

/**
 * Affiche la page de réglages.
 */
function adw_mecapi_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = get_option('adw_mecapi_settings', array());
    $secret = isset($settings['bookings_secret']) ? $settings['bookings_secret'] : '';
    ?>
    <div class="wrap">
        <h1>ADW MEC API <small style="font-weight:normal;">v<?php echo esc_html(ADW_MECAPI_VERSION); ?></small></h1>

        <?php settings_errors('adw_mecapi'); ?>

        <form method="post">
            <?php wp_nonce_field('adw_mecapi_settings', 'adw_mecapi_nonce'); ?>

            <h2 class="title">Modules (API REST publique wp/v2/mec-events)</h2>
            <p>Ces champs s'ajoutent à la réponse JSON publique des événements. Lecture seule, sans impact sur l'affichage du site.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="module_organizer">Organisateur</label></th>
                    <td>
                        <input type="checkbox" id="module_organizer" name="module_organizer" <?php checked(!empty($settings['module_organizer'])); ?> />
                        <span class="description">Champ <code>mec_organizer</code> (nom, email, tél, site).</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="module_location">Lieu</label></th>
                    <td>
                        <input type="checkbox" id="module_location" name="module_location" <?php checked(!empty($settings['module_location'])); ?> />
                        <span class="description">Champ <code>mec_location</code> (nom, adresse, site).</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="module_price">Tarif</label></th>
                    <td>
                        <input type="checkbox" id="module_price" name="module_price" <?php checked(!empty($settings['module_price'])); ?> />
                        <span class="description">Champ <code>mec_price</code> (coût, montant, devise).</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="module_spots">Places restantes</label></th>
                    <td>
                        <input type="checkbox" id="module_spots" name="module_spots" <?php checked(!empty($settings['module_spots'])); ?> />
                        <span class="description">Champ <code>mec_availability</code> (limite, réservées, restantes, complet).</span>
                    </td>
                </tr>
            </table>

            <h2 class="title">Module pré-remplissage (formulaire de réservation MEC)</h2>
            <p>Lorsqu'une page d'événement est ouverte avec des paramètres <code>adw_*</code> dans l'URL, le formulaire de réservation MEC est automatiquement pré-rempli. Exemple :</p>
            <p><code><?php echo esc_html(site_url('/formation/xxx/?adw_prenom=Jean&adw_nom=Dupont&adw_email=jean@exemple.be&adw_tel=0470000000&adw_membre=1234')); ?></code></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="module_prefill">Activer le pré-remplissage</label></th>
                    <td>
                        <input type="checkbox" id="module_prefill" name="module_prefill" <?php checked(!empty($settings['module_prefill'])); ?> />
                        <span class="description">Reconnaît les champs par libellé (prénom, nom, e-mail, tél, n° de membre, adresse, âge). Ne remplit que les champs vides ; aucun script chargé si l'URL ne contient pas de paramètres <code>adw_*</code>.</span>
                    </td>
                </tr>
            </table>

            <h2 class="title">Module inscriptions (server-to-server uniquement)</h2>
            <p>Endpoint <code>GET /wp-json/adw/v1/bookings</code> — liste des inscriptions avec n° de membre. ⚠️ Ne doit <strong>jamais</strong> être appelé depuis un navigateur : le secret resterait visible côté client. Réservé au futur backend de l'application.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="module_bookings">Activer l'endpoint inscriptions</label></th>
                    <td>
                        <input type="checkbox" id="module_bookings" name="module_bookings" <?php checked(!empty($settings['module_bookings'])); ?> />
                        <span class="description">Désactivé par défaut. Sans secret configuré, l'endpoint répond 403.</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bookings_secret">Secret</label></th>
                    <td>
                        <input type="text" id="bookings_secret" name="bookings_secret" class="regular-text" value="<?php echo esc_attr($secret); ?>" autocomplete="off" />
                        <button type="button" class="button" id="adw-generate-secret" onclick="var f=document.getElementById('bookings_secret');f.value=adwRandomSecret();f.focus();">Générer</button>
                        <p class="description">Transmis via le header <code>X-ADW-Secret</code>. Longueur recommandée : 32+ caractères aléatoires.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Enregistrer les réglages'); ?>
        </form>

        <hr />
        <h2>Tests de diagnostic</h2>
        <?php
        $test_url = site_url('/wp-json/wp/v2/mec-events?per_page=1&_fields=id,title,mec_organizer,mec_location,mec_price,mec_availability');
        $resp = wp_remote_get($test_url, array('timeout' => 10));
        ?>
        <table class="widefat striped" style="max-width:900px;">
            <thead><tr><th>Test</th><th style="width:12%;">Résultat</th><th>Détail</th></tr></thead>
            <tbody>
            <?php
            /* Test 1 : API REST joignable + MEC actif. */
            $ok = false; $detail = '';
            if (is_wp_error($resp)) {
                $detail = 'Erreur réseau : ' . esc_html($resp->get_error_message());
            } else {
                $code = wp_remote_retrieve_response_code($resp);
                $body = json_decode(wp_remote_retrieve_body($resp), true);
                if ($code !== 200) {
                    $detail = 'HTTP ' . intval($code) . ' — l\'API REST est-elle accessible ? (sécurité/pare-feu de l\'hébergeur)';
                } elseif (!is_array($body)) {
                    $detail = 'Réponse non JSON.';
                } elseif (!isset($body[0]) && $body === array()) {
                    $ok = true; $detail = 'API OK mais aucun événement MEC trouvé.';
                } elseif (!isset($body[0]['id'])) {
                    $detail = 'Réponse inattendue — M.E. Calendar est-il actif ?';
                } else {
                    $ok = true; $detail = 'API REST et MEC opérationnels.';
                }
            }
            ?>
            <tr>
                <td><strong>1. API REST + M.E. Calendar</strong></td>
                <td><?php echo $ok ? '✅ <strong style="color:#00a32a;">OK</strong>' : '❌ <strong style="color:#d63638;">PAS OK</strong>'; ?></td>
                <td><?php echo $detail; ?></td>
            </tr>

            <?php
            /* Test 2 : les 4 champs personnalisés dans la réponse. */
            $ok = false; $detail = '';
            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $body = json_decode(wp_remote_retrieve_body($resp), true);
                if (isset($body[0]['id'])) {
                    $missing = array();
                    foreach (array('mec_organizer' => 'module_organizer', 'mec_location' => 'module_location', 'mec_price' => 'module_price', 'mec_availability' => 'module_spots') as $field => $module) {
                        if (empty($settings[$module])) {
                            $missing[] = $field . ' (module désactivé)';
                        } elseif (!array_key_exists($field, $body[0])) {
                            $missing[] = $field . ' (absent de la réponse)';
                        }
                    }
                    if (empty($missing)) {
                        $ok = true; $detail = 'Les 4 champs (organisateur, lieu, tarif, places) sont présents.';
                    } else {
                        $detail = 'Manquant : ' . esc_html(implode(', ', $missing));
                    }
                } else {
                    $detail = 'Impossible de tester (voir test 1).';
                }
            } else {
                $detail = 'Impossible de tester (voir test 1).';
            }
            ?>
            <tr>
                <td><strong>2. Champs personnalisés (organisateur, lieu, tarif, places)</strong></td>
                <td><?php echo $ok ? '✅ <strong style="color:#00a32a;">OK</strong>' : '❌ <strong style="color:#d63638;">PAS OK</strong>'; ?></td>
                <td><?php echo $detail; ?></td>
            </tr>

            <?php
            /* Test 3 : endpoint inscriptions (selon activation). */
            $ok = false; $detail = '';
            if (empty($settings['module_bookings'])) {
                $detail = 'Endpoint désactivé (normal en phase actuelle — à activer avec le backend).';
            } else {
                $bresp = wp_remote_get(site_url('/wp-json/adw/v1/bookings?per_page=1'), array('timeout' => 10));
                if (is_wp_error($bresp)) {
                    $detail = 'Erreur réseau : ' . esc_html($bresp->get_error_message());
                } else {
                    $bcode = wp_remote_retrieve_response_code($bresp);
                    if ($bcode === 401) {
                        $ok = true; $detail = 'Endpoint actif et protégé : sans secret → 401 (attendu).';
                    } elseif ($bcode === 403) {
                        $detail = 'Endpoint actif mais aucun secret configuré → 403. Générez un secret ci-dessus.';
                    } elseif ($bcode === 200) {
                        $detail = 'HTTP 200 sans secret — ⚠️ vérifiez la configuration du secret !';
                    } else {
                        $detail = 'HTTP ' . intval($bcode) . ' — inattendu.';
                    }
                }
            }
            ?>
            <tr>
                <td><strong>3. Endpoint inscriptions (sécurité)</strong></td>
                <td><?php echo $ok ? '✅ <strong style="color:#00a32a;">OK</strong>' : ($detail && strpos($detail, 'normal en phase') !== false ? '⚪ <strong>—</strong>' : '❌ <strong style="color:#d63638;">PAS OK</strong>'); ?></td>
                <td><?php echo $detail; ?></td>
            </tr>

            <?php
            /* Test 4 : module préfill actif (test navigateur via lien). */
            $ok = !empty($settings['module_prefill']);
            $events = array();
            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $ev = wp_remote_get(site_url('/wp-json/wp/v2/mec-events?per_page=1&_fields=link'), array('timeout' => 10));
                if (!is_wp_error($ev)) {
                    $evb = json_decode(wp_remote_retrieve_body($ev), true);
                    if (isset($evb[0]['link'])) { $events = $evb; }
                }
            }
            $link = !empty($events) ? add_query_arg(array('adw_prenom' => 'Test', 'adw_nom' => 'Prefill', 'adw_email' => 'test@atelierduweb.be', 'adw_tel' => '0470000000', 'adw_membre' => 'TEST'), $events[0]['link']) : '';
            $detail = $ok ? 'Cliquez le lien de test : la modale doit s\'ouvrir et les champs se remplir à l\'étape Participants.' : 'Module désactivé — cochez-le et enregistrez.';
            ?>
            <tr>
                <td><strong>4. Pré-remplissage (test manuel)</strong></td>
                <td><?php echo $ok ? '✅ <strong style="color:#00a32a;">OK</strong>' : '❌ <strong style="color:#d63638;">PAS OK</strong>'; ?></td>
                <td>
                    <?php echo $detail; ?>
                    <?php if ($ok && $link) : ?>
                        <a href="<?php echo esc_url($link); ?>" target="_blank" class="button button-small" style="margin-left:8px;">Ouvrir le test dans un onglet</a>
                    <?php endif; ?>
                </td>
            </tr>
            </tbody>
        </table>
        <p class="description">Tests 1-3 exécutés côté serveur au chargement de cette page ; test 4 à faire dans le navigateur (le remplissage s\'y vérifie visuellement).</p>

        <script>
            function adwRandomSecret() {
                var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                var s = '';
                for (var i = 0; i < 40; i++) {
                    s += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return s;
            }
        </script>
    </div>
    <?php
}
