// Bootstrap JS + Popper are loaded from CDN in layouts/app.blade.php.
// Do NOT import them here — loading Bootstrap twice causes dropdown conflicts.

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';