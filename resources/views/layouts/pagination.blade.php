@props(['paginator'])

@if($paginator instanceof \Illuminate\Contracts\Pagination\Paginator || $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-slate-100">
        <p class="text-[11px] font-medium text-slate-400 uppercase tracking-wider">
            @if($paginator->total() > 0)
                Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data
            @else
                Tidak ada data
            @endif
        </p>

        <div>
            {{ $paginator->appends(request()->query())->links() }}
        </div>
    </div>
@endif
