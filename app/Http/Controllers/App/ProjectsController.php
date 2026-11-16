<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Brain\ProjectContext;
use Illuminate\Contracts\View\View;

/**
 * Проекты текущего пользователя и число карточек в каждом.
 */
class ProjectsController extends Controller
{
    public function index(ProjectContext $context): View
    {
        return view('app.projects', [
            'projects' => $context->projects(),
        ]);
    }
}
