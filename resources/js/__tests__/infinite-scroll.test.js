// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initInfiniteScroll } from '../infinite-scroll.js';

const sentinelHtml = (next) => `
    <div data-infinite-sentinel="rows" ${next ? `data-next-url="${next}"` : ''}>
        <div data-infinite-trigger></div>
        <p data-infinite-loading class="hidden"></p>
        <p data-infinite-end class="hidden"></p>
        <div data-infinite-error class="hidden"><button data-infinite-retry id="retry"></button></div>
    </div>`;

function page(next) {
    document.body.innerHTML = `
        <table><tbody data-infinite-list="rows"><tr><td>row 1</td></tr></tbody></table>
        <div data-infinite-list="rows" id="cards"><article>card 1</article></div>
        ${sentinelHtml(next)}`;
}

const response = (rows, cards, next) => ({
    ok: true,
    text: async () => `<html><body>
        <table><tbody data-infinite-list="rows">${rows}</tbody></table>
        <div data-infinite-list="rows">${cards}</div>
        ${sentinelHtml(next)}</body></html>`,
});

let io;
beforeEach(() => {
    io = { cb: null };
    vi.stubGlobal('IntersectionObserver', class {
        constructor(cb) { io.cb = cb; }
        observe() {}
        unobserve() {}
    });
});
afterEach(() => vi.unstubAllGlobals());

describe('shared infinite scroll', () => {
    it('appends rows to every list with the same key and follows the next url', async () => {
        page('/items?page=2');
        const fetchMock = vi.fn()
            .mockResolvedValueOnce(response('<tr><td>row 2</td></tr>', '<article>card 2</article>', '/items?page=3'))
            .mockResolvedValueOnce(response('<tr><td>row 3</td></tr>', '<article>card 3</article>', null));
        vi.stubGlobal('fetch', fetchMock);
        const events = [];
        document.addEventListener('infinite-scroll:appended', (e) => events.push(e.detail.elements.length));

        const [ctl] = initInfiniteScroll();
        await ctl.loadNext();

        expect(fetchMock.mock.calls[0][0]).toBe('/items?page=2');
        expect(document.querySelectorAll('tbody tr')).toHaveLength(2);
        expect(document.querySelectorAll('#cards article')).toHaveLength(2);
        expect(ctl.nextUrl).toBe('/items?page=3');
        expect(events).toEqual([2]);

        await ctl.loadNext();
        expect(fetchMock.mock.calls[1][0]).toBe('/items?page=3');
        expect(document.querySelectorAll('tbody tr')).toHaveLength(3);
        expect(ctl.nextUrl).toBeNull();
        expect(document.querySelector('[data-infinite-end]').classList.contains('hidden')).toBe(false);

        await ctl.loadNext(); // nothing more: no extra request
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('loads when the sentinel becomes visible', async () => {
        page('/items?page=2');
        const fetchMock = vi.fn().mockResolvedValue(response('<tr><td>row 2</td></tr>', '<article>c</article>', null));
        vi.stubGlobal('fetch', fetchMock);
        initInfiniteScroll();

        io.cb([{ isIntersecting: false }]);
        expect(fetchMock).not.toHaveBeenCalled();
        io.cb([{ isIntersecting: true }]);
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('does not request anything when there is no next page', async () => {
        page(null);
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        const [ctl] = initInfiniteScroll();
        await ctl.loadNext();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('ignores concurrent triggers while a request is in flight', async () => {
        page('/items?page=2');
        let release;
        const fetchMock = vi.fn().mockReturnValue(new Promise((r) => { release = r; }));
        vi.stubGlobal('fetch', fetchMock);
        const [ctl] = initInfiniteScroll();
        const first = ctl.loadNext();
        ctl.loadNext();
        expect(fetchMock).toHaveBeenCalledTimes(1);
        release(response('<tr><td>x</td></tr>', '<article>x</article>', null));
        await first;
    });

    it('shows an error with retry and keeps the same url after a failure', async () => {
        page('/items?page=2');
        const fetchMock = vi.fn()
            .mockResolvedValueOnce({ ok: false, status: 500, text: async () => '' })
            .mockResolvedValueOnce(response('<tr><td>row 2</td></tr>', '<article>c</article>', null));
        vi.stubGlobal('fetch', fetchMock);
        const [ctl] = initInfiniteScroll();

        await ctl.loadNext();
        expect(document.querySelector('[data-infinite-error]').classList.contains('hidden')).toBe(false);
        expect(ctl.nextUrl).toBe('/items?page=2');

        await ctl.loadNext();
        expect(document.querySelector('[data-infinite-error]').classList.contains('hidden')).toBe(true);
        expect(document.querySelectorAll('tbody tr')).toHaveLength(2);
    });
});
