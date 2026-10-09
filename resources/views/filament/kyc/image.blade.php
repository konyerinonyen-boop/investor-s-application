@php
    $record = $getRecord();

    $field = $getState();

    $url = $field
        ? asset('storage/' . $field)
        : null;
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">

    <div class="mb-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
            {{ $getLabel() }}
        </h3>
    </div>

    @if ($url)

        <a
            href="{{ $url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="block"
        >
            <img
                src="{{ $url }}"
                alt="{{ $getLabel() }}"
                class="mx-auto max-h-[400px] w-full rounded-lg object-contain"
            >
        </a>

        <div class="mt-3 text-center">
            <a
                href="{{ $url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="text-sm font-medium text-primary-600 hover:underline"
            >
                Open full image
            </a>
        </div>

    @else

        <div class="flex min-h-[250px] items-center justify-center rounded-lg bg-gray-50 dark:bg-gray-800">
            <span class="text-sm text-gray-500">
                No {{ strtolower($getLabel()) }} uploaded.
            </span>
        </div>

    @endif

</div>