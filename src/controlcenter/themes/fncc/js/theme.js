/**
 * Layout behaviour of the "fncc" theme: sidebar drawer, collapsible menu
 * groups and the top bar menus. Plain JavaScript, no library.
 */
(function () {
    "use strict";

    var OPEN_GROUPS_KEY = "fncc-open-groups";

    function store(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) { /* ignored */ }
    }

    function read(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }

    /* ------------------------------------------------------------ sidebar */
    function setupSidebar() {
        var sidebar = document.getElementById("fncc-sidebar");
        var backdrop = document.getElementById("fncc-backdrop");
        var toggles = document.querySelectorAll("[data-fncc-sidebar-toggle]");
        if (!sidebar) { return; }

        function open(isOpen) {
            sidebar.classList.toggle("is-open", isOpen);
            if (backdrop) { backdrop.hidden = !isOpen; }
            Array.prototype.forEach.call(toggles, function (button) {
                button.setAttribute("aria-expanded", isOpen ? "true" : "false");
            });
            if (isOpen) {
                var first = sidebar.querySelector("a, button");
                if (first) { first.focus(); }
            }
        }

        Array.prototype.forEach.call(toggles, function (button) {
            button.addEventListener("click", function (event) {
                event.preventDefault();
                open(!sidebar.classList.contains("is-open"));
            });
        });

        if (backdrop) {
            backdrop.addEventListener("click", function () { open(false); });
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && sidebar.classList.contains("is-open")) {
                open(false);
                var toggle = document.querySelector("[data-fncc-sidebar-toggle]");
                if (toggle) { toggle.focus(); }
            }
        });
    }

    /* ------------------------------------------------------- menu groups */
    function setupNavGroups() {
        var openGroups = (read(OPEN_GROUPS_KEY) || "").split(",");

        Array.prototype.forEach.call(
            document.querySelectorAll("[data-fncc-group-toggle]"),
            function (button) {
                var id = button.getAttribute("aria-controls");
                var panel = document.getElementById(id);
                if (!panel) { return; }

                // The group holding the current page is open from the server.
                var isOpen = button.getAttribute("aria-expanded") === "true";
                if (!isOpen && openGroups.indexOf(id) !== -1) {
                    isOpen = true;
                    button.setAttribute("aria-expanded", "true");
                    panel.hidden = false;
                }

                button.addEventListener("click", function () {
                    var nowOpen = button.getAttribute("aria-expanded") !== "true";
                    button.setAttribute("aria-expanded", nowOpen ? "true" : "false");
                    panel.hidden = !nowOpen;

                    var ids = [];
                    Array.prototype.forEach.call(
                        document.querySelectorAll("[data-fncc-group-toggle][aria-expanded='true']"),
                        function (open) { ids.push(open.getAttribute("aria-controls")); }
                    );
                    store(OPEN_GROUPS_KEY, ids.join(","));
                });
            }
        );
    }

    /* ---------------------------------------------------------- dropdown */
    function setupDropdowns() {
        var toggles = document.querySelectorAll("[data-fncc-dropdown-toggle]");

        function closeAll(except) {
            Array.prototype.forEach.call(toggles, function (button) {
                if (button === except) { return; }
                var menu = document.getElementById(button.getAttribute("aria-controls"));
                button.setAttribute("aria-expanded", "false");
                if (menu) { menu.hidden = true; }
            });
        }

        Array.prototype.forEach.call(toggles, function (button) {
            var menu = document.getElementById(button.getAttribute("aria-controls"));
            if (!menu) { return; }
            button.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                var isOpen = button.getAttribute("aria-expanded") === "true";
                closeAll(button);
                button.setAttribute("aria-expanded", isOpen ? "false" : "true");
                menu.hidden = isOpen;
            });
        });

        document.addEventListener("click", function () { closeAll(null); });
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") { closeAll(null); }
        });
    }

    function init() {
        setupSidebar();
        setupNavGroups();
        setupDropdowns();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
}());
