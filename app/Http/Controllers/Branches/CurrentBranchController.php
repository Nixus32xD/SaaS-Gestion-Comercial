<?php

namespace App\Http\Controllers\Branches;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\CurrentBranch;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentBranchController extends Controller
{
    public function update(
        Request $request,
        CurrentBusiness $currentBusiness,
        CurrentBranch $currentBranch,
    ): RedirectResponse {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
        ]);

        $business = $currentBusiness->get();

        abort_if($business === null, 403);

        $branch = Branch::query()
            ->forBusiness($business->id)
            ->active()
            ->whereKey($validated['branch_id'])
            ->first();

        abort_if($branch === null, 403, 'La sucursal seleccionada no pertenece al comercio actual.');

        $request->session()->put('branch_id', $branch->id);
        $currentBranch->set($branch);

        return back()->with('success', 'Sucursal activa actualizada.');
    }
}
