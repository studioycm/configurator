<?php

namespace App\Filament\Resources;

interface HasWorkspaceTable
{
    /** @return array{owner: int|string, widthVariant: string, profileVariant: string} */
    public function tableHostIdentity(): array;

    /** @return list<string> */
    public function tableProfileControlNames(): array;

    /** @return array<string, mixed>|null */
    public function tableProfileColumnMigration(): ?array;

    /** @return list<string> */
    public function tableQuickFilterNames(): array;

    /** @return array<string, string> */
    public function scopedSearchColumns(): array;

    /** @param array<string, mixed> $state */
    public function restoreTableBrowserProfile(array $state): void;
}
