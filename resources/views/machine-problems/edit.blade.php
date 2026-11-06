@extends('layouts.app')

@section('content')
    <div class="p-6 bg-gray-50">

        <div class="mb-6">

            <h1 class="text-2xl font-bold text-gray-800">
                Edit Machine Problem
            </h1>

            <p class="text-sm text-gray-500">
                Update big problem master
            </p>

        </div>

        @if (session('error'))
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                <ul class="list-disc ml-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6 max-w-3xl">

            <form action="{{ route('machine-problems.update', $machineProblem->id) }}" method="POST">

                @csrf
                @method('PUT')

                {{-- MACHINE TYPE --}}
                <div class="mb-4">

                    <label class="block mb-2">
                        Machine Type
                    </label>

                    <select name="machine_type" class="w-full border p-3 rounded">

                        @foreach ($machines as $machine)
                            <option value="{{ $machine->machine_type }}"
                                {{ $machineProblem->machine_type == $machine->machine_type ? 'selected' : '' }}>

                                {{ $machine->machine_type }}

                            </option>
                        @endforeach

                    </select>

                </div>

                {{-- CATEGORY --}}
                <div class="mb-4">

                    <label for="category" class="block mb-2">
                        Category
                    </label>

                    <input type="text" id="category" name="category" list="categoryOptions" autocomplete="off"
                        value="{{ old('category', $machineProblem->category) }}"
                        class="w-full border p-3 rounded" required>
                    <datalist id="categoryOptions">
                        @foreach ($categories as $category)
                            <option value="{{ $category }}"></option>
                        @endforeach
                    </datalist>

                </div>

                {{-- PROBLEM --}}
                <div class="mb-6">

                    <label class="block mb-2">
                        Big Problem
                    </label>

                    <input type="text" name="problem" value="{{ old('problem', $machineProblem->problem) }}"
                        class="w-full border p-3 rounded">

                </div>

                <div class="flex gap-3">

                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">

                        Update

                    </button>

                    <a href="{{ route('machine-problems.index') }}" class="bg-gray-300 hover:bg-gray-400 px-5 py-2 rounded">

                        Cancel

                    </a>

                </div>

            </form>

        </div>

    </div>
@endsection
