<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Social Accounts</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-100">

<div class="max-w-3xl mx-auto px-4 py-10">

    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            Social Accounts
        </h1>

        <p class="mt-2 text-gray-600">
            Connect and manage your social media accounts.
        </p>
    </div>


    <!-- Authentication -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">

        <h2 class="text-xl font-semibold text-gray-900 mb-4">
            Authentication
        </h2>

        <label
            for="bearerToken"
            class="block text-sm font-medium text-gray-700 mb-2"
        >
            Bearer Token
        </label>

        <input
            type="password"
            id="bearerToken"
            placeholder="Paste your Sanctum token"
            autocomplete="off"
            class="w-full p-3 text-sm border border-gray-300 rounded-lg
                   focus:outline-none focus:ring-2 focus:ring-blue-500"
        >

        <button
            type="button"
            onclick="authenticate()"
            class="mt-3 px-5 py-3 rounded-lg bg-gray-900 text-white
                   hover:bg-gray-800 transition"
        >
            Authenticate
        </button>

        <p id="authStatus" class="mt-3 text-sm"></p>

    </div>


    <!-- OAuth -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">

        <h2 class="text-xl font-semibold text-gray-900 mb-5">
            Connect account
        </h2>

        <div class="grid gap-4 sm:grid-cols-2">

            <!-- Instagram -->
            <button
                type="button"
                onclick="connectInstagram()"
                class="w-full p-5 text-left border rounded-lg
                       hover:bg-gray-50 transition"
            >
                <div class="flex items-center justify-between">

                    <div>
                        <h3 class="font-semibold text-gray-900">
                            Instagram
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            Connect your Instagram account
                        </p>
                    </div>

                    <span class="font-medium text-pink-600">
                        Connect
                    </span>

                </div>
            </button>


            <!-- Facebook -->
            <button
                type="button"
                onclick="connectFacebook()"
                class="w-full p-5 text-left border rounded-lg
                       hover:bg-gray-50 transition"
            >
                <div class="flex items-center justify-between">

                    <div>
                        <h3 class="font-semibold text-gray-900">
                            Facebook
                        </h3>

                        <p class="mt-1 text-sm text-gray-500">
                            Connect a Facebook Page
                        </p>
                    </div>

                    <span
                        id="facebookButton"
                        class="font-medium text-blue-600"
                    >
                        Connect
                    </span>

                </div>
            </button>

        </div>

    </div>


    <!-- Connected Accounts -->
    <div class="bg-white rounded-xl shadow-sm p-6">

        <div class="flex items-center justify-between mb-5">

            <h2 class="text-xl font-semibold text-gray-900">
                Connected accounts
            </h2>

            <button
                type="button"
                onclick="loadConnectedAccounts()"
                class="text-sm font-medium text-blue-600
                       hover:text-blue-700"
            >
                Refresh
            </button>

        </div>

        <div id="connectedAccounts">
            <p class="text-sm text-gray-500">
                Authenticate to load connected accounts.
            </p>
        </div>

    </div>

</div>


<script>

let authenticated = false;


/*
|--------------------------------------------------------------------------
| Initialization
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', () => {

    const token = sessionStorage.getItem('api_token');

    if (!token) {
        return;
    }

    document.getElementById('bearerToken').value = token;

    authenticate();
});


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

async function authenticate() {

    const token = document
        .getElementById('bearerToken')
        .value
        .trim();

    if (!token) {
        setAuthStatus(
            'Weka Bearer Token kwanza.',
            'error'
        );

        return;
    }

    try {

        const response = await fetch('/api/user', {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        });

        if (!response.ok) {

            authenticated = false;

            sessionStorage.removeItem('api_token');

            setAuthStatus(
                'Bearer Token si valid.',
                'error'
            );

            renderEmptyAccounts(
                'Authenticate with a valid Bearer Token.'
            );

            return;
        }

        const user = await response.json();

        sessionStorage.setItem('api_token', token);

        authenticated = true;

        setAuthStatus(
            `Authenticated as ${user.name ?? user.email ?? 'user'}`,
            'success'
        );

        await loadConnectedAccounts();

    } catch (error) {

        authenticated = false;

        setAuthStatus(
            'Imeshindikana kuwasiliana na server.',
            'error'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Facebook OAuth
|--------------------------------------------------------------------------
*/

async function connectFacebook() {

    if (!authenticated) {

        alert(
            'Authenticate kwanza kwa kuweka Bearer Token.'
        );

        return;
    }

    const token = sessionStorage.getItem('api_token');

    const button = document.getElementById('facebookButton');

    button.textContent = 'Connecting...';

    try {

        const response = await fetch(
            '/api/social/facebook/connect',
            {
                method: 'GET',
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: 'application/json',
                },
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ||
                'Failed to start Facebook OAuth.'
            );
        }

        if (!data.url) {
            throw new Error(
                'Facebook OAuth URL was not returned.'
            );
        }

        /*
         * Navigate browser to Facebook.
         */
        window.location.href = data.url;

    } catch (error) {

        button.textContent = 'Connect';

        alert(error.message);
    }
}


/*
|--------------------------------------------------------------------------
| Instagram
|--------------------------------------------------------------------------
|
| Instagram production OAuth will be wired after Facebook flow.
|--------------------------------------------------------------------------
*/

function connectInstagram() {

    alert(
        'Instagram OAuth bado haijaunganishwa kwenye test flow hii.'
    );
}


/*
|--------------------------------------------------------------------------
| Connected Accounts
|--------------------------------------------------------------------------
*/

async function loadConnectedAccounts() {

    const token = sessionStorage.getItem('api_token');

    const container =
        document.getElementById('connectedAccounts');

    if (!token || !authenticated) {

        renderEmptyAccounts(
            'Authenticate to view connected accounts.'
        );

        return;
    }

    container.innerHTML = `
        <p class="text-sm text-gray-500">
            Loading connected accounts...
        </p>
    `;

    try {

        const response = await fetch(
            '/api/social-accounts',
            {
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: 'application/json',
                },
            }
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ||
                'Failed to load social accounts.'
            );
        }

        if (!data.length) {

            renderEmptyAccounts(
                'No social accounts connected yet.'
            );

            return;
        }

        container.innerHTML = data
            .map(renderAccount)
            .join('');

    } catch (error) {

        container.innerHTML = `
            <p class="text-sm text-red-600">
                ${escapeHtml(error.message)}
            </p>
        `;
    }
}


/*
|--------------------------------------------------------------------------
| Render Account
|--------------------------------------------------------------------------
*/

function renderAccount(account) {

    const provider =
        capitalize(account.provider);

    const name =
        account.username ||
        account.display_name ||
        'Connected account';

    const status =
        capitalize(account.status);

    const statusClass =
        account.status === 'active'
            ? 'text-green-600'
            : 'text-yellow-600';

    return `
        <div
            class="flex items-center justify-between
                   p-4 border rounded-lg"
        >

            <div>

                <h3 class="font-semibold text-gray-900">
                    ${escapeHtml(provider)}
                </h3>

                <p class="mt-1 text-sm text-gray-500">
                    ${escapeHtml(name)}
                </p>

            </div>

            <span class="text-sm font-medium ${statusClass}">
                ${escapeHtml(status)}
            </span>

        </div>
    `;
}


/*
|--------------------------------------------------------------------------
| UI Helpers
|--------------------------------------------------------------------------
*/

function setAuthStatus(message, type) {

    const status =
        document.getElementById('authStatus');

    status.textContent = message;

    status.className =
        type === 'success'
            ? 'mt-3 text-sm text-green-600'
            : 'mt-3 text-sm text-red-600';
}


function renderEmptyAccounts(message) {

    document.getElementById(
        'connectedAccounts'
    ).innerHTML = `
        <p class="text-sm text-gray-500">
            ${escapeHtml(message)}
        </p>
    `;
}


function capitalize(value) {

    if (!value) {
        return '';
    }

    return value.charAt(0).toUpperCase()
        + value.slice(1);
}


function escapeHtml(value) {

    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

</script>

</body>
</html>