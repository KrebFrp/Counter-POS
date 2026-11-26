@if($paginator->hasPages())
<nav class="pagination" aria-label="Pagination">
@if($paginator->onFirstPage())<span class="page-button disabled">Previous</span>@else<a class="page-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif
<span class="page-number">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
@if($paginator->hasMorePages())<a class="page-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span class="page-button disabled">Next</span>@endif
</nav>
@endif
