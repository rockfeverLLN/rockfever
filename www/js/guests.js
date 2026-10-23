// Sign-up list at the door (src/Events/views/admin/list.twig): search a dancer, mark them here, VIP or not here,
// add someone who didn't sign up, and the evening's numbers. The page gives the URLs in data- attributes.
(function () {
    var list = document.getElementById('guests-list');
    if (!list) {
        return;
    }
    var search = document.getElementById('guest-search');
    var nothing = document.querySelector('.guests-list-nothing');
    var firstnameInput = document.getElementById('improvised-firstname');
    var lastnameInput = document.getElementById('improvised-lastname');

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            body: new URLSearchParams(data),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.text();
        });
    }

    function get(url) {
        return fetch(url, {credentials: 'same-origin'}).then(function (response) {
            return response.text();
        });
    }

    // Shown for 4 seconds at the top right, as Materialize's toasts were
    function toast(html) {
        var container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        var element = document.createElement('div');
        element.className = 'toast';
        element.innerHTML = html;
        container.appendChild(element);
        setTimeout(function () {
            element.classList.add('closing');
            setTimeout(function () {
                element.remove();
            }, 300);
        }, 4000);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function normalize(text) {
        return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
    }

    function filterList() {
        var value = normalize(search.value);
        var visible = 0;
        list.querySelectorAll('.collection-item').forEach(function (item) {
            var names = Array.prototype.map.call(item.querySelectorAll('span'), function (span) {
                return span.textContent;
            }).join(' ');
            var shown = value.length === 0 || normalize(names).indexOf(value) > -1;
            item.style.display = shown ? '' : 'none';
            visible += shown ? 1 : 0;
        });
        nothing.style.display = visible === 0 ? '' : 'none';
    }

    function loadStats() {
        get(list.dataset.statsUrl).then(function (data) {
            var stats = JSON.parse(data);
            var registered = parseInt(stats[0]);
            var here = parseInt(stats[1]);
            var vip = parseInt(stats[2]);
            var improvised = parseInt(stats[3]);

            var totalAttendees = here + vip + improvised;
            document.getElementById('attendees').textContent = totalAttendees;
            document.getElementById('revenue').textContent = (here * 2 + improvised * 3) + '€';
            document.getElementById('improvised').textContent = improvised;
            var venueStat = totalAttendees / (here + vip + registered);
            document.getElementById('venue-stats').textContent = Math.round(venueStat * 10000) / 100 + '% des pré-inscrits';
        });
    }

    function loadList() {
        get(list.dataset.listUrl).then(function (html) {
            list.innerHTML = html;
            filterList();
            loadStats();
        });
    }

    // The buttons of each dancer (src/Events/views/admin/ajax/dancers-list.twig)
    list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-dancer]');
        if (!button) {
            return;
        }
        button.parentElement.querySelector('.loader').style.display = 'block';
        post(list.dataset.statusUrl, {id: button.dataset.dancer, status: button.dataset.status}).then(function (data) {
            if (data === 'OK') {
                loadList();
            } else {
                alert('Oops');
            }
            search.value = '';
            search.focus();
        });
    });

    search.addEventListener('keyup', filterList);

    document.getElementById('improvised-button').addEventListener('click', function () {
        var firstname = firstnameInput.value;
        var lastname = lastnameInput.value;
        firstnameInput.value = '';
        lastnameInput.value = '';
        post(list.dataset.addUrl, {
            firstname: firstname,
            lastname: lastname,
            status: 3,
            event_id: list.dataset.event
        }).then(function (data) {
            if (data === 'OK') {
                toast('<i class="material-icons left">check</i> ' + escapeHtml(firstname + ' ' + lastname) + ' ajouté !');
                loadList();
            } else if (data === 'ALREADY') {
                alert(firstname + ' ' + lastname + ' figurait déjà dans la liste');
            } else {
                alert('Oops');
            }
        });
    });

    document.getElementById('attendees').addEventListener('click', function () {
        loadList();
        loadStats();
    });

    loadList();
})();
