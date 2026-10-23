// The site's widgets, on the public pages (views/layout.twig loads this after the first paint) and in the admin.
// They replace Materialize's scripts and jQuery with no library: the markup and classes stay the ones Materialize's
// CSS styles (www/css/base.css, whose last section holds the states set here).
(function () {
    function all(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    // Side menu on phones and tablets: the menu button opens it, a click beside it or Escape closes it
    all('.sidenav-trigger[data-target]').forEach(function (trigger) {
        var menu = document.getElementById(trigger.dataset.target);
        if (!menu) {
            return;
        }
        var overlay = document.createElement('div');
        overlay.className = 'sidenav-overlay';
        document.body.appendChild(overlay);
        trigger.setAttribute('aria-expanded', 'false');

        function setOpen(open) {
            menu.classList.toggle('open', open);
            overlay.classList.toggle('open', open);
            trigger.setAttribute('aria-expanded', String(open));
            document.body.style.overflow = open ? 'hidden' : '';
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            setOpen(true);
            var link = menu.querySelector('a');
            if (link) {
                link.focus({preventScroll: true});
            }
        });
        overlay.addEventListener('click', function () {
            setOpen(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.classList.contains('open')) {
                setOpen(false);
                trigger.focus();
            }
        });
        // The top menu takes over on wide screens
        window.addEventListener('resize', function () {
            if (window.innerWidth > 992 && menu.classList.contains('open')) {
                setOpen(false);
            }
        });
    });

    // Dropdown menus (the admin's "Paramètres"): open over their link on click, close on a click elsewhere
    all('.dropdown-trigger[data-target]').forEach(function (trigger) {
        var menu = document.getElementById(trigger.dataset.target);
        if (!menu) {
            return;
        }
        trigger.after(menu);
        trigger.setAttribute('aria-haspopup', 'true');
        trigger.setAttribute('aria-expanded', 'false');

        function setOpen(open) {
            menu.classList.toggle('open', open);
            trigger.setAttribute('aria-expanded', String(open));
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setOpen(!menu.classList.contains('open'));
            var link = menu.querySelector('a');
            if (link && menu.classList.contains('open')) {
                link.focus();
            }
        });
        document.addEventListener('click', function (event) {
            if (!menu.contains(event.target)) {
                setOpen(false);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.classList.contains('open')) {
                setOpen(false);
                trigger.focus();
            }
        });
    });

    // Parallax headers: the image moves slower than the page, as Materialize's parallax did
    var parallaxImages = all('.parallax-container .parallax img');
    if (parallaxImages.length) {
        var updateParallax = function () {
            var windowHeight = window.innerHeight;
            parallaxImages.forEach(function (image) {
                var container = image.closest('.parallax-container');
                var containerHeight = container.offsetHeight || 500;
                var top = container.getBoundingClientRect().top;
                if (top + containerHeight > 0 && top < windowHeight) {
                    var scrolled = (windowHeight - top) / (containerHeight + windowHeight);
                    image.style.transform = 'translate3d(-50%, ' + (image.offsetHeight - containerHeight) * scrolled + 'px, 0)';
                }
            });
        };
        var parallaxFrame = null;
        var requestParallax = function () {
            if (parallaxFrame === null) {
                parallaxFrame = requestAnimationFrame(function () {
                    parallaxFrame = null;
                    updateParallax();
                });
            }
        };
        window.addEventListener('scroll', requestParallax, {passive: true});
        window.addEventListener('resize', requestParallax);
        parallaxImages.forEach(function (image) {
            image.addEventListener('load', requestParallax);
        });
        updateParallax();
    }

    // Tooltips (data-tooltip, above their element, or below with data-position="bottom"). The texts are the
    // admin's own, with <br> line breaks
    var tooltip = null;

    function showTooltip(element) {
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.className = 'material-tooltip';
            tooltip.setAttribute('role', 'tooltip');
            tooltip.appendChild(document.createElement('div')).className = 'tooltip-content';
            document.body.appendChild(tooltip);
        }
        var content = tooltip.firstChild;
        content.textContent = '';
        element.dataset.tooltip.split(/<br\s*\/?>/i).forEach(function (line, index) {
            if (index > 0) {
                content.appendChild(document.createElement('br'));
            }
            content.appendChild(document.createTextNode(line));
        });
        var rect = element.getBoundingClientRect();
        var top = element.dataset.position === 'bottom' ? rect.bottom + 20 : rect.top - tooltip.offsetHeight;
        var left = rect.left + (rect.width - tooltip.offsetWidth) / 2;
        left = Math.max(2, Math.min(left, document.documentElement.clientWidth - tooltip.offsetWidth - 2));
        tooltip.style.top = (top + window.scrollY) + 'px';
        tooltip.style.left = (left + window.scrollX) + 'px';
        tooltip.classList.add('open');
    }

    function hideTooltip() {
        if (tooltip) {
            tooltip.classList.remove('open');
        }
    }

    all('.tooltipped[data-tooltip]').forEach(function (element) {
        element.addEventListener('mouseenter', function () {
            showTooltip(element);
        });
        element.addEventListener('focus', function () {
            showTooltip(element);
        });
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('blur', hideTooltip);
    });

    // Modals: <dialog class="modal">, opened by the links to them (.modal-trigger), closed by .modal-close, Escape
    // or a click beside them. Opened as plain dialogs, not modal ones: TinyMCE's menus and windows, in some of
    // them, must be able to show above
    var modalOverlay = null;
    var openModal = null;

    function closeModal() {
        if (openModal) {
            openModal.close();
            openModal = null;
            modalOverlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.modal-trigger');
        var modal = trigger && document.querySelector(trigger.getAttribute('href') || '#' + trigger.dataset.target);
        if (modal && modal.tagName === 'DIALOG') {
            event.preventDefault();
            if (!modalOverlay) {
                modalOverlay = document.createElement('div');
                modalOverlay.className = 'modal-overlay';
                modalOverlay.addEventListener('click', closeModal);
                document.body.appendChild(modalOverlay);
            }
            closeModal();
            modalOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';
            modal.show();
            openModal = modal;
            return;
        }
        var close = event.target.closest('dialog.modal .modal-close');
        if (close && close.type !== 'submit') {
            event.preventDefault();
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        // Escape in one of TinyMCE's windows only closes that window
        if (event.key === 'Escape' && !document.querySelector('.tox-dialog')) {
            closeModal();
        }
    });

    // Collapsibles: a click on an item's header opens it and closes the others
    all('.collapsible').forEach(function (collapsible) {
        collapsible.addEventListener('click', function (event) {
            var header = event.target.closest('.collapsible-header');
            if (!header || header.parentElement.parentElement !== collapsible) {
                return;
            }
            var item = header.parentElement;
            var open = !item.classList.contains('active');
            all(':scope > li', collapsible).forEach(function (other) {
                other.classList.remove('active');
            });
            item.classList.toggle('active', open);
        });
    });

    // Cards whose .activator shows their .card-reveal, closed by its title
    document.addEventListener('click', function (event) {
        var card = event.target.closest('.card');
        var reveal = card && card.querySelector('.card-reveal');
        if (!reveal) {
            return;
        }
        if (event.target.closest('.activator')) {
            card.style.overflow = 'hidden';
            reveal.classList.add('open');
        } else if (event.target.closest('.card-reveal .card-title')) {
            reveal.classList.remove('open');
            card.style.overflow = '';
        }
    });

    // Text fields: their label moves up while they're focused or filled
    var textFields = 'input[type=text], input[type=password], input[type=email], input[type=url], input[type=tel], '
        + 'input[type=number], input[type=search], textarea';

    function updateLabel(field, focused) {
        var label = field.parentElement.querySelector(':scope > label');
        if (label) {
            label.classList.toggle('active', focused || field.value.length > 0 || field.hasAttribute('placeholder')
                || field.autofocus || (field.validity && field.validity.badInput));
        }
    }

    all(textFields).forEach(function (field) {
        updateLabel(field, field === document.activeElement);
    });
    document.addEventListener('focusin', function (event) {
        if (event.target.matches(textFields)) {
            updateLabel(event.target, true);
        }
    });
    document.addEventListener('focusout', function (event) {
        if (event.target.matches(textFields)) {
            updateLabel(event.target, false);
        }
    });
    // Filled in by the browser
    document.addEventListener('change', function (event) {
        if (event.target.matches(textFields)) {
            updateLabel(event.target, event.target === document.activeElement);
        }
    });

    // Text areas grow with their text, where the browser can't do it alone (field-sizing in base.css)
    if (!(window.CSS && CSS.supports('field-sizing', 'content'))) {
        var resize = function (textarea) {
            textarea.style.height = '';
            textarea.style.height = textarea.scrollHeight + 'px';
        };
        all('textarea.materialize-textarea').forEach(resize);
        document.addEventListener('input', function (event) {
            if (event.target.matches('textarea.materialize-textarea')) {
                resize(event.target);
            }
        });
    }

    // File fields: the chosen file's name shows in the text field next to the button
    document.addEventListener('change', function (event) {
        var field = event.target.closest('.file-field');
        var path = field && field.querySelector('input.file-path');
        if (path && event.target.type === 'file') {
            path.value = Array.prototype.map.call(event.target.files, function (file) {
                return file.name;
            }).join(', ');
            updateLabel(path, false);
        }
    });

    // The lock of a soirée's sign-ups (admin > Événements) shows what a click on it does
    all('.mdi.mdi-lock-open, .mdi.mdi-lock').forEach(function (icon) {
        function swap() {
            icon.classList.toggle('mdi-lock');
            icon.classList.toggle('mdi-lock-open');
        }

        icon.addEventListener('mouseenter', swap);
        icon.addEventListener('mouseleave', swap);
    });

    // The images of the "À propos" sections (views/galleryLayout.twig): copy their URL, laid out in columns
    if (typeof ClipboardJS === 'function') {
        new ClipboardJS('.to-clipboard');
    }
    var grid = document.querySelector('.grid');
    if (grid && typeof Masonry === 'function') {
        new Masonry(grid, {itemSelector: '.col'});
    }

    if (typeof tinymce !== 'undefined') tinymce.init({
        selector: '.tinymce',
        // The open source edition of TinyMCE, loaded from jsDelivr: no Tiny Cloud account or API key
        license_key: 'gpl',
        content_css: '/css/base.css',
        language: 'fr_FR',
        language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@26.10.19/langs7/fr_FR.js',
        branding: false,
        promotion: false,
        plugins: 'image link table lists code',
        menu: {
            edit: {title: 'Modifier', items: 'undo redo | cut copy paste pastetext | selectall'},
            format: {title: 'Format', items: 'bold italic underline strikethrough superscript subscript | styles | removeformat'},
            table: {title: 'Table', items: 'inserttable tableprops deletetable | cell row column'},
            tools: {title: 'Tools', items: 'code'}
        },
        toolbar: 'undo redo | styles bold italic forecolor numlist | alignleft aligncenter alignright | link imageUploader image code',
        height: 300,
        setup: function (editor) {
            editor.ui.registry.addButton('imageUploader', {
                icon: 'upload',
                tooltip: "Télécharger une image sur le serveur",
                onAction: function () {
                    window.open("/admin/about/gallery", "", "width=500,height=400");
                }
            });
        },
        image_dimensions: true,
        image_class_list: [
            {title: 'Aucune', value: ''},
            {title: 'Responsive', value: 'responsive-img'}
        ]
    });
})();
