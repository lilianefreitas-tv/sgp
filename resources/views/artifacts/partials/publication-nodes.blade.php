@foreach ($nodes as $node)
    <div class="document-node document-node-level-{{ $level }}">
        @if ($node['children'] !== [])
            <h{{ min($level + 2, 5) }}>{{ $node['label'] }}</h{{ min($level + 2, 5) }}>
            @include('artifacts.partials.publication-nodes', ['nodes' => $node['children'], 'level' => $level + 1])
        @else
            <div class="document-field">
                <span class="document-label">{{ $node['label'] }}</span>
                <span class="document-value">{{ $node['value'] }}</span>
            </div>
        @endif
    </div>
@endforeach
