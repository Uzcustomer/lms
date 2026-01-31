<select
    id="{{ $id }}"
    name="{{ $name }}"
    {{ $attributes->merge(['class' => 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm custom-select']) }}
>
    <option value="">{{ $placeholder ?? __('Select an option') }}</option>
    @foreach ($options as $option)
        <option value="{{ $option['value'] }}" {{ $option['selected'] ?? false ? 'selected' : '' }}>
            {{ $option['label'] }}
        </option>
    @endforeach
</select>

@once
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    @endpush
@endonce

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            new TomSelect('#{{ $id }}', {
                allowEmptyOption: true,
                plugins: ['clear_button', 'input_autogrow'],
                persist: false,
                create: false,
                closeAfterSelect: true,
                maxOptions: 500,
                render: {
                    no_results: function(data, escape) {
                        return '<div class="no-results">{{ __('Natijalar topilmadi') }}</div>';
                    }
                },
                // Enable search functionality
                searchField: ['text', 'value'],
                // Customize the search behavior
                score: function(search) {
                    var score = this.getScoreFunction(search);
                    return function(item) {
                        return score(item);
                    };
                },
            });
        });
    </script>
@endpush
