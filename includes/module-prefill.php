<?php
/**
 * Module 4 — Pré-remplissage du formulaire de réservation MEC.
 *
 * Fonctionnement : l'application de gestion des membres ouvre la page
 * d'un événement avec des paramètres d'URL préfixés « adw_ » :
 *
 *   https://atelierduweb.be/formation/xxx/?adw_prenom=Jean&adw_nom=Dupont
 *     &adw_email=jean@exemple.be&adw_tel=0470000000&adw_membre=1234
 *
 * Un script injecté en wp_footer (seulement si des paramètres adw_* sont
 * présents) remplit le formulaire de réservation MEC. Le formulaire est
 * multi-étapes : les champs participants (étape 2) sont rendus en AJAX
 * dans #mec_booking{event_id} — couvert via MutationObserver + poll.
 *
 * Stratégie de reconnaissance (issue du HTML réel du site) :
 *   1. Sélecteurs structurels MEC :
 *      - nom      : .mec-book-reg-field-name input, input[name$="][name]"]
 *      - e-mail   : .mec-book-reg-field-mec_email input, input[name$="][email]"]
 *   2. Repli par libellé sur les champs personnalisés
 *      (li.mec-book-reg-field-*, .mec-book-bfixed-field-*) pour
 *      tél, adresse, âge, n° de membre.
 *   3. Jamais les champs de décompte de billets (book[tickets][X],
 *      .mec-book-ticket-limit) ni les inputs système.
 *
 * Garanties (site en production) :
 *   - Aucune écriture en base, aucun fichier modifié.
 *   - Script minime, sans dépendance, chargé uniquement quand utile.
 *   - Ne remplit que les champs vides (idempotent).
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

add_action('template_redirect', 'adw_mecapi_prefill_open_modal');
add_action('wp_footer', 'adw_mecapi_prefill_maybe_print');

/**
 * Si l'URL contient des paramètres adw_* sans « method », on redirige vers
 * la même URL avec method=mec-booking-modal : la modale de réservation
 * s'ouvre directement avec les champs pré-remplis.
 */
function adw_mecapi_prefill_open_modal() {
    if (!adw_mecapi_setting('module_prefill')) {
        return;
    }

    $has_adw = false;
    foreach ($_GET as $key => $ignored) {
        if (strpos(sanitize_key($key), 'adw_') === 0) {
            $has_adw = true;
            break;
        }
    }
    if (!$has_adw || isset($_GET['method'])) {
        return;
    }

    if (!is_singular('mec-events')) {
        return;
    }

    wp_safe_redirect(add_query_arg('method', 'mec-booking-modal'));
    exit;
}

/**
 * Injecte le script de pré-remplissage si l'URL contient des paramètres adw_*.
 */
function adw_mecapi_prefill_maybe_print() {
    if (!adw_mecapi_setting('module_prefill')) {
        return;
    }

    $params = array(
        'prenom'  => 'adw_prenom',
        'nom'     => 'adw_nom',
        'email'   => 'adw_email',
        'tel'     => 'adw_tel',
        'membre'  => 'adw_membre',
        'adresse' => 'adw_adresse',
        'age'     => 'adw_age',
    );

    $values = array();
    foreach ($params as $key => $param) {
        if (isset($_GET[$param])) {
            $values[$key] = sanitize_text_field(wp_unslash($_GET[$param]));
        }
    }

    if (empty($values)) {
        return;
    }

    /* Le champ « name » de MEC attend le participant complet. */
    if (isset($values['nom']) && isset($values['prenom'])) {
        $values['nom_complet'] = $values['prenom'] . ' ' . $values['nom'];
    } elseif (isset($values['nom'])) {
        $values['nom_complet'] = $values['nom'];
    } elseif (isset($values['prenom'])) {
        $values['nom_complet'] = $values['prenom'];
    }

    $json = wp_json_encode($values);
    ?>
    <script>
    (function () {
        if (window.ADWPrefill) { return; }
        window.ADWPrefill = true;

        var data = <?php echo $json; ?>;

        function norm(s) {
            return (s || '').toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        /* Motif recherché comme mot entier (évite « nom » dans « prenom »). */
        function wordMatch(text, pattern) {
            var p = norm(pattern).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp('(^|[^a-z])' + p).test(norm(text));
        }

        var LABELS = {
            tel:     ['tel', 'phone', 'telephone', 'gsm', 'mobile', 'portable'],
            membre:  ['membre', 'member'],
            adresse: ['adresse', 'address', 'rue'],
            age:     ['age', 'date de naissance'],
            prenom:  ['prenom', 'first name', 'firstname', 'given name'],
            nom:     ['nom de famille', 'last name', 'lastname', 'surname', 'family name']
        };

        function fieldLabelText(field) {
            var holder = field.closest('li, label, .mec-book-reg-field, .mec-book-bfixed-field, p, div');
            if (!holder) { return ''; }
            var label = holder.querySelector('label');
            return (label ? label.textContent : '') || holder.textContent || '';
        }

        function matchLabel(field, patterns) {
            var text = norm(field.name) + ' ' + norm(field.id) + ' ' + norm(field.placeholder) + ' ' + norm(fieldLabelText(field));
            for (var i = 0; i < patterns.length; i++) {
                if (wordMatch(text, patterns[i])) { return true; }
            }
            return false;
        }

        function editable(field) {
            if (field.disabled || field.readOnly) { return false; }
            if (field.value && String(field.value).length) { return false; }
            if (field.getAttribute('aria-disabled') === 'true') { return false; }
            return true;
        }

        /* Seuls les champs de saisie de participant — jamais le décompte
           de billets (book[tickets][X], .mec-book-ticket-limit) ni les
           champs système (action, event_id, step, nonce...). */
        function regFields() {
            var selector = '.mec-book-reg-field input, .mec-book-reg-field select, .mec-book-reg-field textarea,'
                + ' .mec-book-bfixed-field input, .mec-book-bfixed-field select, .mec-book-bfixed-field textarea,'
                + ' input[name$="][name]"], input[name$="][email]"], input[name*="][reg]["]';
            return Array.prototype.filter.call(document.querySelectorAll(selector), editable);
        }

        function setValue(field, value) {
            if (field.tagName === 'SELECT') {
                for (var i = 0; i < field.options.length; i++) {
                    if (norm(field.options[i].textContent) === norm(value) ||
                        norm(field.options[i].value) === norm(value)) {
                        field.selectedIndex = i;
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        return true;
                    }
                }
                return false;
            }
            if (field.type === 'radio' || field.type === 'checkbox' || field.type === 'hidden') { return false; }
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }

        function fill(key, predicate) {
            if (!data[key]) { return; }
            var fields = regFields();
            for (var i = 0; i < fields.length; i++) {
                if (predicate(fields[i])) {
                    setValue(fields[i], data[key]);
                    return;
                }
            }
        }

        function isNameField(f) {
            return !!(f.closest && f.closest('.mec-book-reg-field-name')) || /\]\[name]$/.test(f.name || '');
        }
        function isEmailField(f) {
            return !!(f.closest && f.closest('.mec-book-reg-field-mec_email, .mec-book-reg-field-email')) || /\]\[email]$/.test(f.name || '');
        }

        function fillAll() {
            if (data.nom_complet) { fill('nom_complet', isNameField); }
            if (data.email) { fill('email', isEmailField); }
            if (data.prenom) { fill('prenom', function (f) { return matchLabel(f, LABELS.prenom); }); }
            if (data.nom) { fill('nom', function (f) { return !isNameField(f) && matchLabel(f, LABELS.nom); }); }
            if (data.tel) { fill('tel', function (f) { return matchLabel(f, LABELS.tel); }); }
            if (data.membre) { fill('membre', function (f) { return matchLabel(f, LABELS.membre); }); }
            if (data.adresse) { fill('adresse', function (f) { return matchLabel(f, LABELS.adresse); }); }
            if (data.age) { fill('age', function (f) { return matchLabel(f, LABELS.age); }); }
        }

        /* L'étape 2 est rendue en AJAX : on observe #mec_booking* + le body. */
        function containers() {
            return document.querySelectorAll('[id^="mec_booking"], .mec-booking');
        }

        var attempts = 0;
        var observer = null;

        function tick() {
            fillAll();
            if (++attempts >= 24) { return; }
            setTimeout(tick, 500);
        }

        function start() {
            if (typeof MutationObserver !== 'undefined') {
                observer = new MutationObserver(function () { fillAll(); });
                containers().forEach(function (t) { observer.observe(t, { childList: true, subtree: true }); });
                observer.observe(document.body, { childList: true, subtree: true });
            }
            tick();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    })();
    </script>
    <?php
}
