<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canCreateProjects(), 403);

        $search = trim((string) $request->query('search'));
        $type = (string) $request->query('type');
        $status = (string) $request->query('status');

        $clients = Client::query()
            ->withCount('projects')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('contact_name', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('document', "%{$search}%", caseSensitive: false);
            }))
            ->when(array_key_exists($type, ClientType::options()),
                fn ($query) => $query->where('type', $type))
            ->when(in_array($status, ['active', 'inactive'], true),
                fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('clients.index', compact('clients', 'search', 'type', 'status'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canCreateProjects(), 403);

        return view('clients.create', ['types' => ClientType::options()]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        Client::create($request->validated());

        return to_route('clients.index')->with('success', 'Cliente ou unidade demandante cadastrado com sucesso.');
    }

    public function edit(Request $request, Client $client): View
    {
        abort_unless($request->user()->canCreateProjects(), 403);

        return view('clients.edit', compact('client') + ['types' => ClientType::options()]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return to_route('clients.index')->with('success', 'Cliente ou unidade demandante atualizado com sucesso.');
    }
}
