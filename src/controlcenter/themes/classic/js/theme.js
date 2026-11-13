/**
 * Control center theme "classic": the bar of groups at the top.
 *
 * One group at a time is open. On a wide screen the list hangs under its
 * button, on a narrow one the whole bar is a drawer; the same two handlers
 * cover both, because what changes is the stylesheet, not the markup.
 *
 * The shared behaviours (light and dark, confirmations, unsaved changes) are
 * in controlcenter/js/fncc-ui.js and are not repeated here.
 */
(function () {
    "use strict";

    var menu, backdrop, burger;

    function groups() {
        return Array.prototype.slice.call(document.querySelectorAll("[data-fncl-group-toggle]"));
    }

    function listOf(button) {
        return document.getElementById(button.getAttribute("aria-controls"));
    }

    function closeGroups(except) {
        groups().forEach(function (button) {
            if (button === except) { return; }
            button.setAttribute("aria-expanded", "false");
            var list = listOf(button);
            if (list) { list.hidden = true; }
        });
    }

    function toggleGroup(button) {
        var open = button.getAttribute("aria-expanded") === "true";
        closeGroups(button);
        button.setAttribute("aria-expanded", open ? "false" : "true");
        var list = listOf(button);
        if (list) { list.hidden = open; }
    }

    function drawerOpen() {
        return !!menu && menu.classList.contains("is-open");
    }

    function setDrawer(open) {
        if (!menu) { return; }
        menu.classList.toggle("is-open", open);
        if (backdrop) { backdrop.hidden = !open; }
        if (burger) { burger.setAttribute("aria-expanded", open ? "true" : "false"); }
        if (!open) { closeGroups(null); }
    }

    function init() {
        menu = document.getElementById("fncl-menu");
        backdrop = document.getElementById("fncl-backdrop");
        burger = document.querySelector("[data-fncl-menu-toggle]");
        if (!menu) { return; }

        // Closed to start with: the open group of a wide screen would otherwise
        // be a panel over the page before anyone asked for it.
        closeGroups(null);

        document.addEventListener("click", function (event) {
            var toggle = event.target.closest ? event.target.closest("[data-fncl-group-toggle]") : null;
            if (toggle) {
                event.preventDefault();
                toggleGroup(toggle);
                return;
            }
            if (event.target.closest && event.target.closest("[data-fncl-menu-toggle]")) {
                event.preventDefault();
                setDrawer(!drawerOpen());
                return;
            }
            // A click anywhere else puts the open panel away.
            if (!event.target.closest || !event.target.closest(".fncl-menu")) {
                closeGroups(null);
                if (drawerOpen()) { setDrawer(false); }
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key !== "Escape") { return; }
            closeGroups(null);
            if (drawerOpen()) {
                setDrawer(false);
                if (burger) { burger.focus(); }
            }
        });

        if (backdrop) {
            backdrop.addEventListener("click", function () { setDrawer(false); });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
}());
