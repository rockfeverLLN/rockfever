// Photo viewer of the gallery (src/Photos/views/index.twig): opens a photo full screen, goes to the previous or
// next one (arrows, keyboard, swipe), downloads it, and sends a deletion request without leaving the page.
// Without this script, each photo is a plain link to the file.
(function () {
    var viewer = document.getElementById('photos-viewer');
    var links = Array.prototype.slice.call(document.querySelectorAll('.photos-tile a'));
    if (!viewer || !links.length || typeof viewer.showModal !== 'function') {
        return;
    }
    var image = viewer.querySelector('.photos-viewer-image');
    var count = viewer.querySelector('.photos-viewer-count');
    var download = viewer.querySelector('[data-viewer="download"]');
    var prev = viewer.querySelector('[data-viewer="prev"]');
    var next = viewer.querySelector('[data-viewer="next"]');
    var form = viewer.querySelector('.photos-request');
    var message = form.querySelector('.photos-request-message');
    var submit = form.querySelector('[type="submit"]');
    var cancel = form.querySelector('[data-viewer="cancel"]');
    var current = 0;
    var opener = null;

    function show(index) {
        current = Math.max(0, Math.min(links.length - 1, index));
        var link = links[current];
        image.classList.add('is-loading');
        image.src = link.dataset.large;
        image.alt = 'Photo ' + (current + 1) + ' sur ' + links.length;
        count.textContent = (current + 1) + ' / ' + links.length;
        download.href = link.dataset.download;
        prev.disabled = current === 0;
        next.disabled = current === links.length - 1;
        closeRequest();
        // The next photos are ready when asked for
        [current - 1, current + 1].forEach(function (i) {
            if (links[i]) {
                new Image().src = links[i].dataset.large;
            }
        });
    }

    function open(index) {
        opener = links[index];
        viewer.showModal();
        document.documentElement.style.overflow = 'hidden';
        show(index);
    }

    function openRequest() {
        form.action = links[current].dataset.request;
        form.hidden = false;
        message.textContent = '';
        message.className = 'photos-request-message';
        submit.hidden = false;
        submit.disabled = false;
        cancel.textContent = 'Annuler';
        form.querySelector('input[name="name"]').focus();
    }

    function closeRequest() {
        form.hidden = true;
    }

    image.addEventListener('load', function () {
        image.classList.remove('is-loading');
    });

    links.forEach(function (link, index) {
        link.addEventListener('click', function (event) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
                return;
            }
            event.preventDefault();
            open(index);
        });
    });

    viewer.addEventListener('close', function () {
        document.documentElement.style.overflow = '';
        image.removeAttribute('src');
        if (opener) {
            opener.focus();
        }
    });

    viewer.addEventListener('click', function (event) {
        var button = event.target.closest('[data-viewer]');
        var action = button && button.dataset.viewer;
        if (action === 'close') {
            viewer.close();
        } else if (action === 'prev') {
            show(current - 1);
        } else if (action === 'next') {
            show(current + 1);
        } else if (action === 'request') {
            form.hidden ? openRequest() : closeRequest();
        } else if (action === 'cancel') {
            closeRequest();
        } else if (event.target.classList.contains('photos-viewer-stage') && form.hidden) {
            // A click beside the photo closes it
            viewer.close();
        }
    });

    viewer.addEventListener('keydown', function (event) {
        if (!form.hidden) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeRequest();
            }
            return;
        }
        if (event.key === 'ArrowLeft') {
            show(current - 1);
        } else if (event.key === 'ArrowRight') {
            show(current + 1);
        }
    });

    // Swipe left or right on phones
    var touchX = null;
    var touchY = null;
    var stage = viewer.querySelector('.photos-viewer-stage');
    stage.addEventListener('touchstart', function (event) {
        if (event.touches.length === 1) {
            touchX = event.touches[0].clientX;
            touchY = event.touches[0].clientY;
        } else {
            touchX = null;
        }
    }, {passive: true});
    stage.addEventListener('touchend', function (event) {
        if (touchX === null || !form.hidden) {
            return;
        }
        var dx = event.changedTouches[0].clientX - touchX;
        var dy = event.changedTouches[0].clientY - touchY;
        touchX = null;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) {
            show(current + (dx < 0 ? 1 : -1));
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        submit.disabled = true;
        message.className = 'photos-request-message';
        message.textContent = 'Envoi…';
        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {'Accept': 'application/json'},
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            message.textContent = data.message;
            message.classList.add(data.ok ? 'is-success' : 'is-error');
            if (data.ok) {
                form.reset();
                submit.hidden = true;
                cancel.textContent = 'Fermer';
            } else {
                submit.disabled = false;
            }
        }).catch(function () {
            message.textContent = 'La demande n\'a pas pu être envoyée. Réessaie, ou écris-nous via la page Contact.';
            message.classList.add('is-error');
            submit.disabled = false;
        });
    });
})();
