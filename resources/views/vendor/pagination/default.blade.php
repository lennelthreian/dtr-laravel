@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" style="display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;">
        {{-- Previous Page --}}
        @if ($paginator->onFirstPage())
            <span style="padding:6px 12px;font-size:13px;border:1.5px solid var(--gray-300);border-radius:6px;color:var(--gray-400);cursor:default;display:inline-flex;align-items:center;gap:4px;">&larr; Prev</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" style="padding:6px 12px;font-size:13px;border:1.5px solid var(--gray-300);border-radius:6px;color:var(--primary);text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:all 0.2s ease;" onmouseover="this.style.background='var(--gray-50)';this.style.borderColor='var(--gray-400)';" onmouseout="this.style.background='transparent';this.style.borderColor='var(--gray-300)';">&larr; Prev</a>
        @endif

        {{-- Page Numbers --}}
        @foreach ($elements as $element)
            {{-- Separator --}}
            @if (is_string($element))
                <span style="padding:6px 10px;font-size:13px;color:var(--gray-500);">...</span>
            @endif

            {{-- Pages --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span style="padding:6px 12px;font-size:13px;font-weight:700;border:1.5px solid var(--primary);border-radius:6px;background:var(--primary);color:var(--white);display:inline-flex;align-items:center;justify-content:center;min-width:36px;">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" style="padding:6px 12px;font-size:13px;border:1.5px solid var(--gray-300);border-radius:6px;color:var(--gray-700);text-decoration:none;display:inline-flex;align-items:center;justify-content:center;min-width:36px;transition:all 0.2s ease;" onmouseover="this.style.background='var(--gray-50)';this.style.borderColor='var(--gray-400)';" onmouseout="this.style.background='transparent';this.style.borderColor='var(--gray-300)';">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" style="padding:6px 12px;font-size:13px;border:1.5px solid var(--gray-300);border-radius:6px;color:var(--primary);text-decoration:none;display:inline-flex;align-items:center;gap:4px;transition:all 0.2s ease;" onmouseover="this.style.background='var(--gray-50)';this.style.borderColor='var(--gray-400)';" onmouseout="this.style.background='transparent';this.style.borderColor='var(--gray-300)';">Next &rarr;</a>
        @else
            <span style="padding:6px 12px;font-size:13px;border:1.5px solid var(--gray-300);border-radius:6px;color:var(--gray-400);cursor:default;display:inline-flex;align-items:center;gap:4px;">Next &rarr;</span>
        @endif
    </nav>
@endif
