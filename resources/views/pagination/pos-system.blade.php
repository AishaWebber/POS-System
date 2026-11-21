@if ($paginator->hasPages())
<nav aria-label="Pagination"><ul class="pagination">
@if ($paginator->onFirstPage())<li><span aria-disabled="true">Previous</span></li>@else<li><a href="{{ $paginator->previousPageUrl() }}">Previous</a></li>@endif
<li><span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span></li>
@if ($paginator->hasMorePages())<li><a href="{{ $paginator->nextPageUrl() }}">Next</a></li>@else<li><span aria-disabled="true">Next</span></li>@endif
</ul></nav>
@endif
