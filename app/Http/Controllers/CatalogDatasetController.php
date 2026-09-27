<?php

namespace App\Http\Controllers;

use App\DTO\CatalogSnapshot;
use App\Services\CatalogSnapshots;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CatalogDatasetController extends Controller
{
    public function __invoke(Request $request, string $group, CatalogSnapshots $snapshots): Response
    {
        abort_unless(ctype_digit($group) && (string) (int) $group === $group, 404);
        $revision = $snapshots->revision((int) $group);
        $response = response()->json()->setPrivate()->setEtag($this->etag($group, $revision));
        $response->headers->set('Cache-Control', 'private, no-cache');
        if ($response->isNotModified($request)) {
            return $response;
        }
        $snapshot = $snapshots->get((int) $group);
        $response->setData($snapshot->data);
        $response->setEtag($this->etag($group, $snapshot->revision()));

        return $response;
    }

    private function etag(string $group, string $revision): string
    {
        return 'catalog-'.$group.'-'.CatalogSnapshot::SCHEMA.'-'.$revision;
    }
}
