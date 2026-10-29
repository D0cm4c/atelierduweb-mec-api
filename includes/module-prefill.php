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
 * présents) remplit le formulaire de réservation MEC — modale ou page —
 * en reconnaissant les champs par leur libellé ou leur attribut,
 * indépendamment des identifiants générés par MEC.
 *
 * Garanties (site en production) :
 *   - Aucune écriture en base, aucun fichier modifié.
 *   - Script minime, sans dépendance, chargé uniquement quand utile.
 *   - Gère l'ouverture asynchrone de la modale MEC (poll + MutationObserver).
 *
 * @package ADW_MECAPI
 */

if (!defined('ABSPATH')) {
    return;
}

add_action('wp_footer', 'adw_mecapi_prefill_maybe_print');

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

    $json = wp_json_encode($values);
    ?>
    <script>
    (function () {
        if (typeof ADWPrefill !== 'undefined') { return; }
        window.ADWPrefill = true;

        var data = <?php echo $json; ?>;

        /* Noms de champs / libellés acceptés, en minuscules sans accents. */
        var PATTERNS = {
            prenom:  ['prenom', 'prénom', 'first name', 'firstname', 'given name'],
            nom:     ['nom', 'last name', 'lastname', 'surname', 'family name', 'nom de famille'],
            email:   ['email', 'e-mail', 'courriel', 'mail'],
            tel:     ['tel', 'phone', 'telephone', 'gsm', 'mobile', 'portable'],
            membre:  ['membre', 'numero de membre', 'n° de membre', 'numéro de membre', 'member'],
            adresse: ['adresse', 'address', 'rue'],
            age:     ['age', 'âge', 'date de naissance']
        };

        function norm(s) {
            return (s || '').toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function fieldLabel(field) {
            if (field.id) {
                var label = document.querySelector('label[for="' + CSS.escape(field.id) + '"]');
                if (label) { return label.textContent || ''; }
            }
            var wrap = field.closest('label');
            if (wrap) { return wrap.textContent || ''; }
            var holder = field.closest('.mec-book-reg-field, .mec-book-inner, .mec-booking-reg-field, li, p, div');
            if (holder) { return holder.textContent || ''; }
            return '';
        }

        function matchField(field, patterns) {
            var text = norm(field.name) + ' ' + norm(field.id) + ' ' + norm(field.placeholder) + ' ' + norm(field.getAttribute('aria-label')) + ' ' + norm(fieldLabel(field));
            for (var i = 0; i < patterns.length; i++) {
                var p = norm(patterns[i]);
                if (p && text.indexOf(p) !== -1) { return true; }
            }
            return false;
        }

        function setValue(field, value) {
            if (field.tagName === 'SELECT') {
                for (var i = 0; i < field.options.length; i++) {
                    if (norm(field.options[i].textContent).indexOf(norm(value)) !== -1 ||
                        norm(field.options[i].value) === norm(value)) {
                        field.value = field.options[i].value;
                        return true;
                    }
                }
                return false;
            }
            if (field.type === 'radio' || field.type === 'checkbox') { return false; }
            field.value = value;
            return true;
        }

        function fillOne(key, fields) {
            if (!data[key]) { return; }
            var patterns = PATTERNS[key] || [];
            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                if (f.disabled || f.readOnly || (f.value && f.value.length)) { continue; }
                if (matchField(f, patterns)) { setValue(f, data[key]); return; }
            }
        }

        function fillAll() {
            var fields = document.querySelectorAll('input[type=text], input[type=email], input[type=tel], input[type=number], input[type=date], input:not([type]), select');
            Object.keys(PATTERNS).forEach(function (key) { fillOne(key, fields); });
        }

        /* Champs visibles uniquement + idempotent (remplit seulement les champs vides). */
        function visible(f) {
            return !!(f.offsetWidth || f.offsetHeight || f.getClientRects().length);
        }

        var attempts = 0, filledOnce = false;
        function tick() {
            var fields = document.querySelectorAll('input[type=text], input[type=email], input[type=tel], input[type=number], input[type=date], input:not([type]), select');
            fields = Array.prototype.filter.call(fields, visible);
            if (fields.length) {
                if (!filledOnce) { fillAll(); filledOnce = true; }
                /* La modale MEC peut re-rendre son formulaire : on complète les vides. */
                fillAll();
            }
            if (++attempts < 40) { setTimeout(tick, 500); }
        }
        tick();

        if (typeof MutationObserver !== 'undefined') {
            new MutationObserver(function () {
                fillAll();
            }).observe(document.body, { childList: true, subtree: true });
        }
    })();
    </script>
    <?php
}
