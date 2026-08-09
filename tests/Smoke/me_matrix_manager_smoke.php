<?php

use App\Http\Controllers\MeMatrixController;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MeMatrixVersion;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'System Admin'))->firstOrFail();
$portfolio = Sector::query()->orderBy('name')->firstOrFail();
$app['auth']->guard()->setUser($admin);
$controller = $app->make(MeMatrixController::class);
$suffix = Str::upper(Str::random(9));
$code = 'MATRIX-SMOKE-'.$suffix;
$storedPaths = [];

$requestFor = function (string $method, string $uri, array $input = [], array $files = []) use ($app, $admin): Request {
    $request = Request::create($uri, $method, $input, [], $files);
    $request->setUserResolver(fn () => $admin);
    $app->instance('request', $request);

    return $request;
};

$upload = function (int $sequence) use ($controller, $requestFor, $portfolio, $code, $suffix): MeMatrixVersion {
    $title = "Matrix manager smoke {$suffix} version {$sequence}";
    $file = UploadedFile::fake()->createWithContent(
        "matrix-manager-{$suffix}-{$sequence}.csv",
        "Indicator,Target,Actual\nPDO {$sequence},".($sequence * 10).','.($sequence * 4)."\n"
    );
    $controller->store($requestFor(
        'POST',
        '/budget/me/matrices',
        [
            'portfolio_id' => $portfolio->id,
            'title' => $title,
            'matrix_code' => $code,
            'effective_from' => '2026-01-01',
            'change_summary' => "Controlled smoke change {$sequence}",
        ],
        ['matrix_file' => $file]
    ));

    return MeMatrixVersion::query()->where('title', $title)->with('repositoryItem')->firstOrFail();
};

DB::beginTransaction();

try {
    $first = $upload(1);
    $storedPaths[] = $first->repositoryItem->file_path;
    if ($first->version_number !== 1
        || $first->formatLabel() !== 'CSV'
        || $first->inspectionTotals()['sheet_count'] !== 1
        || $first->repositoryItem?->validation_status !== 'pending') {
        throw new RuntimeException('The first controlled matrix version was not inspected and registered correctly.');
    }

    $controller->activate($requestFor('POST', "/budget/me/matrices/{$first->id}/activate"), $first);
    $first->refresh();
    if ($first->status !== 'active' || $first->repositoryItem?->fresh()?->validation_status !== 'validated') {
        throw new RuntimeException('Matrix activation did not synchronize repository validation.');
    }

    $second = $upload(2);
    $storedPaths[] = $second->repositoryItem->file_path;
    if ($second->version_number !== 2) {
        throw new RuntimeException('Automatic matrix version numbering did not advance.');
    }
    $controller->activate($requestFor('POST', "/budget/me/matrices/{$second->id}/activate"), $second);
    $first->refresh();
    $second->refresh();
    if ($first->status !== 'retired'
        || ! $first->repositoryItem?->fresh()?->retired_at
        || $second->status !== 'active') {
        throw new RuntimeException('Activating a newer version did not retire the previous matrix and repository record.');
    }

    $view = $controller->index($requestFor(
        'GET',
        '/budget/me/matrices?status=active&format=CSV&q='.rawurlencode($code),
        ['status' => 'active', 'format' => 'CSV', 'q' => $code]
    ));
    $html = $view->with('errors', new ViewErrorBag)->render();
    if ($view->getData()['matrices']->total() !== 1
        || ! str_contains($html, $second->title)
        || ! str_contains($html, 'matrix-status-chart')
        || ! str_contains($html, 'Matrix version register')) {
        throw new RuntimeException('The filtered matrix workspace did not render the active controlled version.');
    }

    $pdfResponse = $controller->pdf($requestFor(
        'GET',
        '/budget/me/matrices/export/pdf?matrix_id='.$second->id,
        ['matrix_id' => $second->id]
    ));
    if ($pdfResponse->headers->get('content-type') !== 'application/pdf'
        || ! str_starts_with($pdfResponse->getContent(), '%PDF')) {
        throw new RuntimeException('The individual matrix control-sheet PDF was not generated.');
    }

    $download = $controller->download(
        $requestFor('GET', "/budget/me/matrices/{$second->id}/download"),
        $second
    );
    if ($download->getStatusCode() !== 200) {
        throw new RuntimeException('The protected original matrix download failed.');
    }

    $third = $upload(3);
    $thirdPath = $third->repositoryItem->file_path;
    $controller->destroy($requestFor('DELETE', "/budget/me/matrices/{$third->id}"), $third);
    if (MeMatrixVersion::query()->whereKey($third->id)->exists()
        || MeKnowledgeEvidenceItem::query()->whereKey($third->repository_item_id)->exists()
        || Storage::disk('local')->exists($thirdPath)) {
        throw new RuntimeException('Draft deletion did not remove the register, repository and storage records together.');
    }

    echo "ME_MATRIX_MANAGER_OK\n";
} finally {
    $storedPaths = array_merge(
        $storedPaths,
        MeKnowledgeEvidenceItem::query()
            ->where('title', 'like', "Matrix manager smoke {$suffix}%")
            ->pluck('file_path')
            ->filter()
            ->all()
    );
    DB::rollBack();
    Storage::disk('local')->delete(array_values(array_unique(array_filter($storedPaths))));
}
