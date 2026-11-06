@extends('layouts.app')

@section('content')

    @php
        $selectedCategory = old('category', request('category'));
    @endphp

    <div class="container mx-auto px-4 py-6 sm:px-6 max-w-3xl">

        <div class="bg-white rounded-lg shadow">

            <div class="border-b px-6 py-4">
                <h1 class="text-2xl font-bold text-gray-800">
                    Add Machine Problem Finding
                </h1>

                <p class="text-gray-500 text-sm">
                    Add one finding without importing a file
                </p>
            </div>

            <div class="p-6">

                @if (session('success'))
                    <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded mb-4">
                        {{ session('success') }}
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

                @if ($categories->isEmpty())
                    <div class="bg-amber-50 border border-amber-300 text-amber-800 px-4 py-3 rounded mb-4 text-sm">
                        No category exists yet. Add one in
                        <a href="{{ route('machine-problems.create') }}" class="font-medium underline">Problem Category</a>
                        first.
                    </div>
                @endif

                <form action="{{ route('machine-problem-findings.store') }}" method="POST">

                    @csrf

                    <!-- Category -->
                    <div class="mb-5">
                        <label for="category" class="block font-medium mb-2">
                            Category
                        </label>

                        <select id="category" name="category" class="border rounded w-full px-3 py-2" required>
                            <option value="">Select category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" {{ $selectedCategory == $category ? 'selected' : '' }}>
                                    {{ $category }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Finding -->
                    <div class="mb-6">
                        <label for="finding" class="block font-medium mb-2">
                            Finding
                        </label>

                        <input type="text" id="finding" name="finding" value="{{ old('finding') }}"
                            class="border rounded w-full px-3 py-2" required
                            {{ $selectedCategory ? 'autofocus' : '' }}>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">

                        <a href="{{ route('machine-problem-findings.index') }}"
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
