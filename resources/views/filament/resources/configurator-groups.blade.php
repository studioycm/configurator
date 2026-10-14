<ul class="space-y-2">
    @forelse ($groups as $group)
        <li><a class="underline" href="{{ \App\Filament\Resources\Groups\GroupResource::getUrl('edit', ['record' => $group]) }}">{{ $group->name }}</a></li>
    @empty
        <li>{{ __('configurator.groups.empty_assignment_help') }}</li>
    @endforelse
</ul>
