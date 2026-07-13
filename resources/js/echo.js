import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const config = window.theraRealtime;

if (config?.enabled) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: config.appKey,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
