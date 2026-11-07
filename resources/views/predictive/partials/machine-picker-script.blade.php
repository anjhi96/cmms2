{{-- Shared by the Add and Edit WO forms. Include once per page. --}}
<script>
    // Machine Master type-ahead for the Add WO form. Only a pick from the
    // list fills Area / Machine Type; typing a number by hand is still sent
    // and checked by the server.
    window.machinePicker = function (initial, woDate, dueInit) {
        const addDays = (iso, n) => { const d = new Date(iso + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); };
        return {
            woDate: woDate, due: dueInit || (woDate ? addDays(woDate, 14) : ''), dueTouched: !!dueInit,
            syncDue() { if (!this.dueTouched && this.woDate) this.due = addDays(this.woDate, 14); },
            q: initial, results: [], picked: null, loading: false, failed: false, searched: false, busy: false, timer: null, ctrl: null,
            init() { if (this.q) this.search(true); },
            schedule() { clearTimeout(this.timer); this.timer = setTimeout(() => this.search(), 250); },
            // A search still pending or in flight would hit the server right
            // behind the POST and could consume its flashed validation errors.
            submitted() { this.busy = true; clearTimeout(this.timer); if (this.ctrl) this.ctrl.abort(); },
            pick(m) { this.q = m.machine_number; this.picked = m; this.results = []; },
            async search(exactOnly = false) {
                const term = this.q.trim();
                this.picked = null;
                if (!term) { this.results = []; this.searched = false; return; }
                this.loading = true; this.failed = false;
                if (this.ctrl) this.ctrl.abort();
                this.ctrl = new AbortController();
                try {
                    const r = await fetch(@js(route('predictive.machines')) + '?q=' + encodeURIComponent(term), {
                        headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: this.ctrl.signal,
                    });
                    if (!r.ok) throw new Error('bad status');
                    const list = await r.json();
                    this.searched = true;
                    const exact = list.find(m => m.machine_number.toLowerCase() === term.toLowerCase());
                    if (exact) { this.picked = exact; this.q = exact.machine_number; }
                    this.results = exactOnly || exact ? [] : list;
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    this.failed = true; this.results = [];
                } finally {
                    this.loading = false;
                }
            },
        };
    };
</script>
