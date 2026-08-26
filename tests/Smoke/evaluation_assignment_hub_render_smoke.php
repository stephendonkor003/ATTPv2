<?php

use App\Http\Controllers\EvaluationAssignmentController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;

$compiledPath = sys_get_temp_dir().'/attp-assignment-hub-smoke-'.getmypid();

if (! is_dir($compiledPath) && ! mkdir($compiledPath, 0777, true) && ! is_dir($compiledPath)) {
    throw new RuntimeException('Unable to create an isolated Blade cache for the assignment hub smoke test.');
}

putenv('VIEW_COMPILED_PATH='.$compiledPath);
$_ENV['VIEW_COMPILED_PATH'] = $compiledPath;
$_SERVER['VIEW_COMPILED_PATH'] = $compiledPath;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$administrator = User::query()
    ->where('user_type', 'admin')
    ->firstOrFail();

Auth::setUser($administrator);

$view = $app->make(EvaluationAssignmentController::class)->hub();
$view->with('errors', new ViewErrorBag);
$procurements = $view->getData()['procurements'];
$sampleEmail = $procurements
    ->flatMap(fn ($procurement) => $procurement->evaluationAssignments)
    ->pluck('evaluator.email')
    ->filter()
    ->first();
$html = $view->render();

if (! str_contains($html, '<th scope="col">Email</th>')) {
    throw new RuntimeException('The evaluator email column did not render.');
}

if ($sampleEmail && ! str_contains($html, e($sampleEmail))) {
    throw new RuntimeException('An assigned evaluator email did not render.');
}

if (! str_contains($html, 'Evaluator Assignments')) {
    throw new RuntimeException('The assignment workspace heading did not render.');
}

echo 'EVALUATION_ASSIGNMENT_HUB_RENDER_OK '.strlen($html)." bytes\n";
