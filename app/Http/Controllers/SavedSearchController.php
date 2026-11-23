<?php

namespace App\Http\Controllers;

use App\Enums\AlertFrequency;
use App\Http\Requests\SavedSearchStoreRequest;
use App\Http\Requests\SavedSearchUpdateRequest;
use App\Http\Resources\SavedSearchResource;
use App\Jobs\SendAlertDigest;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    /**
     * List the user's saved searches, newest first.
     */
    public function index(Request $request): Response
    {
        $savedSearches = $this->currentUser($request)
            ->savedSearches()
            ->latest()
            ->orderByDesc('id')
            ->get();

        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection($savedSearches),
            'alertFrequencies' => AlertFrequency::options(),
        ]);
    }

    /**
     * Save a search. Saving criteria the user already has leads to the search
     * they hold rather than a second copy, which would alert them twice.
     */
    public function store(SavedSearchStoreRequest $request): RedirectResponse
    {
        $this->currentUser($request)->savedSearches()->firstOrCreate($request->criteria());

        return redirect()->route('saved-searches.index');
    }

    /**
     * Rename a search or change how often it alerts. The request has already
     * checked that the search is the user's. The criteria never change: they
     * are what the search has matched listings against so far.
     */
    public function update(SavedSearchUpdateRequest $request, SavedSearch $savedSearch): RedirectResponse
    {
        $savedSearch->update([
            'name' => $request->filled('name') ? $request->string('name')->toString() : null,
            'alert_frequency' => $request->enum('alert_frequency', AlertFrequency::class),
        ]);

        // Alerts gathered for the next daily or weekly run would otherwise
        // sit unemailed until another listing happened to match.
        if ($savedSearch->wasChanged('alert_frequency') && $savedSearch->alert_frequency === AlertFrequency::Instant) {
            SendAlertDigest::dispatch($this->currentUser($request), [AlertFrequency::Instant]);
        }

        return redirect()->route('saved-searches.index');
    }

    /**
     * Delete a search, which also stops its alerts.
     */
    public function destroy(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        // Someone else's search is reported as missing rather than forbidden,
        // so the response does not confirm that it exists.
        abort_unless($savedSearch->isOwnedBy($request->user()), 404);

        $savedSearch->delete();

        return redirect()->route('saved-searches.index');
    }
}
