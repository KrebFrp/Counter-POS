@props(['records'])
<div class="pagination-bar"><p>@if($records->total())Showing <strong>{{ $records->firstItem() }}–{{ $records->lastItem() }}</strong> of <strong>{{ $records->total() }}</strong> records @else 0 records @endif</p>{{ $records->links('components.pagination-links') }}</div>
