<?php
/**
 * Module 4 — Pré-remplissage du formulaire de réservation MEC.
 *
 * Fonctionnement : l'application de gestion des membres ouvre la page
 * d'un événement avec des paramètres d'URL préfixés « adw_ » :
 *
 *   https://atelierduweb.be/formation/xxx/?adw_prenom=Jean&adw_nom=Dupont
 *     &adw_email=jean@exemple.be&adw_membre=1234&adw_genre=F&adw_statut=Etudiant
 *
 * Un script injecté en wp_footer (seulement si des paramètres adw_* sont
 * présents) remplit le formulaire de réservation MEC. Le formulaire est
 * multi-étapes : les champs participants (étape 2) sont rendus en AJAX
 * dans #mec_booking{event_id} — couvert via MutationObserver + poll.
 *
 * Champs remplis :
 *   - Nom / e-mail : sélecteurs structurels MEC (.mec-book-reg-field-name,
 *     input[name$="][name]"], .mec-book-reg-field-mec_email).
 *   - Tous les autres champs (tél, n° de membre, genre, statut, adresse,
 *     âge, ...) : reconnus par leur libellé. Chaque paramètre adw_<clef>
 *     hors liste connue est apparié au champ dont le libellé contient
 *     <clef> (ex. adw_genre -> champ « Genre », adw_statut -> « Statut »).
 *   - Texte, listes déroulantes, boutons radio et cases à cocher simples.
 *
 * Garanties (site en production) :
 *   - Aucune écriture en base, aucun fichier modifié.
 *   - Script minime, sans dépendance, chargé uniquement quand utile.
 *   - Ne remplit que les champs vides / non cochés (idempotent).
 *   - Jamais le décompte de billets (book[tickets][X]) ni les champs système.
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

    /* Champs logiques du pré-remplissage. Chaque entrée accepte plusieurs
     * synonymes de paramètres URL : la convention du plugin (adw_prenom,
     * adw_tel, adw_membre, ...) et celle de l'application (adw_name,
     * adw_phone, adw_member_no, ...). Le premier trouvé gagne. */
    $params = array(
        'prenom'         => array('adw_prenom', 'adw_firstname'),
        'nom'            => array('adw_nom', 'adw_lastname', 'adw_name'),
        'email'          => array('adw_email'),
        'tel'            => array('adw_tel', 'adw_phone'),
        'membre'         => array('adw_membre', 'adw_member_no', 'adw_member'),
        'adresse'        => array('adw_adresse', 'adw_address'),
        'code_postal'    => array('adw_code_postal', 'adw_postal_code', 'adw_zip'),
        'date_naissance' => array('adw_date_naissance', 'adw_birthdate', 'adw_dob'),
        'genre'          => array('adw_genre', 'adw_gender'),
        'statut'         => array('adw_statut', 'adw_status'),
        'age'            => array('adw_age'),
    );

    $values = array();
    foreach ($params as $key => $aliases) {
        foreach ($aliases as $param) {
            if (isset($_GET[$param])) {
                $values[$key] = sanitize_text_field(wp_unslash($_GET[$param]));
                break;
            }
        }
    }

    /* Paramètres adw_* libres : adw_<clef> remplit le champ dont le
       libellé contient <clef> (n° de membre, code postal, etc.). */
    $known = array();
    foreach ($params as $aliases) {
        $known = array_merge($known, $aliases);
    }
    foreach ($_GET as $key => $value) {
        $clean = sanitize_key($key);
        if (strpos($clean, 'adw_') !== 0 || in_array($clean, $known, true)) {
            continue;
        }
        $short = substr($clean, 4);
        if ($short !== '') {
            $values[$short] = sanitize_text_field(wp_unslash($value));
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

        /* Clés traitées séparément (nom structurel, e-mail, nom complet fusionné). */
        var SPECIAL = ['nom_complet', 'email', 'prenom', 'nom'];

        /* Libellés par clé, en plus du nom du paramètre lui-même. */
        var EXTRA_LABELS = {
            tel:            ['phone', 'telephone', 'gsm', 'mobile', 'portable'],
            membre:         ['member'],
            adresse:        ['address', 'rue', 'adresse postale'],
            code_postal:    ['postal'],
            date_naissance: ['date de naissance', 'naissance', 'age'],
            genre:         ['sexe', 'civilite'],
            statut:         ['status', 'situation'],
            prenom:         ['first name', 'firstname', 'given name'],
            nom:           ['nom de famille', 'last name', 'lastname', 'surname', 'family name']
        };

        /* Identifiants MEC (data-field-id) des champs configurés sur le
           site atelierduweb.be — reconnaissance prioritaire, infaillible
           même si les libellés changent. */
        var FIELD_IDS = {
            membre:         '26',
            date_naissance: '2',
            adresse:        '3',
            code_postal:    '4',
            tel:            '5',
            genre:          '6',
            statut:         '7'
        };

        function norm(s) {
            return (s || '').toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        /* Motif recherché comme mot entier (évite « nom » dans « prenom »). */
        function wordMatch(text, pattern) {
            var p = norm(pattern).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp('(^|[^a-z])' + p).test(norm(text));
        }

        function fieldLabelText(field) {
            var holder = field.closest('li, label, .mec-book-reg-field, .mec-book-bfixed-field, p, div');
            if (!holder) { return ''; }
            var label = holder.querySelector('label');
            return (label ? label.textContent : '') || holder.textContent || '';
        }

        function patternsFor(key) {
            var patterns = EXTRA_LABELS[key] ? EXTRA_LABELS[key].slice() : [];
            patterns.push(key);
            return patterns;
        }

        function matchLabel(field, patterns) {
            var text = norm(field.name) + ' ' + norm(field.id) + ' ' + norm(field.placeholder) + ' ' + norm(fieldLabelText(field));
            for (var i = 0; i < patterns.length; i++) {
                if (wordMatch(text, patterns[i])) { return true; }
            }
            return false;
        }

        function fieldHolder(field) {
            return field.closest('li, .mec-book-reg-field, .mec-book-bfixed-field');
        }

        function holderVisible(holder) {
            return !!holder && !!(holder.offsetWidth || holder.offsetHeight || holder.getClientRects().length);
        }

        function editable(field) {
            if (field.disabled || field.readOnly) { return false; }
            if ((field.type !== 'radio' && field.type !== 'checkbox') &&
                field.value && String(field.value).length) { return false; }
            if (field.getAttribute('aria-disabled') === 'true') { return false; }
            /* Champs masqués par CSS (ex. radios admin) : ignorés. */
            if (!holderVisible(fieldHolder(field))) { return false; }
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

        /* Une option (radio/checkbox/select) correspond si son libellé ou
           sa valeur est égal à la valeur, ou la première lettre si valeur
           courte (F -> Femme, M -> Masculin). */
        function optionMatches(value, text, optValue) {
            var v = norm(value), t = norm(text), ov = norm(optValue);
            if (t === v || ov === v) { return true; }
            if (v.length === 1 && (t.charAt(0) === v || ov.charAt(0) === v)) { return true; }
            return false;
        }

        function setValue(field, value) {
            if (field.tagName === 'SELECT') {
                for (var i = 0; i < field.options.length; i++) {
                    if (optionMatches(value, field.options[i].textContent, field.options[i].value)) {
                        field.selectedIndex = i;
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        return true;
                    }
                }
                return false;
            }
            if (field.type === 'radio' || field.type === 'checkbox') {
                /* Le libellé de l'option est le label englobant ou suivant. */
                var optLabel = field.closest('label');
                var text = optLabel ? optLabel.textContent : '';
                if (!optLabel && field.nextElementSibling) { text = field.nextElementSibling.textContent; }
                if (!field.checked && optionMatches(value, text, field.value)) {
                    field.checked = true;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    return true;
                }
                return false;
            }
            if (field.type === 'hidden') { return false; }
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        }

        function fill(key, predicate) {
            if (!data[key]) { return; }
            var fields = regFields();
            for (var i = 0; i < fields.length; i++) {
                if (predicate(fields[i]) && setValue(fields[i], data[key])) {
                    filled[key] = true;
                    return;
                }
            }
        }

        var filled = {};

        function isNameField(f) {
            return !!(f.closest && f.closest('.mec-book-reg-field-name')) || /\]\[name]$/.test(f.name || '');
        }
        function isEmailField(f) {
            return !!(f.closest && f.closest('.mec-book-reg-field-mec_email, .mec-book-reg-field-email')) || /\]\[email]$/.test(f.name || '');
        }

        function fillAll() {
            if (data.nom_complet) { fill('nom_complet', isNameField); }
            if (data.email) { fill('email', isEmailField); }
            Object.keys(data).forEach(function (key) {
                if (SPECIAL.indexOf(key) !== -1) { return; }
                /* 1. Reconnaissance par data-field-id MEC (prioritaire). */
                if (FIELD_IDS[key]) {
                    var fid = FIELD_IDS[key];
                    fill(key, function (f) {
                        var h = fieldHolder(f);
                        return !!(h && h.getAttribute('data-field-id') === fid);
                    });
                    if (filled[key]) { return; }
                }
                /* 2. Repli par libellé. */
                fill(key, function (f) { return matchLabel(f, patternsFor(key)); });
            });
        }

        /* L'étape 2 est rendue en AJAX : on observe #mec_booking* + le body. */
        function containers() {
            return document.querySelectorAll('[id^="mec_booking"], .mec-booking');
        }

        var attempts = 0;

        function tick() {
            fillAll();
            if (++attempts >= 24) { return; }
            setTimeout(tick, 500);
        }

        function start() {
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function () { fillAll(); });
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
