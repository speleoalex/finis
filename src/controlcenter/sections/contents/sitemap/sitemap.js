/**
 * Sitemap editor: reorder, nest, show and hide the pages of the site.
 *
 * The page order lives in the DOM while it is edited and is sent to the server
 * in one hidden field, "sectionstring", formatted as "id:level:hidden,..." in
 * the order of the rows. FNCC_UpdateSections() rebuilds parent and position
 * out of it, so the contract with the server is unchanged.
 *
 * Works with the mouse, with a finger and with the keyboard: the arrow buttons
 * do what dragging does, which is also what a screen reader can use.
 */
(function () {
    "use strict";

    var MAX_LEVEL = 9;

    var tree, field, form, saveBar, status, resetButton;
    var initialState = "";

    function rows() {
        return Array.prototype.slice.call(tree.querySelectorAll(".fncc-tree__item"));
    }

    function levelOf(row) {
        return parseInt(row.getAttribute("data-level"), 10) || 0;
    }

    function setLevel(row, level) {
        if (level < 0) { level = 0; }
        if (level > MAX_LEVEL) { level = MAX_LEVEL; }
        row.setAttribute("data-level", level);
    }

    function isHidden(row) {
        return row.getAttribute("data-hidden") === "1";
    }

    /**
     * The rows that hang under this one: every following row that is deeper.
     */
    function descendants(row) {
        var list = [];
        var level = levelOf(row);
        var next = row.nextElementSibling;
        while (next && levelOf(next) > level) {
            list.push(next);
            next = next.nextElementSibling;
        }
        return list;
    }

    /**
     * A page can only be one step deeper than the page above it, and the first
     * one is always at the root. Without this a saved tree could claim a parent
     * that is not there.
     */
    function normalize() {
        var all = rows();
        var previousLevel = 0;
        all.forEach(function (row, index) {
            var level = levelOf(row);
            if (index === 0) {
                level = 0;
            } else if (level > previousLevel + 1) {
                level = previousLevel + 1;
            }
            setLevel(row, level);
            previousLevel = level;
        });
        updateButtons();
    }

    function updateButtons() {
        var all = rows();
        all.forEach(function (row, index) {
            var level = levelOf(row);
            var previousLevel = index > 0 ? levelOf(all[index - 1]) : -1;
            // Up and down move the page among the pages of its own group.
            disable(row, "up", previousSibling(row) === null);
            disable(row, "down", nextSibling(row) === null);
            disable(row, "left", level === 0);
            // Indenting is only possible under a page that can be a parent.
            disable(row, "right", index === 0 || level > previousLevel);
        });
    }

    function disable(row, action, off) {
        var button = row.querySelector("[data-fncc-move='" + action + "']");
        if (!button) { return; }
        button.disabled = !!off;
    }

    function serialize() {
        return rows().map(function (row) {
            return row.getAttribute("data-id") + ":" + levelOf(row) + ":" + (isHidden(row) ? "1" : "0");
        }).join(",");
    }

    function markChanged() {
        var changed = serialize() !== initialState;
        if (field) { field.value = serialize(); }
        if (saveBar) { saveBar.classList.toggle("is-dirty", changed); }
        if (status) {
            status.textContent = changed
                ? status.getAttribute("data-text-dirty")
                : status.getAttribute("data-text-clean");
        }
        if (resetButton) { resetButton.hidden = !changed; }
        if (changed && form) {
            // Let the shared dirty guard warn before leaving the page.
            form.dispatchEvent(new CustomEvent("fncc:changed"));
        }
    }

    /**
     * Move a row and everything under it before the given reference row
     * (or to the end when reference is null).
     */
    function moveBlock(row, reference) {
        var block = [row].concat(descendants(row));
        block.forEach(function (item) {
            tree.insertBefore(item, reference);
        });
    }

    /**
     * The page above this one at the same depth, or null when this is the
     * first page of its group.
     */
    function previousSibling(row) {
        var all = rows();
        var level = levelOf(row);
        var cursor = all.indexOf(row) - 1;
        // Walk back over the children of the page above to reach its first row.
        while (cursor >= 0 && levelOf(all[cursor]) > level) { cursor--; }
        return (cursor >= 0 && levelOf(all[cursor]) === level) ? all[cursor] : null;
    }

    /**
     * The page below this one at the same depth, or null when this is the
     * last page of its group.
     */
    function nextSibling(row) {
        var all = rows();
        var level = levelOf(row);
        var next = all[all.indexOf(row) + descendants(row).length + 1];
        return (next && levelOf(next) === level) ? next : null;
    }

    /** Swap the page with the one above it, children included. */
    function moveUp(row) {
        var target = previousSibling(row);
        if (!target) { return; }
        moveBlock(row, target);
        normalize();
        markChanged();
        focusMove(row, "up");
    }

    /** Swap the page with the one below it, children included. */
    function moveDown(row) {
        var target = nextSibling(row);
        if (!target) { return; }
        var targetBlock = [target].concat(descendants(target));
        moveBlock(row, targetBlock[targetBlock.length - 1].nextElementSibling);
        normalize();
        markChanged();
        focusMove(row, "down");
    }

    function indent(row, delta) {
        var block = [row].concat(descendants(row));
        block.forEach(function (item) {
            setLevel(item, levelOf(item) + delta);
        });
        normalize();
        markChanged();
        focusMove(row, delta > 0 ? "right" : "left");
    }

    function focusMove(row, action) {
        var button = row.querySelector("[data-fncc-move='" + action + "']");
        if (button && !button.disabled) {
            button.focus();
        } else {
            var handle = row.querySelector(".fncc-tree__handle");
            if (handle) { handle.focus(); }
        }
    }

    function toggleHidden(button) {
        var row = button.closest(".fncc-tree__item");
        var hidden = !isHidden(row);
        row.setAttribute("data-hidden", hidden ? "1" : "0");
        row.classList.toggle("is-hidden", hidden);
        button.setAttribute("aria-pressed", hidden ? "true" : "false");
        button.title = hidden
            ? button.getAttribute("data-text-show")
            : button.getAttribute("data-text-hide");
        markChanged();
    }

    /* ------------------------------------------------------- drag & drop */
    /* Pointer events cover mouse, pen and touch with one code path. */
    function setupDrag() {
        var dragging = null;
        var block = [];
        var dropTarget = null;
        var dropAfter = false;

        function clearMarks() {
            rows().forEach(function (row) {
                row.classList.remove("is-dropbefore", "is-dropafter");
            });
        }

        function rowFromPoint(x, y) {
            var element = document.elementFromPoint(x, y);
            return element && element.closest ? element.closest(".fncc-tree__item") : null;
        }

        tree.addEventListener("pointerdown", function (event) {
            var handle = event.target.closest(".fncc-tree__handle");
            if (!handle) { return; }
            event.preventDefault();
            dragging = handle.closest(".fncc-tree__item");
            block = [dragging].concat(descendants(dragging));
            block.forEach(function (row) { row.classList.add("is-dragging"); });
            handle.setPointerCapture(event.pointerId);
        });

        tree.addEventListener("pointermove", function (event) {
            if (!dragging) { return; }
            var over = rowFromPoint(event.clientX, event.clientY);
            clearMarks();
            dropTarget = null;
            if (!over || block.indexOf(over) !== -1) { return; }
            var box = over.getBoundingClientRect();
            dropAfter = (event.clientY - box.top) > (box.height / 2);
            dropTarget = over;
            over.classList.add(dropAfter ? "is-dropafter" : "is-dropbefore");
        });

        function endDrag(event) {
            if (!dragging) { return; }
            var handle = dragging.querySelector(".fncc-tree__handle");
            if (handle && event && handle.hasPointerCapture && handle.hasPointerCapture(event.pointerId)) {
                handle.releasePointerCapture(event.pointerId);
            }
            if (dropTarget) {
                var reference;
                if (dropAfter) {
                    var targetBlock = [dropTarget].concat(descendants(dropTarget));
                    reference = targetBlock[targetBlock.length - 1].nextElementSibling;
                } else {
                    reference = dropTarget;
                }
                // The dropped page becomes a sibling of the row it was dropped
                // on: taking the depth of the row above instead would turn the
                // pages that follow into its children.
                var delta = levelOf(dropTarget) - levelOf(dragging);
                block.forEach(function (row) { setLevel(row, levelOf(row) + delta); });
                moveBlock(dragging, reference);
                normalize();
                markChanged();
            }
            block.forEach(function (row) { row.classList.remove("is-dragging"); });
            clearMarks();
            dragging = null;
            block = [];
            dropTarget = null;
        }

        tree.addEventListener("pointerup", endDrag);
        tree.addEventListener("pointercancel", endDrag);
    }

    function init() {
        tree = document.getElementById("fncc-sitemap");
        if (!tree) { return; }
        field = document.getElementById("sectionstring");
        form = tree.closest("form");
        saveBar = document.getElementById("fncc-sitemap-savebar");
        status = document.getElementById("fncc-sitemap-status");
        resetButton = document.getElementById("fncc-sitemap-reset");

        normalize();
        initialState = serialize();
        if (field) { field.value = initialState; }
        if (resetButton) { resetButton.hidden = true; }

        tree.addEventListener("click", function (event) {
            var move = event.target.closest("[data-fncc-move]");
            if (move) {
                event.preventDefault();
                var row = move.closest(".fncc-tree__item");
                var action = move.getAttribute("data-fncc-move");
                if (action === "up") { moveUp(row); }
                if (action === "down") { moveDown(row); }
                if (action === "left") { indent(row, -1); }
                if (action === "right") { indent(row, 1); }
                return;
            }
            var toggle = event.target.closest("[data-fncc-hidden-toggle]");
            if (toggle) {
                event.preventDefault();
                toggleHidden(toggle);
            }
        });

        // The handle also answers the arrow keys, so the tree is usable
        // without a pointer.
        tree.addEventListener("keydown", function (event) {
            var handle = event.target.closest(".fncc-tree__handle");
            if (!handle) { return; }
            var row = handle.closest(".fncc-tree__item");
            var handled = true;
            if (event.key === "ArrowUp") { moveUp(row); }
            else if (event.key === "ArrowDown") { moveDown(row); }
            else if (event.key === "ArrowLeft") { indent(row, -1); }
            else if (event.key === "ArrowRight") { indent(row, 1); }
            else { handled = false; }
            if (handled) {
                event.preventDefault();
                handle.focus();
            }
        });

        if (resetButton) {
            resetButton.addEventListener("click", function () {
                window.location.reload();
            });
        }

        if (form) {
            form.addEventListener("submit", function () {
                if (field) { field.value = serialize(); }
            });
        }

        setupDrag();
        markChanged();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
}());
