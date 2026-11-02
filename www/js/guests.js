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

    // Lowercase, without accents or apostrophes, other signs as spaces: "Jean-Pierre O'Brién" is "jean pierre obrien"
    function normalize(text) {
        return text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f'\u2019]/g, '')
            .replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
    }

    // At most one letter added, removed, changed, or two letters swapped: "dupond" for "dupont", "jaen" for "jean"
    function oneTypoAway(a, b) {
        if (a === b) {
            return true;
        }
        if (Math.abs(a.length - b.length) > 1) {
            return false;
        }
        var i = 0;
        while (i < a.length && i < b.length && a[i] === b[i]) {
            i++;
        }
        if (a.length === b.length) {
            return a.slice(i + 1) === b.slice(i + 1)
                || (a[i] === b[i + 1] && a[i + 1] === b[i] && a.slice(i + 2) === b.slice(i + 2));
        }
        return a.length > b.length ? a.slice(i + 1) === b.slice(i) : a.slice(i) === b.slice(i + 1);
    }

    // How well the typed words match a dancer, 0 if one of them doesn't: each word must start a part of the name
    // (in any order, "dup j" finds Jean Dupont), or else be inside it ("pont"). With typos, a word of 3 letters or
    // more may also start a part of the name with one typo, for when nothing matches without.
    function score(item, tokens, typos) {
        var words = normalize(item.dataset.name).split(' ');
        var compact = words.join('');
        var total = 0;
        for (var t = 0; t < tokens.length; t++) {
            var token = tokens[t];
            var best = 0;
            words.forEach(function (word) {
                if (word.lastIndexOf(token, 0) === 0) {
                    best = Math.max(best, word === token ? 4 : 3);
                } else if (typos && token.length >= 3) {
                    for (var length = token.length - 1; length <= token.length + 1 && length <= word.length; length++) {
                        if (oneTypoAway(token, word.slice(0, length))) {
                            best = Math.max(best, 1);
                        }
                    }
                }
            });
            if (best < 2 && compact.indexOf(token) > -1) {
                best = 2;
            }
            if (!best) {
                return 0;
            }
            total += best;
        }
        return total;
    }

    // The name with the start of each part a typed word begins marked: "<mark>Jea</mark>n"
    function highlight(text, tokens) {
        return text.split(/([^\p{L}\p{N}'\u2019]+)/u).map(function (part) {
            var word = normalize(part);
            var length = 0;
            tokens.forEach(function (token) {
                if (token.length > length && word.lastIndexOf(token, 0) === 0) {
                    length = token.length;
                }
            });
            if (!length) {
                return escapeHtml(part);
            }
            // As many letters of the name as it took to match: "é" is one, "'" none
            var chars = Array.from(part);
            var cut = 0;
            for (var count = 0; cut < chars.length && count < length; cut++) {
                count += normalize(chars[cut]).length;
            }
            return '<mark>' + escapeHtml(chars.slice(0, cut).join('')) + '</mark>' + escapeHtml(chars.slice(cut).join(''));
        }).join('');
    }

    // The dancers shown, best match first, for Enter to know when there's only one
    var matches = [];

    function filterList() {
        var tokens = normalize(search.value).split(' ').filter(Boolean);
        var items = Array.prototype.slice.call(list.querySelectorAll('.collection-item'));
        var scores = items.map(function (item) {
            return tokens.length ? score(item, tokens, false) : 1;
        });
        if (tokens.length && scores.every(function (value) { return value === 0; })) {
            scores = items.map(function (item) {
                return score(item, tokens, true);
            });
        }
        matches = [];
        items.forEach(function (item, i) {
            item.style.display = scores[i] ? '' : 'none';
            if (scores[i]) {
                matches.push({item: item, score: scores[i]});
            }
            item.querySelectorAll('.guest-name').forEach(function (name) {
                if (name.dataset.text === undefined) {
                    name.dataset.text = name.textContent;
                }
                name.innerHTML = highlight(name.dataset.text, tokens);
            });
        });
        // Sorted by score, then in the list's own order
        matches.sort(function (a, b) {
            return b.score - a.score || a.item.dataset.order - b.item.dataset.order;
        });
        matches.forEach(function (match) {
            list.appendChild(match.item);
        });
        // block, not '': style.css hides it by default
        nothing.style.display = matches.length === 0 ? 'block' : 'none';
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
            list.querySelectorAll('.collection-item').forEach(function (item, i) {
                item.dataset.order = i;
            });
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
        var loader = button.parentElement.querySelector('.loader');
        loader.style.display = 'block';
        post(list.dataset.statusUrl, {id: button.dataset.dancer, status: button.dataset.status}).then(function (data) {
            if (data === 'OK') {
                loadList();
            } else {
                // The list isn't reloaded, so the spinner would keep turning
                loader.style.display = 'none';
                alert('Oops');
            }
            search.value = '';
            search.focus();
            filterList();
        });
    });

    // input also catches pasting, autocorrect, dictation and the field's clear button
    search.addEventListener('input', filterList);

    // Enter marks the only dancer found here, Shift+Enter as VIP; Escape empties the search
    search.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            search.value = '';
            filterList();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (matches.length !== 1) {
                if (matches.length > 1) {
                    toast(matches.length + ' danseurs correspondent, précisez le nom');
                }
                return;
            }
            var item = matches[0].item;
            var button = item.querySelector('[data-status="' + (event.shiftKey ? 2 : 1) + '"]');
            if (button) {
                button.click();
            } else {
                toast(escapeHtml(item.dataset.name) + ' est déjà marqué');
            }
        }
    });

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

    loadList();
})();
