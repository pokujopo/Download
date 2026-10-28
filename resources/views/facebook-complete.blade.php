<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Select Facebook Page</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-100">

<div class="max-w-2xl mx-auto px-4 py-10">

    <div class="bg-white rounded-xl shadow-sm p-6">

        <h1 class="text-2xl font-bold text-gray-900">
            Select Facebook Page
        </h1>

        <p class="mt-2 text-gray-600">
            Choose which Facebook Page you want to connect.
        </p>


        <div
            id="status"
            class="mt-6 text-sm text-gray-500"
        >
            Loading available Pages...
        </div>


        <div
            id="pages"
            class="mt-4 space-y-3"
        ></div>

    </div>

</div>


<script>

const params =
    new URLSearchParams(window.location.search);

const connection =
    params.get('connection');

const token =
    sessionStorage.getItem('api_token');

const status =
    document.getElementById('status');

const pages =
    document.getElementById('pages');


/*
|--------------------------------------------------------------------------
| Validate Test Session
|--------------------------------------------------------------------------
*/

if (!connection) {

    showError(
        'Facebook connection is missing.'
    );

} else if (!token) {

    showError(
        'Authentication token is missing.'
    );

} else {

    loadPages();
}


/*
|--------------------------------------------------------------------------
| Load Facebook Pages
|--------------------------------------------------------------------------
*/

async function loadPages() {

    try {

        const response = await fetch(
            `/api/social/facebook/options?connection=${
                encodeURIComponent(connection)
            }`,
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
                'Failed to load Facebook Pages.'
            );
        }

        if (!data.pages?.length) {

            showError(
                'No Facebook Pages are available.'
            );

            return;
        }

        status.textContent =
            'Select a Page to connect:';

        pages.innerHTML =
            data.pages
                .map(renderPage)
                .join('');

    } catch (error) {

        showError(error.message);
    }
}


/*
|--------------------------------------------------------------------------
| Render Page
|--------------------------------------------------------------------------
*/

function renderPage(page) {

    const instagram =
        page.instagram_business_account
            ? `
                <p class="mt-1 text-xs text-pink-600">
                    Instagram account available
                </p>
              `
            : '';

    return `
        <button
            type="button"
            onclick="selectPage('${escapeAttribute(page.id)}')"
            class="w-full text-left p-4 border rounded-lg
                   hover:bg-gray-50 transition"
        >

            <div class="flex items-center justify-between">

                <div>

                    <h3 class="font-semibold text-gray-900">
                        ${escapeHtml(page.name)}
                    </h3>

                    <p class="mt-1 text-xs text-gray-500">
                        Page ID: ${escapeHtml(page.id)}
                    </p>

                    ${instagram}

                </div>

                <span
                    class="text-sm font-medium text-blue-600"
                >
                    Connect
                </span>

            </div>

        </button>
    `;
}


/*
|--------------------------------------------------------------------------
| Select Page
|--------------------------------------------------------------------------
*/

async function selectPage(pageId) {

    status.textContent =
        'Connecting Facebook Page...';

    pages.innerHTML = '';

    try {

        const response = await fetch(
            '/api/social/facebook/select',
            {
                method: 'POST',

                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },

                body: JSON.stringify({
                    connection,
                    page_id: pageId,
                }),
            }
        );

        const data = await response.json();

        if (!response.ok) {

            throw new Error(
                data.message ||
                'Failed to connect Facebook Page.'
            );
        }

        status.innerHTML = `
            <span class="text-green-600 font-medium">
                Facebook Page connected successfully.
            </span>
        `;

        pages.innerHTML = `
            <div class="mt-4">

                <p class="text-sm text-gray-600">
                    Connected:
                    <strong>
                        ${escapeHtml(
                            data.social_account?.display_name
                            || 'Facebook Page'
                        )}
                    </strong>
                </p>

                <a
                    href="/settings/social-accounts"
                    class="inline-block mt-5 px-5 py-3
                           rounded-lg bg-gray-900
                           text-white hover:bg-gray-800"
                >
                    Back to Social Accounts
                </a>

            </div>
        `;

    } catch (error) {

        status.innerHTML = `
            <span class="text-red-600">
                ${escapeHtml(error.message)}
            </span>
        `;
    }
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function showError(message) {

    status.innerHTML = `
        <span class="text-red-600">
            ${escapeHtml(message)}
        </span>
    `;
}


function escapeHtml(value) {

    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}


function escapeAttribute(value) {

    return String(value)
        .replaceAll('\\', '\\\\')
        .replaceAll("'", "\\'");
}

</script>

</body>
</html>