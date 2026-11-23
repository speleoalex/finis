/**
 * Images of a dbview record page in an overlay instead of a new window.
 * Included by modules/dbview/section.php in the record page, after
 * window.FN_IMAGE_OVERLAY_LABELS (translated labels). A click on a link that
 * holds an image and points to an image file (the image fields of finis mark
 * theirs with the class fn-image-zoom) shows the picture over the page, with
 * two icons to open it in a new window or download it. A click with a
 * modifier key keeps the normal behaviour of the link (new tab).
 * The overlay adds no history entry: dbview uses popstate for its ajax lists.
 */
(function () {
    if (window.FN_ImageOverlay) {
        return; // the record page loaded again by the ajax navigation
    }
    window.FN_ImageOverlay = true;
    var labels = window.FN_IMAGE_OVERLAY_LABELS || {};
    var imageUrl = /\.(jpe?g|png|gif|webp|bmp|svg)([?#].*)?$/i;
    var overlay = null, picture = null, openLink = null, downloadLink = null, message = null, lastFocus = null;

    function label(key, fallback) {
        return labels[key] || fallback;
    }

    function build() {
        var style = document.createElement("style");
        style.textContent =
            ".fn-imgov{position:fixed;inset:0;z-index:2000;display:flex;align-items:center;justify-content:center;" +
            "background:rgba(0,0,0,.88);padding:56px 12px 12px;box-sizing:border-box}" +
            ".fn-imgov[hidden]{display:none}" +
            ".fn-imgov img{max-width:100%;max-height:100%;object-fit:contain;background:#fff;box-shadow:0 4px 24px rgba(0,0,0,.5)}" +
            ".fn-imgov-msg{color:#fff;font-size:1rem}" +
            ".fn-imgov-bar{position:absolute;top:6px;right:6px;display:flex;gap:4px}" +
            ".fn-imgov-bar a,.fn-imgov-bar button{display:inline-flex;align-items:center;justify-content:center;" +
            "min-width:44px;height:44px;border:0;border-radius:22px;background:rgba(255,255,255,.15);color:#fff;" +
            "font-size:22px;line-height:1;text-decoration:none;cursor:pointer}" +
            ".fn-imgov-bar a:hover,.fn-imgov-bar button:hover{background:rgba(255,255,255,.3)}" +
            ".fn-imgov-bar a:focus-visible,.fn-imgov-bar button:focus-visible{outline:2px solid #fff;outline-offset:2px}";
        document.head.appendChild(style);

        overlay = document.createElement("div");
        overlay.className = "fn-imgov";
        overlay.hidden = true;
        overlay.setAttribute("role", "dialog");
        overlay.setAttribute("aria-modal", "true");
        overlay.innerHTML =
            '<div class="fn-imgov-bar">' +
            '<a class="fn-imgov-open" target="_blank" rel="noopener">&#8599;</a>' +
            '<a class="fn-imgov-download" download>&#10515;</a>' +
            '<button type="button" class="fn-imgov-close">&#10005;</button>' +
            '</div><img alt=""><div class="fn-imgov-msg" hidden></div>';
        picture = overlay.querySelector("img");
        message = overlay.querySelector(".fn-imgov-msg");
        openLink = overlay.querySelector(".fn-imgov-open");
        downloadLink = overlay.querySelector(".fn-imgov-download");
        var closeButton = overlay.querySelector(".fn-imgov-close");
        [[openLink, label("open", "Open in a new window")],
            [downloadLink, label("download", "Download")],
            [closeButton, label("close", "Close")]].forEach(function (item) {
            item[0].title = item[1];
            item[0].setAttribute("aria-label", item[1]);
        });
        message.textContent = label("error", "Image not available");
        picture.addEventListener("error", function () {
            picture.hidden = true;
            message.hidden = false;
        });
        closeButton.addEventListener("click", close);
        // a click outside the picture and the icons closes it
        overlay.addEventListener("click", function (e) {
            if (e.target === overlay || e.target === message) {
                close();
            }
        });
        // the new window and the download leave the page as it is
        openLink.addEventListener("click", close);
        document.body.appendChild(overlay);
    }

    function show(url, alt) {
        if (!overlay) {
            build();
        }
        lastFocus = document.activeElement;
        picture.hidden = false;
        message.hidden = true;
        picture.alt = alt || "";
        picture.src = url;
        openLink.href = url;
        downloadLink.href = url;
        var name = decodeURIComponent(new URL(url, window.location.href).pathname.split("/").pop());
        downloadLink.setAttribute("download", /\.php$/i.test(name) ? "" : name);
        overlay.hidden = false;
        document.documentElement.style.overflow = "hidden";
        overlay.querySelector(".fn-imgov-close").focus();
    }

    function close() {
        if (!overlay || overlay.hidden) {
            return;
        }
        overlay.hidden = true;
        picture.removeAttribute("src");
        document.documentElement.style.overflow = "";
        if (lastFocus && lastFocus.focus) {
            lastFocus.focus();
        }
    }

    document.addEventListener("click", function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
            return;
        }
        var link = e.target.closest ? e.target.closest("a[href]") : null;
        if (!link || (overlay && overlay.contains(link)) || !link.querySelector("img")) {
            return;
        }
        var href = link.getAttribute("href");
        if (!link.classList.contains("fn-image-zoom") && !imageUrl.test(href)) {
            return;
        }
        e.preventDefault();
        var img = link.querySelector("img");
        show(link.href, img.getAttribute("alt") || img.getAttribute("title") || "");
    });
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            close();
        }
    });
})();
