// Mesure la hauteur réelle du bandeau fixe (.site-header) et du fil
// d'ariane (.breadcrumb) pour positionner ce dernier juste en dessous et
// réserver l'espace correspondant (.header-spacer), plutôt qu'une hauteur
// fixe en CSS : le bandeau peut faire 1, 2 ou 3 lignes selon la largeur
// d'écran et la longueur du nom d'utilisateur affiché.
(function () {
    function sync() {
        var header = document.querySelector('.site-header');
        var spacer = document.querySelector('.header-spacer');
        if (!header || !spacer) return;
        var crumb = document.querySelector('.breadcrumb');
        var headerH = header.offsetHeight;
        var crumbH = crumb ? crumb.offsetHeight : 0;
        if (crumb) crumb.style.top = headerH + 'px';
        spacer.style.height = (headerH + crumbH) + 'px';
    }

    window.addEventListener('resize', sync);
    window.addEventListener('load', sync);

    var header = document.querySelector('.site-header');
    var crumb = document.querySelector('.breadcrumb');
    if (window.MutationObserver) {
        var observer = new MutationObserver(sync);
        if (header) observer.observe(header, { childList: true, subtree: true, characterData: true });
        if (crumb) observer.observe(crumb, { childList: true, subtree: true, characterData: true });
    }

    sync();
    // Exposée pour être rappelée explicitement quand le bandeau passe de
    // display:none à visible après coup (ex. admin/index.php : le contenu
    // reste masqué le temps de vérifier la session, donc la première
    // mesure automatique donne une hauteur nulle).
    window.qaSyncFixedHeader = sync;
})();
