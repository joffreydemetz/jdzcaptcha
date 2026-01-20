import { VERSION, defaults } from './constants.js';
import { Utils } from './utils.js';
import { Widget } from './widget.js';
import Fetch from './fetch.js';

export const Captcha = {
    NAME: 'JdzCaptcha',
    VERSION: VERSION,
    debugMode: window.JDZ_DEBUG_MODE || false,
    debug: Utils.debug,
    config: null,
    initialized: false,
    instances: [],

    /**
     * Intialize the captcha configuration and load the widgets.
     * @param {string} loaderUrl The URL to fetch the captcha configuration from.
     * @param {string} [selector=null] The CSS selector for the elements to initialize the captcha on.
     * @param {Object} [options={}] Additional options to override the default configuration.
     * @returns {Object} The Captcha instance.
     */
    initialize: function (loaderUrl, selector = null, options = {}) {
        const loadSelector = (selector) => {
            if (!selector) return;
            document.addEventListener('DOMContentLoaded', () => {
                this.loadFromSelector(selector);
            });
        };

        if (this.initialized) {
            Utils.warning('Already initialized. Cannot use Captcha.initliaze() twice.');
            loadSelector(selector);
            return this;
        }

        if (!loaderUrl) {
            // If no loaderUrl is provided, use the default options.
            Utils.error('LoaderUrl is required to initialize the configuration.');
            this.config = Utils.extend({}, defaults, options || {});
            this.initialized = true;
            loadSelector(selector);
            return this;
        }

        Fetch({
            url: loaderUrl,
            type: 'POST',
            success: (data) => {
                this.config = Utils.extend({}, defaults, options || {}, data || {});
                this.initialized = true;
                loadSelector(selector);
            },
            error: () => {
                Utils.error('Failed to load configuration. Using default options.');
                this.config = Utils.extend({}, defaults, options || {});
                this.initialized = true;
                loadSelector(selector);
            },
        });

        return this;
    },

    /**
     * Initializes the captcha widgets for the specified elements.
     * @param {string} selector The CSS selector for the elements to initialize the captcha on.
     * @param {Object} [options={}] Additional options to override the config for the newInstances.
     * @returns {Object} The Captcha instance.
     */
    loadFromSelector: function (selector, options = {}) {
        if (!selector) {
            return this;
        }

        // Merge config with the provided options, if available.
        options = Utils.extend({}, this.config, options || {});

        // Initialize the captcha widgets for the elements matching the selector.
        const newInstances = Array.from(document.querySelectorAll(selector))
            .map((element) => new Widget(element, options))
            .filter((widget) => widget !== null);

        if (newInstances.length === 0) {
            Utils.warn('No valid elements found for the provided selector:', selector);
            return this;
        }

        // Merge new instances with existing ones.
        this.instances = [...this.instances, ...newInstances];

        // Bind callbacks, if provided in the options, only to the new instances.
        if (options.callbacks) {
            newInstances.forEach((widget) => {
                Object.entries(options.callbacks).forEach(([event, callback]) => {
                    widget.$element.addEventListener(event, callback);
                });
            });
        }

        return this;
    },

    checkIfValid: function () {
        if (!this.initialized) {
            Utils.warning('Captcha has not been initialized.');
            return false;
        }

        if (this.instances.length === 0) {
            Utils.warning('No instances found.');
            return false;
        }

        return true;
    },

    /**
     * Resets the specified widget, or all widgets if no widget identifier is provided.
     * @param {string} widgetId The identifier of the widget to reset.
     * @returns {Object} The Captcha instance (for method chaining).
     */
    reset: function (widgetId = null) {
        if (!this.checkIfValid()) {
            Utils.error('Cannot reset.');
            return this;
        }

        // If no widgetId is provided, reset all instances.
        if (!widgetId) {
            this.instances.forEach((widget) => {
                widget.reset();
            });
            return this;
        }

        // If widgetId is provided, reset the specific instance.
        const widget = this.instances.find((w) => w.id === widgetId);
        if (widget) {
            widget.reset();
        } else {
            Utils.error(`No instance found with ID "${widgetId}".Cannot reset.`);
        }

        return this;
    },

    /**
     * Binds an event listener to all widgets in the captcha instance.
     * @param {string} event The name of the event to bind to.
     * @param {Function} callback The function to be called when the event is triggered.
     * @param {string} widgetId The identifier of the widget to reset.
     * @returns {Object} The Captcha instance (for method chaining).
     */
    bind: function (event, callback, widgetId = null) {
        if (!this.checkIfValid()) {
            Utils.error('Cannot bind.');
            return this;
        }

        // If no widgetId is provided, bind the event to all instances.
        if (!widgetId) {
            this.instances.forEach((widget) => {
                widget.$element.addEventListener(event, callback);
            });
            return this;
        }

        // If widgetId is provided, bind the event to the specific instance.
        const widget = this.instances.find((w) => w.id === widgetId);
        if (widget) {
            widget.$element.addEventListener(event, callback);
        } else {
            Utils.error(`No instance found with ID "${widgetId}".Cannot bind event.`);
        }

        return this;
    },
};