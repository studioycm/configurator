<?php

namespace App\Http\Controllers;

use App\DTO\CatalogSnapshot;
use App\Models\Group;
use App\Services\CatalogPresentation;
use App\Services\CatalogSnapshots;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class CatalogDatasetController extends Controller
{
    public function __invoke(Request $request, string $group, CatalogSnapshots $snapshots, CatalogPresentation $presentation): Response
    {
        abort_unless(ctype_digit($group) && (string) (int) $group === $group, 404);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = DB::transaction(function () use ($request, $group, $snapshots, $presentation): ?Response {
                $revision = $snapshots->revision((int) $group);
                $shared = $presentation->current();
                $response = response()->json()->setPrivate()->setEtag($this->etag($group, $revision, $shared['version']));
                $response->headers->set('Cache-Control', 'private, no-cache');
                if ($response->isNotModified($request)) {
                    return $response;
                }
                $snapshot = $snapshots->cached((int) $group, $revision);
                if ($snapshot === null) {
                    return null;
                }
                $record = Group::query()->findOrFail((int) $group);
                $available = $presentation->resolveCategoryIds($shared['settings'], [$record]);
                $response->setData([...$snapshot->data, 'displayVersion' => $shared['version'], 'presentation' => $presentation->effective($record, $shared['settings'], $available)]);

                return $response;
            }, attempts: 1);
            if ($response !== null) {
                return $response;
            }
            if ($attempt === 0) {
                $snapshots->get((int) $group);
            }
        }
        throw new ServiceUnavailableHttpException(2, __('catalog.messages.the_catalog_could_not_be_prepared_please_retry'));
    }

    private function etag(string $group, string $revision, string $displayVersion): string
    {
        return 'catalog-'.$group.'-'.CatalogSnapshot::SCHEMA.'-'.$revision.'-'.$displayVersion;
    }
}
