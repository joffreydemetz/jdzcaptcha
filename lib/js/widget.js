import { CSS, defaults } from './constants.js';
import { Utils } from './utils.js';
import { UI } from './ui.js';

export class Widget {
    /**
     * Creates a new Widget instance.
     * @param {HTMLElement} $element The DOM element to generate the widget into.
     * @param {Object} options An object containing the configuration options for the widget.
     */
    constructor($element, options) {
        // ignore the element if it is not a valid DOM element
        if (!$element) {
            Utils.warn('Element is not a valid DOM element.');
            return null;
        }

        // Ensure the `path` option is set
        if (!options.path) {
            this.error('The option "path" has not been set.');
            return null;
        }

        // ignore the element if it is not a valid DOM element
        if ($element.dataset.jdzcId) {
            Utils.warn('The widget is already initialized.');
            return null;
        }

        this.id = this.generateCaptchaId();
        this.$element = $element;
        this.$element.dataset.jdzcId = this.id;
        this.$iconHolder = null;
        this.token = null;
        this.startedInitialization = false;
        this.invalidateTimeoutId = null;
        this.captchaImageWidth = 0;
        this.generated = false; // Tracks if the captcha is fully generated
        this.generatedInTime = 0;
        this.hovering = false; // Tracks if the user is hovering over the selection area
        this.submitting = false; // Tracks if the captcha is currently submitting
        this.options = options;

        this.generate();
    }

    /**
     * Generates the widget and sets up event listeners.
     */
    generate() {
        if (this.generated) {
            Utils.warn('The widget ' + this.id + ' has already been generated.');
            return;
        }

        // Get the CSRF token from the closest form, if available
        const $form = this.$element.closest('form');
        if ($form) {
            const $tokenInput = $form.querySelector(`input[name = "jdzc[${this.options.fields.token}]"]`);
            this.token = $tokenInput ? $tokenInput.value : null;
        }

        // Throw an exception if the token is not found
        if (!this.token) {
            Utils.error('CSRF token is missing or invalid for widget[' + this.id + ']. Ensure the form contains a valid input field for the token.');
            return;
        }

        this.$element.setAttribute('data-theme', this.options.variant);
        this.$element.classList.add(`jdzc-theme-${this.options.variant}`);

        // Apply the custom font family, if set
        if (this.options.fontFamily) {
            this.$element.style.fontFamily = this.options.fontFamily;
        }

        // If not initialized yet, show the 'initial' captcha holder
        if (!this.startedInitialization && this.options.security.enableInitialMessage) {
            const $circleElement = this.$element.querySelector(`.${CSS.circle}`);
            const $titleElement = this.$element.querySelector(`.${CSS.title}`);
            if ($circleElement) $circleElement.style.animationDuration = '2s';
            if ($titleElement) $titleElement.innerText = this.options.messages.initialization.loading;

            // Delay initialization to show the loading state.
            setTimeout(() => {
                this.$element.classList.remove(CSS.init);
                this.generate();
            }, this.options.security.initializeDelay);

            this.$element.classList.add(CSS.init);
            this.$element.classList.remove(CSS.error, CSS.success);
            this.$element.innerHTML = UI.buildCaptchaInitialHolder(this.options);

            return;
        }

        // Build the captcha if it hasn't been built yet
        if (!this.generated) {
            this.$element.innerHTML = UI.buildCaptchaHolder(this.options, this.id);
        }

        // Assign the icon holder
        this.$iconHolder = this.$element.querySelector(`.${CSS.boxB}`);

        // Add the loading spinner
        UI.addLoadingSpinner(this.$iconHolder);

        // If the loadingAnimationDelay has been set and is not 0, add the loading delay
        if (this.options.security.loadingAnimationDelay && this.options.security.loadingAnimationDelay > 0 && !this.options.security.enableInitialMessage) {
            setTimeout(() => this.load(), this.options.security.loadingAnimationDelay);
        } else {
            this.load();
        }

        // Register event listener for the selection area
        const $selectionArea = this.$element.querySelector(`.${CSS.selection}`);
        if ($selectionArea) {
            $selectionArea.addEventListener('click', (event) => {
                const rect = $selectionArea.getBoundingClientRect();
                const xPos = event.clientX - rect.left;
                const yPos = event.clientY - rect.top;

                // Call submitIconSelection with the calculated coordinates
                this.submitIconSelection(xPos, yPos);
            });
        }

        this.generated = true; // Set generated to true after successful generation
    }

    /**
     * Loads the captcha data for the widget.
     */
    load() {
        if (this.generated) {
            return; // Prevent loading if already generated
        }

        const requestPayload = Utils.createPayload({
            i: this.id,
            a: 1,
            t: this.$element.getAttribute('data-theme') || 'light',
            tk: this.options.token,
        });

        Fetch({
            url: this.options.path,
            type: 'POST',
            headers: this.createHeaders(this.options.token),
            data: { payload: requestPayload },
            success: (data) => {
                if (data && typeof data === 'string' && Utils.isBase64(data)) {
                    const result = JSON.parse(atob(data));

                    if (result.error) {
                        UI.processCaptchaRequestError(result.error, result.data);
                        return;
                    }

                    // Create the Base64 payload.
                    const imageRequestPayload = Utils.createPayload({ i: this.id, tk: this.options.token });
                    const urlParamSeparator = this.options.path.indexOf('?') > -1 ? '&' : '?';

                    // Load the captcha image.
                    const $iconsHolder = this.$iconHolder.querySelector(`.${CSS.icons}`);
                    $iconsHolder.style.backgroundImage = `url(${this.options.path}${urlParamSeparator}payload = ${imageRequestPayload})`;

                    UI.removeLoadingSpinnerOnImageLoad($iconsHolder, () => UI.removeLoadingSpinner(this.$iconHolder));

                    // Add the selection area to the captcha holder.
                    $iconsHolder.parentNode.insertAdjacentHTML('beforeend', `<div class="${CSS.selection}"><i></i></div>`);
                    const selectionCursor = this.$iconHolder.querySelector(`.${CSS.selection} > i`);

                    // Register the events.
                    this.registerSelectionEvents();

                    // Trigger the 'init' event if not already generated.
                    if (!this.generated) {
                        Utils.trigger(this.$element, 'jdzc.init', { captchaId: this.id, options: this.options });
                    }

                    // Determine the width of the image.
                    const modalSelection = this.$iconHolder.querySelector(`.${CSS.selection}`);
                    this.captchaImageWidth = Utils.width(modalSelection);

                    // Set the building timestamp.
                    this.generatedInTime = new Date();
                    this.generated = true;

                    // Start the invalidation timer and save the timer identifier.
                    this.invalidateTimeoutId = setTimeout(() => this.invalidateSession(true), this.options.security.invalidateTime);

                    return;
                }

                this.setCaptchaError('The JdzCaptcha could not be loaded.', 'Invalid data was returned by the captcha back-end service. Make sure JdzCaptcha is installed/configured properly.');
            },
            error: () => this.showIncorrectIconMessage(
                this.options.messages.incorrect.title, // Top message
                this.options.messages.incorrect.subtitle, // Bottom message
                true // Reset the captcha
            ),
        });

        this.generated = true; // Set generated to true after successful loading
    }

    /**
     * Resets the widget.
     */
    reset() {
        Utils.clearInvalidationTimeout(this.invalidateTimeoutId);
        this.startedInitialization = false;
        this.generated = false;
        Utils.trigger(this.$element, 'jdzc.reset', { captchaId: this.id });
        this.generate();
    }

    /**
     * Invalidates the captcha session.
     * @param {boolean} invalidateServer Whether to invalidate the session on the server side.
     */
    invalidateSession(invalidateServer = true) {
        // Reset the captcha state
        this.generated = false;
        this.startedInitialization = false;

        // If server-side invalidation is required
        if (invalidateServer) {
            const payload = Utils.createPayload({ i: this.id, a: 3, tk: this.token });

            Fetch({
                url: this.options.path,
                type: 'POST',
                headers: this.createHeaders(this.token),
                data: { payload },
                success: () => {
                    // Trigger the 'invalidated' event
                    Utils.trigger(this.$element, 'jdzc.invalidated', { captchaId: this.id });

                    // Reset the captcha holder
                    this.resetCaptchaHolder();
                },
                error: () => {
                    // Handle error during server-side invalidation
                    this.setCaptchaError('The JdzCaptcha could not be reset.', 'Invalid data was returned by the captcha back-end service. Make sure JdzCaptcha is installed/configured properly.');
                },
            });
        } else {
            // Reset the captcha holder directly if no server-side invalidation is required
            this.resetCaptchaHolder();
        }
    }

    /**
     * Submits the icon selection made by the user to the server for validation.
     * @param {number} xPos The clicked X position.
     * @param {number} yPos The clicked Y position.
     */
    submitIconSelection(xPos, yPos) {
        if (this.submitting) {
            return; // Prevent duplicate submissions
        }

        if (xPos !== undefined && yPos !== undefined) {
            this.submitting = true; // Set submitting to true

            // Stop the reset timeout.
            Utils.clearInvalidationTimeout(this.invalidateTimeoutId);

            // Round the clicked position.
            xPos = Math.round(xPos);
            yPos = Math.round(yPos);

            // Update the form fields with the captcha data.
            const $selectionField = this.$element.querySelector(`input[name = "jdzc[${this.options.fields.selection}]"]`);
            const $idField = this.$element.querySelector(`input[name = "jdzc[${this.options.fields.id}]"]`);
            if ($selectionField) {
                $selectionField.setAttribute('value', [xPos, yPos, this.captchaImageWidth].join(','));
            }
            if ($idField) {
                $idField.setAttribute('value', this.id);
            }

            // Hide the mouse cursor.
            const $selectionCursor = this.$iconHolder.querySelector(`.${CSS.selection} > i`);
            if ($selectionCursor) {
                $selectionCursor.style.display = 'none';
            }

            // Create the Base64 payload.
            const requestPayload = Utils.createPayload({
                i: this.id,
                x: xPos,
                y: yPos,
                w: this.captchaImageWidth,
                a: 2,
                tk: this.token,
            });

            // Perform the request.
            Fetch({
                url: this.options.path,
                type: 'POST',
                headers: this.createHeaders(this.token),
                data: { payload: requestPayload },
                success: (data) => {
                    this.submitting = false; // Reset submitting to false
                    if (data.success === true) {
                        this.showCompletionMessage();
                    } else {
                        this.showIncorrectIconMessage();
                    }
                },
                error: () => {
                    this.submitting = false; // Reset submitting to false
                    this.setCaptchaError('The JdzCaptcha selection could not be submitted.', 'Invalid data was returned by the captcha back-end service. Make sure JdzCaptcha is installed/configured properly.');
                },
            });
        }
    }

    /**
     * Displays the success message when the correct icon is selected.
     */
    showCompletionMessage() {
        // Add the success state and remove the error state
        this.$element.classList.add(CSS.success);
        this.$element.classList.remove(CSS.error);

        // Display the success message
        this.$iconHolder.innerHTML = UI.buildValidSelectionMessage(this.options);

        // Unregister the selection events
        this.unregisterSelectionEvents();

        // Trigger the 'success' event
        Utils.trigger(this.$element, 'jdzc.success', { captchaId: this.id });

        // Reset the captcha after a delay
        setTimeout(() => this.reset(), this.options.security.selectionResetDelay);
    }

    /**
     * Displays the error message when the incorrect icon is selected.
     */
    showIncorrectIconMessage() {
        this.$element.classList.add(CSS.error);
        this.$element.classList.remove(CSS.success);
        this.$iconHolder.innerHTML = UI.buildInvalidSelectionMessage(this.options);

        // Trigger the 'error' event.
        Utils.trigger(this.$element, 'jdzc.error', { captchaId: this.id });

        // Reset the captcha after a delay.
        setTimeout(() => this.reset(), this.options.security.selectionResetDelay);
    }

    /**
     * Logs a serious error that prevents the plugin from initializing and updates the captcha state.
     * @param {string} displayError The error message to display in the captcha holder element.
     * @param {string} [consoleError] The error message to display in the developer console. If not provided, `displayError` will be used.
     * @param {boolean} triggerEvent Whether to trigger the custom 'error' event.
     */
    setCaptchaError(displayError, consoleError = '', triggerEvent = true) {
        const DEBUG_MODE = window.JdzCaptcha && window.JdzCaptcha.debugMode;

        // Determine the error messages to display.
        const topMessage = DEBUG_MODE ? 'JdzCaptcha error' : this.messages.incorrect.title;
        const bottomMessage = DEBUG_MODE ? displayError : this.messages.incorrect.subtitle;
        const errorReset = !DEBUG_MODE;

        // Display the error message in the captcha holder.
        this.showIncorrectIconMessage(topMessage, bottomMessage, errorReset);

        // Log the error to the console.
        Utils.error(consoleError || displayError);

        // Trigger the custom 'error' event if required.
        if (triggerEvent) {
            Utils.trigger(this.$element, 'jdzc.error', { captchaId: this.id });
        }
    }

    /**
     * Processes the error data which was received from the server while requesting the captcha data. Actions
     * might be performed based on the given error code or error data.
     * @param code The error code.
     * @param data The payload of the error.
     */
    processCaptchaRequestError(code, data) {
        code = parseInt(code);

        switch (code) {
            case 1: // Too many incorrect selections, timeout.
                this.showIncorrectIconMessage(this.options.messages.timeout.title, this.options.messages.timeout.subtitle, false);

                // Remove the header from the captcha.
                const captchaHeader = this.$element.querySelector(`.${CSS.boxH}`);
                captchaHeader.parentNode.removeChild(captchaHeader);

                // Trigger: timeout
                Utils.trigger(this.$element, 'jdzc.timeout', { captchaId: this.id });

                // Reset the captcha to the init holder.
                setTimeout(() => this.invalidateSession(false), data);
                break;

            case 2: // No CSRF token found while validating.
                this.setCaptchaError('The captcha token is missing or is incorrect.', 'A server request was made without including a captcha token, however this option is enabled.');
                break;

            default: // Any other error.
                this.setCaptchaError('An unexpected error occurred.', 'An unexpected error occurred while JdzCaptcha performed an action.');
                break;
        }
    }

    /**
     * Changes the captcha state to the 'error' state.
     * @param {string} [topMessage] The title message of the error state.
     * @param {string} [bottomMessage] The subtitle message of the error state.
     * @param {boolean} [reset=true] Whether the captcha should reinitialize automatically after some time.
     */
    showIncorrectIconMessage(topMessage = defaults.messages.incorrect.title, bottomMessage = defaults.messages.incorrect.subtitle, reset = true) {
        // Remove opacity styles
        this.$iconHolder.classList.remove(CSS.opacity);
        this.$element.classList.remove(CSS.opacity);

        // Unregister the selection events
        this.unregisterSelectionEvents();

        // Add the error state and display the error message
        this.$element.classList.add(CSS.error);
        this.$iconHolder.innerHTML = UI.buildErrorMessage(topMessage, bottomMessage);

        // Mark the captcha as 'not submitting'
        this.submitting = false;

        // Trigger the 'error' event
        Utils.trigger(this.$element, 'jdzc.error', { captchaId: this.id });

        // Handle timeout or reset the captcha
        if (reset) {
            setTimeout(() => this.reset(), this.options.security.selectionResetDelay);
        } else {
            // Trigger a timeout event if reset is disabled
            Utils.trigger(this.$element, 'jdzc.timeout', { captchaId: this.id });
        }
    }

    /**
     * Resets the state of the captcha holder element.
     * The error state will be removed, hidden input fields will be cleared, and the captcha will be reinitialized.
     */
    resetCaptchaHolder() {
        // Remove the error state
        this.$element.classList.remove(CSS.error);

        // Clear the selection input field
        const $selectionField = this.$element.querySelector(`input[name = "jdzc[${this.options.fields.selection}]"]`);
        if ($selectionField) {
            $selectionField.setAttribute('value', null);
        }

        // Reset the captcha body
        Utils.empty(this.$iconHolder);
        this.$iconHolder.insertAdjacentHTML('beforeend', `<div class="${CSS.icons}"></div>`);

        // Reload the captcha
        this.generate();

        // Trigger the 'refreshed' event
        Utils.trigger(this.$element, 'jdzc.refreshed', { captchaId: this.id });
    }

    /**
     * Registers events linked to the captcha selection area element.
     */
    registerSelectionEvents() {
        const $captchaSelection = this.$element.querySelector(`.${CSS.selection}`);

        // Ensure the element and its cached listeners do not exist.
        if (!$captchaSelection || $captchaSelection._jdzc_listeners) return;

        const handlers = {
            click: this.mouseClickEvent.bind(this),
            mousemove: this.mouseMoveEvent.bind(this),
            mouseenter: this.mouseEnterEvent.bind(this),
            mouseleave: this.mouseLeaveEvent.bind(this),
        };

        // Cache the listeners for later removal.
        $captchaSelection._jdzc_listeners = handlers;

        // Register the events.
        Object.entries(handlers).forEach(([event, handler]) => {
            $captchaSelection.addEventListener(event, handler);
        });
    }

    /**
     * Unregisters all event listeners linked to the captcha selection area element.
     */
    unregisterSelectionEvents() {
        const $captchaSelection = this.$element.querySelector(`.${CSS.selection}`);

        // Ensure the element and its cached listeners exist.
        if (!$captchaSelection || !$captchaSelection._jdzc_listeners) return;

        // Unregister each cached event listener.
        Object.entries($captchaSelection._jdzc_listeners).forEach(([event, handler]) => {
            $captchaSelection.removeEventListener(event, handler);
        });

        // Clear the cached listeners to free memory.
        delete $captchaSelection._jdzc_listeners;
    }

    /**
     * Moves the custom cursor to the current location of the actual cursor.
     * @param {MouseEvent} event The mouse move event.
     */
    moveCustomCursor(event) {
        if (!event.currentTarget) {
            return;
        }

        // Calculate the clicked X and Y position.
        const rect = event.currentTarget.getBoundingClientRect();
        const xPos = Math.round(event.clientX - rect.left);
        const yPos = Math.round(event.clientY - rect.top);

        // Apply the style position to the cursor.
        const $selectionCursor = this.$iconHolder.querySelector(`.${CSS.selection} > i`);
        if ($selectionCursor) {
            $selectionCursor.style.left = `${xPos - 8} px`; // Adjust for cursor size
            $selectionCursor.style.top = `${yPos - 7} px`; // Adjust for cursor size
        }
    }

    /**
     * Handles the user's click on the captcha selection area.
     * @param {MouseEvent} event The mouse click event.
     */
    mouseClickEvent(event) {
        if (!this.generated || this.submitting) {
            return; // Prevent clicking if the captcha is not ready or already submitting
        }

        if (new Date() - this.generatedInTime <= this.options.security.clickDelay) {
            return; // Only allow a user to click after a set click delay
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const xPos = event.clientX - rect.left;
        const yPos = event.clientY - rect.top;

        if (!xPos || !yPos) {
            return; // Invalid click
        }

        this.submitIconSelection(xPos, yPos);
    }

    /**
     * Updates the position of the custom cursor as the user moves the mouse.
     * @param {MouseEvent} event The mouse move event.
     */
    mouseMoveEvent(event) {
        if (!this.hovering || this.submitting || !this.generated) {
            return; // Prevent cursor movement if not hovering, submitting, or generated
        }

        this.moveCustomCursor(event);
    }

    /**
     * Handles the mouse entering the selection area.
     * @param {MouseEvent} event The mouse enter event.
     */
    mouseEnterEvent(event) {
        const $selectionCursor = this.$iconHolder.querySelector(`.${CSS.selection} > i`);
        if ($selectionCursor) {
            $selectionCursor.style.display = 'inline'; // Show the cursor
        }

        this.hovering = true; // Set hovering to true
        this.moveCustomCursor(event); // Update cursor position
    }

    /**
     * Handles the mouse leaving the selection area.
     */
    mouseLeaveEvent() {
        const $selectionCursor = this.$iconHolder.querySelector(`.${CSS.selection} > i`);
        if ($selectionCursor) {
            $selectionCursor.style.display = 'none'; // Hide the cursor
        }

        this.hovering = false; // Set hovering to false
    }

    /**
     * Creates the custom header object which should be included in every AJAX request.
     * @param {string} [token] The captcha session token, possibly empty.
     * @returns {Object} The header object.
     */
    createHeaders(token) {
        return token ? { 'X-JdzCaptcha-Token': token } : {};
    }


    /**
     * Generates a random captcha identifier.
     * @returns {number} The widget identifier.
     */
    generateCaptchaId() {
        const maxNumber = 10 ** 13 - 1; // Maximum 13-digit number
        return Math.floor(Math.random() * maxNumber);
    }
};