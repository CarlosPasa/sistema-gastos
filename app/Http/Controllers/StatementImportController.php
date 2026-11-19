<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatementUploadRequest;
use App\Jobs\ImportBankStatement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatementImportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('imports.index', [
            'imports' => $user->statementImports()->with('category:id,description')->latest()->limit(15)->get(),
            'categories' => $user->categories()->active()->orderBy('description')->get(['id', 'description']),
        ]);
    }

    /**
     * Guarda el archivo y lo encola. La respuesta es inmediata; el
     * procesamiento ocurre en el worker (ImportBankStatement).
     */
    public function store(StatementUploadRequest $request): RedirectResponse
    {
        $import = $request->user()->statementImports()->create([
            'original_name' => mb_substr($request->file('file')->getClientOriginalName(), 0, 255),
            'category_id' => (int) $request->validated('id_category'),
            'content' => $request->utf8Content(),
        ]);

        ImportBankStatement::dispatch($import);

        return redirect()
            ->route('mt2.imports.index')
            ->with('ok', 'Recibimos tu archivo. Se está procesando en segundo plano.');
    }
}
