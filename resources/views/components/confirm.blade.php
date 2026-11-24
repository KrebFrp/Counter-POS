@props(['action', 'name', 'id' => 'remove-record'])
<button type="button" class="button danger-quiet" data-dialog="{{ $id }}"><x-icon name="trash" />Remove</button>
<dialog id="{{ $id }}" class="confirm-dialog" aria-labelledby="{{ $id }}-title">
    <div class="dialog-body"><span class="dialog-icon"><x-icon name="trash" /></span><h2 id="{{ $id }}-title">Remove {{ $name }}?</h2><p>This record will leave the active list. Its previous sales and images will remain in sales history.</p><form method="post" action="{{ $action }}">@csrf @method('DELETE')<div class="form-actions"><button type="button" class="button secondary" data-close-dialog>Cancel</button><button class="button danger">Confirm removal</button></div></form></div>
</dialog>
