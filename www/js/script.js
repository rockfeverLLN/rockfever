$(document).ready(function () {
    $('.sidenav').sidenav();
    $(".dropdown-trigger").dropdown();

    $('.parallax').parallax();
    $('.slider').slider();
    $('.tooltipped').tooltip();
    $('.modal').modal();
    $('.materialboxed').materialbox();
    $('.collapsible').collapsible();

    $('.datepicker').datepicker({
        format: 'dd/mm/yyyy',
        defaultDate: new Date(),
        firstDay: 1,
        minDate: new Date(),
        i18n: {
            cancel: 'Annuler',
            clear: 'Effacer',
            done: 'OK',
            today: 'Aujourd\'hui',
            months: [
                'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
            ],
            monthsShort: ['Jan', 'Fév', 'Mars', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sept', 'Oct', 'Nov', 'Déc'
            ],
            weekdays: ['Dimanche','Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
            weekdaysShort: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
            weekdaysAbbrev: ['D','L','M','M','J','V','S']
        }
    });
    $('.timepicker').timepicker({
        twelveHour: false,
        i18n: {
            cancel: 'Annuler',
            clear: 'Effacer',
            done: 'OK'
        }
    });
    $('select').formSelect();
    // Materialize shows each select as a text input: give it the select's label for screen readers
    $('select').each(function () {
        var label = $('label[for="' + this.id + '"]').text().trim();
        if (this.id && label) {
            $(this).siblings('input.select-dropdown').attr('aria-label', label);
        }
    });

    if (typeof tinymce !== 'undefined') tinymce.init({
        selector: '.tinymce',
        // The open source edition of TinyMCE, loaded from jsDelivr: no Tiny Cloud account or API key
        license_key: 'gpl',
        content_css: '/css/materialize.min.css',
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

    $('.mdi.mdi-lock-open').hover(function () {
        $(this).removeClass("mdi-lock-open").addClass("mdi-lock");
    }, function () {
        $(this).removeClass("mdi-lock").addClass("mdi-lock-open");
    });


    $('.mdi.mdi-lock').hover(function () {
        $(this).removeClass("mdi-lock").addClass("mdi-lock-open");
    }, function () {
        $(this).removeClass("mdi-lock-open").addClass("mdi-lock");
    });
});
