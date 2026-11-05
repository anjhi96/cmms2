/**
 * Shared infinite scroll for every list page (desktop table + mobile cards).
 *
 * Markup contract (see components/infinite-scroll.blade.php):
 *   - rows/cards live directly inside one or more `[data-infinite-list="key"]`
 *   - a `[data-infinite-sentinel="key"]` footer carries `data-next-url`
 *
 * When the sentinel nears the viewport the next-page URL — the current URL
 * plus the page number, so the SAME controller re-applies search, filter,
 * sort and area scope — is fetched as HTML. The matching list containers in
 * the response (matched by key + position) have their children appended to
 * the live ones, and the response's own sentinel supplies the next URL (or
 * none on the last page). Nothing is filtered or sorted client-side.
 *
 * After appending, `infinite-scroll:appended` is dispatched on `document`
 * with `detail.elements`, for pages that bind behaviour to individual rows.
 */

const ROOT_MARGIN = '300px';

function initInfiniteScroll(root = document) {
    return Array.from(root.querySelectorAll('[data-infinite-sentinel]')).map(setup);
}

function setup(sentinel) {
    const key = sentinel.dataset.infiniteSentinel;
    const trigger = sentinel.querySelector('[data-infinite-trigger]');
    const loadingEl = sentinel.querySelector('[data-infinite-loading]');
    const endEl = sentinel.querySelector('[data-infinite-end]');
    const errorEl = sentinel.querySelector('[data-infinite-error]');
    const moreEl = sentinel.querySelector('[data-infinite-more]');

    let nextUrl = sentinel.dataset.nextUrl || null;
    let loading = false;
    let loadedAny = false;
    let observer = null;

    const show = (el, visible) => el && el.classList.toggle('hidden', !visible);

    function listsIn(doc) {
        return Array.from(doc.querySelectorAll(`[data-infinite-list="${key}"]`));
    }

    async function loadNext() {
        if (loading || !nextUrl) {
            return;
        }

        loading = true;
        let failed = false;
        show(errorEl, false);
        show(loadingEl, true);

        try {
            const response = await fetch(nextUrl, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Request failed with status ' + response.status);
            }

            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const incoming = listsIn(doc);
            const live = listsIn(document);
            const added = [];

            live.forEach((list, i) => {
                const source = incoming[i];
                if (!source) {
                    return;
                }
                Array.from(source.children).forEach((child) => {
                    const node = document.importNode(child, true);
                    list.appendChild(node);
                    added.push(node);
                });
            });

            const nextSentinel = doc.querySelector(`[data-infinite-sentinel="${key}"]`);
            nextUrl = nextSentinel?.dataset.nextUrl || null;
            loadedAny = true;

            if (added.length) {
                document.dispatchEvent(new CustomEvent('infinite-scroll:appended', { detail: { key, elements: added } }));
            }
        } catch (error) {
            failed = true;
            show(errorEl, true);
        } finally {
            loading = false;
            show(loadingEl, false);
            show(endEl, !nextUrl && loadedAny);
            show(moreEl, Boolean(nextUrl) && !observer);
            // still in view (short page / tall screen)? re-arm so it keeps filling
            if (nextUrl && observer && trigger && !failed) {
                observer.unobserve(trigger);
                observer.observe(trigger);
            }
        }
    }

    sentinel.querySelectorAll('[data-infinite-retry]').forEach((btn) => btn.addEventListener('click', loadNext));

    if (typeof IntersectionObserver !== 'undefined' && trigger) {
        observer = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                loadNext();
            }
        }, { rootMargin: ROOT_MARGIN });
        observer.observe(trigger);
    } else {
        show(moreEl, Boolean(nextUrl));
    }

    return { loadNext, get nextUrl() { return nextUrl; } };
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initInfiniteScroll());
    } else {
        initInfiniteScroll();
    }
}

export { initInfiniteScroll };
