@props([
    'apis' => [],
    'isCreating' => false
])

<div class="">
    @if($isCreating)
        <div class="text-muted">
            API endpoints will be available after the entity is created.
        </div>
    @else
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Endpoint</th>
                </tr>
            </thead>
            <tbody>
                @foreach($apis as [$method, $color, $endpoint])
                    <tr>
                        <td><span class="badge bg-{{ $color }}">{{ $method }}</span></td>
                        <td><code>{{ $endpoint }}</code></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>