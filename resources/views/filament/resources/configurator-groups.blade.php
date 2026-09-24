<ul class="space-y-2">
    @forelse ($groups as $group)
        <li><a class="underline" href="{{ \App\Filament\Resources\Groups\GroupResource::getUrl('edit', ['record' => $group]) }}">{{ $group->name }}</a></li>
    @empty
        <li>No Groups assigned. Use Assign groups to choose unassigned leaf Groups.</li>
    @endforelse
</ul>
