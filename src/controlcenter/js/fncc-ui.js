/**
 * Control center UI behaviours shared by every theme.
 *
 * Loaded by FNCC_UI_IncludeAssets(). Plain JavaScript, no library: it only
 * reads data attributes emitted by the FNCC_UI_* helpers, so a section stays
 * free of inline onclick handlers.
 *
 *   data-fncc-confirm="question"   ask before following a link / submitting
 *   data-fncc-dirty-guard          warn before leaving the form with changes
 *   data-fncc-theme-toggle         cycle automatic / light / dark
 */
(function () {
    "use strict";

    var THEME_KEY = "fncc-theme";
    var THEME_ORDER = ["auto", "light", "dark"];

    function storage(action, key, value) {
        // Private browsing and blocked site data make localStorage throw.
        try {
            if (action === "get") { return window.localStorage.getItem(key); }
            window.localStorage.setItem(key, value);
        } catch (e) {
            return null;
        }
        return null;
    }

    function applyTheme(mode) {
        var root = document.documentElement;
        if (mode === "light" || mode === "dark") {
            root.setAttribute("data-fncc-theme", mode);
        } else {
            root.removeAttribute("data-fncc-theme");
        }
        Array.prototype.forEach.call(
            document.querySelectorAll("[data-fncc-theme-toggle]"),
            function (button) {
                // Words, not symbols: the glyphs for "automatic" are missing
                // from many fonts and come out as a dot.
                var label = button.getAttribute("data-label-" + mode)
                    || button.getAttribute("data-label-auto")
                    || "Auto";
                var text = button.querySelector("[data-fncc-theme-label]");
                if (text) { text.textContent = label; }
                button.setAttribute("title", label);
            }
        );
    }

    function currentTheme() {
        var stored = storage("get", THEME_KEY);
        return THEME_ORDER.indexOf(stored) === -1 ? "auto" : stored;
    }

    // Confirmation on destructive links and buttons.
    document.addEventListener("click", function (event) {
        var target = event.target.closest ? event.target.closest("[data-fncc-confirm]") : null;
        if (!target) { return; }
        if (!window.confirm(target.getAttribute("data-fncc-confirm"))) {
            event.preventDefault();
            event.stopPropagation();
        }
    });

    document.addEventListener("click", function (event) {
        var toggle = event.target.closest ? event.target.closest("[data-fncc-theme-toggle]") : null;
        if (!toggle) { return; }
        event.preventDefault();
        var next = THEME_ORDER[(THEME_ORDER.indexOf(currentTheme()) + 1) % THEME_ORDER.length];
        storage("set", THEME_KEY, next);
        applyTheme(next);
    });

    /**
     * Warn before leaving a form whose fields were touched. The warning is
     * dropped as soon as the form is submitted, so saving never triggers it.
     */
    function guardForm(form) {
        var dirty = false;
        var mark = function () {
            dirty = true;
            form.setAttribute("data-fncc-dirty", "1");
            document.dispatchEvent(new CustomEvent("fncc:dirty", { detail: { form: form } }));
        };
        form.addEventListener("change", mark);
        form.addEventListener("fncc:changed", mark);
        form.addEventListener("submit", function () {
            dirty = false;
            form.removeAttribute("data-fncc-dirty");
        });
        window.addEventListener("beforeunload", function (event) {
            if (!dirty) { return; }
            event.preventDefault();
            // Browsers show their own wording; a non empty value is what counts.
            event.returnValue = "";
            return "";
        });
    }

    function init() {
        applyTheme(currentTheme());
        Array.prototype.forEach.call(
            document.querySelectorAll("form[data-fncc-dirty-guard]"),
            guardForm
        );
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
}());
