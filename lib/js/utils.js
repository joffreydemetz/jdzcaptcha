export const Utils = {
    /**
     * Extends an object with properties from one or more source objects.
     * @param {Object} [out={}] The target object to extend.
     * @param {...Object} sources The source objects to copy properties from.
     * @returns {Object} The extended object.
     */
    extend: (out = {}, ...sources) => {
        sources.forEach((source) => {
            if (source && typeof source === 'object') {
                Object.keys(source).forEach((key) => {
                    if (typeof source[key] === 'object' && !Array.isArray(source[key])) {
                        out[key] = Utils.extend(out[key] || {}, source[key]);
                    } else {
                        out[key] = source[key];
                    }
                });
            }
        });
        return out;
    },

    thrown: (error) => {
        const DEBUG_MODE = window.JdzCaptcha && window.JdzCaptcha.debugMode;
        if (!DEBUG_MODE) return;

        if (typeof error === 'string') {
            console.warn('JdzCaptcha ERROR:', error);
        } else if (error instanceof Error) {
            console.error('JdzCaptcha ERROR:', error.message);
        } else if (typeof error === 'object') {
            console.dir('JdzCaptcha ERROR:', error);
        } else {
            console.warn('JdzCaptcha ERROR:', error);
        }
    },

    /**
     * Triggers a custom event on a DOM element.
     * @param {HTMLElement} $el The DOM element to trigger the event on.
     * @param {string} event The name of the event to trigger.
     * @param {Object} [data] The data to pass with the event.
     */
    trigger: ($el, event, data) => {
        const domEvent = new CustomEvent(event, { detail: data });
        $el.dispatchEvent(domEvent);
    },

    /**
     * Removes all child elements from a DOM element.
     * @param {HTMLElement} $el The DOM element to empty.
     */
    empty: ($el) => {
        while ($el.firstChild) $el.removeChild($el.firstChild);
    },

    /**
     * Calculates the offset of a DOM element relative to the document.
     * @param {HTMLElement} $el The DOM element to calculate the offset for.
     * @returns {Object} An object containing the top and left offsets.
     */
    detectOffset: ($el) => {
        if (!$el.getClientRects().length) return { top: 0, left: 0 };

        const { top, left } = $el.getBoundingClientRect();
        const { pageYOffset, pageXOffset } = $el.ownerDocument.defaultView;

        return {
            top: top + pageYOffset,
            left: left + pageXOffset,
        };
    },

    /**
     * Gets the computed width of a DOM element in pixels.
     * @param {HTMLElement} $el The DOM element to get the width for.
     * @returns {number} The width of the element in pixels.
     */
    width: ($el) => {
        return parseFloat(getComputedStyle($el, null).width.replace('px', ''));
    },

    /** 
     * Logs debug information to the console if debug mode is enabled.
     * Supports multiple arguments and automatically uses `console.dir` for objects or arrays.
     * @param {...any} args The data to log. The last argument can specify the console method (e.g., 'log', 'warn').
     */
    debug: (...args) => {
        // Check if debug mode is enabled
        const DEBUG_MODE = window.JdzCaptcha && window.JdzCaptcha.debugMode;
        if (!DEBUG_MODE) return;

        // Extract the last argument as the console method, defaulting to 'log'
        const method = typeof args[args.length - 1] === 'string' && console[args[args.length - 1]] ? args.pop() : 'log';

        args.forEach((arg) => {
            // Check if the first argument is an object or array, and use `console.dir` if so
            if (typeof arg === 'object' || Array.isArray(arg)) {
                if (method === 'warn' || method === 'error') {
                    console[method]('JdzCaptcha: ' + method.toUpperCase() + ':');
                }
                console.dir(arg);
            } else {
                if ('dir' === method) {
                    console.log('JdzCaptcha: ' + arg);
                }
                else {
                    console[method](arg);
                }
            }
        });
    },

    /**
     * Logs a warning message to the console if debug mode is enabled.
     * @param {...any} args The data to log as a warning.
     */
    warn: (...args) => {
        Utils.debug(...args, 'warn');
    },

    /**
     * Logs an error message to the console if debug mode is enabled.
     * @param {...any} args The data to log as an error.
     */
    error: (...args) => {
        Utils.debug(...args, 'error');
    },

    /**
     * Creates a Base64 encoded JSON string from the given data parameter.
     * @param {Object} data The payload object to encode.
     * @returns {string} The encoded payload.
     */
    createPayload: (data) => {
        return btoa(JSON.stringify({ ...data, ts: Date.now() }));
    },

    /**
     * Checks if a string is a valid Base64 encoded string.
     * @param {string} str The string to check.
     * @returns {boolean} True if the string is valid Base64, false otherwise.
     */
    isBase64: (str) => {
        try {
            return btoa(atob(str)) === str;
        } catch (err) {
            return false;
        }
    },

    /**
     * Clears a timeout if the timeout ID is valid.
     * @param {number|null} timeoutId The timeout ID to clear.
     */
    clearInvalidationTimeout: (timeoutId) => {
        if (timeoutId) clearTimeout(timeoutId);
    },
};