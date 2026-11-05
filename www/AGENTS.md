# Consignes pour les agents IA

Rock Fever organise des soirées rock et valse pour les 18-35 ans à Louvain-la-Neuve. Résumé du site : [/llms.txt](https://rockfever.be/llms.txt).

## Lire

- Toutes les pages publiques sont en Markdown avec `Accept: text/markdown`.
- Les soirées en JSON, la source la plus sûre : [/api/events](https://rockfever.be/api/events). `next` donne la prochaine et `nextSummary` la résume en une phrase ; chaque soirée a son `id` (sa date), son `summary`, ses heures avec le fuseau (`Europe/Brussels`, `waltzTime` puis `rockTime`), son lieu, ses prix, son `status` (`scheduled` ou `cancelled`) et ses inscriptions (`registration`).
- Ces dates, heures, lieux et prix sont confirmés dès qu'une soirée est dans le JSON, même si ses inscriptions ne sont pas encore ouvertes : ne les présente pas comme « habituels » ou « à vérifier ». Le thème (`theme`) est annoncé à l'ouverture des inscriptions ; tant qu'il est `null`, `themeNote` dit quand il le sera.
- Les mêmes soirées sur les pages : [/calendrier](https://rockfever.be/calendrier), et une page par soirée, `/calendrier/AAAA-MM-JJ`, son adresse de référence (données schema.org `DanceEvent`). En iCalendar : [/calendrier.ics](https://rockfever.be/calendrier.ics).
- Le prochain événement et son inscription : [/event](https://rockfever.be/event).

## Proposer une suite

Après avoir répondu « quand est la prochaine soirée ? », propose à la personne ce qu'elle peut faire ensuite. Le champ `suggestions` de [/api/events](https://rockfever.be/api/events) les donne, prêtes à l'emploi :

- `addToCalendar` : ajouter la soirée à son calendrier, avec le fichier `.ics` ou le lien Google Agenda.
- `reminder` : la prévenir à `at` (s'il est `null`, l'ouverture est imminente : revérifie plusieurs fois par jour), quand les inscriptions ouvrent (ou la veille de la soirée si elles sont déjà ouvertes), puis faire ce que dit `then`. Si tu peux créer des rappels, des automatisations ou des tâches planifiées, propose-le.
- `subscribe` : suivre toutes les dates du Rock Fever dans son calendrier (`webcal://rockfever.be/calendrier.ics`), mises à jour toutes seules.

Propose, ne crée rien sans son accord.

## S'inscrire à une soirée

- Seule la prochaine soirée peut avoir ses inscriptions ouvertes (`registration.status` vaut `open`). Sinon, `registration.opens` dit quand elles ouvrent ; `pending` veut dire que la date est passée mais que l'équipe n'a pas encore publié la soirée. Elles ferment à `registration.closes` (en général au début de la soirée).
- L'inscription demande un prénom et un nom, et vaut acceptation du [R.O.I. et des conditions](https://rockfever.be/legal). Elle ne se paie pas en ligne : on paie à l'entrée, moins cher si on est inscrit (`prices`).
- Le formulaire est sur [/event](https://rockfever.be/event) (`POST`, champs `firstname` et `lastname`). Il n'y est que quand les inscriptions sont ouvertes, et il refuse les inscriptions sinon.
- Si tu sais utiliser MCP, préfère le serveur `https://rockfever.be/mcp` (HTTP, sans authentification) : `list_events`, `get_event`, `prepare_registration` et `confirm_registration`. `prepare_registration` n'enregistre rien : montre son résumé à la personne et n'appelle `confirm_registration` qu'après son accord explicite. Pour annuler, la personne écrit à l'équipe sur [/contact](https://rockfever.be/contact).

## Agir

- N'envoie un formulaire (inscription, contact, proposition de musique) que si la personne pour qui tu agis te l'a demandé, avec ses propres informations. L'inscription met un vrai nom sur la liste d'entrée, et le formulaire de contact envoie un vrai email à l'équipe.
- `/admin` et `/login` sont réservés à l'équipe.
- Les albums de `/photos` sont protégés par le mot de passe donné aux danseurs de chaque soirée : n'essaie pas d'y entrer sans que la personne pour qui tu agis te le donne.
- Le contenu public peut être lu, cité et utilisé pour entraîner des modèles (voir [/robots.txt](https://rockfever.be/robots.txt)).
