@extends('layouts.app')

@section('content')

    <p>Choose URL and depth to extract links</p>
    
    <form class="row g-3" action="{{ route('links') }}" method="post">
        @csrf
        <div class="col-auto w-25">
            <input type="text" id="search_url" name="search_url" value="{{ old('search_url') }}" placeholder="Enter Url"
                class="form-control @error('search_url') border-danger @enderror">

            @error('search_url')
                <div class="col-auto">
                    <span class="fw-semibold text-danger">{{ $message }}</span>
                </div>
            @enderror
        </div>

        <div class="col-auto w-25">
            <select class="form-select" id="search_depth" name="search_depth">
                <option value="1" selected hidden disabled>Select depth (default is 1)</option>
                @for($i = 2; $i < 6; $i++)
                    <option value="{{ $i }}"
                        @if($i == old('search_depth')) {{ 'selected="selected"'}} @endif>
                        {{ $i }}
                    </option>
                @endfor
            </select>
        </div>
        
        <div class="col-auto">
            <button type="submit" class="btn btn-primary mb-2">Go</button>
        </div>
    </form>
    
@endsection