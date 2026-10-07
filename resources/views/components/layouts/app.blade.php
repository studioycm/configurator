<x-layouts.catalog :title="$title ?? __('Settings')" :heading="__('Settings')" :subtitle="__('Manage your profile and account settings')">
    @isset($breadcrumbs)
        <x-slot:breadcrumbs>{{ $breadcrumbs }}</x-slot:breadcrumbs>
    @endisset
    @isset($headerActions)
        <x-slot:headerActions>{{ $headerActions }}</x-slot:headerActions>
    @endisset
    {{ $slot }}
</x-layouts.catalog>
