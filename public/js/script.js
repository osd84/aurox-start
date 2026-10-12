// -----------------------------------------------------------------------------
// script.js : le JS commun à toutes les pages. jQuery, rien d'autre.
// Fichier externe servi par le site : autorisé par la CSP sans nonce.
// -----------------------------------------------------------------------------

$(function () {

    // Confirmation avant un envoi destructeur : <form data-confirm="Supprimer cet article ?">
    // Le texte passe par un attribut data-, jamais par du JS écrit dans la page.
    $(document).on('submit', 'form[data-confirm]', function () {
        return confirm($(this).data('confirm'));
    });

});
