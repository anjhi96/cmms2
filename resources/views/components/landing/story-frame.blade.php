@props(['scene'])

{{-- One storyboard frame per PM step, drawn in the same palette and lighting as the factory scene. --}}
<svg viewBox="0 0 320 200" class="block h-auto w-full" aria-hidden="true" focusable="false">
    <rect width="320" height="200" fill="#0a2e66" />
    <polygon points="140,0 180,0 260,200 60,200" fill="#f9c453" opacity="0.1" />
    <rect y="168" width="320" height="32" fill="#061c3f" />

    @switch($scene)
        @case('schedule')
            {{-- Wall schedule board, today's PM marked in amber --}}
            <rect x="52" y="26" width="216" height="128" fill="#0b3b85" stroke="#3f86d8" stroke-width="3" />
            <rect x="52" y="26" width="216" height="20" fill="#0d47a1" />
            @for ($r = 0; $r < 4; $r++)
                @for ($c = 0; $c < 7; $c++)
                    <rect x="{{ 62 + $c * 29 }}" y="{{ 54 + $r * 24 }}" width="23" height="18"
                        fill="{{ ($r === 1 && $c === 3) ? '#f59e0b' : ((($r * 7 + $c) % 4 === 0) ? '#1e6ecb' : '#0a2e66') }}" />
                @endfor
            @endfor
            <circle cx="160" cy="20" r="5" fill="#cfe1f6" />
        @break

        @case('checklist')
            {{-- Capstan on the left, technician tablet with the checklist on the right --}}
            <circle cx="88" cy="112" r="56" fill="#0b3b85" stroke="#6fa5e2" stroke-width="4" />
            <circle cx="88" cy="112" r="14" fill="#cfe1f6" />
            <line x1="88" y1="56" x2="88" y2="168" stroke="#3f86d8" stroke-width="5" />
            <rect x="170" y="30" width="112" height="140" rx="8" fill="#e8f1fb" />
            @foreach ([56, 84, 112, 140] as $i => $y)
                <rect x="184" y="{{ $y - 8 }}" width="16" height="16" fill="none" stroke="#0d47a1" stroke-width="2.5" />
                @if ($i < 3)
                    <path d="M187 {{ $y }} l4 4 l8 -9" fill="none" stroke="#f59e0b" stroke-width="3" />
                @endif
                <rect x="208" y="{{ $y - 3 }}" width="{{ [58, 44, 52, 36][$i] }}" height="6" fill="#0d47a1" opacity="0.7" />
            @endforeach
        @break

        @case('scan')
            {{-- Phone held up to the QR tag on a machine --}}
            <rect x="40" y="40" width="160" height="128" fill="#0b3b85" stroke="#3f86d8" stroke-width="3" />
            <rect x="80" y="62" width="76" height="76" fill="#ffffff" />
            @foreach ([[86, 68], [126, 68], [86, 108]] as [$fx, $fy])
                <rect x="{{ $fx }}" y="{{ $fy }}" width="24" height="24" fill="#061c3f" />
                <rect x="{{ $fx + 5 }}" y="{{ $fy + 5 }}" width="14" height="14" fill="#ffffff" />
                <rect x="{{ $fx + 9 }}" y="{{ $fy + 9 }}" width="6" height="6" fill="#061c3f" />
            @endforeach
            <rect x="122" y="112" width="8" height="8" fill="#061c3f" />
            <rect x="138" y="122" width="8" height="8" fill="#061c3f" />
            <rect x="196" y="58" width="78" height="128" rx="12" fill="#061c3f" stroke="#cfe1f6" stroke-width="4" />
            <rect x="206" y="72" width="58" height="96" fill="#0d47a1" />
            <path d="M214 88 v-8 h8 M248 80 h8 v8 M256 152 v8 h-8 M222 160 h-8 v-8" fill="none" stroke="#f59e0b" stroke-width="3" />
            <rect x="214" y="118" width="42" height="3" fill="#f59e0b" />
        @break

        @case('analysis')
            {{-- Review screen: history bars and a timeline of past PMs --}}
            <rect x="40" y="24" width="240" height="140" rx="4" fill="#061c3f" stroke="#3f86d8" stroke-width="3" />
            @foreach ([40, 64, 52, 88, 72, 100, 60] as $i => $h)
                <rect x="{{ 62 + $i * 30 }}" y="{{ 132 - $h }}" width="18" height="{{ $h }}"
                    fill="{{ $i === 5 ? '#f59e0b' : '#1e6ecb' }}" />
            @endforeach
            <line x1="56" y1="140" x2="264" y2="140" stroke="#6fa5e2" stroke-width="2" />
            @foreach ([71, 131, 191, 251] as $tx)
                <circle cx="{{ $tx }}" cy="150" r="4" fill="#cfe1f6" />
            @endforeach
        @break
    @endswitch
</svg>
