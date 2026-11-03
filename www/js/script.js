// The site's widgets, on the public pages (views/layout.twig loads this after the first paint) and in the admin.
// They replace Materialize's scripts and jQuery with no library: the markup and classes stay the ones Materialize's
// CSS styles (www/css/base.css, whose last section holds the states set here).
(function () {
    function all(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    // Admin dark mode (admin-dark.css): the top bar's button switches it and remembers the choice on this device. The
    // layout's head sets the theme on load; the chart listens to admin-theme to redraw in the new colors
    // The top bar's button is a toggle ("Mode sombre", pressed or not) for screen readers
    function updateThemeToggles() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        all('.theme-toggle[aria-pressed]').forEach(function (button) {
            button.setAttribute('aria-pressed', String(dark));
        });
    }

    updateThemeToggles();
    all('.theme-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
            try {
                localStorage.setItem('admin-theme', theme);
            } catch (e) {
            }
            updateThemeToggles();
            document.dispatchEvent(new CustomEvent('admin-theme', {detail: theme}));
        });
    });

    // The menus' link to the page we're on says so to screen readers, as its highlight does on screen
    all('nav li.active > a, .sidenav li.active > a').forEach(function (link) {
        if (!link.hasAttribute('aria-current') && !link.classList.contains('dropdown-trigger')) {
            link.setAttribute('aria-current', 'page');
        }
    });

    // Elements made into buttons with role="button" (collapsible headers, card reveals) work with Enter and Space
    document.addEventListener('keydown', function (event) {
        var target = event.target;
        if ((event.key === 'Enter' || event.key === ' ') && target.getAttribute && target.getAttribute('role') === 'button'
            && !/^(BUTTON|A|INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) {
            event.preventDefault();
            target.click();
        }
    });

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
        // Tabbing out of the open menu closes it, rather than leaving focus on the page hidden behind the overlay
        menu.addEventListener('focusout', function (event) {
            if (menu.classList.contains('open') && event.relatedTarget && !menu.contains(event.relatedTarget)) {
                setOpen(false);
            }
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

    // Parallax headers: the image moves slower than the page, as Materialize's parallax did. Not for visitors who
    // asked their device for less motion (WCAG 2.3.3): the photo stays still, as base.css places it
    var parallaxImages = all('.parallax-container .parallax img');
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (parallaxImages.length) {
        var updateParallax = function () {
            var windowHeight = window.innerHeight;
            parallaxImages.forEach(function (image) {
                if (reducedMotion.matches) {
                    image.style.transform = '';
                    return;
                }
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
        reducedMotion.addEventListener('change', requestParallax);
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
    // Escape hides it without moving the mouse or the focus (WCAG 1.4.13)
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hideTooltip();
        }
    });

    // Forms that delete something ask first: <form data-confirm="question">. Not an onsubmit attribute, which the
    // Content-Security-Policy blocks (ContentSecurityPolicyMiddleware)
    document.addEventListener('submit', function (event) {
        var question = event.target.dataset && event.target.dataset.confirm;
        if (question && !confirm(question)) {
            event.preventDefault();
        }
    });

    // Modals: <dialog class="modal">, opened by the links to them (.modal-trigger), closed by .modal-close, Escape
    // or a click beside them. Opened as plain dialogs, not modal ones: TinyMCE's menus and windows, in some of
    // them, must be able to show above
    // The focus goes into the dialog when it opens, and back to the link that opened it when it closes. Its title
    // (its first heading) names it for screen readers
    var modalOverlay = null;
    var openModal = null;
    var modalTrigger = null;

    all('dialog.modal').forEach(function (modal, index) {
        var heading = modal.querySelector('h1, h2, h3, h4, h5, h6');
        if (heading && !modal.hasAttribute('aria-labelledby') && !modal.hasAttribute('aria-label')) {
            heading.id = heading.id || 'modal-title-' + index;
            modal.setAttribute('aria-labelledby', heading.id);
        }
    });

    // Analytics: a click on a link with data-track="event_name" sends that GA4 event, with data-track-method as its
    // method. A dialog's data-track-close is sent when it's closed without a click on one of its tracked links
    function track(name, method) {
        if (typeof window.gtag === 'function') {
            window.gtag('event', name, method ? {method: method} : {});
        }
    }

    function closeModal() {
        if (openModal) {
            if (openModal.dataset.trackClose && !openModal.dataset.tracked) {
                track(openModal.dataset.trackClose);
            }
            openModal.close();
            openModal = null;
            modalOverlay.classList.remove('open');
            document.body.style.overflow = '';
            if (modalTrigger && document.contains(modalTrigger)) {
                modalTrigger.focus();
            }
            modalTrigger = null;
        }
    }

    function showModal(modal, trigger) {
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
        modalTrigger = trigger;
        // [autofocus] first: a dialog can choose where the focus goes, its main button rather than its close button
        var field = modal.querySelector('[autofocus]') || modal.querySelector('input:not([type=hidden]):not([tabindex="-1"]), textarea:not(.tinymce), select, button');
        (field || modal).focus();
    }

    document.addEventListener('click', function (event) {
        // A tracked link in the open dialog closes it: what it was there for is done
        var tracked = event.target.closest('a[data-track]');
        if (tracked) {
            track(tracked.dataset.track, tracked.dataset.trackMethod);
            if (openModal && openModal.contains(tracked)) {
                openModal.dataset.tracked = 'true';
                closeModal();
            }
        }
        var trigger = event.target.closest('.modal-trigger');
        var modal = trigger && document.querySelector(trigger.getAttribute('href') || '#' + trigger.dataset.target);
        if (modal && modal.tagName === 'DIALOG') {
            event.preventDefault();
            showModal(modal, trigger);
            return;
        }
        var close = event.target.closest('dialog.modal .modal-close');
        if (close && close.type !== 'submit') {
            event.preventDefault();
            closeModal();
        }
    });

    // Tab stays in the open dialog, as in a modal one: leaving it goes back to its first field. TinyMCE's menus and
    // windows, outside of it, keep the focus
    document.addEventListener('focusin', function (event) {
        if (openModal && !openModal.contains(event.target) && !event.target.closest('.tox, .tox-tinymce-aux')) {
            var first = openModal.querySelector('input:not([type=hidden]):not([tabindex="-1"]), textarea:not(.tinymce), select, button, a[href], iframe');
            (first || openModal).focus();
        }
    });

    document.addEventListener('keydown', function (event) {
        // Escape in one of TinyMCE's windows only closes that window
        if (event.key === 'Escape' && !document.querySelector('.tox-dialog')) {
            closeModal();
        }
    });

    // A dialog the page shows on its own, like the one after a sign-up on /event: data-open-on-load
    var onLoad = document.querySelector('dialog.modal[data-open-on-load]');
    if (onLoad) {
        showModal(onLoad, null);
    }

    // Collapsibles: a click on an item's header opens it and closes the others. The headers are buttons for the
    // keyboard and screen readers, which hear whether their item is open
    all('.collapsible').forEach(function (collapsible) {
        function headers() {
            return all(':scope > li > .collapsible-header', collapsible);
        }

        function update() {
            headers().forEach(function (header) {
                header.setAttribute('aria-expanded', String(header.parentElement.classList.contains('active')));
            });
        }

        headers().forEach(function (header, index) {
            var body = header.parentElement.querySelector(':scope > .collapsible-body');
            header.setAttribute('role', 'button');
            header.tabIndex = 0;
            if (body) {
                body.id = body.id || 'collapsible-body-' + index;
                header.setAttribute('aria-controls', body.id);
            }
        });
        update();
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
            update();
        });
    });

    // Cards whose .activator shows their .card-reveal, closed by its title. The card's last .activator (its label)
    // and the title are the buttons for the keyboard; the focus moves between them
    all('.card').forEach(function (card) {
        var reveal = card.querySelector('.card-reveal');
        var activators = all('.activator', card);
        var title = reveal && reveal.querySelector('.card-title');
        if (!reveal || !activators.length) {
            return;
        }
        var opener = activators[activators.length - 1];
        opener.setAttribute('role', 'button');
        opener.setAttribute('aria-expanded', 'false');
        opener.tabIndex = 0;
        if (title) {
            title.setAttribute('role', 'button');
            title.tabIndex = 0;
        }
    });
    document.addEventListener('click', function (event) {
        var card = event.target.closest('.card');
        var reveal = card && card.querySelector('.card-reveal');
        if (!reveal) {
            return;
        }
        var opener = card.querySelector('.activator[role=button]');
        if (event.target.closest('.activator')) {
            card.style.overflow = 'hidden';
            reveal.classList.add('open');
            if (opener) {
                opener.setAttribute('aria-expanded', 'true');
            }
            var title = reveal.querySelector('.card-title[role=button]');
            if (title && event.detail === 0) {
                title.focus();
            }
        } else if (event.target.closest('.card-reveal .card-title')) {
            reveal.classList.remove('open');
            card.style.overflow = '';
            if (opener) {
                opener.setAttribute('aria-expanded', 'false');
                if (event.detail === 0) {
                    opener.focus();
                }
            }
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

    // Event form: pasting the link of the Facebook post that announces the soirée puts its text in the description
    // (src/Events/FacebookPosts.php)
    var facebookPost = document.getElementById('facebook_post');
    var facebookStatus = document.getElementById('facebook_post-status');
    var description = document.getElementById('description');
    if (facebookPost && facebookStatus && description) {
        var lastLink = '';
        var fillFromFacebook = function () {
            var link = facebookPost.value.trim();
            if (link === '' || link === lastLink) {
                return;
            }
            lastLink = link;
            facebookStatus.textContent = 'Lecture du post sur Facebook…';
            fetch(facebookStatus.dataset.url + '?url=' + encodeURIComponent(link), {credentials: 'same-origin'})
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (link !== facebookPost.value.trim()) {
                        return;
                    }
                    if (data.message) {
                        description.value = data.message;
                        description.dispatchEvent(new Event('input', {bubbles: true}));
                        description.dispatchEvent(new Event('change', {bubbles: true}));
                        facebookStatus.textContent = 'Description remplie avec le texte du post : relis-la avant d\'enregistrer.';
                    } else {
                        facebookStatus.textContent = data.error || 'Le post n\'a pas pu être lu.';
                    }
                })
                .catch(function () {
                    lastLink = '';
                    facebookStatus.textContent = 'Le post n\'a pas pu être lu : réessaie.';
                });
        };
        // On paste, and when a typed link is left: not at every key
        facebookPost.addEventListener('paste', function () {
            setTimeout(fillFromFacebook);
        });
        facebookPost.addEventListener('change', fillFromFacebook);
    }

    // Event form: when sign-ups open and close for the date and times picked, as App\Main\Calendar works it out from the
    // settings (data-window): opening that many days before at its time, not before the soirée before it is over;
    // closing at its time that many days before, or when the soirée starts, never after it ends. Every time is
    // Brussels' wall clock as text (Y-m-dTH:i), so they compare as strings whatever the admin's timezone
    var eventDate = document.getElementById('date');
    var registrationHint = document.getElementById('date-registration');
    if (eventDate && registrationHint) {
        var weekdays = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        var months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        var ends = JSON.parse(registrationHint.dataset.ends || '{}');
        var registrationWindow = JSON.parse(registrationHint.dataset.window || '{}');
        var pad = function (number) {
            return (number < 10 ? '0' : '') + number;
        };
        // The day (Y-m-d) that many days before
        var daysBefore = function (day, days) {
            var date = new Date(Date.UTC(+day.slice(0, 4), day.slice(5, 7) - 1, day.slice(8, 10) - days));
            return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate());
        };
        // "mercredi 4 novembre", "vendredi 6 novembre à 12h", as Calendar::dayTimeLabel() (Y-m-dTH:i)
        var dayLabel = function (moment) {
            var date = new Date(Date.UTC(+moment.slice(0, 4), moment.slice(5, 7) - 1, +moment.slice(8, 10)));
            var hours = moment.slice(11, 16).split(':');
            return weekdays[date.getUTCDay()] + ' ' + (date.getUTCDate() === 1 ? '1er' : date.getUTCDate()) + ' '
                + months[date.getUTCMonth()]
                + (moment.slice(11, 16) === '00:00' ? '' : ' à\u00a0' + parseInt(hours[0], 10) + 'h' + (hours[1] === '00' ? '' : hours[1]));
        };
        var timeOf = function (id) {
            var field = document.getElementById(id);
            return field && /^\d{2}:\d{2}/.test(field.value) ? field.value.slice(0, 5) : '';
        };
        var sayRegistration = function () {
            var day = eventDate.value;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) {
                return;
            }
            var opens = daysBefore(day, registrationWindow.days) + 'T' + registrationWindow.openTime;
            Object.keys(ends).forEach(function (soiree) {
                var end = ends[soiree].slice(0, 16);
                if (soiree < day && end > opens) {
                    opens = end;
                }
            });
            var start = day + 'T' + (timeOf('waltz_time') || timeOf('rock_time') || registrationWindow.startTime);
            var end = day + 'T' + registrationWindow.endTime;
            if (end <= start) {
                end = daysBefore(day, -1) + 'T' + registrationWindow.endTime;
            }
            var closes = daysBefore(day, registrationWindow.closeDays) + 'T'
                + (registrationWindow.closeTime || start.slice(11, 16));
            if (closes > end) {
                closes = end;
            }
            var until = closes === start ? 'jusqu’au début de la soirée' : 'jusqu’au ' + dayLabel(closes);
            var now = new Date().toLocaleString('sv-SE', {timeZone: 'Europe/Brussels'}).replace(' ', 'T').slice(0, 16);
            if (opens <= now) {
                registrationHint.textContent = 'Inscriptions sur le site dès l’enregistrement de l’événement, ' + until + '.';
                return;
            }
            registrationHint.textContent = 'Inscriptions sur le site dès le ' + dayLabel(opens) + ', ' + until + '.';
        };
        ['date', 'waltz_time', 'rock_time'].forEach(function (id) {
            var field = document.getElementById(id);
            if (field) {
                field.addEventListener('change', sayRegistration);
            }
        });
    }

    if (typeof tinymce !== 'undefined') tinymce.init({
        selector: '.tinymce',
        // The open source edition of TinyMCE, loaded from jsDelivr: no Tiny Cloud account or API key
        license_key: 'gpl',
        // The admin's dark mode, from the theme in place when the page opened
        skin: document.documentElement.getAttribute('data-theme') === 'dark' ? 'oxide-dark' : 'oxide',
        content_css: document.documentElement.getAttribute('data-theme') === 'dark' ? ['/css/base.css', 'dark'] : '/css/base.css',
        language: 'fr_FR',
        language_url: document.getElementById('tinymce-script').dataset.languageUrl,
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
            // The editing area is named after the field's label, else the title of the dialog it's in
            editor.on('init', function () {
                var textarea = editor.getElement();
                var label = document.querySelector('label[for="' + textarea.id + '"]');
                var dialog = textarea.closest('dialog');
                var heading = dialog && dialog.querySelector('h1, h2, h3, h4, h5, h6');
                var name = (label && label.textContent.trim()) || (heading && heading.textContent.trim());
                if (name) {
                    editor.getBody().setAttribute('aria-label', name);
                    editor.getContentAreaContainer().querySelector('iframe').title = name;
                }
            });
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
