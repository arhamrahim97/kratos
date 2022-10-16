<div class="table-responsive {{ $class ?? '' }}">
    <table class="table table-hover table-striped" id="{{ $id }}" width="100%">
        <thead>
            <tr class="text-center fw-bold">
                @foreach ($th as $item)
                    <td>{{ $item }}</td>
                @endforeach
            </tr>
        </thead>
        <tbody>

        </tbody>
    </table>
</div>
