<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    /**
     * List the user's alerts, newest first.
     */
    public function index(Request $request): Response
    {
        // `id` is a tiebreaker: one listing going live can create several
        // alerts in the same second, and without it they could swap places
        // between pages.
        $alerts = $this->currentUser($request)
            ->alerts()
            ->with(['savedSearch', 'listing.branch'])
            ->latest('alerts.created_at')
            ->orderByDesc('alerts.id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Alerts/Index', [
            'alerts' => AlertResource::collection($alerts),
        ]);
    }
}
