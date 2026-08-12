<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Initiative;
use App\Services\InitiativeConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

class InitiativeConversionController extends Controller
{
    public function index(Request $request, InitiativeConversionService $conversions): View
    {
        $initiatives = Initiative::query()->with('project')->latest('updated_at')->get();
        try {
            $availability = $initiatives->mapWithKeys(fn (Initiative $initiative) => [
                $initiative->id => $conversions->availability($initiative, $request->user()),
            ]);
        } catch (LogicException) {
            abort(403);
        }

        return view('initiatives.index', [
            'initiatives' => $initiatives,
            'availability' => $availability,
        ]);
    }

    public function show(Request $request, Initiative $initiative, InitiativeConversionService $conversions): View
    {
        try {
            $availability = $conversions->availability($initiative, $request->user());
        } catch (LogicException) {
            abort(403);
        }

        return view('initiatives.conversion', [
            'initiative' => $initiative,
            'availability' => $availability,
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function convert(Request $request, Initiative $initiative, InitiativeConversionService $conversions): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer'],
            'objective' => ['required', 'string', 'max:5000'],
            'justification' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $project = $conversions->convert($initiative, $data, $request->user());
        } catch (LogicException $exception) {
            abort(409, $exception->getMessage());
        }

        return to_route('projects.show', $project)->with('success', 'Iniciativa convertida em projeto.');
    }
}
