<?php

namespace App\Http\Controllers;

use App\Models\MachineProblem;
use Illuminate\Http\Request;
use App\Models\Machine;
use App\Imports\MachineProblemImport;
use Maatwebsite\Excel\Facades\Excel;

class MachineProblemController extends Controller
{
    public function index(Request $request)
    {
        $query = MachineProblem::query();

        // FILTER
        if ($request->machine_type) {
            $query->where('machine_type', $request->machine_type);
        }

        if ($request->problem) {
            $query->where('problem', $request->problem);
        }

        // SORT
        $sort = $request->input('sort');

        switch ($sort) {
            case 'machine_type_asc':
                $query->orderBy('machine_type', 'asc');
                break;

            case 'machine_type_desc':
                $query->orderBy('machine_type', 'desc');
                break;

            case 'problem_asc':
                $query->orderBy('problem', 'asc');
                break;

            case 'problem_desc':
                $query->orderBy('problem', 'desc');
                break;

            default:
                $query->orderBy('machine_type', 'asc');
        }

        $machineProblems = $query->get();

        // 🔥 INI YANG KAMU LUPA
        $machines = Machine::orderBy('machine_type')->get();

        return view('machine-problems.index', compact(
            'machineProblems',
            'machines'
        ));
    }


    public function create()
    {
        $machines = Machine::select('machine_type')
            ->whereNotNull('machine_type')
            ->where('machine_type', '!=', '')
            ->distinct()
            ->orderBy('machine_type')
            ->get();

        $categories = $this->categoryOptions();

        return view('machine-problems.create', compact('machines', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'machine_type' => 'required',
            'category' => 'required|string|max:255',
            'problem' => 'required|string|max:255',
        ], [
            'machine_type.required' => 'Machine type must be selected',
        ]);

        $problem = trim($request->problem);
        $category = trim($request->category);

        $exists = MachineProblem::where('machine_type', $request->machine_type)
            ->where('problem', $problem)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'Problem already exists for this machine type.');
        }

        MachineProblem::create([
            'machine_type' => $request->machine_type,
            'category' => $category,
            'problem' => $problem,
        ]);

        // "Save & add another" keeps the machine type and category so several
        // problems can be typed in one sitting.
        if ($request->boolean('add_another')) {
            return redirect()
                ->route('machine-problems.create', [
                    'machine_type' => $request->machine_type,
                    'category' => $category,
                ])
                ->with('success', 'Problem saved. Add the next one.');
        }

        return redirect()
            ->route('machine-problems.index')
            ->with('success', 'Problem saved successfully');
    }

    private function categoryOptions()
    {
        return MachineProblem::select('category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    public function edit(MachineProblem $machineProblem)
    {
        $machines = Machine::select('machine_type')
            ->distinct()
            ->orderBy('machine_type')
            ->get();

        $categories = $this->categoryOptions();

        return view(
            'machine-problems.edit',
            compact('machineProblem', 'machines', 'categories')
        );
    }

    public function update(Request $request, MachineProblem $machineProblem)
    {
        $request->validate([
            'machine_type' => 'required',
            'category'     => 'required|string|max:255',
            'problem'      => 'required|string|max:255',
        ]);

        // cek duplicate selain data yang sedang diedit
        $exists = MachineProblem::where(
            'machine_type',
            $request->machine_type
        )
            ->where(
                'problem',
                trim($request->problem)
            )
            ->where('id', '!=', $machineProblem->id)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'Problem already exists for this machine type.');
        }

        $machineProblem->update([
            'machine_type' => $request->machine_type,
            'category'     => trim($request->category),
            'problem'      => trim($request->problem),
        ]);

        return redirect()
            ->route('machine-problems.index')
            ->with('success', 'Problem updated successfully');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $mime = strtolower($value->getClientMimeType());
                    $allowedExtensions = ['csv', 'txt', 'xlsx', 'xls'];
                    $allowedMimes = [
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/octet-stream',
                    ];

                    if (!in_array($extension, $allowedExtensions, true) && !in_array($mime, $allowedMimes, true)) {
                        $fail('File must be a CSV or Excel file.');
                    }
                },
            ],
        ]);

        try {
            Excel::import(
                new MachineProblemImport(),
                $request->file('file')
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'file' => 'Import failed: ' . $e->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Machine Problems imported successfully.'
        );
    }

    public function getByType($type)
    {
        // trim($type) berfungsi untuk menghapus spasi liar jika ada teks yang tidak rata
        return MachineProblem::where('machine_type', trim($type))
            ->orderBy('problem', 'asc')
            ->get();
    }

    public function destroy(MachineProblem $machineProblem)
    {
        $machineProblem->delete();

        return back()->with('success', 'Problem deleted');
    }
}
