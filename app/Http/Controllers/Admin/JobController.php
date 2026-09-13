<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PrintJobResource;
use App\Models\PrintJob;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    /**
     * Show every print job, all members together.
     *
     * Vue de diagnostic : c’est ici qu’on voit d’un coup d’œil qu’aucune tâche
     * ne sort plus de l’imprimante, et pourquoi.
     */
    public function index(Request $request): Response
    {
        $jobs = PrintJob::query()
            ->with('user')
            ->latest()
            ->limit(200)
            ->get();

        return Inertia::render('admin/jobs', [
            'jobs' => PrintJobResource::collection($jobs),
        ]);
    }
}
