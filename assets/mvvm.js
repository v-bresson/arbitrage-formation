// ===================================================================
// Mini-noyau MVVM "maison", sans dépendance externe.
//
// - qaReactive(obj) enveloppe un objet dans un Proxy qui traque les
//   lectures (dans un effet actif) et déclenche les effets concernés à
//   chaque écriture (set/delete), y compris en profondeur (objets et
//   tableaux imbriqués enveloppés à la volée).
// - qaWatchEffect(fn) exécute fn() immédiatement, mémorise les
//   propriétés réactives lues pendant l'exécution, et ré-exécute fn()
//   automatiquement (regroupé en microtâche pour éviter les rendus en
//   double) dès qu'une d'elles change.
//
// Usage typique d'une page : un seul état réactif (le ViewModel), un
// seul qaWatchEffect(render) qui redessine la vue — plus aucun appel
// manuel à une fonction de rendu après une mutation d'état ou un fetch.
//
// Volontairement limité aux données de listes/état d'écran : les
// formulaires dans les modales restent des <input> natifs non liés
// (lus via getElementById à la soumission), pour ne pas perdre le
// focus/curseur en cours de frappe — un ré-rendu complet à chaque
// caractère saisi casserait la saisie sans un diff de DOM fin (hors
// périmètre de ce noyau minimaliste).
// ===================================================================

let qaActiveEffect = null;
const qaTargetMap = new WeakMap();

function qaTrack(target, key) {
    if (!qaActiveEffect) return;
    let depsMap = qaTargetMap.get(target);
    if (!depsMap) qaTargetMap.set(target, depsMap = new Map());
    let dep = depsMap.get(key);
    if (!dep) depsMap.set(key, dep = new Set());
    dep.add(qaActiveEffect);
}

function qaTrigger(target, key) {
    const depsMap = qaTargetMap.get(target);
    if (!depsMap) return;
    const dep = depsMap.get(key);
    if (dep) dep.forEach(runner => runner());
}

const qaRawToReactive = new WeakMap();

function qaReactive(target) {
    if (target === null || typeof target !== 'object') return target;
    if (qaRawToReactive.has(target)) return qaRawToReactive.get(target);

    const proxy = new Proxy(target, {
        get(obj, key, receiver) {
            const result = Reflect.get(obj, key, receiver);
            qaTrack(obj, key);
            return (result !== null && typeof result === 'object') ? qaReactive(result) : result;
        },
        set(obj, key, value, receiver) {
            const ok = Reflect.set(obj, key, value, receiver);
            qaTrigger(obj, key);
            return ok;
        },
        deleteProperty(obj, key) {
            const ok = Reflect.deleteProperty(obj, key);
            qaTrigger(obj, key);
            return ok;
        },
    });
    qaRawToReactive.set(target, proxy);
    return proxy;
}

// Exécute fn() en tant qu'effet réactif : ré-exécuté automatiquement
// (regroupé en microtâche) dès qu'une propriété réactive lue pendant
// son exécution est modifiée par la suite.
function qaWatchEffect(fn) {
    let scheduled = false;
    const runner = () => {
        if (scheduled) return;
        scheduled = true;
        queueMicrotask(() => {
            scheduled = false;
            qaActiveEffect = runner;
            try { fn(); } finally { qaActiveEffect = null; }
        });
    };
    // Premier passage synchrone (pas de regroupement nécessaire au démarrage).
    qaActiveEffect = runner;
    try { fn(); } finally { qaActiveEffect = null; }
    return runner;
}
