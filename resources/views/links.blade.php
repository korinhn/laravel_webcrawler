@extends('layouts.app')

@section('content')

    <div class="row mb-2">
        <p><a href="{{ route('home') }}" class="link-offset-2 link-underline link-underline-opacity-0" >Search another URL</a></p>
    </div>
    
    @if($errors)
        <div class="alert alert-danger" role="alert">
            Failed to resolve <strong>{{ $domain }}</strong>, please provide URL scheme and www prefix.
        </div>
    @endif

    @if($domainExists)
        <form class="row g-3" action="{{ route('refresh') }}" method="post">
            @csrf
            @method('PUT')
    
            <input type="hidden" id="search_url" name="search_url" value="{{ $domain }}">

            <div class="alert alert-success" role="alert">URL found in database, click to refresh.</div>
            
            <div class="col-auto w-25">
                <select class="form-select" id="search_depth" name="search_depth">
                    <option value="1" selected hidden >Select depth (default is 1)</option>
                    @for($val = 2; $val < 6; $val++)
                        <option value="{{ $val }}"
                            @if($val == old('search_depth')) {{ 'selected="selected"' }} @endif>
                            {{ $val }}
                        </option>
                    @endfor
                </select>
            </div>
            
            <div class="col-auto">
                <button type="submit" class="btn btn-primary mb-2">Refresh</button>    
            </div>
        </form>
    @endif

    @if($weblinks && $weblinks->count() > 0)
        @if(!$crawlTiming && $processTiming)
            <div class="alert alert-success" role="alert">Finished successfully. Found {{ $weblinks->count() }} links, in {{ $processTiming }} seconds.</div>
            
        @elseif($crawlTiming)
            <div class="alert alert-success" role="alert">
                Finished successfully. Found {{ $liveLinks }} live links and {{ $deadLinks }} dead links. Crawler took {{ $crawlTiming }} sec. and Validator took {{ $cleanupTiming }} sec.
            </div>
        @endif
            
        <table class="table table-striped">
            <thead>
                <tr class="table-primary">
                    <th colspan="2">Root URL: {{ $domain }}</th>
                <tr>
            <thead>
            <tbody>
                {{-- @foreach ($weblinks as $link)
                    <tr>
                        <td>{{ ++$i }}</td>
                        <td><a href="<?=$link['web_link']?>"><?=$link['web_link']?></a></td>
                    </tr>
                @endforeach --}}
                @for($idx = 0; $idx < $weblinks->count(); $idx++)
                    <tr>
                        <td>{{ $idx+1 }}</td>
                        <td><a href="<?=$weblinks[$idx]['web_link']?>"><?=$weblinks[$idx]['web_link']?></a></td>
                    </tr>
                @endfor
            </tbody>
        </table>
        {{-- {!! $data->weblinks->links('pagination::bootstrap-4') !!} --}}{{-- 'pagination::bootstrap-4' --}}
        {{-- {!! $weblinks->links() !!} --}}
    @endif
@endsection