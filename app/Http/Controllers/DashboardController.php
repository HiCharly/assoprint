<?php

namespace App\Http\Controllers;

use App\Http\Resources\PrintJobResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the landing page of a logged in member.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'pagesPrinted' => $user->pagesPrinted(),
            'jobsInProgress' => $user->printJobs()->inProgress()->count(),
            'recentJobs' => PrintJobResource::collection(
                $user->printJobs()->latest()->limit(5)->get(),
            ),
        ]);
    }
}
