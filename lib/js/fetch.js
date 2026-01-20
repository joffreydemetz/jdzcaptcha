/**
 * Sends an HTTP request using the Fetch API.
 * @param {Object} options The options for the request.
 * @param {string} options.url The URL to send the request to.
 * @param {string} [options.type='GET'] The HTTP method (e.g., 'GET', 'POST').
 * @param {Object} [options.headers={}] The headers to include in the request.
 * @param {Object|FormData} [options.data] The data to send with the request.
 * @param {Function} [options.success] The callback function for a successful response.
 * @param {Function} [options.error] The callback function for an error response.
 */
const Fetch = async (options = {}) => {
    const { url, type = 'GET', headers = {}, data, success, error } = options;

    const fetchOptions = {
        method: type.toUpperCase(),
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...headers,
        },
    };

    if (data) {
        if (data instanceof FormData) {
            fetchOptions.body = data;
        } else {
            fetchOptions.headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(data);
        }
    }

    try {
        const response = await fetch(url, fetchOptions);

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        const result = await response.json().catch(() => response.text());
        if (success) success(result);
    } catch (err) {
        if (error) error(err);
    }
};

export default Fetch;