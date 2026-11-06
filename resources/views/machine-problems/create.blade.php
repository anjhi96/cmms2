@extends('layouts.app')

@section('content')

    @php
        $selectedType = old('machine_type', request('machine_type'));
        $selectedCategory = old('category', request('category'));
    @endphp

    <div class="container mx-auto px-4 py-6 sm:px-6 max-w-3xl">

        <div class="bg-white rounded-lg shadow">

            <div class="border-b px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-800">
                    Add Machine Big Problem
                </h1>

                <p class="text-gray-500 text-sm">
                    Add one problem without importing a file
                </p>
            </div>

            <div class="p-6">

                @if (session('success'))
                    <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded mb-4">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded mb-4">
                        <ul class="list-disc ml-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('machine-problems.store') }}" method="POST">

                    @csrf

                    <!-- Machine Type -->
                    <div class="mb-5">
                        <label for="machine_type" class="block font-medium mb-2">
                            Machine Type
                        </label>

                        <select id="machine_type" name="machine_type" class="border rounded w-full px-3 py-2" required>
                            <option value="">Select machine type</option>
                            @foreach ($machines as $machine)
                                <option value="{{ $machine->machine_type }}"
                                    {{ $selectedType == $machine->machine_type ? 'selected' : '' }}>
                                    {{ $machine->machine_type }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Category -->
                    <div class="mb-5">
                        <label for="category" class="block font-medium mb-2">
                            Category
                        </label>

                        <input type="text" id="category" name="category" list="categoryOptions"
                            value="{{ $selectedCategory }}" autocomplete="off"
                            class="border rounded w-full px-3 py-2" required>
                        <datalist id="categoryOptions">
                            @foreach ($categories as $category)
                                <option value="{{ $category }}"></option>
                            @endforeach
                        </datalist>

                        <p class="text-gray-500 text-xs mt-1">
                            Pick an existing category or type a new one. Problem Findings are grouped by this category.
                        </p>
                    </div>

                    <!-- Big Problem -->
                    <div class="mb-6">
                        <label for="problem" class="block font-medium mb-2">
                            Big Problem
                        </label>

                        <input type="text" id="problem" name="problem" value="{{ old('problem') }}"
                            class="border rounded w-full px-3 py-2" required
                            {{ $selectedType && $selectedCategory ? 'autofocus' : '' }}>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">

                        <a href="{{ route('machine-problems.index') }}"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded text-center">
                            Cancel
                        </a>

                        <button type="submit" name="add_another" value="1"
                            class="border border-blue-600 text-blue-700 hover:bg-blue-50 px-5 py-2 rounded">
                            Save &amp; Add Another
                        </button>

                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">
                            Save
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

@endsection
