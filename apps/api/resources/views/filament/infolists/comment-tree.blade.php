@php
    $report = $getRecord();
@endphp
@if($report)
    @livewire('comment-context-tree', ['reportId' => $report->id])
@else
    <div style="padding: 16px; background-color: #fee2e2; color: #991b1b; border-radius: 6px;">Error: Record is null!</div>
@endif
