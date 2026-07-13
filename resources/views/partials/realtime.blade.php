@auth
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @php
            $realtimeConnection = config('broadcasting.connections.reverb');
            $realtimeEnabled = config('broadcasting.default') === 'reverb'
                && filled($realtimeConnection['key'] ?? null)
                && filled($realtimeConnection['options']['host'] ?? null);
        @endphp
        <script>
            window.theraRealtime = {{ Illuminate\Support\Js::from([
                'enabled' => $realtimeEnabled,
                'userId' => auth()->id(),
                'role' => auth()->user()->role,
                'appKey' => $realtimeEnabled ? $realtimeConnection['key'] : null,
                'host' => $realtimeEnabled ? $realtimeConnection['options']['host'] : null,
                'port' => $realtimeEnabled ? (int) $realtimeConnection['options']['port'] : null,
                'scheme' => $realtimeEnabled ? $realtimeConnection['options']['scheme'] : null,
            ]) }};
        </script>
        @vite('resources/js/app.js')
    @endif
@endauth
