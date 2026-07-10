<div>
    @foreach ($items as $item)
        <p>{{ $item['question'] ?? '' }}</p>
    @endforeach
</div>
