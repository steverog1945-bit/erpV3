@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    @include('postingan._daftar', ['basisUrl' => route('postingan.index'), 'paramTetap' => []])
</div>
@endsection
